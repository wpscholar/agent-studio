<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use RuntimeException;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;

final class WpdbRunRepository implements RunRepository
{
    public function add(Run $run): void
    {
        global $wpdb;

        $inserted = $wpdb->insert(Schema::table(), $this->row($run, true));
        if ($inserted === false) {
            throw new RuntimeException('The run could not be stored: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; logged by the caller.
        }

        $run->assignId((int) $wpdb->insert_id);
    }

    public function save(Run $run): void
    {
        global $wpdb;

        if ($run->id() === null) {
            $this->add($run);

            return;
        }

        $wpdb->update(Schema::table(), $this->row($run, false), ['id' => $run->id()]);
    }

    public function find(int $id): ?Run
    {
        global $wpdb;

        $table = Schema::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function claim(int $id, int $now): ?Run
    {
        global $wpdb;

        $table = Schema::table();
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, started_at = CASE WHEN started_at = 0 THEN %d ELSE started_at END WHERE id = %d AND status = %s",
                RunStatus::Running->value,
                $now,
                $id,
                RunStatus::Queued->value
            )
        );

        return $claimed === 1 ? $this->find($id) : null;
    }

    public function findByDedupeKey(int $agentId, string $dedupeKey, int $since): ?Run
    {
        global $wpdb;

        $table = Schema::table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE agent_id = %d AND dedupe_key = %s AND created_at >= %d ORDER BY id DESC LIMIT 1",
                $agentId,
                $dedupeKey,
                $since
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function countSince(int $agentId, int $since): int
    {
        global $wpdb;

        $table = Schema::table();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE agent_id = %d AND created_at >= %d AND status <> %s",
                $agentId,
                $since,
                RunStatus::Skipped->value
            )
        );
    }

    public function recent(int $limit, int $offset = 0, ?int $agentId = null, ?RunStatus $status = null): array
    {
        global $wpdb;

        $table = Schema::table();
        [$where, $args] = $this->filter($agentId, $status);
        $args[] = max(1, $limit);
        $args[] = max(0, $offset);

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $args),
            ARRAY_A
        );

        $runs = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $runs[] = $this->hydrate($row);
        }

        return $runs;
    }

    public function count(?int $agentId = null, ?RunStatus $status = null): int
    {
        global $wpdb;

        $table = Schema::table();
        [$where, $args] = $this->filter($agentId, $status);
        $sql = "SELECT COUNT(*) FROM {$table} {$where}";

        return (int) $wpdb->get_var($args === [] ? $sql : $wpdb->prepare($sql, $args));
    }

    public function pruneOlderThan(int $timestamp): int
    {
        global $wpdb;

        $table = Schema::table();
        $finished = "'" . implode("','", [
            RunStatus::Completed->value,
            RunStatus::Failed->value,
            RunStatus::Cancelled->value,
            RunStatus::Skipped->value,
        ]) . "'";

        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE created_at < %d AND status IN ({$finished})", $timestamp)
        );

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function filter(?int $agentId, ?RunStatus $status): array
    {
        $clauses = [];
        $args = [];

        if ($agentId !== null) {
            $clauses[] = 'agent_id = %d';
            $args[] = $agentId;
        }

        if ($status !== null) {
            $clauses[] = 'status = %s';
            $args[] = $status->value;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $args];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Run $run, bool $includeImmutable): array
    {
        $data = $run->toArray();

        $row = [
            'status' => $data['status'],
            'input' => $this->encode($data['input']),
            'transcript' => $this->encode($data['transcript']),
            'steps' => $this->encode($data['steps']),
            'pending' => $data['pending'] === null ? null : $this->encode($data['pending']),
            'output' => $data['output'],
            'error' => $data['error'],
            'tokens_in' => $data['tokens_in'],
            'tokens_out' => $data['tokens_out'],
            'started_at' => $data['started_at'],
            'finished_at' => $data['finished_at'],
        ];

        if ($includeImmutable) {
            $row['agent_id'] = $data['agent_id'];
            $row['trigger_type'] = $data['trigger_type'];
            $row['dedupe_key'] = substr((string) $data['dedupe_key'], 0, 191);
            $row['created_at'] = $data['created_at'];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Run
    {
        foreach (['input', 'transcript', 'steps', 'pending'] as $column) {
            $value = $row[$column] ?? null;
            $row[$column] = is_string($value) && $value !== '' ? json_decode($value, true) : null;
        }

        $row['input'] = is_array($row['input']) ? $row['input'] : [];
        $row['transcript'] = is_array($row['transcript']) ? $row['transcript'] : [];
        $row['steps'] = is_array($row['steps']) ? $row['steps'] : [];

        return Run::fromArray($row);
    }

    private function encode(mixed $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return is_string($json) ? $json : '[]';
    }
}
