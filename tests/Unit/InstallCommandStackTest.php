<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

/**
 * Dublê que corta o acesso ao banco e registra qual builder de views foi chamado,
 * isolando a resolução da stack (--stack vs. prompt interativo).
 */
class StackSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:stack-spy {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--theme : Include theme-aware components}';

    /** @var array<int, string> */
    public array $calls = [];

    public ?string $controllerStub = null;

    /** Força o resultado da detecção automática, sem depender do vendor do teste. */
    public ?bool $fakeWayfinderInstalled = null;

    public function resolvedRouteHelper(): string
    {
        return $this->routeHelper;
    }

    protected function wayfinderIsInstalled(): bool
    {
        return $this->fakeWayfinderInstalled ?? parent::wayfinderIsInstalled();
    }

    protected function generateWayfinderRoutes(): self
    {
        // Não dispara subprocesso no teste.
        return $this;
    }

    protected function tableExists()
    {
        return true;
    }

    protected function buildController(): self
    {
        $this->controllerStub = $this->template === 'react' ? 'InertiaController' : 'Controller';

        return $this;
    }

    protected function buildModel(): self
    {
        return $this;
    }

    public function buildRouter(): self
    {
        return $this;
    }

    /**
     * Schema convencional para o pré-voo ficar silencioso: este arquivo é sobre a
     * resolução de stack, não sobre a inspeção da tabela.
     */
    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }

    protected function buildReactComponents(): self
    {
        $this->calls[] = 'react';

        return $this;
    }

    protected function buildVueComponents(): self
    {
        $this->calls[] = 'vue';

        return $this;
    }

    protected function buildBladeViews(): self
    {
        $this->calls[] = 'blade';

        return $this;
    }
}

class InstallCommandStackTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    private function spyCommand(): StackSpyInstallCommand
    {
        $command = new StackSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);

        return $command;
    }

    public function test_stack_option_routes_to_react_builder(): void
    {
        $command = $this->spyCommand();

        $this->artisan('test:stack-spy users --stack=react')->assertExitCode(0);

        $this->assertSame(['react'], $command->calls);
        $this->assertSame('InertiaController', $command->controllerStub);
    }

    public function test_stack_option_routes_to_vue_builder(): void
    {
        $command = $this->spyCommand();

        $this->artisan('test:stack-spy users --stack=vue')->assertExitCode(0);

        $this->assertSame(['vue'], $command->calls);
        $this->assertSame('Controller', $command->controllerStub);
    }

    public function test_stack_option_routes_to_blade_builder(): void
    {
        $command = $this->spyCommand();

        $this->artisan('test:stack-spy users --stack=blade')->assertExitCode(0);

        $this->assertSame(['blade'], $command->calls);
        $this->assertSame('Controller', $command->controllerStub);
    }

    public function test_stack_defaults_to_react_when_option_is_omitted(): void
    {
        $command = $this->spyCommand();

        $this->artisan('test:stack-spy users')->assertExitCode(0);

        $this->assertSame(['react'], $command->calls);
    }

    public function test_invalid_stack_fails_before_touching_the_database(): void
    {
        $this->spyCommand();

        $this->artisan('test:stack-spy users --stack=angular')->assertExitCode(1);
    }

    public function test_routes_option_overrides_the_configured_route_helper(): void
    {
        $command = $this->spyCommand();
        $command->fakeWayfinderInstalled = true;

        config()->set('crud.inertia.route_helper', 'auto');

        $this->artisan('test:stack-spy users --routes=ziggy')->assertExitCode(0);

        $this->assertSame('ziggy', $command->resolvedRouteHelper());
    }

    public function test_auto_picks_wayfinder_when_the_package_is_installed(): void
    {
        $command = $this->spyCommand();
        $command->fakeWayfinderInstalled = true;

        config()->set('crud.inertia.route_helper', 'auto');

        $this->artisan('test:stack-spy users')->assertExitCode(0);

        $this->assertSame('wayfinder', $command->resolvedRouteHelper());
    }

    public function test_auto_falls_back_to_ziggy_without_wayfinder(): void
    {
        $command = $this->spyCommand();
        $command->fakeWayfinderInstalled = false;

        config()->set('crud.inertia.route_helper', 'auto');

        $this->artisan('test:stack-spy users')->assertExitCode(0);

        $this->assertSame('ziggy', $command->resolvedRouteHelper());
    }

    public function test_config_value_wins_over_auto_detection(): void
    {
        $command = $this->spyCommand();
        $command->fakeWayfinderInstalled = true;

        config()->set('crud.inertia.route_helper', 'ziggy');

        $this->artisan('test:stack-spy users')->assertExitCode(0);

        $this->assertSame('ziggy', $command->resolvedRouteHelper());
    }

    public function test_invalid_routes_option_fails(): void
    {
        $this->spyCommand();

        $this->artisan('test:stack-spy users --routes=inertia')->assertExitCode(1);
    }

    public function test_route_helper_defaults_to_ziggy_when_config_is_missing(): void
    {
        $command = $this->spyCommand();
        $command->fakeWayfinderInstalled = false;

        config()->set('crud.inertia', []);

        $this->artisan('test:stack-spy users')->assertExitCode(0);

        $this->assertSame('ziggy', $command->resolvedRouteHelper());
    }
}
