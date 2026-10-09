<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * A button under a message. Approve and Reject come back as an IncomingAction
 * (or, for channels without a way back, open a signed link); Link opens a page.
 */
final class MessageButton
{
    public const APPROVE = 'approve';
    public const REJECT = 'reject';
    public const LINK = 'link';

    /**
     * @param string $value For approve and reject: the action value. For link: the address.
     * @param string $url   For approve and reject on channels that can only open links: the signed address to call.
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $label,
        public readonly string $value,
        public readonly string $url = '',
    ) {
    }
}
