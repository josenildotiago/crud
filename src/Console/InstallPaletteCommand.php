<?php

namespace Crud\Console;

use Crud\MarkedRegion;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class InstallPaletteCommand extends Command
{
    protected $signature = 'crud:install-palette
                            {--stack= : react, vue ou svelte (padrão: detectar pelo projeto)}
                            {--force : Sobrescreve arquivos existentes sem perguntar}';

    protected $description = 'Instala a camada de paleta de cores sobre o starter kit';

    /**
     * Stub => caminho em `resources/`, para o que é igual nas três stacks.
     *
     * @var array<string, string>
     */
    private const SHARED = [
        'crud-palettes.css.stub' => 'css/crud-palettes.css',
        'crud-palette.ts.stub' => 'js/lib/crud-palette.ts',
    ];

    /**
     * O que cada stack tem de próprio.
     *
     * A detecção é pela página de aparência, não pelo arquivo de entrada: `app.ts` serve
     * vue e svelte, então ele não distingue as duas. As âncoras não entram aqui porque são
     * as mesmas nos três kits — `<AppearanceTabs />` e `initializeTheme();`.
     *
     * @var array<string, array{page: string, entry: string, stub: string, target: string, markers: array{0: string, 1: string}, import: string}>
     */
    private const STACKS = [
        'react' => [
            'page' => 'js/pages/settings/appearance.tsx',
            'entry' => 'js/app.tsx',
            'stub' => 'crud-palette-selector.tsx.stub',
            'target' => 'js/components/crud-palette-selector.tsx',
            'markers' => ['{/* crud:palette:start */}', '{/* crud:palette:end */}'],
            'import' => "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
        ],
        'vue' => [
            'page' => 'js/pages/settings/Appearance.vue',
            'entry' => 'js/app.ts',
            'stub' => 'CrudPaletteSelector.vue.stub',
            'target' => 'js/components/CrudPaletteSelector.vue',
            'markers' => ['<!-- crud:palette:start -->', '<!-- crud:palette:end -->'],
            'import' => "import CrudPaletteSelector from '@/components/CrudPaletteSelector.vue';",
        ],
        'svelte' => [
            'page' => 'js/pages/settings/Appearance.svelte',
            'entry' => 'js/app.ts',
            'stub' => 'CrudPaletteSelector.svelte.stub',
            'target' => 'js/components/CrudPaletteSelector.svelte',
            'markers' => ['<!-- crud:palette:start -->', '<!-- crud:palette:end -->'],
            'import' => "import CrudPaletteSelector from '@/components/CrudPaletteSelector.svelte';",
        ],
    ];

    /** A stack desta execução, resolvida no `handle()`. */
    private string $stack = 'react';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $stack = $this->resolveStack();

        if ($stack === null) {
            return self::FAILURE;
        }

        $this->stack = $stack;

        info("🎨 Instalando a camada de paleta ({$this->stack})...");

        foreach (self::SHARED as $stub => $destino) {
            $this->writeStub($stub, $destino);
        }

        $this->writeStub(self::STACKS[$this->stack]['stub'], self::STACKS[$this->stack]['target']);

        $this->editAppCss();
        $this->editAppTsx();

        if (config('crud.palette.settings_page', true)) {
            $this->editAppearancePage();
        }

        info('✅ Paleta instalada.');

        return self::SUCCESS;
    }

    /**
     * Qual stack instalar: `--stack` manda, senão vale o que o projeto revela.
     *
     * Pedir uma stack que não bate com o projeto é o caso que merece pergunta: o usuário
     * pode estar fazendo isso de propósito, mas na maioria das vezes é engano, e o
     * resultado seria um seletor que a build dele não compila.
     *
     * Devolve null quando não há como seguir — quem chama devolve FAILURE.
     */
    private function resolveStack(): ?string
    {
        $pedida = $this->option('stack');
        $detectada = $this->detectStack();

        if ($pedida !== null && !array_key_exists($pedida, self::STACKS)) {
            $this->components->error(
                "Stack `{$pedida}` inválida. Opções: " . implode(', ', array_keys(self::STACKS))
            );

            return null;
        }

        if ($pedida === null && $detectada === null) {
            $this->components->error(
                'Não identifiquei a stack deste projeto: não achei a página de aparência de '
                    . 'nenhuma das stacks suportadas (react, vue, svelte). Se for uma delas, '
                    . 'passe --stack=. O livewire ainda não tem paleta.'
            );

            return null;
        }

        if ($pedida !== null && $detectada !== null && $pedida !== $detectada) {
            if (!confirm(
                "Você pediu `{$pedida}`, mas este projeto parece `{$detectada}`. Instalar `{$pedida}` assim mesmo?",
                default: false
            )) {
                info('Instalação cancelada.');

                return null;
            }
        }

        return $pedida ?? $detectada;
    }

    /**
     * A stack que o projeto revela, ou null se não for nenhuma das três.
     */
    private function detectStack(): ?string
    {
        foreach (self::STACKS as $stack => $config) {
            if ($this->files->exists(resource_path($config['page']))) {
                return $stack;
            }
        }

        return null;
    }

    private function writeStub(string $stub, string $destino): void
    {
        $caminho = resource_path($destino);

        if ($this->files->exists($caminho) && !$this->option('force')) {
            if (!confirm("O arquivo resources/{$destino} já existe. Sobrescrever?", default: false)) {
                $this->components->warn("Mantido: resources/{$destino}");

                return;
            }
        }

        $this->files->ensureDirectoryExists(dirname($caminho));
        $this->files->put($caminho, $this->files->get(__DIR__ . '/../stubs/palette/' . $stub));

        $this->components->info("Criado: resources/{$destino}");
    }

    /**
     * Carrega as paletas pelo `app.css`, depois do último `@import` do topo.
     *
     * `@import` em CSS só vale antes das outras regras, por isso a âncora é o último
     * import e não o fim do arquivo.
     */
    private function editAppCss(): void
    {
        $caminho = resource_path('css/app.css');
        $linha = "@import './crud-palettes.css';";

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/css/app.css', $linha);

            return;
        }

        $conteudo = $this->files->get($caminho);

        if (str_contains($conteudo, $linha)) {
            return;
        }

        $linhas = explode("\n", $conteudo);
        $ultimo = null;

        foreach ($linhas as $numero => $atual) {
            if (str_starts_with(trim($atual), '@import ')) {
                $ultimo = $numero;
            }
        }

        if ($ultimo === null) {
            $this->naoEditou('resources/css/app.css', $linha);

            return;
        }

        array_splice($linhas, $ultimo + 1, 0, [$linha]);

        $this->files->put($caminho, implode("\n", $linhas));
        $this->components->info('Atualizado: resources/css/app.css');
    }

    /**
     * Aplica a paleta antes da primeira pintura, no mesmo ponto onde o starter kit já
     * chama `initializeTheme()`.
     */
    private function editAppTsx(): void
    {
        $caminho = resource_path(self::STACKS[$this->stack]['entry']);
        $import = "import { initializeCrudPalette } from '@/lib/crud-palette';";
        $chamada = 'initializeCrudPalette();';
        // O import do módulo de ids é igual nos três: é TypeScript puro dos dois lados.

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $conteudo = $this->files->get($caminho);

        if (str_contains($conteudo, $chamada)) {
            return;
        }

        if (!str_contains($conteudo, 'initializeTheme();')) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $comImport = MarkedRegion::insertImport($conteudo, $import, '@/lib/crud-palette');

        if ($comImport === null) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $this->files->put(
            $caminho,
            str_replace('initializeTheme();', "initializeTheme();\n" . $chamada, $comImport)
        );

        $this->components->info('Atualizado: resources/' . self::STACKS[$this->stack]['entry']);
    }

    /**
     * Põe o seletor junto do claro/escuro, dentro de uma região que o pacote gerencia.
     */
    private function editAppearancePage(): void
    {
        $config = self::STACKS[$this->stack];
        $caminho = resource_path($config['page']);
        $import = $config['import'];
        // O elemento é o mesmo nas três stacks; o que muda é a sintaxe do comentário que
        // delimita a região — `{/* */}` em TSX, `<!-- -->` em Vue e Svelte.
        $bloco = '<CrudPaletteSelector />';
        $region = new MarkedRegion($config['markers'][0], $config['markers'][1]);

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $conteudo = $this->files->get($caminho);

        $novo = $region->exists($conteudo)
            ? $region->replace($conteudo, $bloco)
            : $region->install($conteudo, '/<AppearanceTabs\s*\/>/', $bloco);

        if ($novo === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $comImport = MarkedRegion::insertImport($novo, $import, '@/components/crud-palette-selector');

        if ($comImport === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $this->files->put($caminho, $comImport);
        $this->components->info('Atualizado: resources/' . $config['page']);
    }

    /**
     * Não conseguimos escrever com segurança: o usuário recebe o trecho e decide.
     */
    private function naoEditou(string $arquivo, string $trecho): void
    {
        $this->components->warn("Não consegui editar {$arquivo}. Acrescente à mão:");
        $this->line('');
        $this->line($trecho);
        $this->line('');
    }
}
