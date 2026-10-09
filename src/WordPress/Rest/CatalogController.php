<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WP_User_Query;
use AgentStudio\Infrastructure\AiModelCatalog;
use AgentStudio\WordPress\Agents\AbilityCatalog;
use AgentStudio\WordPress\Agents\Readiness;
use AgentStudio\WordPress\Agents\TriggerCatalog;
use AgentStudio\WordPress\Capabilities;

/**
 * What the Agents and Abilities screens choose from. Lists that can grow long are searched and paged here.
 *
 *   GET /triggers?search=&group=&page=&per_page=                  What can start an agent.
 *   GET /abilities?search=&category=&risk=&source=&page=&per_page=  What agents can be allowed to do.
 *   GET /models                                                   Connected AI providers and their models.
 *   GET /users?search=                                            People an agent can act as.
 *   GET /readiness                                                Whether this site can run agents.
 */
final class CatalogController
{
    private const PER_PAGE_DEFAULT = 30;
    private const PER_PAGE_MAX = 100;

    public function __construct(
        private readonly TriggerCatalog $triggers,
        private readonly AbilityCatalog $abilities,
        private readonly AiModelCatalog $models,
        private readonly Readiness $readiness,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $can = [Capabilities::class, 'canManage'];
        $paging = [
            'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
            'per_page' => ['type' => 'integer', 'default' => self::PER_PAGE_DEFAULT, 'minimum' => 1, 'maximum' => self::PER_PAGE_MAX],
            'search' => ['type' => 'string', 'default' => ''],
        ];

        register_rest_route('agent-studio/v1', '/triggers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'triggers'],
                'permission_callback' => $can,
                'args' => $paging + ['group' => ['type' => 'string', 'default' => '']],
            ],
        ]);
        register_rest_route('agent-studio/v1', '/abilities', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'abilities'],
                'permission_callback' => $can,
                'args' => $paging + [
                    'category' => ['type' => 'string', 'default' => ''],
                    'risk' => ['type' => 'string', 'default' => ''],
                    'source' => ['type' => 'string', 'default' => ''],
                ],
            ],
        ]);
        register_rest_route('agent-studio/v1', '/models', [
            ['methods' => 'GET', 'callback' => [$this, 'models'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/users', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'users'],
                'permission_callback' => $can,
                'args' => ['search' => ['type' => 'string', 'default' => '']],
            ],
        ]);
        register_rest_route('agent-studio/v1', '/readiness', [
            ['methods' => 'GET', 'callback' => [$this, 'readiness'], 'permission_callback' => $can],
        ]);
    }

    public function triggers(WP_REST_Request $request): WP_REST_Response
    {
        [$page, $perPage] = self::paging($request);
        $result = $this->triggers->search((string) $request->get_param('search'), (string) $request->get_param('group'), $page, $perPage);

        return self::paged($result['items'], $result['total'], $page, $perPage, ['groups' => $result['groups']]);
    }

    public function abilities(WP_REST_Request $request): WP_REST_Response
    {
        [$page, $perPage] = self::paging($request);
        $result = $this->abilities->search(
            [
                'search' => (string) $request->get_param('search'),
                'category' => (string) $request->get_param('category'),
                'risk' => (string) $request->get_param('risk'),
                'source' => (string) $request->get_param('source'),
            ],
            $page,
            $perPage
        );

        return self::paged($result['items'], $result['total'], $page, $perPage, ['categories' => $result['categories']]);
    }

    public function models(): WP_REST_Response
    {
        return new WP_REST_Response(['providers' => $this->models->providers(), 'connectorsUrl' => admin_url('options-connectors.php')]);
    }

    /**
     * People who can edit content: agents act with one person's permissions, so only these make sense.
     */
    public function users(WP_REST_Request $request): WP_REST_Response
    {
        $search = trim((string) $request->get_param('search'));
        $args = [
            'capability' => 'edit_posts',
            'number' => 20,
            'orderby' => 'display_name',
            'fields' => 'all',
        ];
        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'display_name', 'user_nicename'];
        }

        $items = [];
        foreach ((new WP_User_Query($args))->get_results() as $user) {
            if ($user instanceof WP_User) {
                $items[] = ['id' => $user->ID, 'name' => $user->display_name, 'login' => $user->user_login, 'roles' => array_values($user->roles)];
            }
        }

        return new WP_REST_Response($items);
    }

    public function readiness(): WP_REST_Response
    {
        return new WP_REST_Response($this->readiness->check());
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function paging(WP_REST_Request $request): array
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = max(1, min(self::PER_PAGE_MAX, (int) ($request->get_param('per_page') ?: self::PER_PAGE_DEFAULT)));

        return [$page, $perPage];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $extra
     */
    private static function paged(array $items, int $total, int $page, int $perPage, array $extra = []): WP_REST_Response
    {
        return new WP_REST_Response(
            ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))] + $extra
        );
    }
}
