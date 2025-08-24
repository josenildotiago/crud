<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class CreateThemeCommand extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crud:create-theme {name? : Theme name}
                            {--base-color= : Base color in OKLCH format}
                            {--auto-generate : Auto-generate color variations}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new theme with color variations';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn() => text(
                label: 'What is the theme name?',
                placeholder: 'My Theme',
                required: true,
                validate: function ($value) {
                    if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $value)) {
                        return 'Theme name must contain only letters, numbers, spaces, and hyphens.';
                    }
                    return null;
                }
            )
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $themeName = $this->argument('name');
        $themeId = Str::kebab($themeName);

        info("🎨 Criando tema: {$themeName}");

        // Check if theme system is installed
        if (!$this->isThemeSystemInstalled()) {
            $this->components->error('Sistema de temas não instalado. Execute: php artisan crud:install-theme-system');
            return self::FAILURE;
        }

        // Get base color
        $baseColor = $this->getBaseColor();

        // Generate theme configuration
        $themeConfig = $this->generateThemeConfig($themeId, $themeName, $baseColor);

        // Add theme to themes.ts file
        $this->addThemeToFile($themeConfig);

        info("✅ Tema '{$themeName}' criado com sucesso!");

        $this->components->info('O tema foi adicionado ao arquivo themes.ts e está disponível para uso.');

        return self::SUCCESS;
    }

    /**
     * Check if theme system is installed.
     */
    protected function isThemeSystemInstalled(): bool
    {
        return $this->files->exists(resource_path('js/lib/themes.ts'));
    }

    /**
     * Get base color from user input or option.
     */
    protected function getBaseColor(): string
    {
        if ($baseColor = $this->option('base-color')) {
            return $baseColor;
        }

        if ($this->option('auto-generate')) {
            return $this->selectPredefinedColor();
        }

        $colorInput = select(
            label: 'Como deseja definir a cor base?',
            options: [
                'predefined' => 'Escolher de uma paleta predefinida',
                'custom' => 'Inserir cor customizada (OKLCH)',
                'hex' => 'Inserir cor em formato HEX (será convertida)',
            ],
            default: 'predefined'
        );

        return match ($colorInput) {
            'predefined' => $this->selectPredefinedColor(),
            'custom' => $this->inputCustomColor(),
            'hex' => $this->convertHexToOklch(),
            default => 'oklch(0.55 0.2 220)'
        };
    }

    /**
     * Select a predefined color.
     */
    protected function selectPredefinedColor(): string
    {
        $colors = [
            'blue' => 'oklch(0.55 0.2 240)',
            'green' => 'oklch(0.55 0.2 140)',
            'purple' => 'oklch(0.55 0.2 280)',
            'red' => 'oklch(0.55 0.2 20)',
            'orange' => 'oklch(0.55 0.2 50)',
            'yellow' => 'oklch(0.55 0.2 90)',
            'pink' => 'oklch(0.55 0.2 320)',
            'gray' => 'oklch(0.55 0.05 220)',
            'teal' => 'oklch(0.55 0.2 180)',
            'indigo' => 'oklch(0.55 0.2 260)',
        ];

        $selected = select(
            label: 'Escolha uma cor base:',
            options: array_keys($colors),
            default: 'blue'
        );

        return $colors[$selected];
    }

    /**
     * Input custom OKLCH color.
     */
    protected function inputCustomColor(): string
    {
        return text(
            label: 'Digite a cor em formato OKLCH (ex: oklch(0.55 0.2 240)):',
            placeholder: 'oklch(0.55 0.2 240)',
            default: 'oklch(0.55 0.2 240)',
            validate: fn(string $value) => $this->validateOklchColor($value)
        );
    }

    /**
     * Convert HEX to OKLCH (simplified conversion).
     */
    protected function convertHexToOklch(): string
    {
        $hex = text(
            label: 'Digite a cor em formato HEX (ex: #3b82f6):',
            placeholder: '#3b82f6',
            validate: fn(string $value) => $this->validateHexColor($value)
        );

        // This is a simplified conversion - in production you might want to use a proper color conversion library
        return $this->hexToOklch($hex);
    }

    /**
     * Validate OKLCH color format.
     */
    protected function validateOklchColor(string $value): ?string
    {
        if (!preg_match('/^oklch\(\s*[0-9.]+\s+[0-9.]+\s+[0-9.]+\s*\)$/', $value)) {
            return 'Formato OKLCH inválido. Use: oklch(lightness chroma hue)';
        }

        return null;
    }

    /**
     * Validate HEX color format.
     */
    protected function validateHexColor(string $value): ?string
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return 'Formato HEX inválido. Use: #RRGGBB';
        }

        return null;
    }

    /**
     * Simple HEX to OKLCH conversion (approximation).
     */
    protected function hexToOklch(string $hex): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        // Simple approximation to OKLCH (this is not accurate, use a proper library in production)
        $lightness = round(($r + $g + $b) / 3, 2);
        $chroma = 0.2; // Default chroma
        $hue = 240; // Default hue, should be calculated properly

        return "oklch({$lightness} {$chroma} {$hue})";
    }

    /**
     * Generate theme configuration.
     */
    protected function generateThemeConfig(string $id, string $name, string $baseColor): array
    {
        // Parse base color
        preg_match('/oklch\(\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\)/', $baseColor, $matches);
        $lightness = (float) ($matches[1] ?? 0.55);
        $chroma = (float) ($matches[2] ?? 0.2);
        $hue = (float) ($matches[3] ?? 240);

        return [
            'id' => $id,
            'name' => $name,
            'variables' => [
                'light' => $this->generateLightVariables($lightness, $chroma, $hue),
                'dark' => $this->generateDarkVariables($lightness, $chroma, $hue),
            ]
        ];
    }

    /**
     * Generate light mode variables.
     */
    protected function generateLightVariables(float $l, float $c, float $h): array
    {
        return [
            '--background' => 'oklch(1 0 0)',
            '--foreground' => 'oklch(0.15 0 0)',
            '--card' => 'oklch(1 0 0)',
            '--card-foreground' => 'oklch(0.15 0 0)',
            '--popover' => 'oklch(1 0 0)',
            '--popover-foreground' => 'oklch(0.15 0 0)',
            '--primary' => "oklch({$l} {$c} {$h})",
            '--primary-foreground' => 'oklch(0.98 0 0)',
            '--secondary' => "oklch(" . round($l + 0.35, 2) . " " . round($c * 0.3, 2) . " {$h})",
            '--secondary-foreground' => 'oklch(0.15 0 0)',
            '--muted' => "oklch(" . round($l + 0.4, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--muted-foreground' => 'oklch(0.45 0 0)',
            '--accent' => "oklch(" . round($l + 0.3, 2) . " " . round($c * 0.4, 2) . " {$h})",
            '--accent-foreground' => 'oklch(0.15 0 0)',
            '--destructive' => 'oklch(0.6 0.2 20)',
            '--destructive-foreground' => 'oklch(0.98 0 0)',
            '--border' => "oklch(" . round($l + 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--input' => "oklch(" . round($l + 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--ring' => "oklch({$l} {$c} {$h})",
            '--chart-1' => "oklch({$l} {$c} {$h})",
            '--chart-2' => "oklch({$l} {$c} " . ($h + 60) % 360 . ")",
            '--chart-3' => "oklch({$l} {$c} " . ($h + 120) % 360 . ")",
            '--chart-4' => "oklch({$l} {$c} " . ($h + 180) % 360 . ")",
            '--chart-5' => "oklch({$l} {$c} " . ($h + 240) % 360 . ")",
            '--sidebar' => 'oklch(0.98 0 0)',
            '--sidebar-foreground' => 'oklch(0.45 0 0)',
            '--sidebar-primary' => "oklch({$l} {$c} {$h})",
            '--sidebar-primary-foreground' => 'oklch(0.98 0 0)',
            '--sidebar-accent' => "oklch(" . round($l + 0.3, 2) . " " . round($c * 0.4, 2) . " {$h})",
            '--sidebar-accent-foreground' => 'oklch(0.15 0 0)',
            '--sidebar-border' => "oklch(" . round($l + 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--sidebar-ring' => "oklch({$l} {$c} {$h})",
        ];
    }

    /**
     * Generate dark mode variables.
     */
    protected function generateDarkVariables(float $l, float $c, float $h): array
    {
        return [
            '--background' => 'oklch(0.05 0 0)',
            '--foreground' => 'oklch(0.98 0 0)',
            '--card' => 'oklch(0.05 0 0)',
            '--card-foreground' => 'oklch(0.98 0 0)',
            '--popover' => 'oklch(0.05 0 0)',
            '--popover-foreground' => 'oklch(0.98 0 0)',
            '--primary' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " {$h})",
            '--primary-foreground' => 'oklch(0.15 0 0)',
            '--secondary' => "oklch(" . round($l - 0.25, 2) . " " . round($c * 0.3, 2) . " {$h})",
            '--secondary-foreground' => 'oklch(0.98 0 0)',
            '--muted' => "oklch(" . round($l - 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--muted-foreground' => 'oklch(0.65 0 0)',
            '--accent' => "oklch(" . round($l - 0.25, 2) . " " . round($c * 0.4, 2) . " {$h})",
            '--accent-foreground' => 'oklch(0.98 0 0)',
            '--destructive' => 'oklch(0.7 0.25 20)',
            '--destructive-foreground' => 'oklch(0.98 0 0)',
            '--border' => "oklch(" . round($l - 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--input' => "oklch(" . round($l - 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--ring' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " {$h})",
            '--chart-1' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " {$h})",
            '--chart-2' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " " . ($h + 60) % 360 . ")",
            '--chart-3' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " " . ($h + 120) % 360 . ")",
            '--chart-4' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " " . ($h + 180) % 360 . ")",
            '--chart-5' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " " . ($h + 240) % 360 . ")",
            '--sidebar' => 'oklch(0.1 0 0)',
            '--sidebar-foreground' => 'oklch(0.65 0 0)',
            '--sidebar-primary' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " {$h})",
            '--sidebar-primary-foreground' => 'oklch(0.15 0 0)',
            '--sidebar-accent' => "oklch(" . round($l - 0.25, 2) . " " . round($c * 0.4, 2) . " {$h})",
            '--sidebar-accent-foreground' => 'oklch(0.98 0 0)',
            '--sidebar-border' => "oklch(" . round($l - 0.35, 2) . " " . round($c * 0.2, 2) . " {$h})",
            '--sidebar-ring' => "oklch(" . round($l + 0.15, 2) . " " . round($c * 0.8, 2) . " {$h})",
        ];
    }

    /**
     * Add theme to themes.ts file.
     */
    protected function addThemeToFile(array $themeConfig): void
    {
        $themesPath = resource_path('js/lib/themes.ts');
        $content = $this->files->get($themesPath);

        // Convert theme config to TypeScript
        $themeTs = $this->convertThemeToTypeScript($themeConfig);

        // Find the closing bracket of the themes array
        $pattern = '/(\],?\s*\]\s*;?\s*)$/';

        if (preg_match($pattern, $content)) {
            // Add the new theme before the closing bracket
            $replacement = ",\n{$themeTs}\n$1";
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            // If pattern not found, append at the end (fallback)
            $content .= "\n\n// New theme added by artisan command\n{$themeTs}";
        }

        $this->files->put($themesPath, $content);
    }

    /**
     * Convert theme configuration to TypeScript format.
     */
    protected function convertThemeToTypeScript(array $themeConfig): string
    {
        $id = $themeConfig['id'];
        $name = $themeConfig['name'];
        $lightVars = $themeConfig['variables']['light'];
        $darkVars = $themeConfig['variables']['dark'];

        $lightVarsTs = $this->formatVariables($lightVars);
        $darkVarsTs = $this->formatVariables($darkVars);

        return <<<TS
    {
        id: '{$id}',
        name: '{$name}',
        variables: {
            light: {{$lightVarsTs}
            },
            dark: {{$darkVarsTs}
            }
        }
    }
TS;
    }

    /**
     * Format variables for TypeScript.
     */
    protected function formatVariables(array $variables): string
    {
        $formatted = [];
        foreach ($variables as $key => $value) {
            $formatted[] = "                '{$key}': '{$value}'";
        }

        return "\n" . implode(",\n", $formatted) . "\n            ";
    }
}
