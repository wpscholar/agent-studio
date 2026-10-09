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
