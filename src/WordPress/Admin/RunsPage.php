<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Run\CancelRunHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Labels;
use AgentStudio\WordPress\Urls;

/**
 * The run log: what agents did, and the place to approve or reject what they ask to do.
 */
final class RunsPage
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly RunRepository $runs,
        private readonly AgentRepository $agents,
        private readonly ResolveApprovalHandler $approvals,
        private readonly CancelRunHandler $cancel,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_agent_studio_resolve_approval', [$this, 'resolveApproval']);
        add_action('admin_post_agent_studio_cancel_run', [$this, 'cancelRun']);
    }

    public function resolveApproval(): void
    {
        $runId = isset($_POST['run_id']) ? absint(wp_unslash($_POST['run_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() checks the nonce.
        $run = $this->runs->find($runId);
        $agent = $run === null ? null : $this->agents->find($run->agentId());
        if (! Capabilities::canApprove($agent, get_current_user_id())) {
            wp_die(esc_html__('You are not allowed to approve this agent’s actions.', 'agent-studio'), '', ['response' => 403]);
        }
        check_admin_referer('agent_studio_run_' . $runId);

        $approve = isset($_POST['decision']) && sanitize_key(wp_unslash($_POST['decision'])) === 'approve'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() checks the nonce.
        $done = $this->approvals->handle($runId, $approve, get_current_user_id());

        Flash::redirect(
            Urls::run($runId),
            $done ? 'success' : 'error',
            $done
                ? ($approve ? __('Approved. The agent will carry on.', 'agent-studio') : __('Rejected. The agent will carry on without it.', 'agent-studio'))
                : __('This run is no longer waiting for approval.', 'agent-studio')
        );
    }

    public function cancelRun(): void
    {
        $runId = isset($_POST['run_id']) ? absint(wp_unslash($_POST['run_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() checks the nonce.
        $this->authorize('agent_studio_run_' . $runId);

        $done = $this->cancel->handle($runId);

        Flash::redirect(
            Urls::run($runId),
            $done ? 'success' : 'error',
            $done ? __('The run was cancelled.', 'agent-studio') : __('This run can no longer be cancelled.', 'agent-studio')
        );
    }

    private function authorize(string $action): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'agent-studio'), '', ['response' => 403]);
        }

        check_admin_referer($action);
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'agent-studio'), '', ['response' => 403]);
        }

        echo '<div class="wrap agent-studio-wrap"><h1 class="wp-heading-inline">' . esc_html__('Agent runs', 'agent-studio') . '</h1><hr class="wp-header-end">';
        Flash::render();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page navigation.
        $runId = isset($_GET['run']) ? absint(wp_unslash($_GET['run'])) : 0;
        $run = $runId > 0 ? $this->runs->find($runId) : null;

        if ($run !== null) {
            $this->detail($run);
        } else {
            $this->listing();
        }

        echo '</div>';
    }

    private function listing(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
        $status = isset($_GET['status']) ? RunStatus::tryFrom(sanitize_key(wp_unslash($_GET['status']))) : null;
        $agentId = isset($_GET['agent']) ? absint(wp_unslash($_GET['agent'])) : 0;
        $page = max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1);
        // phpcs:enable

        $filterAgent = $agentId > 0 ? $agentId : null;
        $total = $this->runs->count($filterAgent, $status);
        $runs = $this->runs->recent(self::PER_PAGE, ($page - 1) * self::PER_PAGE, $filterAgent, $status);

        echo '<ul class="subsubsub">';
        $links = [];
        $links[] = sprintf('<li><a href="%s"%s>%s</a></li>', esc_url(Urls::runs()), $status === null ? ' class="current"' : '', esc_html__('All', 'agent-studio'));
        foreach ([RunStatus::AwaitingApproval, RunStatus::Running, RunStatus::Queued, RunStatus::Completed, RunStatus::Failed] as $case) {
            $links[] = sprintf(
                '<li><a href="%s"%s>%s</a></li>',
                esc_url(add_query_arg('status', $case->value, Urls::runs())),
                $status === $case ? ' class="current"' : '',
                esc_html(Labels::status($case))
            );
        }
        echo wp_kses_post(implode(' | ', $links)) . '</ul>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ([__('Run', 'agent-studio'), __('Agent', 'agent-studio'), __('Started by', 'agent-studio'), __('Status', 'agent-studio'), __('When', 'agent-studio')] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($runs === []) {
            echo '<tr><td colspan="5">' . esc_html__('No runs yet. Runs appear here as soon as an agent is started.', 'agent-studio') . '</td></tr>';
        }

        foreach ($runs as $run) {
            $id = (int) $run->id();
            $agent = $this->agents->find($run->agentId());
            printf(
                '<tr><td><a href="%1$s">#%2$d</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
                esc_url(Urls::run($id)),
                (int) $id,
                esc_html($agent !== null ? $agent->name : __('(deleted agent)', 'agent-studio')),
                esc_html(Labels::trigger($run->triggerType())),
                wp_kses_post($this->badge($run->status())),
                esc_html(human_time_diff($run->createdAt()) . ' ' . __('ago', 'agent-studio'))
            );
        }

        echo '</tbody></table>';

        $pages = (int) ceil($total / self::PER_PAGE);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(
                paginate_links(
                    [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $pages,
                    ]
                ) ?? ''
            );
            echo '</div></div>';
        }
    }

    private function badge(RunStatus $status): string
    {
        return sprintf('<span class="agent-studio-badge agent-studio-badge--%s">%s</span>', esc_attr($status->value), esc_html(Labels::status($status)));
    }

    private function detail(Run $run): void
    {
        $id = (int) $run->id();
        $agent = $this->agents->find($run->agentId());

        printf('<p><a href="%s">&larr; %s</a></p>', esc_url(Urls::runs()), esc_html__('All runs', 'agent-studio'));
        printf(
            '<h2>%s</h2>',
            esc_html(sprintf(
                /* translators: 1: run number, 2: agent name */
                __('Run #%1$d · %2$s', 'agent-studio'),
                $id,
                $agent !== null ? $agent->name : __('(deleted agent)', 'agent-studio')
            ))
        );

        echo '<table class="widefat striped agent-studio-meta"><tbody>';
        $this->row(__('Status', 'agent-studio'), $this->badge($run->status()));
        $this->row(__('Started by', 'agent-studio'), esc_html(Labels::trigger($run->triggerType())));
        $created = (string) wp_date('Y-m-d H:i:s', $run->createdAt());
        $this->row(__('Created', 'agent-studio'), esc_html($created));
        if ($run->finishedAt() > 0) {
            $finished = (string) wp_date('Y-m-d H:i:s', $run->finishedAt());
            $this->row(__('Finished', 'agent-studio'), esc_html($finished));
        }
        $this->row(__('Model calls', 'agent-studio'), esc_html((string) $run->modelCalls()));
        $this->row(__('Tokens', 'agent-studio'), esc_html(sprintf('%d in · %d out', $run->tokensIn(), $run->tokensOut())));
        if ($agent !== null) {
            $this->row(__('Agent', 'agent-studio'), '<a href="' . esc_url(Urls::editAgent($agent->id)) . '">' . esc_html__('Edit agent', 'agent-studio') . '</a>');
        }
        echo '</tbody></table>';

        $pending = $run->pendingApproval();
        if ($pending !== null && $run->status() === RunStatus::AwaitingApproval) {
            echo '<div class="notice notice-warning inline agent-studio-approval"><h3>' . esc_html__('This agent is asking for permission', 'agent-studio') . '</h3>';
            printf(
                '<p>%s <code>%s</code> <span class="agent-studio-risk agent-studio-risk--%s">%s</span></p>',
                esc_html__('It wants to use', 'agent-studio'),
                esc_html($pending->abilityName),
                esc_attr($pending->risk->value),
                esc_html(Labels::risk($pending->risk))
            );
            echo '<pre class="agent-studio-json">' . esc_html($this->json($pending->input)) . '</pre>';
            if (Capabilities::canApprove($agent, get_current_user_id())) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="agent-studio-inline-form">';
                wp_nonce_field('agent_studio_run_' . $id);
                echo '<input type="hidden" name="action" value="agent_studio_resolve_approval"><input type="hidden" name="run_id" value="' . esc_attr((string) $id) . '">';
                echo '<button class="button button-primary" name="decision" value="approve">' . esc_html__('Approve', 'agent-studio') . '</button> ';
                echo '<button class="button" name="decision" value="reject">' . esc_html__('Reject', 'agent-studio') . '</button>';
                echo '</form>';
            } else {
                echo '<p>' . esc_html__('Only administrators and the person who set up this agent can approve this.', 'agent-studio') . '</p>';
            }
            echo '</div>';
        }

        if ($run->status() === RunStatus::Queued || $run->status() === RunStatus::AwaitingApproval) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="agent-studio-inline-form">';
            wp_nonce_field('agent_studio_run_' . $id);
            echo '<input type="hidden" name="action" value="agent_studio_cancel_run"><input type="hidden" name="run_id" value="' . esc_attr((string) $id) . '">';
            echo '<button class="button button-link-delete">' . esc_html__('Cancel this run', 'agent-studio') . '</button></form>';
        }

        if ($run->output() !== '') {
            echo '<h3>' . esc_html__('Result', 'agent-studio') . '</h3><div class="agent-studio-output">' . nl2br(esc_html($run->output())) . '</div>';
        }

        if ($run->error() !== '') {
            echo '<h3>' . esc_html__('Problem', 'agent-studio') . '</h3><div class="notice notice-error inline"><p>' . esc_html($run->error()) . '</p></div>';
        }

        echo '<h3>' . esc_html__('What started it', 'agent-studio') . '</h3>';
        echo '<p class="description">' . esc_html__('This data came from outside the agent and is shown exactly as received.', 'agent-studio') . '</p>';
        echo '<pre class="agent-studio-json">' . esc_html($this->json($run->input())) . '</pre>';

        echo '<h3>' . esc_html__('Log', 'agent-studio') . '</h3><ol class="agent-studio-steps">';
        foreach ($run->steps() as $step) {
            echo '<li><span class="agent-studio-step-kind">' . esc_html(Labels::step($step->kind)) . '</span> ';
            echo '<span class="agent-studio-step-time">' . esc_html((string) wp_date('H:i:s', $step->at)) . '</span>';
            if ($step->label !== '') {
                echo '<div>' . esc_html($step->label) . '</div>';
            }
            if ($step->data !== []) {
                echo '<details><summary>' . esc_html__('Details', 'agent-studio') . '</summary><pre class="agent-studio-json">' . esc_html($this->json($step->data)) . '</pre></details>';
            }
            echo '</li>';
        }
        echo '</ol>';
    }

    private function row(string $label, string $html): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . wp_kses_post($html) . '</td></tr>';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function json(array $data): string
    {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }
}
