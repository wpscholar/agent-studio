<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

use WPAgents\Contracts\Notifier;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Run\PendingApproval;
use WPAgents\Domain\Run\Run;

/**
 * Emails the site administrator when an agent needs approval.
 */
final class WordPressNotifier implements Notifier
{
    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        /**
         * Filters who is emailed when an agent needs approval.
         *
         * Return an empty string to turn the email off.
         *
         * @param string $email Recipient. Default the site admin email.
         * @param Agent  $agent The agent.
         * @param Run    $run   The run that is waiting.
         */
        $to = apply_filters('wpa_approval_notify_email', get_option('admin_email'), $agent, $run);

        if (! is_string($to) || ! is_email($to)) {
            return;
        }

        $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
        $link = admin_url('admin.php?page=wpa-runs&run=' . (int) $run->id());

        $subject = sprintf('[%s] "%s" needs your approval', $site, $agent->name);
        $body = sprintf(
            "The agent \"%s\" wants to use the ability \"%s\" (%s).\n\nReview and approve or reject it here:\n%s\n",
            $agent->name,
            $pending->abilityName,
            $pending->risk->value,
            $link
        );

        wp_mail($to, $subject, $body);
    }
}
