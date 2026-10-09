<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use WP_User;
use AgentStudio\Domain\Agent\Agent;

/**
 * Who can do what.
 *
 * - Manage: set up agents, skills, channels and connections.
 * - Approve: approve or reject any agent's actions.
 * - Chat: talk to agents from a linked chat account.
 *
 * Anyone with the base capability (manage_options by default) has all three.
 * A role editor can also give them to other roles one by one. The person who
 * set up an agent can approve that agent's actions if they can still manage agents.
 */
final class Capabilities
{
    public const MANAGE = 'agent_studio_manage';
    public const APPROVE = 'agent_studio_approve';
    public const CHAT = 'agent_studio_chat';

    public const ALL = [self::MANAGE, self::APPROVE, self::CHAT];

    public function register(): void
    {
        add_filter('user_has_cap', [self::class, 'grant'], 10, 4);
    }

    /**
     * Gives every Agent Studio capability to users who have the base capability,
     * unless a role explicitly denies one.
     *
     * @param array<string, bool> $allcaps
     * @param list<string>        $caps
     * @param array<int, mixed>   $args
     *
     * @return array<string, bool>
     */
    public static function grant(array $allcaps, array $caps, array $args, WP_User $user): array
    {
        $base = self::base();
        if (in_array($base, self::ALL, true) || empty($allcaps[$base])) {
            return $allcaps;
        }

        foreach (self::ALL as $capability) {
            if (! array_key_exists($capability, $allcaps)) {
                $allcaps[$capability] = true;
            }
        }

        return $allcaps;
    }

    /**
     * The capability needed to manage agents, skills, channels and connections.
     */
    public static function manage(): string
    {
        return self::MANAGE;
    }

    public static function canManage(): bool
    {
        return current_user_can(self::MANAGE);
    }

    /**
     * Admins, people given the approve capability, and the person who set up the agent.
     */
    public static function canApprove(?Agent $agent, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (user_can($userId, self::APPROVE)) {
            return true;
        }

        return $agent !== null && $agent->ownerId === $userId && user_can($userId, self::MANAGE);
    }

    public static function canChat(int $userId): bool
    {
        return $userId > 0 && (user_can($userId, self::CHAT) || user_can($userId, self::MANAGE));
    }

    private static function base(): string
    {
        /**
         * Filters the capability that grants every Agent Studio capability.
         *
         * Agents can change content and send email, so this defaults to the
         * administrator capability.
         *
         * @param string $capability Default "manage_options".
         */
        return (string) apply_filters('agent_studio_manage_capability', 'manage_options');
    }
}
