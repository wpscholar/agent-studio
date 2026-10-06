<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

use WPAgents\Domain\Run\Run;
use WPAgents\Domain\Run\RunStatus;

interface RunRepository
{
    /**
     * Stores a new run and assigns its id.
     */
    public function add(Run $run): void;

    public function save(Run $run): void;

    public function find(int $id): ?Run;

    /**
     * Atomically moves a queued run to running and returns it.
     *
     * Returns null when the run does not exist or is not queued, which means
     * another worker already took it.
     */
    public function claim(int $id, int $now): ?Run;

    /**
     * A run for this agent with this dedupe key created at or after $since.
     */
    public function findByDedupeKey(int $agentId, string $dedupeKey, int $since): ?Run;

    /**
     * Runs for this agent created at or after $since, excluding skipped ones.
     */
    public function countSince(int $agentId, int $since): int;

    /**
     * Newest first.
     *
     * @return list<Run>
     */
    public function recent(int $limit, int $offset = 0, ?int $agentId = null, ?RunStatus $status = null): array;

    public function count(?int $agentId = null, ?RunStatus $status = null): int;

    /**
     * Deletes finished runs created before the timestamp. Returns how many were deleted.
     */
    public function pruneOlderThan(int $timestamp): int;
}
