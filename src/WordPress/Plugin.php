<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Channels\ChannelNotifier;
use AgentStudio\Application\Channels\ChannelService;
use AgentStudio\Application\Channels\ChannelTypes;
use AgentStudio\Application\Channels\ConversationReplies;
use AgentStudio\Application\Channels\InboundHandler;
use AgentStudio\Application\CompositeNotifier;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelEndpoints;
use AgentStudio\Contracts\Channel\ChannelPhrases as ChannelPhrasesContract;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Infrastructure\WordPressChannelRepository;
use AgentStudio\Infrastructure\WpdbContactRepository;
use AgentStudio\Infrastructure\WpdbConversationRepository;
use AgentStudio\WordPress\Channels\ChannelPhrases;
use AgentStudio\WordPress\Channels\NtfyChannelType;
use AgentStudio\WordPress\Channels\SlackChannelType;
use AgentStudio\WordPress\Channels\TelegramChannelType;
use AgentStudio\WordPress\Channels\WordPressChannelEndpoints;
use AgentStudio\WordPress\Rest\ChannelsController;
use AgentStudio\Application\Connections\ConnectionAbilityGateway;
use AgentStudio\Application\Connections\ConnectionService;
use AgentStudio\Application\Connections\ConnectionTypes;
use AgentStudio\Application\Execute\CompositeAbilityGateway;
use AgentStudio\Application\Mcp\McpClient;
use AgentStudio\Application\OAuth\OAuthClient;
use AgentStudio\Application\OAuth\OAuthDiscovery;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\PendingSignInStore;
use AgentStudio\Contracts\SecretStore;
use AgentStudio\Infrastructure\Secrets\SecretStoreFactory;
use AgentStudio\Infrastructure\TransientPendingSignInStore;
use AgentStudio\Infrastructure\WordPressConnectionRepository;
use AgentStudio\Infrastructure\WordPressHttpClient;
use AgentStudio\WordPress\Connections\McpConnectionType;
use AgentStudio\WordPress\Hooks\ConnectionHooks;
use AgentStudio\WordPress\Rest\ConnectionsController;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Application\Execute\ExecuteRunHandler;
use AgentStudio\Application\Execute\ToolExecutor;
use AgentStudio\Application\Execute\ToolRegistryFactory;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Application\Retention\PruneRunsHandler;
use AgentStudio\Application\Run\CancelRunHandler;
use AgentStudio\Application\Schedule\RunSchedulesHandler;
use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\Identity;
use AgentStudio\Contracts\JobQueue;
use AgentStudio\Contracts\ModelClient;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Contracts\ScheduleState;
use AgentStudio\Contracts\Settings;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Infrastructure\AbilitiesApiGateway;
use AgentStudio\Infrastructure\AiModelCatalog;
use AgentStudio\Infrastructure\CoreAiModelClient;
use AgentStudio\Infrastructure\OptionScheduleState;
use AgentStudio\Infrastructure\ProcessRecursionGuard;
use AgentStudio\Infrastructure\Schema;
use AgentStudio\Infrastructure\SystemClock;
use AgentStudio\Infrastructure\WordPressAgentRepository;
use AgentStudio\Infrastructure\WordPressIdentity;
use AgentStudio\Infrastructure\WordPressJobQueue;
use AgentStudio\Infrastructure\WordPressNotifier;
use AgentStudio\Infrastructure\WordPressSettings;
use AgentStudio\Infrastructure\WordPressSkillRepository;
use AgentStudio\Infrastructure\WpdbRunRepository;
use AgentStudio\WordPress\Abilities\AgentAbilities;
use AgentStudio\WordPress\Abilities\StarterAbilities;
use AgentStudio\WordPress\Admin\AbilitiesPage;
use AgentStudio\WordPress\Admin\AgentEditor;
use AgentStudio\WordPress\Admin\Assets;
use AgentStudio\WordPress\Admin\Menu;
use AgentStudio\WordPress\Admin\PostNotes;
use AgentStudio\WordPress\Admin\RunsPage;
use AgentStudio\WordPress\Admin\TemplatesPage;
use AgentStudio\WordPress\Cli\AgentsCommand;
use AgentStudio\WordPress\Hooks\ScheduleHooks;
use AgentStudio\WordPress\Hooks\TriggerHooks;
use AgentStudio\WordPress\Hooks\WorkerHooks;
use AgentStudio\WordPress\Rest\AgentsController;
use AgentStudio\WordPress\Rest\SkillsController;
use AgentStudio\WordPress\Rest\WebhookController;

