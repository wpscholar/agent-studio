<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Domain\Skill\Skill;
use AgentStudio\Domain\Skill\SkillMarkdown;
use AgentStudio\Infrastructure\WordPressSkillRepository;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Urls;

/**
 * The Skills screen's API.
 *
 *   GET    /skills                                List skills.
 *   POST   /skills                                Create one. Body: name, description, instructions.
 *   GET    /skills/{slug}                         One skill with its history.
 *   PUT    /skills/{slug}                         Change it.
 *   DELETE /skills/{slug}                         Delete it.
 *   POST   /skills/import                         Read a SKILL.md. Body: markdown, dry_run.
 *   GET    /skills/{slug}/export                  The skill as a SKILL.md file.
 *   GET    /skills/{slug}/revisions/{id}          An earlier version.
 *   POST   /skills/{slug}/revisions/{id}/restore  Go back to it.
 */
final class SkillsController
{
    public const NAME_MAX = 200;
    public const DESCRIPTION_MAX = 1024;
    public const INSTRUCTIONS_MAX = 200000;

    private const SLUG = '(?P<slug>[a-z0-9][a-z0-9-]{0,63})';

    public function __construct(
        private readonly WordPressSkillRepository $skills,
        private readonly AgentRepository $agents,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $can = [Capabilities::class, 'canManage'];

        register_rest_route('agent-studio/v1', '/skills', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/skills/import', [
            ['methods' => 'POST', 'callback' => [$this, 'import'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/skills/' . self::SLUG, [
            ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $can],
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/skills/' . self::SLUG . '/export', [
            ['methods' => 'GET', 'callback' => [$this, 'export'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/skills/' . self::SLUG . '/revisions/(?P<revision>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'revision'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/skills/' . self::SLUG . '/revisions/(?P<revision>\d+)/restore', [
            ['methods' => 'POST', 'callback' => [$this, 'restore'], 'permission_callback' => $can],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $usage = $this->usage();
        $items = [];
        foreach ($this->skills->all() as $skill) {
            $post = $this->skills->findPost($skill->slug);
            $items[] = $this->summary($skill, $post, $usage[$skill->slug] ?? []);
        }

        return new WP_REST_Response($items);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function show(WP_REST_Request $request)
    {
        $found = $this->find((string) $request->get_param('slug'));
        if ($found instanceof WP_Error) {
            return $found;
        }

        return new WP_REST_Response($this->detail(...$found));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        $name = trim((string) $request->get_param('name'));
        $slug = SkillMarkdown::slugify($name);
        if ($slug === '') {
            return self::error('agent_studio_skill_invalid', __('Give the skill a name that uses some letters or numbers.', 'agent-studio'));
        }

        if ($this->skills->findBySlug($slug) !== null) {
            return self::error(
                'agent_studio_skill_exists',
                sprintf(
                    /* translators: %s: skill name */
                    __('There is already a skill called “%s”. Pick a different name, or open that skill to change it.', 'agent-studio'),
                    $name
                ),
                409
            );
        }

        return $this->persist($slug, $request, 201);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        $found = $this->find((string) $request->get_param('slug'));
        if ($found instanceof WP_Error) {
            return $found;
        }

        return $this->persist($found[0]->slug, $request, 200);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function destroy(WP_REST_Request $request)
    {
        $found = $this->find((string) $request->get_param('slug'));
        if ($found instanceof WP_Error) {
            return $found;
        }

        $this->skills->delete($found[0]->slug);

        return new WP_REST_Response(['deleted' => true]);
    }

    /**
     * Reads a SKILL.md. With dry_run, only says what importing would do.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function import(WP_REST_Request $request)
    {
        $markdown = (string) $request->get_param('markdown');
        if (strlen($markdown) > self::INSTRUCTIONS_MAX) {
            return self::error('agent_studio_skill_too_large', __('That file is too large for a skill.', 'agent-studio'));
        }

        try {
            $skill = SkillMarkdown::parse($markdown);
        } catch (InvalidArgumentException $e) {
            return self::error('agent_studio_skill_invalid', $e->getMessage());
        }

        $exists = $this->skills->findBySlug($skill->slug) !== null;

        if (! $request->get_param('dry_run')) {
            try {
                $this->skills->save($skill);
            } catch (RuntimeException $e) {
                return self::error('agent_studio_skill_not_saved', $e->getMessage(), 500);
            }
        }

        return new WP_REST_Response(
            [
                'exists' => $exists,
                'skill' => [
                    'slug' => $skill->slug,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'instructions' => $skill->body,
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function export(WP_REST_Request $request)
    {
        $found = $this->find((string) $request->get_param('slug'));
        if ($found instanceof WP_Error) {
            return $found;
        }

        return new WP_REST_Response(
            ['filename' => $found[0]->slug . '.SKILL.md', 'markdown' => SkillMarkdown::render($found[0])]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function revision(WP_REST_Request $request)
    {
        $revision = $this->findRevision($request);
        if ($revision instanceof WP_Error) {
            return $revision;
        }

        return new WP_REST_Response(
            [
                'id' => $revision->ID,
                'date' => strtotime($revision->post_modified_gmt . ' UTC'),
                'author' => self::authorName((int) $revision->post_author),
                'name' => $revision->post_title,
                'description' => $revision->post_excerpt,
                'instructions' => $revision->post_content,
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function restore(WP_REST_Request $request)
    {
        $revision = $this->findRevision($request);
        if ($revision instanceof WP_Error) {
            return $revision;
        }

        if (wp_restore_post_revision($revision->ID) === false) {
            return self::error('agent_studio_skill_not_restored', __('That version could not be restored.', 'agent-studio'), 500);
        }

        return $this->show($request);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    private function persist(string $slug, WP_REST_Request $request, int $status)
    {
        $name = trim((string) $request->get_param('name'));
        $description = trim((string) $request->get_param('description'));
        $instructions = trim((string) $request->get_param('instructions'));

        $problem = match (true) {
            $name === '' => __('Give the skill a name.', 'agent-studio'),
            mb_strlen($name) > self::NAME_MAX => __('The name is too long. Keep it short, like a title.', 'agent-studio'),
            $description === '' => __('Say when an agent should use this skill. Agents read this to decide whether to open it.', 'agent-studio'),
            mb_strlen($description) > self::DESCRIPTION_MAX => sprintf(
                /* translators: %d: maximum number of characters */
                __('Keep “When to use it” under %d characters. Put the details in the instructions.', 'agent-studio'),
                self::DESCRIPTION_MAX
            ),
            $instructions === '' => __('Write the instructions the agent should follow.', 'agent-studio'),
            strlen($instructions) > self::INSTRUCTIONS_MAX => __('The instructions are too long for a skill.', 'agent-studio'),
            default => null,
        };

        if ($problem !== null) {
            return self::error('agent_studio_skill_invalid', $problem);
        }

        try {
            $skill = new Skill($slug, $name, $description, $instructions);
            $this->skills->save($skill);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return self::error('agent_studio_skill_not_saved', $e->getMessage(), 500);
        }

        $post = $this->skills->findPost($slug);

        return new WP_REST_Response($post === null ? [] : $this->detail($skill, $post), $status);
    }

    /**
     * @return array{0: Skill, 1: WP_Post}|WP_Error
     */
    private function find(string $slug)
    {
        $post = $this->skills->findPost($slug);
        $skill = $post === null ? null : $this->skills->hydrate($post);
        if ($post === null || $skill === null) {
            return self::error('agent_studio_skill_not_found', __('That skill no longer exists.', 'agent-studio'), 404);
        }

        return [$skill, $post];
    }

    /**
     * @return WP_Post|WP_Error
     */
    private function findRevision(WP_REST_Request $request)
    {
        $found = $this->find((string) $request->get_param('slug'));
        if ($found instanceof WP_Error) {
            return $found;
        }

        $revisionId = (int) $request->get_param('revision');
        $revision = wp_get_post_revision($revisionId);
        if (! $revision instanceof WP_Post || (int) $revision->post_parent !== $found[1]->ID) {
            return self::error('agent_studio_revision_not_found', __('That version no longer exists.', 'agent-studio'), 404);
        }

        return $revision;
    }

    /**
     * @param list<array{id: int, name: string, url: string}> $usedBy
     *
     * @return array<string, mixed>
     */
    private function summary(Skill $skill, ?WP_Post $post, array $usedBy): array
    {
        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'modified' => $post === null ? 0 : (int) strtotime($post->post_modified_gmt . ' UTC'),
            'usedBy' => $usedBy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Skill $skill, WP_Post $post): array
    {
        $revisions = [];
        foreach (wp_get_post_revisions($post->ID, ['order' => 'DESC']) as $revision) {
            if (! $revision instanceof WP_Post) {
                continue;
            }

            $revisions[] = [
                'id' => $revision->ID,
                'date' => (int) strtotime($revision->post_modified_gmt . ' UTC'),
                'author' => self::authorName((int) $revision->post_author),
                // WordPress also stores the version you are looking at; the screen labels it.
                'current' => $revision->post_title === $post->post_title
                    && $revision->post_content === $post->post_content
                    && $revision->post_excerpt === $post->post_excerpt,
            ];
        }

        return $this->summary($skill, $post, $this->usage()[$skill->slug] ?? []) + [
            'instructions' => $skill->body,
            'revisions' => $revisions,
        ];
    }

    /**
     * Which agents list each skill.
     *
     * @return array<string, list<array{id: int, name: string, url: string}>>
     */
    private function usage(): array
    {
        $usage = [];
        foreach ($this->agents->all() as $agent) {
            foreach ($agent->skills as $slug) {
                $usage[$slug][] = ['id' => $agent->id, 'name' => $agent->name, 'url' => Urls::editAgent($agent->id)];
            }
        }

        return $usage;
    }

    private static function authorName(int $userId): string
    {
        $user = get_userdata($userId);

        return $user === false ? __('Someone', 'agent-studio') : $user->display_name;
    }

    private static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
