<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Domain\Ability\Risk;
use AgentStudio\Domain\Agent\ApprovalMode;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Domain\Run\StepKind;

/**
 * Translatable names for the values the Domain only knows as codes.
 */
final class Labels
{
    public static function risk(Risk $risk): string
    {
        return match ($risk) {
            Risk::Read => __('Read', 'agent-studio'),
            Risk::Write => __('Write', 'agent-studio'),
            Risk::Destructive => __('Destructive', 'agent-studio'),
            Risk::External => __('External', 'agent-studio'),
        };
    }

    public static function riskHelp(Risk $risk): string
    {
        return match ($risk) {
            Risk::Read => __('Reads data and changes nothing.', 'agent-studio'),
            Risk::Write => __('Creates or changes site data.', 'agent-studio'),
            Risk::Destructive => __('Deletes data or changes it in a way that is hard to undo.', 'agent-studio'),
            Risk::External => __('Has an effect outside the site, such as sending email.', 'agent-studio'),
        };
    }

    public static function status(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Queued => __('Queued', 'agent-studio'),
            RunStatus::Running => __('Running', 'agent-studio'),
            RunStatus::AwaitingApproval => __('Needs approval', 'agent-studio'),
            RunStatus::Completed => __('Completed', 'agent-studio'),
            RunStatus::Failed => __('Failed', 'agent-studio'),
            RunStatus::Cancelled => __('Cancelled', 'agent-studio'),
            RunStatus::Skipped => __('Skipped', 'agent-studio'),
        };
    }

    public static function trigger(TriggerType $type): string
    {
        return match ($type) {
            TriggerType::PostStatus => __('Post status change', 'agent-studio'),
            TriggerType::UserRegistered => __('New user', 'agent-studio'),
            TriggerType::CommentPosted => __('New comment', 'agent-studio'),
            TriggerType::Schedule => __('Schedule', 'agent-studio'),
            TriggerType::Webhook => __('Webhook', 'agent-studio'),
            TriggerType::Manual => __('Manual', 'agent-studio'),
            TriggerType::Message => __('Message', 'agent-studio'),
            TriggerType::Custom => __('Plugin event', 'agent-studio'),
        };
    }

    public static function approval(ApprovalMode $mode): string
    {
        return match ($mode) {
            ApprovalMode::Writes => __('Ask before any change (recommended)', 'agent-studio'),
            ApprovalMode::Destructive => __('Ask only before destructive or external actions', 'agent-studio'),
            ApprovalMode::Never => __('Never ask', 'agent-studio'),
        };
    }

    public static function step(StepKind $kind): string
    {
        return match ($kind) {
            StepKind::Model => __('Model', 'agent-studio'),
            StepKind::ToolCall => __('Tool call', 'agent-studio'),
            StepKind::ToolResult => __('Tool result', 'agent-studio'),
            StepKind::Approval => __('Approval', 'agent-studio'),
            StepKind::Note => __('Note', 'agent-studio'),
            StepKind::Error => __('Error', 'agent-studio'),
        };
    }
}