/**
 * Builds the object graph and connects it to WordPress.
 */
final class Plugin
{
    private static ?Container $container = null;

    public static function boot(): void
    {
        $container = self::container();

        Schema::upgradeIfNeeded();

        // First: everything below checks these capabilities.
        $container->get(Capabilities::class)->register();
        $container->get(PostTypeRegistrar::class)->register();
        $container->get(TriggerHooks::class)->register();
        $container->get(WorkerHooks::class)->register();
        $container->get(ScheduleHooks::class)->register();
        $container->get(WebhookController::class)->register();
        $container->get(AgentsController::class)->register();
        $container->get(SkillsController::class)->register();
        $container->get(ConnectionsController::class)->register();
        $container->get(ChannelsController::class)->register();
        $container->get(ConnectionHooks::class)->register();
        $container->get(AgentAbilities::class)->register();
        $container->get(StarterAbilities::class)->register();

        if (is_admin()) {
            $container->get(Assets::class)->register();
            $container->get(Menu::class)->register();
            $container->get(AgentEditor::class)->register();
            $container->get(RunsPage::class)->register();
            $container->get(TemplatesPage::class)->register();
            $container->get(PostNotes::class)->register();
        }

        if (defined('WP_CLI') && WP_CLI) {
            AgentsCommand::register($container->get(AgentsCommand::class));
        }

        /**
         * Fires once Agent Studio is ready. Other plugins can register their own
         * abilities, or read services from the container here.
         *
         * @param Container $container The service container.
         */
        do_action('agent_studio_loaded', $container);
    }

    public static function activate(): void
    {
        Schema::install();

        $container = self::container();
        $container->get(PostTypeRegistrar::class)->registerPostTypes();
        WorkerHooks::schedulePruning();
        add_filter('cron_schedules', [ScheduleHooks::class, 'addInterval']);
        ScheduleHooks::scheduleChecks();
        ConnectionHooks::schedule();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(WorkerHooks::PRUNE_HOOK);
        wp_clear_scheduled_hook(ScheduleHooks::HOOK);
        wp_clear_scheduled_hook(ConnectionHooks::HOOK);
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            self::$container = self::build();
        }

