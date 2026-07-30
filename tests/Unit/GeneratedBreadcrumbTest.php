<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class BreadcrumbSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:breadcrumb {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    protected function tableExists()
    {
        return true;
    }

    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }

    protected function buildController(): self
    {
        return $this;
    }

    protected function buildModel(): self
    {
        return $this;
    }

    protected function buildViews(): self
    {
        return $this;
    }

    public function buildRouter(): self
    {
        return $this;
    }

    protected function generateWayfinderRoutes(): self
    {
        return $this;
    }

    /** @return array<string, string> */
    public function replacementsForTest(): array
    {
        return $this->buildReplacements();
    }
}

/**
 * Os breadcrumbs dos componentes gerados escrevem a URL do registro à mão, com um
 * template literal. Faltando o `${}`, o href sai com a string `item.id` dentro da URL e
 * o link dá 404 — e o `tsc` não pega, porque continua sendo string válida.
 */
class GeneratedBreadcrumbTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    /** @return array<string, string> */
    private function replacements(): array
    {
        $command = new BreadcrumbSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);
        $this->artisan('test:breadcrumb items')->assertExitCode(0);

        return $command->replacementsForTest();
    }

    private function render(string $stub): string
    {
        $replacements = $this->replacements();

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            file_get_contents(__DIR__ . '/../../src/stubs/react/' . $stub)
        );
    }

    public function test_o_breadcrumb_do_show_interpola_o_id(): void
    {
        $rendered = $this->render('Show.stub');

        $this->assertStringContainsString('href: `/items/${item.id}`', $rendered);
        $this->assertStringNotContainsString('/items/item.id', $rendered);
    }

    public function test_o_breadcrumb_do_edit_interpola_o_id(): void
    {
        $rendered = $this->render('Edit.stub');

        $this->assertStringContainsString('href: `/items/${item.id}/edit`', $rendered);
        $this->assertStringNotContainsString('/items/item.id/edit`', $rendered);
    }
}
