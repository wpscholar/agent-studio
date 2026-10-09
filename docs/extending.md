# Extending Agent Studio

Plugins can add four kinds of things. Each appears in Agent Studio's own screens, with the same plain-language setup as the built-in ones.

| Add a…          | Hook                                     | Implement                             | Appears in                        |
| --------------- | ---------------------------------------- | ------------------------------------- | --------------------------------- |
| Trigger         | `agent_studio_register_triggers`         | `Contracts\Trigger\TriggerDefinition` | Each agent's "Add a trigger" list |
| Template        | `agent_studio_agent_templates` (filter)  | An array                              | Agents → New agent                |
| Channel type    | `agent_studio_register_channel_types`    | `Contracts\Channel\ChannelType`       | Agents → Channels → Add           |
| Connection type | `agent_studio_register_connection_types` | `Contracts\Connection\ConnectionType` | Agents → Connections → Add        |

Abilities are the fifth: register them with the WordPress Abilities API (`wp_register_ability()`) and they show up on Agents → Abilities and in every agent's ability list. Give them a category (`wp_register_ability_category()`) so people can filter by it.

Setup forms are described as data (`Domain\Setup\SetupField`, `Domain\Setup\SetupStep`), so you never write admin UI. Field types: `text`, `url`, `secret`, `textarea`, `select`, `toggle`. Secret fields are stored encrypted and never sent back to the browser.

## A trigger

```php
use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Contracts\Trigger\TriggerDefinition;
use AgentStudio\Domain\Setup\SetupField;

final class My_Order_Placed implements TriggerDefinition {
	public function key(): string { return 'my-shop/order-placed'; }
	public function label(): string { return __( 'An order is placed', 'my-shop' ); }
	public function description(): string { return __( 'When a customer completes checkout.', 'my-shop' ); }

	public function fields(): array {
		return [ new SetupField( 'min_total', __( 'Only orders over', 'my-shop' ), SetupField::TEXT, __( 'Leave empty for every order.', 'my-shop' ) ) ];
	}

	public function matches( array $config, array $payload ): bool {
		return '' === ( $config['min_total'] ?? '' ) || $payload['total'] > (float) $config['min_total'];
	}

	// Shown to the agent outside the untrusted event data: never include visitor-written text here.
	public function about( array $payload ): string {
		return sprintf( 'Order #%d was placed.', $payload['order_id'] );
	}
}

add_action( 'agent_studio_register_triggers', fn ( TriggerDefinitions $t ) => $t->register( new My_Order_Placed() ) );

// When it happens (the third argument stops the same order starting an agent twice within an hour):
do_action( 'agent_studio_trigger', 'my-shop/order-placed', [ 'order_id' => $id, 'total' => $total ], "order-$id" );
```

## A template

```php
add_filter( 'agent_studio_agent_templates', function ( array $templates ) {
	$templates['big-order-thanks'] = [
		'title'        => __( 'Big order thank-you', 'my-shop' ),
		'summary'      => __( 'Writes a personal thank-you note for large orders. You approve each email.', 'my-shop' ),
		'instructions' => __( 'Write a short, warm thank-you email…', 'my-shop' ),
		'config'       => [
			'triggers'  => [ [ 'type' => 'custom', 'config' => [ 'kind' => 'my-shop/order-placed', 'min_total' => '500' ] ] ],
			'abilities' => [ 'agent-studio/send-email' ],
			'approval'  => 'writes',
		],
	];
	return $templates;
} );
```

Agents made from a template start as drafts, so nothing runs until someone reads them and turns them on.

## A channel type

Implement `Contracts\Channel\ChannelType`: capabilities (receive, reply, notify, approve), setup steps, `activate()` (for example register a webhook pointing at `$context->inboundUrl`), `receive()` (verify the request, then return `IncomingMessage`s and `IncomingAction`s), `send()` (with optional Approve/Reject buttons), `acknowledge()` and `pairingInstructions()`.

Agent Studio does the rest: who may chat, linking chat accounts to WordPress accounts, conversations and their summaries, approvals, replies, and the Channels screen. `WordPress\Channels\TelegramChannelType` is a compact example.

If setup can only finish once the channel exists (because the service needs this channel's addresses), also implement `GuidedSetup`. If people connect by signing in at the service, implement `AuthorizesWithOAuth`. `SlackChannelType` uses both.

```php
add_action( 'agent_studio_register_channel_types', fn ( $types ) => $types->register( new My_Chat_Channel( /* … */ ) ) );
```

## A connection type

Implement `Contracts\Connection\ConnectionType`: setup fields, `validate()`, `resource()` (the address OAuth tokens are for), `tools()` and `call()`. Throw `Contracts\Connection\AuthRequired` (with the service's `WWW-Authenticate` header) when credentials are missing or rejected: Agent Studio then offers sign-in or an API key, stores them, and refreshes tokens. Your tools appear to agents as `mcp-<connection>/<tool>`, labelled by the risk you report through `RemoteTool`.

```php
add_action( 'agent_studio_register_connection_types', fn ( $types ) => $types->register( new My_Api_Connection( /* … */ ) ) );
```
