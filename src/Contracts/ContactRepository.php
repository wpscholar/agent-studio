<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Channel\Contact;

interface ContactRepository
{
    public function find(int $id): ?Contact;

    public function findByExternalId(int $channelId, string $externalId): ?Contact;

    /**
     * @return list<Contact> Newest first.
     */
    public function forChannel(int $channelId): array;

    public function save(Contact $contact): Contact;

    public function delete(int $id): void;

    public function deleteForChannel(int $channelId): void;
}
