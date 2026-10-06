<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Cli;

use WP_CLI;
use WPAgents\Application\Approval\ResolveApprovalHandler;
use WPAgents\Application\Dispatch\DispatchOutcome;
use WPAgents\Application\Dispatch\DispatchTriggerHandler;
use WPAgents\Application\Execute\ExecuteRunHandler;
use WPAgents\Application\Retention\PruneRunsHandler;
use WPAgents\Application\Run\CancelRunHandler;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\RunRepository;
use WPAgents\Domain\Agent\TriggerEvent;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\WordPress\Labels;

/**
 * Manage WP Agents from the command line.
 */
final class AgentsCommand
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly DispatchTriggerHandler $dispatch,
        private readonly ExecuteRunHandler $execute,
        private readonly ResolveApprovalHandler $approvals,
        private readonly CancelRunHandler $cancel,
        private readonly PruneRunsHandler $prune,
    ) {
    }

    public static function register(AgentsCommand $command): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        WP_CLI::add_command('agents', $command);
    }

    /**
     * Lists agents.
     *
     * ## EXAMPLES
     *
     *     wp agents list
     *
     * @subcommand list
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function list_(array $args, array $assocArgs): void
    {
        $rows = [];
        foreach ($this->agents->all() as $agent) {
            $rows[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status->value,
                'triggers' => implode(', ', array_map(static fn ($t): string => $t->type->value, $agent->triggers)),
                'abilities' => count($agent->abilities),
            ];
        }

        WP_CLI\Utils\format_items('table', $rows, ['id', 'name', 'status', 'triggers', 'abilities']);
    }

    /**
     * Starts an agent.
     *
     * ## OPTIONS
     *
     * <agent-id>
     * : The agent to run.
     *
     * [--payload=<json>]
     * : Event data to give the agent, as JSON.
     *
     * [--wait]
     * : Run it now and print the result, instead of queueing it.
     *
     * ## EXAMPLES
     *
     *     wp agents run 42 --payload='{"topic":"welcome"}' --wait
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function run(array $args, array $assocArgs): void
    {
        $agentId = (int) ($args[0] ?? 0);
        if ($this->agents->find($agentId) === null) {
            WP_CLI::error('There is no agent with that ID.');
        }

        $payload = [];
        if (isset($assocArgs['payload'])) {
            $decoded = json_decode((string) $assocArgs['payload'], true);
            if (! is_array($decoded)) {
                WP_CLI::error('The payload must be valid JSON describing an object.');
            }

            $payload = $decoded;
        }

        $result = $this->dispatch->handle(TriggerEvent::targeted(TriggerType::Manual, $agentId, $payload, '', 0));
        $dispatch = $result->forAgent($agentId);

        if ($dispatch === null || $dispatch->outcome !== DispatchOutcome::Queued || $dispatch->runId === null) {
            WP_CLI::error('The agent could not be started.');
        }

        $runId = $dispatch->runId;

        if (empty($assocArgs['wait'])) {
            WP_CLI::success(sprintf('Queued run %d.', $runId));

            return;
        }

        $this->execute->handle($runId);
        $this->show($runId);
    }

    /**
     * Shows recent runs.
     *
     * ## OPTIONS
     *
     * [--agent=<id>]
     * : Only runs of this agent.
     *
     * [--status=<status>]
     * : Only runs with this status, for example awaiting_approval.
     *
     * [--limit=<n>]
     * : How many to show. Default 20.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function runs(array $args, array $assocArgs): void
    {
        $agentId = isset($assocArgs['agent']) ? (int) $assocArgs['agent'] : null;
        $status = isset($assocArgs['status']) ? RunStatus::tryFrom((string) $assocArgs['status']) : null;
        $limit = max(1, min(200, (int) ($assocArgs['limit'] ?? 20)));

        $rows = [];
        foreach ($this->runs->recent($limit, 0, $agentId, $status) as $run) {
            $rows[] = [
                'id' => $run->id(),
                'agent' => $run->agentId(),
                'trigger' => $run->triggerType()->value,
                'status' => $run->status()->value,
                'created' => gmdate('Y-m-d H:i:s', $run->createdAt()),
            ];
        }

        WP_CLI\Utils\format_items('table', $rows, ['id', 'agent', 'trigger', 'status', 'created']);
    }

    /**
     * Shows one run.
     *
     * ## OPTIONS
     *
     * <run-id>
     * : The run to show.
     *
     * @subcommand show
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function show_(array $args, array $assocArgs): void
    {
        $this->show((int) ($args[0] ?? 0));
    }

    /**
     * Approves the action a run is waiting on.
     *
     * ## OPTIONS
     *
     * <run-id>
     * : The run to approve.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function approve(array $args, array $assocArgs): void
    {
        $this->resolve((int) ($args[0] ?? 0), true);
    }

    /**
     * Rejects the action a run is waiting on.
     *
     * ## OPTIONS
     *
     * <run-id>
     * : The run to reject.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function reject(array $args, array $assocArgs): void
    {
        $this->resolve((int) ($args[0] ?? 0), false);
    }

    /**
     * Cancels a queued run or one waiting for approval.
     *
     * ## OPTIONS
     *
     * <run-id>
     * : The run to cancel.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function cancel(array $args, array $assocArgs): void
    {
        if ($this->cancel->handle((int) ($args[0] ?? 0))) {
            WP_CLI::success('Cancelled.');

            return;
        }

        WP_CLI::error('That run cannot be cancelled.');
    }

    /**
     * Deletes runs older than the retention period.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     */
    public function prune(array $args, array $assocArgs): void
    {
        WP_CLI::success(sprintf('Deleted %d old runs.', $this->prune->handle()));
    }

    private function resolve(int $runId, bool $approve): void
    {
        $userId = get_current_user_id();
        if ($userId === 0) {
            WP_CLI::error('Run this with --user=<login> so the decision is recorded against a person.');
        }

        if (! $this->approvals->handle($runId, $approve, $userId)) {
            WP_CLI::error('That run is not waiting for approval.');
        }

        WP_CLI::success($approve ? 'Approved.' : 'Rejected.');
    }

    private function show(int $runId): void
    {
        $run = $this->runs->find($runId);
        if ($run === null) {
            WP_CLI::error('There is no run with that ID.');
        }

        WP_CLI::log(sprintf('Run #%d: %s', $runId, Labels::status($run->status())));

        foreach ($run->steps() as $step) {
            WP_CLI::log(sprintf('  [%s] %s', $step->kind->value, $step->label));
        }

        if ($run->output() !== '') {
            WP_CLI::log('');
            WP_CLI::log($run->output());
        }

        if ($run->error() !== '') {
            WP_CLI::warning($run->error());
        }

        $pending = $run->pendingApproval();
        if ($pending !== null) {
            WP_CLI::warning(sprintf('Waiting for approval to use %s. Run: wp agents approve %d', $pending->abilityName, $runId));
        }
    }
}
