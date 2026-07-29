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
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    /** @var array<int, string> */
    public array $calls = [];

    public ?string $controllerStub = null;

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

    protected function debugColumns(): void
    {
        // Sem banco no teste.
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
}
