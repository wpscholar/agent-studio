<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

use WPAgents\Contracts\Identity;

final class WordPressIdentity implements Identity
{
    public function runAs(int $userId, callable $callback): mixed
    {
        $previous = get_current_user_id();
        wp_set_current_user($userId);

        try {
            return $callback();
        } finally {
            wp_set_current_user($previous);
        }
    }
}
