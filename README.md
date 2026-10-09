# Agent Studio

Build AI agents inside WordPress. An agent is a set of instructions, something that starts it, and a short list of things it is allowed to do. It runs in the background on your own site, uses the same abilities other plugins already expose, and asks you before it changes anything you did not pre-approve.

> **Status:** v1 is built. This README also documents the roadmap beyond v1. Items under [Roadmap](#roadmap) are plans, not features.

## What v1 does

- **Agents** are created in the WordPress admin: a name, plain-language instructions, triggers, an ability allowlist, optional skills, and safety settings.
- **Triggers:** a post changes status, a user registers, a comment is posted, a schedule comes round (every hour, day or week at a set time), a signed webhook is called, or you click **Run now** (also available over REST and WP-CLI).
- **Abilities:** agents use the WordPress Abilities API (6.9+). Anything another plugin registers can be given to an agent. A few starter abilities ship so agents are useful on a plain site: get and search posts, update a post, add a private note, read and moderate comments, look up a user, send an email.
- **Skills:** reusable SKILL.md-style instructions, managed on **Agents → Skills**. An agent always sees each skill's "when to use it" line and loads the full text only when it needs it. Write skills on that screen, import or download a `SKILL.md`, see which agents use each one, and restore any earlier version.
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

| Hook                                                                    | Purpose                                                                         |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `agent_studio_loaded` (action)                                          | Fires when ready, with the service container.                                   |
| `agent_studio_manage_capability`                                        | Capability that grants every Agent Studio capability. Default `manage_options`. |
| `agent_studio_agent_can_use_ability`                                    | Hide an ability from every agent, whatever its allowlist says.                  |
| `agent_studio_register_starter_abilities`                               | Return `false` to skip the starter abilities.                                   |
| `agent_studio_allow_email_recipient`                                    | Block recipients of the `send-email` ability.                                   |
| `agent_studio_expose_abilities_to_mcp`                                  | Make Agent Studio abilities public MCP tools. Default `false`.                  |
| `agent_studio_trigger_payload`                                          | Change event data before it reaches an agent.                                   |
| `agent_studio_comment_trigger_types`, `agent_studio_ignored_post_types` | Tune which events can trigger agents.                                           |
| `agent_studio_agent_templates`                                          | Add or change the admin templates.                                              |
| `agent_studio_approval_notify_email`                                    | Change who gets approval emails.                                                |
| `agent_studio_run_retention_days`                                       | Days to keep runs. Default 30.                                                  |
| `agent_studio_ai_provider`                                              | Pick the provider for agents whose model is set to Automatic.                   |
| `agent_studio_model_client`                                             | Replace the model client (another runtime, or a fake in tests).                 |
| `agent_studio_dispatch_failed` (action)                                 | A trigger could not be dispatched.                                              |

Register your own abilities with `wp_register_ability()`. They appear in the agent editor with a risk label inferred from their annotations (`readonly`, `destructive`); an ability that does not say it is read-only is treated as a write. Set `meta.agent_studio.risk` to `read`, `write`, `destructive` or `external` to override.

Uninstalling removes the run log and settings. Agents and skills are kept unless `AGENT_STUDIO_REMOVE_ALL_DATA` is `true`.

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

Everything above: six triggers including schedules, a model choice per agent, ability allowlists, skills, approvals, run log, signed webhooks, REST and WP-CLI control, starter abilities, templates.

Known limits in v1: no settings screen (use filters); one model call loop per run with no memory between runs; agents act as a chosen existing user rather than a dedicated agent identity; runs execute inside WordPress.

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
- **Channels.** Talk to the same agent from somewhere other than WordPress: Slack, Discord, Microsoft Teams, or a chat box on your site. Each channel is set up step by step from Agents → Channels, and plugins can add their own channel types to that screen.
- **Connections.** Connect an external MCP server in a few clicks. Its tools appear as abilities you can give to agents, with the same risk labels and approvals. WordPress is the MCP client here, the opposite of the usual setup.
- **Expose agents over MCP.** Let other MCP clients start chosen agents.

### v3: scale

- Multisite and agency fleet management: shared templates and skills, per-site overrides, central run log.
- Evaluation suites: replay recorded events against an agent and score the outcomes before changing instructions.
- Marketplace-style distribution of agent bundles and skills.
- Approval routing: roles, delegates, mobile approvals, and policy rules ("never email outside the company domain").

Roadmap items may change. Open an issue to discuss one before building on it.

## License

GPL-2.0-or-later.
