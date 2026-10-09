# Agent Studio

Build AI agents inside WordPress. An agent is a set of instructions, something that starts it, and a short list of things it is allowed to do. It runs in the background on your own site, uses the same abilities other plugins already expose, and asks you before it changes anything you did not pre-approve.

> **Status:** v1 is built. This README also documents the roadmap beyond v1. Items under [Roadmap](#roadmap) are plans, not features.

## What v1 does

- **Agents** are created in the WordPress admin: a name, plain-language instructions, triggers, an ability allowlist, optional skills, and safety settings.
- **Triggers:** a post changes status, a user registers, a comment is posted, a schedule comes round (every hour, day or week at a set time), a signed webhook is called, or you click **Run now** (also available over REST and WP-CLI).
- **Abilities:** agents use the WordPress Abilities API (6.9+). Anything another plugin registers can be given to an agent. A few starter abilities ship so agents are useful on a plain site: get and search posts, update a post, add a private note, read and moderate comments, look up a user, send an email.
- **Skills:** reusable SKILL.md-style instructions, managed on **Agents → Skills**. An agent always sees each skill's "when to use it" line and loads the full text only when it needs it. Write skills on that screen, import or download a `SKILL.md`, see which agents use each one, and restore any earlier version.
- **Connections:** on **Agents → Connections**, paste a service's MCP server address and Agent Studio works out the rest. Open servers connect at once. Protected ones offer **Sign in** (OAuth 2.1 with PKCE, following the MCP authorization spec: it discovers the sign-in server and registers itself when the service allows it) or an API key in whatever header the service wants. The service's tools then appear in every agent's ability list as `mcp-<connection>/<tool>`, for agents only: they are not registered with the Abilities API. A tool counts as "read" only if it says it is read-only; anything else needs approval like any other write. Speaks MCP 2026-07-28 and falls back to the older session handshake for servers that need it.
- **Channels:** on **Agents → Channels**, set up places where people talk to an agent or where you get notified, step by step. **Telegram**: chat with an agent from Telegram and approve its actions with a tap. **ntfy**: push notifications on your phone with Approve and Reject buttons. Only people you let in can chat; they act with the agent's permissions. People link their chat account to their WordPress account with a one-time code, and only linked people whose account may approve can approve from the chat. Approval requests go to email and to every notification channel whose owner may approve.
- **Conversations:** a chat or thread is one ongoing conversation until it has been quiet for 24 hours. Each message is still its own run, with the same approvals and log. The agent sees the last 20 messages word for word (and at most about 24,000 characters); older ones are folded into a running summary written by the agent's own model, so long chats never outgrow the model.
- **Secrets:** API keys and tokens never go in ordinary settings. They use the WordPress Secrets API when it exists (proposed for 7.2) and, until then, are encrypted with libsodium in options WordPress does not load on every page. The key comes from `AGENT_STUDIO_SECRETS_KEY` if you define it, otherwise from your `wp-config.php` security keys. Saved secrets move to core automatically once the Secrets API is available.
- **Permissions:** three capabilities: `agent_studio_manage` (set up agents and skills), `agent_studio_approve` (approve any agent's actions) and `agent_studio_chat` (talk to agents from a linked chat account). Administrators have all three. A role editor can give them to other roles one by one, and the person who set up an agent can approve that agent's actions.
- **AI:** models are called through the WordPress AI Client (7.0+) with whatever provider you connected. Each agent uses **Automatic** (WordPress picks a connected model that can use the agent's abilities) or one model you choose from your connected providers. Agent Studio holds no API keys.
- **Approval:** reads run freely. Writes, destructive actions and external effects can pause the run until you approve. Approve from the run screen or `wp agents approve`.
- **Run log:** every run keeps its trigger data, steps, tool calls, results and token use. Old runs are pruned after 30 days (filterable).
- **Templates:** a pre-publish reviewer, a welcome-email writer and a comment moderator, each created paused.

## Requirements

- WordPress 7.0 or newer (Abilities API and AI Client are in core)
- PHP 8.2 or newer
- An AI provider connected in WordPress
- Action Scheduler is used when present. Otherwise runs go through WP-Cron.

## How a run works

1. An event matches an active agent's trigger. Dedupe, an hourly rate limit and a recursion guard run first.
2. A run is stored and queued. A background job picks it up.
3. The job switches to the agent's **Acts as** user, so every ability checks that user's permissions, and restores the real user afterwards.
4. The model sees the instructions, the event data, and only the tools you allowed. It can call tools until it answers or hits the step limit.
5. A tool that needs approval pauses the run and emails you. Approve or reject, and the run resumes.

### Safety model

- **Least privilege.** An agent can only call abilities on its allowlist, and only as the user it acts as. Give it a user with just the access it needs.
- **Event data is untrusted.** Post content, comments and webhook bodies are wrapped and labelled as data, never as instructions. This reduces prompt injection but cannot eliminate it, which is why approvals exist. Keep write and external abilities behind approval for anything triggered by public input.
- **Agents cannot start agents by accident.** Events caused by an agent's own actions never trigger agents.
- **Limits.** Per-agent step limit and runs-per-hour limit.
- **Nothing public by default.** Agent abilities are not exposed over MCP unless you opt in.

## Webhooks

Turn on the Webhook trigger and save. The agent edit screen shows the URL and a secret.

```
POST /wp-json/agent-studio/v1/webhooks/{agent_id}
X-Agent-Studio-Timestamp: <unix seconds, within 5 minutes of the server clock>
X-Agent-Studio-Signature: sha256=<hex HMAC-SHA256 of "{timestamp}.{raw body}" using the secret>
Idempotency-Key: <optional; the same key within 24 hours starts the agent once>
```

```bash
body='{"order":42}'
ts=$(date +%s)
sig=$(printf '%s.%s' "$ts" "$body" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST "https://example.com/wp-json/agent-studio/v1/webhooks/123" \
  -H "Content-Type: application/json" -H "X-Agent-Studio-Timestamp: $ts" \
  -H "X-Agent-Studio-Signature: sha256=$sig" -H "Idempotency-Key: order-42" -d "$body"
```

Responses: `202` queued, `200` duplicate (same run), `401` bad or stale signature, `429` hourly limit reached. Bodies over 256 KB are rejected.

## WP-CLI

```bash
wp agents list
wp agents run 42 --payload='{"topic":"launch"}' --wait
wp agents runs --status=awaiting_approval
wp agents show 17
wp agents approve 17 --user=admin
wp agents reject 17 --user=admin
wp agents cancel 17
wp agents prune
```

## Extending

Filters and actions (all prefixed `agent_studio_`):

| Hook                                                                    | Purpose                                                                            |
| ----------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| `agent_studio_loaded` (action)                                          | Fires when ready, with the service container.                                      |
| `agent_studio_manage_capability`                                        | Capability that grants every Agent Studio capability. Default `manage_options`.    |
| `agent_studio_agent_can_use_ability`                                    | Hide an ability from every agent, whatever its allowlist says.                     |
| `agent_studio_register_starter_abilities`                               | Return `false` to skip the starter abilities.                                      |
| `agent_studio_allow_email_recipient`                                    | Block recipients of the `send-email` ability.                                      |
| `agent_studio_expose_abilities_to_mcp`                                  | Make Agent Studio abilities public MCP tools. Default `false`.                     |
| `agent_studio_trigger_payload`                                          | Change event data before it reaches an agent.                                      |
| `agent_studio_comment_trigger_types`, `agent_studio_ignored_post_types` | Tune which events can trigger agents.                                              |
| `agent_studio_agent_templates`                                          | Add or change the admin templates.                                                 |
| `agent_studio_approval_notify_email`                                    | Change who gets approval emails.                                                   |
| `agent_studio_run_retention_days`                                       | Days to keep runs. Default 30.                                                     |
| `agent_studio_register_channel_types` (action)                          | Add your own kind of channel (implement `Contracts\Channel\ChannelType`).          |
| `agent_studio_register_connection_types` (action)                       | Add your own kind of connection (implement `Contracts\Connection\ConnectionType`). |
| `agent_studio_ai_provider`                                              | Pick the provider for agents whose model is set to Automatic.                      |
| `agent_studio_model_client`                                             | Replace the model client (another runtime, or a fake in tests).                    |
| `agent_studio_dispatch_failed` (action)                                 | A trigger could not be dispatched.                                                 |

Register your own abilities with `wp_register_ability()`. They appear in the agent editor with a risk label inferred from their annotations (`readonly`, `destructive`); an ability that does not say it is read-only is treated as a write. Set `meta.agent_studio.risk` to `read`, `write`, `destructive` or `external` to override.

Uninstalling removes the run log, conversations, settings and saved credentials. Agents, skills, connections and channels are kept unless `AGENT_STUDIO_REMOVE_ALL_DATA` is `true`.

## Architecture

Portable hexagonal layout, following [`wpscholar/standards`](https://github.com/wpscholar/standards):

```
src/Domain/          Agents, runs, skills, connections, channels, conversations, risk rules. Pure PHP.
src/Application/     Dispatch, execute, approve, MCP client, OAuth, connections, channels. Pure PHP.
src/Contracts/       Interfaces: repositories, model client, queue, clock…
src/Infrastructure/  WordPress adapters: posts, wpdb, Abilities API, AI Client…
src/WordPress/       Hooks, REST, admin screens, CLI, wiring.
```

Domain and Application never touch WordPress. That is enforced by a test and is what makes a different runtime possible later. See [`docs/architecture.md`](./docs/architecture.md).

## Development

```bash
make setup                  # Docker environment: WordPress via WP-CLI, deps, test scaffolding
make shell                  # then run the commands below inside the container
composer test:unit          # no WordPress needed
composer test:integration   # real WordPress
composer lint && composer analyze && npm run lint && npm run format:check
npm run package             # release zip
```

See [`docs/environment.md`](./docs/environment.md), [`docs/testing.md`](./docs/testing.md), [`docs/deployment.md`](./docs/deployment.md).

## Roadmap

### v1 (built)

Everything above: six triggers including schedules, a model choice per agent, ability allowlists, skills, connections to MCP servers, approvals, run log, signed webhooks, REST and WP-CLI control, starter abilities, templates.

Known limits in v1: no settings screen (use filters); no memory between runs except within a channel conversation; replies arrive when the run finishes (no typing indicator or streaming); agents act as a chosen existing user rather than a dedicated agent identity; runs execute inside WordPress.

### v1.x: polish

- Settings screen: default provider and model, retention, notification address, MCP exposure.
- More triggers: form submissions, WooCommerce order events, taxonomy and meta changes, REST-driven custom events.
- More starter abilities: media, terms, menus, options (read-only), outbound HTTP with an allowlist.
- Dry-run mode that shows what an agent would do without doing it, and a run replay view.
- Per-agent token and cost budgets with a hard stop.

### v2: reach and trust

- **Remote runtime driver.** Run the agent loop on an external runtime (for example Cloudflare Workers) that calls back into WordPress over MCP, so long or heavy runs do not use PHP workers. The Application layer already talks to a `ModelClient` and `JobQueue` contract, so this is a new adapter, not a rewrite.
- **Dedicated agent users.** Create a user per agent with a capability ceiling, and show exactly which permissions it holds.
- **Natural-language agent builder.** Describe the job, review the generated triggers, abilities and instructions, then approve.
- **Agent bundles.** Export and import an agent with its skills and ability list as one file.
- **Agent-to-agent.** One agent can start another with a bounded hand-off.
- **Memory.** Things an agent should remember between runs, such as preferences and facts about the site. A memory is saved only when a person adds it or approves one the agent suggests. It belongs to whoever saved it and stays private unless shared. No automatic forgetting or ranking. Managed on a simple Agents → Memory screen rather than the post editor. Stored the way WordPress's proposed Knowledge feature stores memories (title, short description, text, revisions), so it can move to core storage if that ships.
- **More channels.** Slack (connected with OAuth), Discord, Microsoft Teams, and a chat box in the admin. Plugins can already add their own channel types to Agents → Channels.
- **Expose agents over MCP.** Let other MCP clients start chosen agents.

### v3: scale

- Multisite and agency fleet management: shared templates and skills, per-site overrides, central run log.
- Evaluation suites: replay recorded events against an agent and score the outcomes before changing instructions.
- Marketplace-style distribution of agent bundles and skills.
- Approval routing: roles, delegates, mobile approvals, and policy rules ("never email outside the company domain").

Roadmap items may change. Open an issue to discuss one before building on it.

## License

GPL-2.0-or-later.
