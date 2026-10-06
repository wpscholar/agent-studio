<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Agent\Trigger;
use WPAgents\Domain\Agent\TriggerEvent;
use WPAgents\Domain\Agent\TriggerType;

final class TriggerTest extends TestCase
{
    public function testPostStatusFiltersOnTypeAndStatus(): void
    {
        $trigger = new Trigger(TriggerType::PostStatus, ['post_types' => 'post, page', 'to' => 'publish']);

        $this->assertTrue($trigger->matches($this->postEvent('post', 'publish', 'draft')));
        $this->assertTrue($trigger->matches($this->postEvent('page', 'publish', 'pending')));
        $this->assertFalse($trigger->matches($this->postEvent('product', 'publish', 'draft')));
        $this->assertFalse($trigger->matches($this->postEvent('post', 'draft', 'publish')));
    }

    public function testEmptyFiltersMatchEverything(): void
    {
        $trigger = new Trigger(TriggerType::PostStatus);

        $this->assertTrue($trigger->matches($this->postEvent('anything', 'whatever', 'else')));
    }

    public function testFromStatusFilter(): void
    {
        $trigger = new Trigger(TriggerType::PostStatus, ['from' => ['draft'], 'to' => ['pending']]);

        $this->assertTrue($trigger->matches($this->postEvent('post', 'pending', 'draft')));
        $this->assertFalse($trigger->matches($this->postEvent('post', 'pending', 'publish')));
    }

    public function testDifferentEventTypeNeverMatches(): void
    {
        $trigger = new Trigger(TriggerType::UserRegistered);

        $this->assertFalse($trigger->matches($this->postEvent('post', 'publish', 'draft')));
    }

    public function testUserRegisteredRoleFilter(): void
    {
        $trigger = new Trigger(TriggerType::UserRegistered, ['roles' => 'subscriber,customer']);

        $this->assertTrue($trigger->matches(new TriggerEvent(TriggerType::UserRegistered, ['roles' => ['customer']])));
        $this->assertFalse($trigger->matches(new TriggerEvent(TriggerType::UserRegistered, ['roles' => ['administrator']])));
        $this->assertFalse($trigger->matches(new TriggerEvent(TriggerType::UserRegistered, [])));
    }

    public function testCommentTriggerFiltersOnPostType(): void
    {
        $trigger = new Trigger(TriggerType::CommentPosted, ['post_types' => ['post']]);

        $this->assertTrue($trigger->matches(new TriggerEvent(TriggerType::CommentPosted, ['post_type' => 'post'])));
        $this->assertFalse($trigger->matches(new TriggerEvent(TriggerType::CommentPosted, ['post_type' => 'page'])));
    }

    public function testWebhookAndManualAlwaysMatchTheirOwnType(): void
    {
        $this->assertTrue((new Trigger(TriggerType::Webhook))->matches(new TriggerEvent(TriggerType::Webhook)));
        $this->assertTrue((new Trigger(TriggerType::Manual))->matches(new TriggerEvent(TriggerType::Manual)));
    }

    public function testRoundTripThroughAnArray(): void
    {
        $trigger = new Trigger(TriggerType::PostStatus, ['to' => 'publish']);

        $restored = Trigger::fromArray($trigger->toArray());

        $this->assertNotNull($restored);
        $this->assertSame(TriggerType::PostStatus, $restored->type);
        $this->assertSame(['publish'], $restored->list('to'));
    }

    public function testUnknownTypeIsDropped(): void
    {
        $this->assertNull(Trigger::fromArray(['type' => 'bogus']));
    }

    private function postEvent(string $type, string $new, string $old): TriggerEvent
    {
        return new TriggerEvent(
            TriggerType::PostStatus,
            ['post_type' => $type, 'new_status' => $new, 'old_status' => $old]
        );
    }
}
