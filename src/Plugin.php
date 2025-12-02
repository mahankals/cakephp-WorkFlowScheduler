<?php
declare(strict_types=1);

namespace WorkFlowScheduler;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use WorkFlowScheduler\Command\ExecuteWorkflowCommand;

/**
 * Plugin for WorkFlowScheduler
 */
class WorkFlowSchedulerPlugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // remove this method hook if you don't need it
    }

    /**
     * Add routes for the plugin.
     *
     * If your plugin has many routes and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        // remove this method hook if you don't need it
        $routes->plugin(
            'WorkFlowScheduler',
            ['path' => '/work-flow-scheduler'],
            function (RouteBuilder $builder) {
                // Default route
                $builder->connect('/', ['controller' => 'Workflows', 'action' => 'index']);
                $builder->connect('/workflows', ['controller' => 'Workflows', 'action' => 'index']);
                $builder->connect('/status-all', ['controller' => 'Workflows', 'action' => 'statusAll']);

                // Workflow routes
                $builder->connect('/{id}', ['controller' => 'Workflows', 'action' => 'view'])
                    ->setPass(['id']);
                $builder->connect('/{id}/executions', ['controller' => 'Workflows', 'action' => 'executions'])
                    ->setPass(['id']);
                $builder->connect('/{id}/edit', ['controller' => 'Workflows', 'action' => 'edit'])
                    ->setPass(['id']);
                $builder->connect('/{id}/toggle-status', ['controller' => 'Workflows', 'action' => 'toggleStatus'])
                    ->setPass(['id']);
                $builder->connect('/{id}/update-schedule', ['controller' => 'Workflows', 'action' => 'updateSchedule'])
                    ->setPass(['id']);
                $builder->connect('/{id}/execute', ['controller' => 'Workflows', 'action' => 'execute'])
                    ->setPass(['id']);

                // Execution routes
                $builder->connect('/execution/{id}', ['controller' => 'WorkflowExecutions', 'action' => 'view'])
                    ->setPass(['id']);
                $builder->connect('/execution/{id}/status', ['controller' => 'WorkflowExecutions', 'action' => 'status'])
                    ->setPass(['id']);

                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add your middlewares here
        // remove this method hook if you don't need it

        return $middlewareQueue;
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        // Register commands
        $commands->add('work_flow_scheduler.execute_workflow', ExecuteWorkflowCommand::class);

        return $commands;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/5/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        // Add your services here
        // remove this method hook if you don't need it
    }
}
