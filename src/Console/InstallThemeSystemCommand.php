<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class InstallThemeSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crud:install-theme-system
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install the dynamic theme system for React.js';

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
     * Execute the console command.
     */
    public function handle(): int
    {
        info('🎨 Instalando sistema de temas dinâmicos...');

        // Check if Inertia.js is installed
        if (!$this->checkInertiaInstallation()) {
            $this->components->error('Inertia.js não foi detectado. Instale o Inertia.js primeiro.');
            return self::FAILURE;
        }

        // Check if React is configured
        if (!$this->checkReactConfiguration()) {
            $this->components->error('React.js não foi detectado. Configure o React.js primeiro.');
            return self::FAILURE;
        }

        // Install theme files
        $this->installThemeFiles();

        // Update package.json if needed
        $this->updatePackageJson();

        // Update CSS files
        $this->updateCssFiles();

        // Create example components
        $this->createExampleComponents();

        info('✅ Sistema de temas instalado com sucesso!');

        $this->components->info('Próximos passos:');
        $this->components->bulletList([
            'Execute: npm install (se novas dependências foram adicionadas)',
            'Execute: npm run build',
            'Use o comando: php artisan themes:create {nome} para criar novos temas',
            'Importe os componentes de tema em suas páginas React'
        ]);

        return self::SUCCESS;
    }

    /**
     * Check if Inertia.js is installed.
     */
    protected function checkInertiaInstallation(): bool
    {
        $packageJsonPath = base_path('package.json');

        if (!$this->files->exists($packageJsonPath)) {
            return false;
        }

        $packageJson = json_decode($this->files->get($packageJsonPath), true);

        return isset($packageJson['dependencies']['@inertiajs/react']) ||
            isset($packageJson['devDependencies']['@inertiajs/react']);
    }

    /**
     * Check if React is configured.
     */
    protected function checkReactConfiguration(): bool
    {
        return $this->files->exists(resource_path('js/app.tsx')) ||
            $this->files->exists(resource_path('js/app.jsx'));
    }

    /**
     * Install theme files.
     */
    protected function installThemeFiles(): void
    {
        $this->components->info('Instalando arquivos de tema...');

        $files = [
            'themes.ts' => 'js/lib/themes.ts',
            'use-appearance.tsx' => 'js/hooks/use-appearance.tsx',
            'theme-selector.tsx' => 'js/components/theme-selector.tsx',
            'appearance-dropdown.tsx' => 'js/components/appearance-dropdown.tsx',
            'appearance-theme-selector.tsx' => 'js/components/appearance-theme-selector.tsx',
            'appearance-tabs.tsx' => 'js/components/appearance-tabs.tsx',
            'theme-demo.tsx' => 'js/components/theme-demo.tsx',
        ];

        foreach ($files as $stub => $destination) {
            $this->installStubFile($stub, $destination);
        }
    }

    /**
     * Install a stub file.
     */
    protected function installStubFile(string $stub, string $destination): void
    {
        $stubPath = __DIR__ . "/../stubs/react/{$stub}.stub";
        $destinationPath = resource_path($destination);

        if (!$this->files->exists($stubPath)) {
            warning("Stub não encontrado: {$stub}");
            return;
        }

        // Create directory if it doesn't exist
        $this->files->ensureDirectoryExists(dirname($destinationPath));

        // Check if file exists and ask for confirmation
        if ($this->files->exists($destinationPath) && !$this->option('force')) {
            if (!confirm("O arquivo {$destination} já existe. Deseja sobrescrever?")) {
                return;
            }
        }

        // Copy and process the stub
        $content = $this->files->get($stubPath);
        $this->files->put($destinationPath, $content);

        $this->components->task("Instalado: {$destination}");
    }

    /**
     * Update package.json with required dependencies.
     */
    protected function updatePackageJson(): void
    {
        $packageJsonPath = base_path('package.json');

        if (!$this->files->exists($packageJsonPath)) {
            return;
        }

        $packageJson = json_decode($this->files->get($packageJsonPath), true);

        // Add required dependencies if not present
        $requiredDeps = [
            '@radix-ui/react-dropdown-menu' => '^2.0.6',
            '@radix-ui/react-tabs' => '^1.0.4',
            'lucide-react' => '^0.400.0',
        ];

        $needsUpdate = false;
        foreach ($requiredDeps as $package => $version) {
            if (!isset($packageJson['dependencies'][$package])) {
                $packageJson['dependencies'][$package] = $version;
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $this->files->put($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->components->info('package.json atualizado com novas dependências.');
        }
    }

    /**
     * Update CSS files.
     */
    protected function updateCssFiles(): void
    {
        $cssPath = resource_path('css/app.css');

        if (!$this->files->exists($cssPath)) {
            return;
        }

        $content = $this->files->get($cssPath);

        // Check if theme variables are already present
        if (Str::contains($content, '/* Theme Variables */')) {
            return;
        }

        // Add theme variable comment
        $themeComment = "\n/* Theme Variables - Managed by Dynamic Theme System */\n/* Variables below will be overridden by the theme system */\n";

        $content = preg_replace('/(:root\s*{)/', "$1{$themeComment}", $content);

        $this->files->put($cssPath, $content);
        $this->components->task('CSS atualizado com comentários de tema');
    }

    /**
     * Create example components.
     */
    protected function createExampleComponents(): void
    {
        // Create a simple example page if it doesn't exist
        $examplePagePath = resource_path('js/pages/ThemeExample.tsx');

        if (!$this->files->exists($examplePagePath) || $this->option('force')) {
            $this->files->ensureDirectoryExists(dirname($examplePagePath));

            $exampleContent = $this->files->get(__DIR__ . '/../stubs/react/ThemeExample.tsx.stub');
            $this->files->put($examplePagePath, $exampleContent);

            $this->components->task('Página de exemplo criada: pages/ThemeExample.tsx');
        }
    }
}