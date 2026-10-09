<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\Notifier;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

/**
 * Emails the site administrator, and the person who set the agent up, when
 * an agent needs approval.
 */
final class WordPressNotifier implements Notifier
{
    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        /**
         * Filters who is emailed when an agent needs approval.
         *
         * Return an empty string to turn the email off (also for the agent's owner, who is otherwise emailed too).
         *
         * @param string $email Recipient. Default the site admin email.
         * @param Agent  $agent The agent.
         * @param Run    $run   The run that is waiting.
         */
        $to = apply_filters('agent_studio_approval_notify_email', get_option('admin_email'), $agent, $run);

        if ($to === '') {
            return;
        }

        $recipients = is_string($to) && is_email($to) ? [$to] : [];

        $owner = $agent->ownerId > 0 ? get_userdata($agent->ownerId) : false;
        if ($owner !== false && is_email($owner->user_email) && \AgentStudio\WordPress\Capabilities::canApprove($agent, $owner->ID)) {
            $recipients[] = $owner->user_email;
        }

        $recipients = array_values(array_unique(array_map('strtolower', $recipients)));
        if ($recipients === []) {
            return;
        }

        $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
        $link = admin_url('admin.php?page=agent-studio-runs&run=' . (int) $run->id());

        $subject = sprintf('[%s] "%s" needs your approval', $site, $agent->name);
        $body = sprintf(
            "The agent \"%s\" wants to use the ability \"%s\" (%s).\n\nReview and approve or reject it here:\n%s\n",
            $agent->name,
            $pending->abilityName,
            $pending->risk->value,
            $link
        );

        wp_mail($recipients, $subject, $body);
    }
}
