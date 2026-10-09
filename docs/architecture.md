# Architecture

This plugin follows the **portable hexagonal architecture** documented in the [`wpscholar/standards`](https://github.com/wpscholar/standards) repository.

## Canonical references

- [Architecture standard (full)](https://github.com/wpscholar/standards/blob/main/docs/architecture/wordpress-plugin.md)
- [ADR 0008 — Portable PHP Application Architecture](https://github.com/wpscholar/standards/blob/main/docs/decisions/0008-portable-php-architecture.md)
- [Plugin standards (PHP version, CI, tooling)](https://github.com/wpscholar/standards/blob/main/docs/project-types/wordpress-plugin.md)

## TL;DR for this plugin

For non-trivial features:

```text
src/
├── Domain/             pure PHP. No WordPress. Value objects, entities, domain logic.
├── Application/        use cases (command/handler). Orchestrates Domain + Contracts.
├── Contracts/          interfaces at framework boundaries (Mailer, Clock, repositories).
├── Infrastructure/     concrete adapters implementing Contracts using WordPress APIs.
└── WordPress/          hooks, REST controllers, admin pages, CLI, blocks. Thin glue.
```

Dependencies point inward. Domain knows nothing about WordPress. WordPress integration delegates to Application handlers.

## When to use it vs. skip it

**Use the architecture** when the plugin has business rules, non-trivial persistence, external API calls, a reasonable expected lifespan, plans for reuse in Laravel or another runtime, or non-trivial test coverage requirements.

**Skip it** for tiny plugins (a filter, a single admin tweak, a one-action utility). The template doesn't enforce the layout — adopt the architecture when scope warrants it.

## Rule of thumb

If the code answers **"what should happen?"** → Domain/Application.
If the code answers **"how does WordPress do it?"** → Infrastructure / WordPress.

## Anti-patterns (caught by code review)

- `WP_Post`, `WP_User`, `wpdb`, `get_option()` in `src/Domain/` or `src/Application/`.
- Hook callbacks containing real business logic instead of delegating to an Application handler.
- Global state (`global $wpdb;`) inside Application services.
- Service locator pattern instead of constructor injection.

See the canonical references above for examples and patterns (Command/Handler, Repository, Adapter, Service Provider, Factory, DTO, Strategy, Policy).

## WordPress Knowledge

Gutenberg is experimenting with a `wp_knowledge` post type, typed by a `wp_knowledge_type` taxonomy (guideline, memory, note), for content AI features read ([gutenberg#77230](https://github.com/WordPress/gutenberg/issues/77230)). It is not in core, and as of October 2026 it is not planned for the near term, so Agent Studio never depends on it and does not read site guidelines.

- Agent Studio stores what agents know the same way: post type `agent_studio_kb` (title, excerpt as the description, content, author, revisions) typed by the `agent_studio_kb_type` taxonomy. Skills are the `skill` type today; memories and notes would be further types. Post type keys are limited to 20 characters, hence `kb`.
- `WordPressSkillRepository` reads only the `skill` type. If core ships Knowledge, a second `SkillRepository` adapter can read it instead.
- The stock post screens are hidden for knowledge (`show_ui` false). People use Agents → Skills, which explains the fields in plain language.

## Connections (Agent Studio as an MCP client)

- `Domain\Connection\Connection` is a service agents can use. Its non-secret settings, status and last-known tools are JSON in post meta on the private `agent_studio_conn` post type. Secrets never go there: they go through `Contracts\SecretStore`.
- `Contracts\Connection\ConnectionType` is the extension point. Agent Studio ships the MCP server type (`WordPress\Connections\McpConnectionType`); plugins add theirs on `agent_studio_register_connection_types`. The type declares setup fields as `Domain\Setup\SetupField` data, so the Connections screen draws its form without knowing the type. Auth (none, API key, OAuth) is handled once for every type by `Application\Connections\ConnectionService`.
- `Application\Mcp\McpClient` is a small Streamable HTTP client over `Contracts\Http\HttpClient` (WordPress HTTP API in production, a fake in tests). It speaks MCP 2026-07-28 and falls back to the 2025 initialize/session handshake when a server answers like an older one. We wrote our own because the PHP SDKs either have no client OAuth, pull large dependency trees that would need namespace prefixing in a plugin, or call curl directly (bypassing proxies and test mocks).
- `Application\OAuth` follows the MCP authorization spec: protected resource metadata (RFC 9728), authorization server metadata with exact issuer match, PKCE S256 required, `resource` on authorize and token requests (RFC 8707), `iss` checked on return (RFC 9207). Client identity, in order: a client ID the person entered, this site's client ID metadata document (HTTPS sites only), dynamic registration, otherwise the screen asks the person to register an app and shows the callback address to paste.
- `Application\Connections\ConnectionAbilityGateway` offers connection tools to agents as `mcp-<slug>/<tool>`, from the stored tool list so building an agent's tools never waits on the network. `Application\Execute\CompositeAbilityGateway` merges it with the Abilities API gateway. Tool hints are untrusted: only `readOnlyHint` lowers the risk, unmarked tools count as external.
- `Infrastructure\Secrets`: `CoreSecretStore` uses `wp_set_secret()`/`wp_get_secret()` when WordPress has them; `EncryptedOptionSecretStore` is the fallback (libsodium secretbox, one non-autoloaded option per secret, key from `AGENT_STUDIO_SECRETS_KEY` or `wp_salt('secure_auth')`). `SecretStoreFactory::migrateToCore()` moves secrets over on `admin_init` once core supports it.
