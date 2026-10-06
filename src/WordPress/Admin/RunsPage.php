<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WPAgents\Application\Approval\ResolveApprovalHandler;
use WPAgents\Application\Run\CancelRunHandler;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\RunRepository;
use WPAgents\Domain\Run\Run;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\WordPress\Capabilities;
use WPAgents\WordPress\Labels;
use WPAgents\WordPress\Urls;

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
        add_action('admin_post_wpa_resolve_approval', [$this, 'resolveApproval']);
        add_action('admin_post_wpa_cancel_run', [$this, 'cancelRun']);
    }

    public function resolveApproval(): void
    {
        $runId = isset($_POST['run_id']) ? absint(wp_unslash($_POST['run_id'])) : 0;
        $this->authorize('wpa_run_' . $runId);

        $approve = isset($_POST['decision']) && sanitize_key(wp_unslash($_POST['decision'])) === 'approve';
        $done = $this->approvals->handle($runId, $approve, get_current_user_id());

        Flash::redirect(
            Urls::run($runId),
            $done ? 'success' : 'error',
            $done
                ? ($approve ? __('Approved. The agent will carry on.', 'wp-agents') : __('Rejected. The agent will carry on without it.', 'wp-agents'))
                : __('This run is no longer waiting for approval.', 'wp-agents')
        );
    }

    public function cancelRun(): void
    {
        $runId = isset($_POST['run_id']) ? absint(wp_unslash($_POST['run_id'])) : 0;
        $this->authorize('wpa_run_' . $runId);

        $done = $this->cancel->handle($runId);

        Flash::redirect(
            Urls::run($runId),
            $done ? 'success' : 'error',
            $done ? __('The run was cancelled.', 'wp-agents') : __('This run can no longer be cancelled.', 'wp-agents')
        );
    }

    private function authorize(string $action): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'wp-agents'), '', ['response' => 403]);
        }

        check_admin_referer($action);
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'wp-agents'), '', ['response' => 403]);
        }

        echo '<div class="wrap wpa-wrap"><h1 class="wp-heading-inline">' . esc_html__('Agent runs', 'wp-agents') . '</h1><hr class="wp-header-end">';
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
        $links[] = sprintf('<li><a href="%s"%s>%s</a></li>', esc_url(Urls::runs()), $status === null ? ' class="current"' : '', esc_html__('All', 'wp-agents'));
        foreach ([RunStatus::AwaitingApproval, RunStatus::Running, RunStatus::Queued, RunStatus::Completed, RunStatus::Failed] as $case) {
            $links[] = sprintf(
                '<li><a href="%s"%s>%s</a></li>',
                esc_url(add_query_arg('status', $case->value, Urls::runs())),
                $status === $case ? ' class="current"' : '',
                esc_html(Labels::status($case))
            );
        }
        echo implode(' | ', $links) . '</ul>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ([__('Run', 'wp-agents'), __('Agent', 'wp-agents'), __('Started by', 'wp-agents'), __('Status', 'wp-agents'), __('When', 'wp-agents')] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($runs === []) {
            echo '<tr><td colspan="5">' . esc_html__('No runs yet. Runs appear here as soon as an agent is started.', 'wp-agents') . '</td></tr>';
        }

        foreach ($runs as $run) {
            $id = (int) $run->id();
            $agent = $this->agents->find($run->agentId());
            printf(
                '<tr><td><a href="%1$s">#%2$d</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
                esc_url(Urls::run($id)),
                $id,
                esc_html($agent !== null ? $agent->name : __('(deleted agent)', 'wp-agents')),
                esc_html(Labels::trigger($run->triggerType())),
                $this->badge($run->status()),
                esc_html(human_time_diff($run->createdAt()) . ' ' . __('ago', 'wp-agents'))
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
        return sprintf('<span class="wpa-badge wpa-badge--%s">%s</span>', esc_attr($status->value), esc_html(Labels::status($status)));
    }

    private function detail(Run $run): void
    {
        $id = (int) $run->id();
        $agent = $this->agents->find($run->agentId());

        printf('<p><a href="%s">&larr; %s</a></p>', esc_url(Urls::runs()), esc_html__('All runs', 'wp-agents'));
        printf(
            '<h2>%s</h2>',
            esc_html(sprintf(
                /* translators: 1: run number, 2: agent name */
                __('Run #%1$d · %2$s', 'wp-agents'),
                $id,
                $agent !== null ? $agent->name : __('(deleted agent)', 'wp-agents')
            ))
        );

        echo '<table class="widefat striped wpa-meta"><tbody>';
        $this->row(__('Status', 'wp-agents'), $this->badge($run->status()));
        $this->row(__('Started by', 'wp-agents'), esc_html(Labels::trigger($run->triggerType())));
        $this->row(__('Created', 'wp-agents'), esc_html(wp_date('Y-m-d H:i:s', $run->createdAt())));
        if ($run->finishedAt() > 0) {
            $this->row(__('Finished', 'wp-agents'), esc_html(wp_date('Y-m-d H:i:s', $run->finishedAt())));
        }
        $this->row(__('Model calls', 'wp-agents'), esc_html((string) $run->modelCalls()));
        $this->row(__('Tokens', 'wp-agents'), esc_html(sprintf('%d in · %d out', $run->tokensIn(), $run->tokensOut())));
        if ($agent !== null) {
            $this->row(__('Agent', 'wp-agents'), '<a href="' . esc_url(Urls::editAgent($agent->id)) . '">' . esc_html__('Edit agent', 'wp-agents') . '</a>');
        }
        echo '</tbody></table>';

        $pending = $run->pendingApproval();
        if ($pending !== null && $run->status() === RunStatus::AwaitingApproval) {
            echo '<div class="notice notice-warning inline wpa-approval"><h3>' . esc_html__('This agent is asking for permission', 'wp-agents') . '</h3>';
            printf(
                '<p>%s <code>%s</code> <span class="wpa-risk wpa-risk--%s">%s</span></p>',
                esc_html__('It wants to use', 'wp-agents'),
                esc_html($pending->abilityName),
                esc_attr($pending->risk->value),
                esc_html(Labels::risk($pending->risk))
            );
            echo '<pre class="wpa-json">' . esc_html($this->json($pending->input)) . '</pre>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wpa-inline-form">';
            wp_nonce_field('wpa_run_' . $id);
            echo '<input type="hidden" name="action" value="wpa_resolve_approval"><input type="hidden" name="run_id" value="' . esc_attr((string) $id) . '">';
            echo '<button class="button button-primary" name="decision" value="approve">' . esc_html__('Approve', 'wp-agents') . '</button> ';
            echo '<button class="button" name="decision" value="reject">' . esc_html__('Reject', 'wp-agents') . '</button>';
            echo '</form></div>';
        }

        if ($run->status() === RunStatus::Queued || $run->status() === RunStatus::AwaitingApproval) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wpa-inline-form">';
            wp_nonce_field('wpa_run_' . $id);
            echo '<input type="hidden" name="action" value="wpa_cancel_run"><input type="hidden" name="run_id" value="' . esc_attr((string) $id) . '">';
            echo '<button class="button button-link-delete">' . esc_html__('Cancel this run', 'wp-agents') . '</button></form>';
        }

        if ($run->output() !== '') {
            echo '<h3>' . esc_html__('Result', 'wp-agents') . '</h3><div class="wpa-output">' . nl2br(esc_html($run->output())) . '</div>';
        }

        if ($run->error() !== '') {
            echo '<h3>' . esc_html__('Problem', 'wp-agents') . '</h3><div class="notice notice-error inline"><p>' . esc_html($run->error()) . '</p></div>';
        }

        echo '<h3>' . esc_html__('What started it', 'wp-agents') . '</h3>';
        echo '<p class="description">' . esc_html__('This data came from outside the agent and is shown exactly as received.', 'wp-agents') . '</p>';
        echo '<pre class="wpa-json">' . esc_html($this->json($run->input())) . '</pre>';

        echo '<h3>' . esc_html__('Log', 'wp-agents') . '</h3><ol class="wpa-steps">';
        foreach ($run->steps() as $step) {
            echo '<li><span class="wpa-step-kind">' . esc_html(Labels::step($step->kind)) . '</span> ';
            echo '<span class="wpa-step-time">' . esc_html(wp_date('H:i:s', $step->at)) . '</span>';
            if ($step->label !== '') {
                echo '<div>' . esc_html($step->label) . '</div>';
            }
            if ($step->data !== []) {
                echo '<details><summary>' . esc_html__('Details', 'wp-agents') . '</summary><pre class="wpa-json">' . esc_html($this->json($step->data)) . '</pre></details>';
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
