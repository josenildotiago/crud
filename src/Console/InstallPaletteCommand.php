<?php

namespace Crud\Console;

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
}
