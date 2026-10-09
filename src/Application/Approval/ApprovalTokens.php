<?php

declare(strict_types=1);

namespace AgentStudio\Application\Approval;

use AgentStudio\Domain\Run\Run;

/**
 * Signs Approve and Reject buttons sent through channels, so a button only
 * works for the exact action it was sent for, and only until it is decided.
 *
 * Chat buttons carry a short value ("ap:<run>:<sig>"); who pressed it is
 * checked separately. Links (for channels that can only open a URL) also bind
 * the channel and the person they were sent to.
 */
final class ApprovalTokens
{
    private const BUTTON_SIG_LENGTH = 20;

    public function __construct(private readonly string $key)
    {
    }

    public function button(Run $run, bool $approve): string
    {
        return ($approve ? 'ap' : 'rj') . ':' . (int) $run->id() . ':' . $this->sign('button', $run, $approve, self::BUTTON_SIG_LENGTH);
    }

    /**
     * @return array{run: int, approve: bool, signature: string}|null
     */
    public static function parseButton(string $value): ?array
    {
        if (preg_match('/^(ap|rj):(\d+):([a-f0-9]+)$/', $value, $m) !== 1) {
            return null;
        }

        return ['run' => (int) $m[2], 'approve' => $m[1] === 'ap', 'signature' => $m[3]];
    }

    public function verifyButton(Run $run, bool $approve, string $signature): bool
    {
        return $run->pendingApproval() !== null
            && hash_equals($this->sign('button', $run, $approve, self::BUTTON_SIG_LENGTH), $signature);
    }

    public function link(Run $run, bool $approve, int $channelId, int $userId): string
    {
        return $this->sign('link|' . $channelId . '|' . $userId, $run, $approve, 64);
    }

    public function verifyLink(Run $run, bool $approve, int $channelId, int $userId, string $token): bool
    {
        return $run->pendingApproval() !== null
            && hash_equals($this->link($run, $approve, $channelId, $userId), $token);
    }

    private function sign(string $purpose, Run $run, bool $approve, int $length): string
    {
        $pending = $run->pendingApproval();
        $data = implode('|', [$purpose, (int) $run->id(), $pending === null ? '' : $pending->toolCallId, $approve ? 'approve' : 'reject']);

        return substr(hash_hmac('sha256', $data, $this->key), 0, $length);
    }
}
