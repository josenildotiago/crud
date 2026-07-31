<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class CreatePaletteCommand extends Command
{
    protected $signature = 'crud:create-palette
                            {name? : Nome da paleta}
                            {--hue= : Matiz OKLCH, 0 a 360}';

    protected $description = 'Acrescenta uma paleta de cores ao projeto';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cssPath = resource_path('css/crud-palettes.css');
        $tsPath = resource_path('js/lib/crud-palette.ts');

        if (!$this->files->exists($cssPath) || !$this->files->exists($tsPath)) {
            $this->components->error('Rode `php artisan crud:install-palette` antes.');

            return self::FAILURE;
        }

        $nome = $this->argument('name') ?? text('Nome da paleta?', required: true);
        $id = Str::slug($nome);

        $hue = $this->option('hue') ?? text('Matiz OKLCH (0 a 360)?', default: '250');

        if (!is_numeric($hue) || $hue < 0 || $hue > 360) {
            $this->components->error("Matiz `{$hue}` inválido. Use um número de 0 a 360.");

            return self::FAILURE;
        }

        $css = $this->files->get($cssPath);
        $ts = $this->files->get($tsPath);

        // Detectar estilo de fim de linha do TS (para preservar ao gravar)
        $useCRLF = str_contains($ts, "\r\n");

        // Normalizar TS para LF para validação e processamento
        $tsNormalized = str_replace("\r\n", "\n", $ts);

        // Validar que a âncora existe no TS normalizado
        if (!str_contains($tsNormalized, "];\n\nexport function getPalette")) {
            $this->components->error('Âncora de paletas não encontrada no TypeScript. Rode `php artisan crud:install-palette` com `--force` para reconstruir o arquivo.');

            return self::FAILURE;
        }

        // Determinar estado do id nos dois arquivos
        $paletaPath = $this->determinePaletteState($css, $tsNormalized, $id);

        // Processar cada caso
        return match ($paletaPath) {
            'both' => $this->handleDuplicateError($id),
            'css-only' => $this->handleCompleteTS($tsNormalized, $tsPath, $id, $nome, $useCRLF),
            'ts-only' => $this->handleCompleteCSS($css, $cssPath, $id, $hue),
            'none' => $this->handleNewPalette($css, $cssPath, $tsNormalized, $tsPath, $id, $nome, $hue, $useCRLF),
        };
    }

    /**
     * Determina em quais arquivos o id da paleta existe.
     *
     * @return 'both'|'css-only'|'ts-only'|'none'
     */
    private function determinePaletteState(string $css, string $tsNormalized, string $id): string
    {
        $inCSS = str_contains($css, "data-crud-palette='{$id}'");
        $inTS = str_contains($tsNormalized, "id: '{$id}'");

        if ($inCSS && $inTS) {
            return 'both';
        }

        if ($inCSS) {
            return 'css-only';
        }

        if ($inTS) {
            return 'ts-only';
        }

        return 'none';
    }

    private function handleDuplicateError(string $id): int
    {
        $this->components->error("Já existe uma paleta `{$id}`.");

        return self::FAILURE;
    }

    private function handleCompleteTS(string $tsNormalized, string $tsPath, string $id, string $nome, bool $useCRLF): int
    {
        // Paleta só no CSS; completa TS
        $entrada = "    { id: '{$id}', name: '{$nome}' },";
        $tsNovo = str_replace(
            "];\n\nexport function getPalette",
            $entrada . "\n];\n\nexport function getPalette",
            $tsNormalized
        );

        if ($useCRLF) {
            $tsNovo = str_replace("\n", "\r\n", $tsNovo);
        }

        $this->files->put($tsPath, $tsNovo);
        $this->components->info("Paleta `{$id}` estava só no CSS; acrescentei a entrada na lista. Rode `npm run build` para vê-la.");

        return self::SUCCESS;
    }

    private function handleCompleteCSS(string $css, string $cssPath, string $id, float $hue): int
    {
        // Paleta só no TS; completa CSS
        $cssNovo = $css . "\n" . $this->blocks($id, $hue);
        $this->files->put($cssPath, $cssNovo);

        $this->components->info("Paleta `{$id}` estava só na lista; acrescentei os estilos ao CSS. Rode `npm run build` para vê-la.");

        return self::SUCCESS;
    }

    private function handleNewPalette(string $css, string $cssPath, string $tsNormalized, string $tsPath, string $id, string $nome, float $hue, bool $useCRLF): int
    {
        // Paleta nova; acrescenta aos dois
        $cssNovo = $css . "\n" . $this->blocks($id, $hue);
        $this->files->put($cssPath, $cssNovo);

        $entrada = "    { id: '{$id}', name: '{$nome}' },";
        $tsNovo = str_replace(
            "];\n\nexport function getPalette",
            $entrada . "\n];\n\nexport function getPalette",
            $tsNormalized
        );

        if ($useCRLF) {
            $tsNovo = str_replace("\n", "\r\n", $tsNovo);
        }

        $this->files->put($tsPath, $tsNovo);

        $this->components->info("Paleta `{$id}` criada. Rode `npm run build` para vê-la.");

        return self::SUCCESS;
    }

    /**
     * Os dois blocos da paleta, nas mesmas nove variáveis das que vêm no pacote.
     */
    private function blocks(string $id, float $hue): string
    {
        $claro = sprintf('oklch(0.55 0.19 %s)', $hue);
        $escuro = sprintf('oklch(0.7 0.16 %s)', $hue);
        $contraste = sprintf('oklch(0.21 0.03 %s)', $hue);

        return <<<CSS
        :root[data-crud-palette='{$id}'] {
            --primary: {$claro};
            --primary-foreground: oklch(0.985 0 0);
            --ring: {$claro};
            --sidebar-primary: {$claro};
            --sidebar-primary-foreground: oklch(0.985 0 0);
            --sidebar-ring: {$claro};
            --chart-1: {$claro};
            --chart-2: oklch(0.65 0.15 {$hue});
            --chart-3: oklch(0.75 0.11 {$hue});
        }

        :root.dark[data-crud-palette='{$id}'] {
            --primary: {$escuro};
            --primary-foreground: {$contraste};
            --ring: {$escuro};
            --sidebar-primary: {$escuro};
            --sidebar-primary-foreground: {$contraste};
            --sidebar-ring: {$escuro};
            --chart-1: {$escuro};
            --chart-2: oklch(0.62 0.14 {$hue});
            --chart-3: oklch(0.54 0.12 {$hue});
        }

        CSS;
    }
}
