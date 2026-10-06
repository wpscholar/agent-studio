<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAgents\Application\Retention\PruneRunsHandler;
use WPAgents\Contracts\Settings;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Run\Run;
use WPAgents\Tests\Support\TestWorld;

final class PruneRunsHandlerTest extends TestCase
{
    public function testDeletesOldFinishedRunsOnly(): void
    {
        $world = new TestWorld();
        $now = $world->clock->now();
        $day = 86400;

        $oldDone = new Run(null, 1, TriggerType::Manual, [], $now - 40 * $day);
        $oldWaiting = new Run(null, 1, TriggerType::Manual, [], $now - 40 * $day);
        $recentDone = new Run(null, 1, TriggerType::Manual, [], $now - 2 * $day);
        foreach ([$oldDone, $oldWaiting, $recentDone] as $run) {
            $world->runs->add($run);
        }
        $oldDone->skip('done', $now);
        $recentDone->skip('done', $now);
        $world->runs->save($oldDone);
        $world->runs->save($recentDone);

        $deleted = (new PruneRunsHandler($world->runs, $this->settings(30), $world->clock))->handle();

        $this->assertSame(1, $deleted);
        $this->assertNull($world->runs->find((int) $oldDone->id()));
        $this->assertNotNull($world->runs->find((int) $oldWaiting->id()));
        $this->assertNotNull($world->runs->find((int) $recentDone->id()));
    }

    public function testZeroDaysKeepsEverything(): void
    {
        $world = new TestWorld();

        $this->assertSame(0, (new PruneRunsHandler($world->runs, $this->settings(0), $world->clock))->handle());
    }

    private function settings(int $days): Settings
    {
        return new class ($days) implements Settings {
            public function __construct(private readonly int $days)
            {
            }

            public function runRetentionDays(): int
            {
                return $this->days;
            }
        };
    }
}
