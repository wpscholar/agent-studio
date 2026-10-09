<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Trigger;

use AgentStudio\Domain\Setup\SetupField;

/**
 * A kind of event, defined by a plugin, that can start agents: "an order was
 * placed", "a form was submitted"… It appears in every agent's "When should
 * this agent run?" list with its own filter fields.
 *
 * Register it on agent_studio_register_triggers, then fire it with
 * do_action( 'agent_studio_trigger', 'my-plugin/order-placed', $payload ).
 */
interface TriggerDefinition
{
    /** Unique key with a namespace, like "my-plugin/order-placed". */
    public function key(): string;

    /** Short name people see, like "An order is placed". */
    public function label(): string;

    /** One plain sentence: when it fires. */
    public function description(): string;

    /**
     * Optional filters each agent can set, like "Only orders over". Empty values match everything.
     *
     * @return list<SetupField>
     */
    public function fields(): array;

    /**
     * Whether an agent's filters match this event.
     *
     * @param array<string, string> $config  The agent's filter values.
     * @param array<string, mixed>  $payload The event data.
     */
    public function matches(array $config, array $payload): bool;

    /**
     * One sentence for the agent about what happened, for example "Order #123 was placed."
     *
     * @param array<string, mixed> $payload
     */
    public function about(array $payload): string;
}
