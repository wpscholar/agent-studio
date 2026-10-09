<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Channel\Channel;

interface ChannelRepository
{
    public function find(int $id): ?Channel;

    /**
     * @return list<Channel>
     */
    public function all(): array;

    /**
     * Creates the channel when its id is 0, otherwise updates it. Returns it with its id.
     */
    public function save(Channel $channel): Channel;

    public function delete(int $id): void;
}
