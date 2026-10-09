<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Domain\Channel\Contact;
use AgentStudio\Domain\Channel\ContactStatus;

final class WpdbContactRepository implements ContactRepository
{
    public function find(int $id): ?Contact
    {
        global $wpdb;

        $table = Schema::contacts();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findByExternalId(int $channelId, string $externalId): ?Contact
    {
        global $wpdb;

        $table = Schema::contacts();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE channel_id = %d AND external_id = %s", $channelId, $externalId), ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function forChannel(int $channelId): array
    {
        global $wpdb;

        $table = Schema::contacts();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE channel_id = %d ORDER BY last_seen_at DESC, id DESC", $channelId), ARRAY_A);

        return array_map([self::class, 'hydrate'], is_array($rows) ? $rows : []);
    }

    public function save(Contact $contact): Contact
    {
        global $wpdb;

        $row = [
            'channel_id' => $contact->channelId,
            'external_id' => $contact->externalId,
            'name' => mb_substr($contact->name, 0, 191),
            'status' => $contact->status->value,
            'user_id' => $contact->userId,
            'address' => $contact->address,
            'created_at' => $contact->createdAt,
            'last_seen_at' => $contact->lastSeenAt,
        ];

        if ($contact->id > 0) {
            $wpdb->update(Schema::contacts(), $row, ['id' => $contact->id]);

            return $contact;
        }

        $wpdb->insert(Schema::contacts(), $row);

        return new Contact(
            (int) $wpdb->insert_id,
            $contact->channelId,
            $contact->externalId,
            $contact->name,
            $contact->status,
            $contact->userId,
            $contact->address,
            $contact->createdAt,
            $contact->lastSeenAt
        );
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $wpdb->delete(Schema::contacts(), ['id' => $id]);
    }

    public function deleteForChannel(int $channelId): void
    {
        global $wpdb;

        $wpdb->delete(Schema::contacts(), ['channel_id' => $channelId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Contact
    {
        return new Contact(
            (int) $row['id'],
            (int) $row['channel_id'],
            (string) $row['external_id'],
            (string) $row['name'],
            ContactStatus::fromString((string) $row['status']),
            (int) $row['user_id'],
            (string) $row['address'],
            (int) $row['created_at'],
            (int) $row['last_seen_at']
        );
    }
}
