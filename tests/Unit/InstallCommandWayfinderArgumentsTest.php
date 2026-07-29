<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class WayfinderArgumentsSpyInstallCommand extends InstallCommand
{
    /**
     * @return array<int, string>
     */
    public function argumentsForTest(): array
    {
        return $this->wayfinderArguments();
    }
}

/**
 * `wayfinder:generate` reescreve `resources/js/routes/` inteiro, não só o recurso
 * gerado. Rodá-lo com menos opções do que o app usa apaga as variantes de form das
 * rotas do próprio starter kit, e as páginas de auth/settings dele param de compilar:
 * `Property 'form' does not exist`. A geração de um CRUD não pode estreitar a saída
 * que o app já tinha.
 */
class InstallCommandWayfinderArgumentsTest extends TestCase
{
    private string $tmpBasePath;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpBasePath = sys_get_temp_dir() . '/crud-wayfinder-args-' . uniqid();
        (new Filesystem())->makeDirectory($this->tmpBasePath . '/resources/js/routes', 0755, true);
        $this->app->setBasePath($this->tmpBasePath);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmpBasePath);

        parent::tearDown();
    }

    private function putRoutesIndex(string $content): void
    {
        (new Filesystem())->put($this->tmpBasePath . '/resources/js/routes/index.ts', $content);
    }

    private function putNestedRouteFile(string $dir, string $content): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->tmpBasePath . '/resources/js/routes/' . $dir, 0755, true);
        $files->put($this->tmpBasePath . '/resources/js/routes/' . $dir . '/index.ts', $content);
    }

    private function command(): WayfinderArgumentsSpyInstallCommand
    {
        return new WayfinderArgumentsSpyInstallCommand(new Filesystem());
    }

    public function test_form_variants_already_in_the_output_are_preserved(): void
    {
        // Formato do wayfinder com `formVariants: true`, que é o default do vite.config.ts
        // dos starter kits do Laravel 13.
        $this->putRoutesIndex(<<<'TS'
        export const login = (options?: RouteQueryOptions) => ({ url: '/login', method: 'get' });
        const loginForm = (options?: RouteQueryOptions) => ({ action: '/login', method: 'get' });
        login.form = loginForm;
        TS);

        $this->assertSame(['--with-form'], $this->command()->argumentsForTest());
    }

    public function test_output_without_form_variants_stays_without_them(): void
    {
        $this->putRoutesIndex(<<<'TS'
        export const login = (options?: RouteQueryOptions) => ({ url: '/login', method: 'get' });
        TS);

        $this->assertSame([], $this->command()->argumentsForTest());
    }

    public function test_form_variants_are_found_in_nested_route_files_too(): void
    {
        // O wayfinder distribui as rotas em subdiretórios por namespace. Um app cujas
        // rotas de raiz não tenham variante de form ainda gera variantes nas aninhadas:
        // olhar só o index.ts da raiz daria falso negativo e apagaria as variantes do
        // usuário — exatamente o defeito que este método existe para evitar.
        $this->putRoutesIndex(<<<'TS'
        export const home = (options?: RouteQueryOptions) => ({ url: '/', method: 'get' });
        TS);

        $this->putNestedRouteFile('password', <<<'TS'
        export const confirm = (options?: RouteQueryOptions) => ({ url: '/password/confirm', method: 'get' });
        const confirmForm = (options?: RouteQueryOptions) => ({ action: '/password/confirm', method: 'get' });
        confirm.form = confirmForm;
        TS);

        $this->assertSame(['--with-form'], $this->command()->argumentsForTest());
    }

    public function test_absent_output_stays_with_the_plain_generation(): void
    {
        // Nada em resources/js/routes: não há formato do usuário a preservar.
        $this->assertSame([], $this->command()->argumentsForTest());
    }
}
