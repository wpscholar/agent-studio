<?php

declare(strict_types=1);

namespace WPAgents\WordPress;

use WPAgents\Application\Approval\ResolveApprovalHandler;
use WPAgents\Application\Dispatch\DispatchTriggerHandler;
use WPAgents\Application\Execute\ExecuteRunHandler;
use WPAgents\Application\Execute\ToolExecutor;
use WPAgents\Application\Execute\ToolRegistryFactory;
use WPAgents\Application\Prompt\PromptBuilder;
use WPAgents\Application\Retention\PruneRunsHandler;
use WPAgents\Application\Run\CancelRunHandler;
use WPAgents\Contracts\AbilityGateway;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\Clock;
use WPAgents\Contracts\Identity;
use WPAgents\Contracts\JobQueue;
use WPAgents\Contracts\ModelClient;
use WPAgents\Contracts\Notifier;
use WPAgents\Contracts\RecursionGuard;
use WPAgents\Contracts\RunRepository;
use WPAgents\Contracts\Settings;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Infrastructure\AbilitiesApiGateway;
use WPAgents\Infrastructure\CoreAiModelClient;
use WPAgents\Infrastructure\ProcessRecursionGuard;
use WPAgents\Infrastructure\Schema;
use WPAgents\Infrastructure\SystemClock;
use WPAgents\Infrastructure\WordPressAgentRepository;
use WPAgents\Infrastructure\WordPressIdentity;
use WPAgents\Infrastructure\WordPressJobQueue;
use WPAgents\Infrastructure\WordPressNotifier;
use WPAgents\Infrastructure\WordPressSettings;
use WPAgents\Infrastructure\WordPressSkillRepository;
use WPAgents\Infrastructure\WpdbRunRepository;
use WPAgents\WordPress\Abilities\AgentAbilities;
use WPAgents\WordPress\Abilities\StarterAbilities;
use WPAgents\WordPress\Admin\AbilitiesPage;
use WPAgents\WordPress\Admin\AgentEditor;
use WPAgents\WordPress\Admin\Assets;
use WPAgents\WordPress\Admin\ImportSkillPage;
use WPAgents\WordPress\Admin\Menu;
use WPAgents\WordPress\Admin\PostNotes;
use WPAgents\WordPress\Admin\RunsPage;
use WPAgents\WordPress\Admin\TemplatesPage;
use WPAgents\WordPress\Cli\AgentsCommand;
use WPAgents\WordPress\Hooks\TriggerHooks;
use WPAgents\WordPress\Hooks\WorkerHooks;
use WPAgents\WordPress\Rest\AgentsController;
use WPAgents\WordPress\Rest\WebhookController;

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

        $container->get(PostTypeRegistrar::class)->register();
        $container->get(TriggerHooks::class)->register();
        $container->get(WorkerHooks::class)->register();
        $container->get(WebhookController::class)->register();
        $container->get(AgentsController::class)->register();
        $container->get(AgentAbilities::class)->register();
        $container->get(StarterAbilities::class)->register();

        if (is_admin()) {
            $container->get(Assets::class)->register();
            $container->get(Menu::class)->register();
            $container->get(AgentEditor::class)->register();
            $container->get(RunsPage::class)->register();
            $container->get(ImportSkillPage::class)->register();
            $container->get(TemplatesPage::class)->register();
            $container->get(PostNotes::class)->register();
        }

        if (defined('WP_CLI') && WP_CLI) {
            AgentsCommand::register($container->get(AgentsCommand::class));
        }

        /**
         * Fires once WP Agents is ready. Other plugins can register their own
         * abilities, or read services from the container here.
         *
         * @param Container $container The service container.
         */
        do_action('wpa_loaded', $container);
    }

    public static function activate(): void
    {
        Schema::install();

        $container = self::container();
        $container->get(PostTypeRegistrar::class)->registerPostTypes();
        WorkerHooks::schedulePruning();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(WorkerHooks::PRUNE_HOOK);
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
        $c->set(Notifier::class, static fn (): Notifier => new WordPressNotifier());
        $c->set(Settings::class, static fn (): Settings => new WordPressSettings());
        $c->set(JobQueue::class, static fn (): JobQueue => new WordPressJobQueue());
        $c->set(RecursionGuard::class, static fn (): RecursionGuard => new ProcessRecursionGuard());
        $c->set(AgentRepository::class, static fn (): AgentRepository => new WordPressAgentRepository());
        $c->set(SkillRepository::class, static fn (): SkillRepository => new WordPressSkillRepository());
        $c->set(RunRepository::class, static fn (): RunRepository => new WpdbRunRepository());
        $c->set(AbilityGateway::class, static fn (): AbilityGateway => new AbilitiesApiGateway());

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
                $client = apply_filters('wpa_model_client', $default);

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

        $c->set(PromptBuilder::class, static fn (): PromptBuilder => new PromptBuilder());

        $c->set(
            DispatchTriggerHandler::class,
            static fn (Container $c): DispatchTriggerHandler => new DispatchTriggerHandler(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(JobQueue::class),
                $c->get(RecursionGuard::class),
                $c->get(Clock::class)
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
                $c->get(Clock::class)
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
            static fn (Container $c): TriggerHooks => new TriggerHooks($c->get(DispatchTriggerHandler::class))
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
            ImportSkillPage::class,
            static fn (Container $c): ImportSkillPage => new ImportSkillPage($c->get(SkillRepository::class))
        );
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
                $c->get(TemplatesPage::class),
                $c->get(ImportSkillPage::class)
            )
        );
        $c->set(
            AgentEditor::class,
            static fn (Container $c): AgentEditor => new AgentEditor(
                $c->get(AgentRepository::class),
                $c->get(RunRepository::class),
                $c->get(SkillRepository::class),
                $c->get(AbilityGateway::class)
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
