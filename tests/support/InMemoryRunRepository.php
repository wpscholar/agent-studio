<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;

/**
 * Stores runs as arrays, like a database would, so tests also prove that runs
 * survive being saved and loaded.
 */
final class InMemoryRunRepository implements RunRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];
    private int $nextId = 1;

    public function add(Run $run): void
    {
        $run->assignId($this->nextId++);
        $this->save($run);
    }

    public function save(Run $run): void
    {
        $this->rows[(int) $run->id()] = $run->toArray();
    }

    public function find(int $id): ?Run
    {
        return isset($this->rows[$id]) ? Run::fromArray($this->rows[$id]) : null;
    }

    public function claim(int $id, int $now): ?Run
    {
        $run = $this->find($id);
        if ($run === null || $run->status() !== RunStatus::Queued) {
            return null;
        }

        $run->start($now);
        $this->save($run);

        return $run;
    }

    public function findByDedupeKey(int $agentId, string $dedupeKey, int $since): ?Run
    {
        foreach ($this->rows as $row) {
            if ($row['agent_id'] === $agentId && $row['dedupe_key'] === $dedupeKey && $row['created_at'] >= $since) {
                return Run::fromArray($row);
            }
        }

        return null;
    }

    public function countSince(int $agentId, int $since): int
    {
        $count = 0;
        foreach ($this->rows as $row) {
            if ($row['agent_id'] === $agentId && $row['created_at'] >= $since && $row['status'] !== RunStatus::Skipped->value) {
                $count++;
            }
        }

        return $count;
    }

    public function recent(int $limit, int $offset = 0, ?int $agentId = null, ?RunStatus $status = null): array
    {
        $rows = array_filter(
            $this->rows,
            static fn (array $row): bool => ($agentId === null || $row['agent_id'] === $agentId)
                && ($status === null || $row['status'] === $status->value)
        );
        krsort($rows);

        return array_map(
            static fn (array $row): Run => Run::fromArray($row),
            array_values(array_slice($rows, $offset, $limit))
        );
    }

    public function count(?int $agentId = null, ?RunStatus $status = null): int
    {
        return count($this->recent(PHP_INT_MAX, 0, $agentId, $status));
    }

    public function pruneOlderThan(int $timestamp): int
    {
        $deleted = 0;
        foreach ($this->rows as $id => $row) {
            if ($row['created_at'] < $timestamp && RunStatus::fromString($row['status'])->isTerminal()) {
                unset($this->rows[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }
}
