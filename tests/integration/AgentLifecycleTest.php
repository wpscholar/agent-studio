<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Domain\Run\RunStatus;

final class AgentLifecycleTest extends IntegrationTestCase
{
    private int $agentId = 0;

    public function set_up(): void
    {
        parent::set_up();

        $this->agentId = $this->createAgent([
            'triggers' => [['type' => 'post_status', 'config' => ['post_types' => ['post'], 'to' => ['pending']]]],
            'abilities' => ['agent-studio/get-post', 'agent-studio/add-post-note'],
            'approval' => 'writes',
        ], 'Reviewer');
    }

    private function submitPostForReview(): int
    {
        $postId = self::factory()->post->create(['post_status' => 'draft', 'post_title' => 'Cats', 'post_content' => 'Cats are great.']);
        wp_update_post(['ID' => $postId, 'post_status' => 'pending']);

        return $postId;
    }

    public function test_a_matching_event_queues_a_run(): void
    {
        $postId = $this->submitPostForReview();

        $runs = $this->runs->recent(10, 0, $this->agentId);

        $this->assertCount(1, $runs);
        $this->assertSame($postId, $runs[0]->input()['post_id']);
        $this->assertSame(RunStatus::Queued, $runs[0]->status());
    }

    public function test_a_draft_does_not_start_the_agent(): void
    {
        self::factory()->post->create(['post_status' => 'draft']);

        $this->assertCount(0, $this->runs->recent(10, 0, $this->agentId));
    }

    public function test_a_paused_agent_ignores_events(): void
    {
        $this->createAgent(['status' => 'paused', 'triggers' => [['type' => 'user_registered']]], 'Paused');
        self::factory()->user->create();

        $this->assertCount(0, $this->runs->recent(10, 0, $this->agentId));
    }

    public function test_a_write_waits_for_approval_then_runs_as_the_configured_user_and_completes(): void
    {
        $postId = $this->submitPostForReview();
        $runId = (int) $this->runs->recent(1, 0, $this->agentId)[0]->id();

        $this->model
            ->replyWithToolCall($this->toolName('agent-studio/get-post'), ['id' => $postId], 'c1')
            ->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'Add a photo.'], 'c2');

        $this->executor()->handle($runId);

        $run = $this->runs->find($runId);
        $this->assertSame(RunStatus::AwaitingApproval, $run->status());
        $this->assertSame('agent-studio/add-post-note', $run->pendingApproval()->abilityName);
        $this->assertSame('', get_post_meta($postId, '_agent_studio_notes', true), 'Nothing is written before approval.');

        $this->model->replyWithText('Done.');
        $this->assertTrue($this->approvals()->handle($runId, true, $this->adminId));
        $this->assertCount(1, get_post_meta($postId, '_agent_studio_notes', true));

        $this->executor()->handle($runId);

        $run = $this->runs->find($runId);
        $this->assertSame(RunStatus::Completed, $run->status());
        $this->assertSame('Done.', $run->output());
        $this->assertSame($this->adminId, get_current_user_id(), 'The acting user is restored.');
    }

    public function test_a_rejected_action_never_happens_and_the_run_still_finishes(): void
    {
        $postId = $this->submitPostForReview();
        $runId = (int) $this->runs->recent(1, 0, $this->agentId)[0]->id();

        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'No'], 'r1');
        $this->executor()->handle($runId);

        $this->model->replyWithText('Understood.');
        $this->approvals()->handle($runId, false, $this->adminId);
        $this->executor()->handle($runId);

        $this->assertSame(RunStatus::Completed, $this->runs->find($runId)->status());
        $this->assertSame('', get_post_meta($postId, '_agent_studio_notes', true));
    }

    public function test_an_agent_cannot_use_an_ability_it_was_not_given(): void
    {
        $postId = $this->submitPostForReview();
        $runId = (int) $this->runs->recent(1, 0, $this->agentId)[0]->id();

        $this->model
            ->replyWithToolCall('agent_studio_send_email', ['to' => 'a@example.com', 'subject' => 's', 'body' => 'b'], 'x1')
            ->replyWithText('Could not.');
        $this->executor()->handle($runId);

        $run = $this->runs->find($runId);
        $this->assertSame(RunStatus::Completed, $run->status());
        $this->assertSame('', get_post_meta($postId, '_agent_studio_notes', true));
    }

    public function test_the_model_failing_fails_the_run_without_a_fatal_error(): void
    {
        $this->submitPostForReview();
        $runId = (int) $this->runs->recent(1, 0, $this->agentId)[0]->id();

        $this->model->failWith('The provider is down.');
        $this->executor()->handle($runId);

        $run = $this->runs->find($runId);
        $this->assertSame(RunStatus::Failed, $run->status());
        $this->assertStringContainsString('provider is down', $run->error());
    }
}
