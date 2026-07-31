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

    /**
     * Arquivos que a versão antiga instalou e que são só dela.
     *
     * @var array<int, string>
     */
    private const LEGACY_OURS = [
        'js/lib/themes.ts',
        'js/components/theme-selector.tsx',
        'js/components/appearance-dropdown.tsx',
        'js/components/appearance-theme-selector.tsx',
        'js/components/theme-demo.tsx',
        'js/pages/ThemeExample.tsx',
    ];

    /**
     * Arquivos do starter kit que a versão antiga sobrescreveu.
     *
     * O pacote não apaga nem tenta restaurar: só o git do usuário sabe o que estava lá.
     *
     * @var array<int, string>
     */
    private const LEGACY_THEIRS = [
        'js/hooks/use-appearance.tsx',
        'js/components/appearance-tabs.tsx',
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

        $this->handleLegacy();

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

    /**
     * Destinos que carregam paleta — os dois únicos onde sobrescrever pode apagar dado do
     * usuário criado com `crud:create-palette`. Os seletores (`STACKS[...]['target']`) só
     * têm código do pacote: sobrescrever ali nunca perde nada que o usuário escreveu.
     *
     * @var array<int, string>
     */
    private const CARREGAM_PALETA = ['css/crud-palettes.css', 'js/lib/crud-palette.ts'];

    private function writeStub(string $stub, string $destino): void
    {
        $caminho = resource_path($destino);
        $novo = $this->files->get(__DIR__ . '/../stubs/palette/' . $stub);

        if ($this->files->exists($caminho)) {
            $atual = $this->files->get($caminho);
            $extras = in_array($destino, self::CARREGAM_PALETA, true)
                ? $this->extraPaletteIds($atual, $novo)
                : [];

            if ($extras !== []) {
                // `--force` pula a confirmação de sobrescrita comum, mas não esta: ali a
                // perda é de arquivo reinstalável, aqui é de paleta que o usuário criou
                // com `crud:create-palette` e não existe em lugar nenhum do pacote. Por
                // isso o confirm roda sempre, com ou sem `--force` (achado 3).
                $this->components->warn(
                    "resources/{$destino} tem paletas que não vêm no pacote: " . implode(', ', $extras)
                        . '. Sobrescrever com o stub original apaga essas paletas.'
                );

                if (!confirm('Sobrescrever mesmo assim?', default: false)) {
                    $this->components->warn("Mantido: resources/{$destino}");

                    return;
                }
            } elseif (!$this->option('force')) {
                if (!confirm("O arquivo resources/{$destino} já existe. Sobrescrever?", default: false)) {
                    $this->components->warn("Mantido: resources/{$destino}");

                    return;
                }
            }
        }

        $this->files->ensureDirectoryExists(dirname($caminho));
        $this->files->put($caminho, $novo);

        $this->components->info("Criado: resources/{$destino}");
    }

    /**
     * Ids de paleta presentes em `$atual` e ausentes em `$novo` (o stub original).
     *
     * Funciona nos dois formatos sem saber qual é qual: o padrão do CSS
     * (`data-crud-palette='id'`) e o do TS (`id: 'id'`) não colidem, então aplicar os
     * dois regexes em qualquer um dos arquivos só acha o que existe de verdade nele.
     *
     * @return array<int, string>
     */
    private function extraPaletteIds(string $atual, string $novo): array
    {
        return array_values(array_diff($this->paletteIds($atual), $this->paletteIds($novo)));
    }

    /**
     * @return array<int, string>
     */
    private function paletteIds(string $conteudo): array
    {
        preg_match_all("/data-crud-palette='([^']+)'/", $conteudo, $css);
        preg_match_all("/id:\s*'([^']+)'/", $conteudo, $ts);

        return array_values(array_unique(array_merge($css[1], $ts[1])));
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

        // `preg_split('/\R/', ...)` em vez de `explode("\n", ...)`: este método não passa
        // pela `MarkedRegion`, então precisa da mesma tolerância a CRLF por conta própria.
        // Sem isso, um projeto CRLF ainda encontrava a âncora (`str_starts_with` não liga
        // para o fim da linha) e escrevia a linha nova em LF solta no meio do resto em
        // CRLF — sem erro nenhum que explicasse por que só este arquivo mudou.
        $crlf = str_contains($conteudo, "\r\n");
        $linhas = preg_split('/\R/', $conteudo);
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

        $novo = implode("\n", $linhas);
        $this->files->put($caminho, $crlf ? str_replace("\n", "\r\n", $novo) : $novo);
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

        // `MarkedRegion::insertImport()` já preserva CRLF, mas este `str_replace` é nosso,
        // fora dela — sem o mesmo cuidado, ele grudava um "\n" cru depois de
        // `initializeTheme();` mesmo em arquivo CRLF, misturando fim de linha no meio do
        // resto do arquivo (achado 2).
        $quebra = str_contains($comImport, "\r\n") ? "\r\n" : "\n";

        $this->files->put(
            $caminho,
            str_replace('initializeTheme();', 'initializeTheme();' . $quebra . $chamada, $comImport)
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

        // Extrai o módulo da linha de import para usar a chave correta de ordenação.
        // A linha tem forma `import X from '@/components/...'`, e precisamos da string
        // depois do `from`.
        $modulo = $this->extractModule($import);

        if ($modulo === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $comImport = MarkedRegion::insertImport($novo, $import, $modulo);

        if ($comImport === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $this->files->put($caminho, $comImport);
        $this->components->info('Atualizado: resources/' . $config['page']);
    }

    /**
     * Extrai o módulo (caminho após `from`) de uma linha de import.
     *
     * Exemplo: `import X from '@/components/CrudPaletteSelector.vue'` devolve
     * `@/components/CrudPaletteSelector.vue`.
     *
     * Devolve null se a linha não for um import válido.
     */
    private function extractModule(string $importLine): ?string
    {
        $pattern = '/^\s*import\s.+\bfrom\s+[\'"](?<module>[^\'"]+)[\'"];$/';

        if (preg_match($pattern, $importLine, $match) !== 1) {
            return null;
        }

        return $match['module'];
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

    private function handleLegacy(): void
    {
        if (!$this->files->exists(resource_path('js/lib/themes.ts'))) {
            return;
        }

        $this->components->warn('Encontrei o sistema de temas antigo neste projeto.');

        // Achado 4: CRUD gerado com a antiga flag `--theme` materializou, nas páginas
        // Index/Create/Edit/Show do recurso, `import ThemeSelector from
        // '@/components/theme-selector'` e `import { useAppearance } from
        // '@/hooks/use-appearance'`. Apagar `theme-selector.tsx` (oferecido abaixo) deixa
        // essas páginas com import não resolvido, e a build quebra sem pista de onde
        // veio — o comando não sabe quais páginas foram geradas com `--theme` (não
        // guardamos isso em lugar nenhum), então avisa em vez de tentar adivinhar.
        $this->components->warn(
            'Se você gerou CRUD com a flag `--theme` (removida na 5.0.0), as páginas '
                . 'Index/Create/Edit/Show desses recursos ainda importam '
                . '`@/components/theme-selector` e `@/hooks/use-appearance`. Regere esses '
                . 'CRUDs (`php artisan getic:install {tabela}`) antes ou depois de apagar '
                . 'os arquivos abaixo — a build fica quebrada com import não resolvido até lá.'
        );

        $sobrescritos = array_values(array_filter(
            self::LEGACY_THEIRS,
            fn (string $arquivo): bool => $this->files->exists(resource_path($arquivo))
        ));

        if ($sobrescritos !== []) {
            $this->components->warn(
                'Estes arquivos são do seu starter kit e a versão antiga os substituiu. '
                    . 'Recupere-os do git:'
            );

            foreach ($sobrescritos as $arquivo) {
                $this->line("  git checkout -- resources/{$arquivo}");
            }

            $this->line('');
        }

        $nossos = array_values(array_filter(
            self::LEGACY_OURS,
            fn (string $arquivo): bool => $this->files->exists(resource_path($arquivo))
        ));

        if ($nossos === []) {
            return;
        }

        $this->components->info('Instalados pela versão antiga do pacote:');

        foreach ($nossos as $arquivo) {
            $this->line("  resources/{$arquivo}");
        }

        if (!confirm('Apagar os arquivos que a versão antiga instalou?', default: false)) {
            return;
        }

        foreach ($nossos as $arquivo) {
            $this->files->delete(resource_path($arquivo));
        }

        $this->components->info('Removidos. Confira também `@radix-ui/react-tabs` no package.json: '
            . 'a versão antiga acrescentou essa dependência.');
    }
}
