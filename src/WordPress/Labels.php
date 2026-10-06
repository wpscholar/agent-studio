<?php

declare(strict_types=1);

namespace WPAgents\WordPress;

use WPAgents\Domain\Ability\Risk;
use WPAgents\Domain\Agent\ApprovalMode;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\Domain\Run\StepKind;

/**
 * Translatable names for the values the Domain only knows as codes.
 */
final class Labels
{
    public static function risk(Risk $risk): string
    {
        return match ($risk) {
            Risk::Read => __('Read', 'wp-agents'),
            Risk::Write => __('Write', 'wp-agents'),
            Risk::Destructive => __('Destructive', 'wp-agents'),
            Risk::External => __('External', 'wp-agents'),
        };
    }

    public static function riskHelp(Risk $risk): string
    {
        return match ($risk) {
            Risk::Read => __('Reads data and changes nothing.', 'wp-agents'),
            Risk::Write => __('Creates or changes site data.', 'wp-agents'),
            Risk::Destructive => __('Deletes data or changes it in a way that is hard to undo.', 'wp-agents'),
            Risk::External => __('Has an effect outside the site, such as sending email.', 'wp-agents'),
        };
    }

    public static function status(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Queued => __('Queued', 'wp-agents'),
            RunStatus::Running => __('Running', 'wp-agents'),
            RunStatus::AwaitingApproval => __('Needs approval', 'wp-agents'),
            RunStatus::Completed => __('Completed', 'wp-agents'),
            RunStatus::Failed => __('Failed', 'wp-agents'),
            RunStatus::Cancelled => __('Cancelled', 'wp-agents'),
            RunStatus::Skipped => __('Skipped', 'wp-agents'),
        };
    }

    public static function trigger(TriggerType $type): string
    {
        return match ($type) {
            TriggerType::PostStatus => __('Post status change', 'wp-agents'),
            TriggerType::UserRegistered => __('New user', 'wp-agents'),
            TriggerType::CommentPosted => __('New comment', 'wp-agents'),
            TriggerType::Webhook => __('Webhook', 'wp-agents'),
            TriggerType::Manual => __('Manual', 'wp-agents'),
        };
    }

    public static function approval(ApprovalMode $mode): string
    {
        return match ($mode) {
            ApprovalMode::Writes => __('Ask before any change (recommended)', 'wp-agents'),
            ApprovalMode::Destructive => __('Ask only before destructive or external actions', 'wp-agents'),
            ApprovalMode::Never => __('Never ask', 'wp-agents'),
        };
    }

    public static function step(StepKind $kind): string
    {
        return match ($kind) {
            StepKind::Model => __('Model', 'wp-agents'),
            StepKind::ToolCall => __('Tool call', 'wp-agents'),
            StepKind::ToolResult => __('Tool result', 'wp-agents'),
            StepKind::Approval => __('Approval', 'wp-agents'),
            StepKind::Note => __('Note', 'wp-agents'),
            StepKind::Error => __('Error', 'wp-agents'),
        };
    }
}
