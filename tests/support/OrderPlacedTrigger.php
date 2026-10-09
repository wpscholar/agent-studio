<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Trigger\TriggerDefinition;
use AgentStudio\Domain\Setup\SetupField;

/**
 * A plugin-style trigger for tests: an order was placed, optionally over a minimum total.
 */
final class OrderPlacedTrigger implements TriggerDefinition
{
    public function key(): string
    {
        return 'test-shop/order-placed';
    }

    public function label(): string
    {
        return 'An order is placed';
    }

    public function description(): string
    {
        return 'When a customer completes checkout.';
    }

    public function fields(): array
    {
        return [new SetupField('min_total', 'Only orders over', SetupField::TEXT, 'Leave empty for every order.')];
    }

    public function matches(array $config, array $payload): bool
    {
        $min = $config['min_total'] ?? '';

        return $min === '' || (float) ($payload['total'] ?? 0) > (float) $min;
    }

    public function about(array $payload): string
    {
        return sprintf('Order #%d was placed.', (int) ($payload['order_id'] ?? 0));
    }
}
