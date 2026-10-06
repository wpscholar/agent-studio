# WP Agents

Build AI agents inside WordPress. An agent is a set of instructions, something that starts it, and a short list of things it is allowed to do. It runs in the background on your own site, uses the same abilities other plugins already expose, and asks you before it changes anything you did not pre-approve.

> **Status:** v1 is built. This README also documents the roadmap beyond v1. Items under [Roadmap](#roadmap) are plans, not features.

## What v1 does

- **Agents** are created in the WordPress admin: a name, plain-language instructions, triggers, an ability allowlist, optional skills, and safety settings.
- **Triggers:** a post changes status, a user registers, a comment is posted, a signed webhook is called, or you click **Run now** (also available over REST and WP-CLI).
- **Abilities:** agents use the WordPress Abilities API (6.9+). Anything another plugin registers can be given to an agent. A few starter abilities ship so agents are useful on a plain site: get and search posts, update a post, add a private note, read and moderate comments, look up a user, send an email.
- **Skills:** reusable SKILL.md-style instructions. An agent always sees each skill's short description and loads the full text only when it needs it. Create skills in the admin or import a `SKILL.md`.
- **AI:** models are called through the WordPress AI Client (7.0+) with whatever provider you connected. WP Agents holds no API keys.
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
POST /wp-json/wp-agents/v1/webhooks/{agent_id}
X-WPA-Timestamp: <unix seconds, within 5 minutes of the server clock>
X-WPA-Signature: sha256=<hex HMAC-SHA256 of "{timestamp}.{raw body}" using the secret>
Idempotency-Key: <optional; the same key within 24 hours starts the agent once>
```

```bash
body='{"order":42}'
ts=$(date +%s)
sig=$(printf '%s.%s' "$ts" "$body" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST "https://example.com/wp-json/wp-agents/v1/webhooks/123" \
  -H "Content-Type: application/json" -H "X-WPA-Timestamp: $ts" \
  -H "X-WPA-Signature: sha256=$sig" -H "Idempotency-Key: order-42" -d "$body"
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

Filters and actions (all prefixed `wpa_`):

| Hook                                                  | Purpose                                                         |
| ----------------------------------------------------- | --------------------------------------------------------------- |
| `wpa_loaded` (action)                                 | Fires when ready, with the service container.                   |
| `wpa_manage_capability`                               | Capability needed to manage agents. Default `manage_options`.   |
| `wpa_agent_can_use_ability`                           | Hide an ability from every agent, whatever its allowlist says.  |
| `wpa_register_starter_abilities`                      | Return `false` to skip the starter abilities.                   |
| `wpa_allow_email_recipient`                           | Block recipients of the `send-email` ability.                   |
| `wpa_expose_abilities_to_mcp`                         | Make WP Agents abilities public MCP tools. Default `false`.     |
| `wpa_trigger_payload`                                 | Change event data before it reaches an agent.                   |
| `wpa_comment_trigger_types`, `wpa_ignored_post_types` | Tune which events can trigger agents.                           |
| `wpa_agent_templates`                                 | Add or change the admin templates.                              |
| `wpa_approval_notify_email`                           | Change who gets approval emails.                                |
| `wpa_run_retention_days`                              | Days to keep runs. Default 30.                                  |
| `wpa_ai_provider`                                     | Pick which connected AI provider agents use.                    |
| `wpa_model_client`                                    | Replace the model client (another runtime, or a fake in tests). |
| `wpa_dispatch_failed` (action)                        | A trigger could not be dispatched.                              |

Register your own abilities with `wp_register_ability()`. They appear in the agent editor with a risk label inferred from their annotations (`readonly`, `destructive`); an ability that does not say it is read-only is treated as a write. Set `meta.wpa.risk` to `read`, `write`, `destructive` or `external` to override.

Uninstalling removes the run log and settings. Agents and skills are kept unless `WP_AGENTS_REMOVE_ALL_DATA` is `true`.

## Architecture

Portable hexagonal layout, following [`wpscholar/standards`](https://github.com/wpscholar/standards):

```
src/Domain/          Agents, runs, skills, risk rules. Pure PHP.
src/Application/     Dispatch, execute, approve, cancel, prune. Pure PHP.
src/Contracts/       Interfaces: repositories, model client, queue, clock…
src/Infrastructure/  WordPress adapters: posts, wpdb, Abilities API, AI Client…
src/WordPress/       Hooks, REST, admin screens, CLI, wiring.
```

Domain and Application never touch WordPress. That is enforced by a test and is what makes a different runtime possible later. See [`docs/architecture.md`](./docs/architecture.md).

## Development

```bash
composer install && npm install
composer test:unit          # no WordPress needed
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
composer test:integration   # real WordPress
composer lint && composer analyze && npm run lint && npm run format:check
npm run package             # release zip
```

See [`docs/environment.md`](./docs/environment.md), [`docs/testing.md`](./docs/testing.md), [`docs/deployment.md`](./docs/deployment.md).

## Roadmap

### v1 (built)

Everything above: five triggers, ability allowlists, skills, approvals, run log, signed webhooks, REST and WP-CLI control, starter abilities, templates.

Known limits in v1: no scheduled (cron) trigger; no settings screen (use filters); one model call loop per run with no memory between runs; agents act as a chosen existing user rather than a dedicated agent identity; runs execute inside WordPress.

### v1.x: polish

- Scheduled triggers (every hour, daily, a cron expression).
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
- **Memory.** Per-agent notes that persist across runs, with a visible editor.
- **Output channels.** Slack, email digests, and outbound webhooks as first-class results.
- **MCP.** Expose chosen agents as MCP tools, and let agents use external MCP servers.

### v3: scale

- Multisite and agency fleet management: shared templates and skills, per-site overrides, central run log.
- Evaluation suites: replay recorded events against an agent and score the outcomes before changing instructions.
- Marketplace-style distribution of agent bundles and skills.
- Approval routing: roles, delegates, mobile approvals, and policy rules ("never email outside the company domain").

Roadmap items may change. Open an issue to discuss one before building on it.

## License

GPL-2.0-or-later.