        return self::$container;
    }

    private static function build(): Container
    {
        $c = new Container();

        $c->set(Clock::class, static fn (): Clock => new SystemClock());
        $c->set(Identity::class, static fn (): Identity => new WordPressIdentity());
        $c->set(
            Notifier::class,
            static fn (Container $c): Notifier => new CompositeNotifier([new WordPressNotifier(), $c->get(ChannelNotifier::class)])
        );
        $c->set(ChannelRepository::class, static fn (): ChannelRepository => new WordPressChannelRepository());
        $c->set(ContactRepository::class, static fn (): ContactRepository => new WpdbContactRepository());
        $c->set(ConversationRepository::class, static fn (): ConversationRepository => new WpdbConversationRepository());
        $c->set(ApprovalPolicy::class, static fn (): ApprovalPolicy => new WordPressApprovalPolicy());
        $c->set(ChannelPhrasesContract::class, static fn (): ChannelPhrasesContract => new ChannelPhrases());
        $c->set(ChannelEndpoints::class, static fn (): ChannelEndpoints => new WordPressChannelEndpoints());
        $c->set(
            ApprovalTokens::class,
            static function (Container $c): ApprovalTokens {
                $secrets = $c->get(SecretStore::class);
                $key = $secrets->get('approval-signing-key');
                if ($key === null) {
                    $key = bin2hex(random_bytes(32));
                    $secrets->set('approval-signing-key', $key);
                }

                return new ApprovalTokens($key);
            }
        );
        $c->set(
            ChannelTypes::class,
            static function (Container $c): ChannelTypes {
                $types = new ChannelTypes();
                $types->register(new SlackChannelType($c->get(HttpClient::class)));
                $types->register(new TelegramChannelType($c->get(HttpClient::class)));
                $types->register(new NtfyChannelType($c->get(HttpClient::class)));

                /**
                 * Fires when the kinds of channel are collected. Register your own with
                 * $types->register( new My_Channel_Type() ); it then appears on the
                 * Channels screen with its setup steps.
                 *
                 * @param ChannelTypes $types The registry.
                 */
                do_action('agent_studio_register_channel_types', $types);

                return $types;
            }
        );
        $c->set(
            ChannelService::class,
            static fn (Container $c): ChannelService => new ChannelService(
                $c->get(ChannelRepository::class),
                $c->get(ChannelTypes::class),
                $c->get(SecretStore::class),
                $c->get(ContactRepository::class),
                $c->get(ConversationRepository::class),
                $c->get(PendingSignInStore::class),
                $c->get(ChannelEndpoints::class)
            )
        );
        $c->set(
            ConversationService::class,
            static fn (Container $c): ConversationService => new ConversationService(
                $c->get(ConversationRepository::class),
                $c->get(ModelClient::class),
                $c->get(Clock::class)
            )
        );
        $c->set(
            ChannelNotifier::class,
            static fn (Container $c): ChannelNotifier => new ChannelNotifier(
                $c->get(ChannelRepository::class),
                $c->get(ChannelService::class),
                $c->get(ConversationService::class),
                $c->get(ApprovalPolicy::class),
                $c->get(ApprovalTokens::class),
                $c->get(ChannelEndpoints::class),
                $c->get(ChannelPhrasesContract::class)
            )
        );
        $c->set(
            ConversationReplies::class,
            static fn (Container $c): ConversationReplies => new ConversationReplies(
                $c->get(ConversationService::class),
                $c->get(ChannelService::class),
                $c->get(ChannelRepository::class),
                $c->get(ContactRepository::class),
                $c->get(AgentRepository::class),
                $c->get(ApprovalPolicy::class),
                $c->get(ApprovalTokens::class),
                $c->get(ChannelPhrasesContract::class)
            )
        );
        $c->set(
            InboundHandler::class,
            static fn (Container $c): InboundHandler => new InboundHandler(
                $c->get(ChannelService::class),
                $c->get(ChannelRepository::class),
                $c->get(ContactRepository::class),
                $c->get(ConversationService::class),
                $c->get(AgentRepository::class),
                $c->get(DispatchTriggerHandler::class),
                $c->get(RunRepository::class),
                $c->get(ResolveApprovalHandler::class),
                $c->get(ApprovalTokens::class),
                $c->get(ApprovalPolicy::class),
                $c->get(ChannelPhrasesContract::class),
                $c->get(Clock::class)
            )
        );
        $c->set(
            ChannelsController::class,
            static fn (Container $c): ChannelsController => new ChannelsController(
                $c->get(ChannelRepository::class),
                $c->get(ChannelService::class),
                $c->get(ChannelTypes::class),
                $c->get(ContactRepository::class),
                $c->get(AgentRepository::class),
                $c->get(InboundHandler::class),
                $c->get(RunRepository::class),
                $c->get(ResolveApprovalHandler::class),
                $c->get(ApprovalTokens::class),
                $c->get(ApprovalPolicy::class),
                $c->get(ChannelPhrasesContract::class)
            )
        );
        $c->set(Settings::class, static fn (): Settings => new WordPressSettings());
        $c->set(JobQueue::class, static fn (): JobQueue => new WordPressJobQueue());
        $c->set(RecursionGuard::class, static fn (): RecursionGuard => new ProcessRecursionGuard());
        $c->set(AgentRepository::class, static fn (): AgentRepository => new WordPressAgentRepository());
        $c->set(WordPressSkillRepository::class, static fn (): WordPressSkillRepository => new WordPressSkillRepository());
        $c->set(SkillRepository::class, static fn (Container $c): SkillRepository => $c->get(WordPressSkillRepository::class));
        $c->set(RunRepository::class, static fn (): RunRepository => new WpdbRunRepository());
        $c->set(
            AbilityGateway::class,
            static fn (Container $c): AbilityGateway => new CompositeAbilityGateway(
                new AbilitiesApiGateway(),
                $c->get(ConnectionAbilityGateway::class)
            )
        );
        $c->set(SecretStore::class, static fn (): SecretStore => SecretStoreFactory::make());
        $c->set(HttpClient::class, static fn (): HttpClient => new WordPressHttpClient());
        $c->set(PendingSignInStore::class, static fn (): PendingSignInStore => new TransientPendingSignInStore());
        $c->set(ConnectionRepository::class, static fn (): ConnectionRepository => new WordPressConnectionRepository());
        $c->set(
            McpClient::class,
            static fn (Container $c): McpClient => new McpClient($c->get(HttpClient::class), 'Agent Studio', AGENT_STUDIO_VERSION)
        );
        $c->set(
            ConnectionTypes::class,
            static function (Container $c): ConnectionTypes {
                $types = new ConnectionTypes();
                $types->register(new McpConnectionType($c->get(McpClient::class)));

                /**
                 * Fires when the kinds of connection are collected. Register your own
                 * with $types->register( new My_Connection_Type() ); it then appears
                 * on the Connections screen and its tools are offered to agents.
                 *
                 * @param ConnectionTypes $types The registry.
                 */
                do_action('agent_studio_register_connection_types', $types);

                return $types;
            }
        );
        $c->set(
            ConnectionService::class,
            static fn (Container $c): ConnectionService => new ConnectionService(
                $c->get(ConnectionRepository::class),
                $c->get(ConnectionTypes::class),
                $c->get(SecretStore::class),
                new OAuthDiscovery($c->get(HttpClient::class)),
                new OAuthClient($c->get(HttpClient::class)),
                $c->get(PendingSignInStore::class),
                $c->get(Clock::class)
            )
        );
        $c->set(
            ConnectionAbilityGateway::class,
            static fn (Container $c): ConnectionAbilityGateway => new ConnectionAbilityGateway(
                $c->get(ConnectionRepository::class),
                $c->get(ConnectionService::class)
            )
        );
        $c->set(
            ConnectionsController::class,
            static fn (Container $c): ConnectionsController => new ConnectionsController(
                $c->get(ConnectionRepository::class),
                $c->get(ConnectionService::class),
                $c->get(ConnectionTypes::class),
                $c->get(AgentRepository::class)
            )
        );
        $c->set(
            ConnectionHooks::class,
            static fn (Container $c): ConnectionHooks => new ConnectionHooks(
                $c->get(ConnectionRepository::class),
                $c->get(ConnectionService::class)
            )
        );
        $c->set(ScheduleState::class, static fn (): ScheduleState => new OptionScheduleState());

        $c->set(
            ModelClient::class,
            static function (): ModelClient {
                $default = new CoreAiModelClient();

                /**
                 * Filters the model client agents use. Replace it to run agents
                 * on a different runtime, or to fake the model in tests.
                 *
                 * @param ModelClient $client The default client, built on the WordPress AI Client.
                 */
                $client = apply_filters('agent_studio_model_client', $default);

                return $client instanceof ModelClient ? $client : $default;
            }
        );

        $c->set(
            ToolRegistryFactory::class,
            static fn (Container $c): ToolRegistryFactory => new ToolRegistryFactory(
                $c->get(AbilityGateway::class),
                $c->get(SkillRepository::class)
            )
        );

        $c->set(
            ToolExecutor::class,
            static fn (Container $c): ToolExecutor => new ToolExecutor(
                $c->get(AbilityGateway::class),
                $c->get(Identity::class),
                $c->get(RecursionGuard::class),
                $c->get(Clock::class)
            )
        );

        $c->set(
            PromptBuilder::class,
            static fn (Container $c): PromptBuilder => new PromptBuilder()
        );

        $c->set(
            TriggerDefinitions::class,
            static function (): TriggerDefinitions {
                $definitions = new TriggerDefinitions();

                /**
                 * Fires when plugin-defined triggers are collected. Register yours with
                 * $definitions->register( new My_Trigger() ); it then appears in every
                 * agent's "When should this agent run?" list. Fire it with
                 * do_action( 'agent_studio_trigger', 'my-plugin/event', $payload ).
                 *
                 * @param TriggerDefinitions $definitions The registry.
                 */
                do_action('agent_studio_register_triggers', $definitions);

                return $definitions;
            }
        );

        $c->set(
            DispatchTriggerHandler::class,
            static fn (Container $c): DispatchTriggerHandler => new DispatchTriggerHandler(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(JobQueue::class),
                $c->get(RecursionGuard::class),
                $c->get(Clock::class),
                $c->get(TriggerDefinitions::class)
            )
        );

        $c->set(
            ExecuteRunHandler::class,
            static fn (Container $c): ExecuteRunHandler => new ExecuteRunHandler(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(ToolRegistryFactory::class),
                $c->get(ModelClient::class),
                $c->get(ToolExecutor::class),
                $c->get(PromptBuilder::class),
                $c->get(Notifier::class),
                $c->get(Identity::class),
                $c->get(RecursionGuard::class),
                $c->get(Clock::class),
                $c->get(ConversationService::class),
                $c->get(ConversationReplies::class)
            )
        );

        $c->set(
            ResolveApprovalHandler::class,
            static fn (Container $c): ResolveApprovalHandler => new ResolveApprovalHandler(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(ToolRegistryFactory::class),
                $c->get(ToolExecutor::class),
                $c->get(JobQueue::class),
                $c->get(Clock::class)
            )
        );

        $c->set(
            CancelRunHandler::class,
            static fn (Container $c): CancelRunHandler => new CancelRunHandler(
                $c->get(RunRepository::class),
                $c->get(Clock::class)
            )
        );

        $c->set(
            PruneRunsHandler::class,
            static fn (Container $c): PruneRunsHandler => new PruneRunsHandler(
                $c->get(RunRepository::class),
                $c->get(Settings::class),
                $c->get(Clock::class)
            )
        );

        $c->set(PostTypeRegistrar::class, static fn (): PostTypeRegistrar => new PostTypeRegistrar());
        $c->set(
            TriggerHooks::class,
            static fn (Container $c): TriggerHooks => new TriggerHooks(
                $c->get(DispatchTriggerHandler::class),
                $c->get(TriggerDefinitions::class)
            )
        );
        $c->set(
            RunSchedulesHandler::class,
            static fn (Container $c): RunSchedulesHandler => new RunSchedulesHandler(
                $c->get(AgentRepository::class),
                $c->get(DispatchTriggerHandler::class),
                $c->get(ScheduleState::class),
                $c->get(Clock::class)
            )
        );
        $c->set(
            ScheduleHooks::class,
            static fn (Container $c): ScheduleHooks => new ScheduleHooks($c->get(RunSchedulesHandler::class))
        );
        $c->set(
            WorkerHooks::class,
            static fn (Container $c): WorkerHooks => new WorkerHooks(
                $c->get(ExecuteRunHandler::class),
                $c->get(PruneRunsHandler::class)
            )
        );
        $c->set(
            WebhookController::class,
            static fn (Container $c): WebhookController => new WebhookController(
                $c->get(AgentRepository::class),
                $c->get(DispatchTriggerHandler::class),
                $c->get(Clock::class)
            )
        );
        $c->set(
            AgentsController::class,
            static fn (Container $c): AgentsController => new AgentsController(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(DispatchTriggerHandler::class)
            )
        );
        $c->set(
            SkillsController::class,
            static fn (Container $c): SkillsController => new SkillsController(
                $c->get(WordPressSkillRepository::class),
                $c->get(AgentRepository::class)
            )
        );
        $c->set(Capabilities::class, static fn (): Capabilities => new Capabilities());
        $c->set(
            AgentAbilities::class,
            static fn (Container $c): AgentAbilities => new AgentAbilities(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(SkillRepository::class),
                $c->get(DispatchTriggerHandler::class)
            )
        );
        $c->set(StarterAbilities::class, static fn (): StarterAbilities => new StarterAbilities());

        $c->set(Assets::class, static fn (): Assets => new Assets());
        $c->set(PostNotes::class, static fn (): PostNotes => new PostNotes());
        $c->set(TemplatesPage::class, static fn (): TemplatesPage => new TemplatesPage());
        $c->set(
            AbilitiesPage::class,
            static fn (Container $c): AbilitiesPage => new AbilitiesPage($c->get(AbilityGateway::class))
        );
        $c->set(
            RunsPage::class,
            static fn (Container $c): RunsPage => new RunsPage(
                $c->get(RunRepository::class),
                $c->get(AgentRepository::class),
                $c->get(ResolveApprovalHandler::class),
                $c->get(CancelRunHandler::class)
            )
        );
        $c->set(
            Menu::class,
            static fn (Container $c): Menu => new Menu(
                $c->get(RunsPage::class),
                $c->get(AbilitiesPage::class),
                $c->get(TemplatesPage::class)
            )
        );
        $c->set(
            AgentEditor::class,
            static fn (Container $c): AgentEditor => new AgentEditor(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(SkillRepository::class),
                $c->get(AbilityGateway::class),
                new AiModelCatalog(),
                $c->get(TriggerDefinitions::class)
            )
        );
        $c->set(
            AgentsCommand::class,
            static fn (Container $c): AgentsCommand => new AgentsCommand(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(DispatchTriggerHandler::class),
                $c->get(ExecuteRunHandler::class),
                $c->get(ResolveApprovalHandler::class),
                $c->get(CancelRunHandler::class),
                $c->get(PruneRunsHandler::class)
            )
        );

        return $c;
    }
}
