<?php

namespace Tests\Unit;

use Crud\Console\InstallThemeSystemCommand;
use Crud\Console\CreateThemeCommand;
use Crud\CrudManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Crud\CrudServiceProvider;

class CrudPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    private function cleanupTestFiles(): void
    {
        $paths = [
            resource_path('js/Components/ui'),
            resource_path('js/hooks'),
            resource_path('js/types/themes.ts'),
            config_path('themes.php'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    /** @test */
    public function it_can_install_theme_system()
    {
        $this->artisan('crud:install-theme-system')
            ->expectsQuestion('Do you want to install the theme system?', 'yes')
            ->expectsQuestion('Which frontend stack are you using?', 'React.js with Inertia.js')
            ->assertExitCode(0);

        // Check if theme config is published
        $this->assertFileExists(config_path('themes.php'));

        // Check if React components are created
        $this->assertFileExists(resource_path('js/types/themes.ts'));
        $this->assertFileExists(resource_path('js/hooks/use-appearance.tsx'));
        $this->assertFileExists(resource_path('js/Components/ui/theme-selector.tsx'));
    }

    /** @test */
    public function it_can_create_new_theme()
    {
        // First install theme system
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $this->artisan('crud:create-theme')
            ->expectsQuestion('What is the theme name?', 'Ocean')
            ->expectsQuestion('What is the primary color (OKLCH format)?', '0.7 0.15 220')
            ->expectsQuestion('What is the secondary color (OKLCH format)?', '0.8 0.1 240')
            ->expectsQuestion('Is this a dark theme?', false)
            ->assertExitCode(0);

        // Check if theme is added to themes.ts
        $themesFile = resource_path('js/types/themes.ts');
        $this->assertFileExists($themesFile);

        $content = File::get($themesFile);
        $this->assertStringContainsString('ocean', $content);
        $this->assertStringContainsString('Ocean', $content);
    }

    /** @test */
    public function crud_manager_can_check_theme_system_installation()
    {
        $manager = app(CrudManager::class);

        // Initially not installed
        $this->assertFalse($manager->isThemeSystemInstalled());

        // Install theme system
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        // Now should be installed
        $this->assertTrue($manager->isThemeSystemInstalled());
    }

    /** @test */
    public function crud_manager_can_get_available_themes()
    {
        $manager = app(CrudManager::class);

        // Install theme system first
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $themes = $manager->getThemes();

        $this->assertIsArray($themes);
        $this->assertArrayHasKey('default', $themes);
        $this->assertArrayHasKey('name', $themes['default']);
        $this->assertArrayHasKey('colors', $themes['default']);
    }

    /** @test */
    public function it_validates_theme_name_format()
    {
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        // Test invalid theme name
        $this->artisan('crud:create-theme')
            ->expectsQuestion('What is the theme name?', 'Invalid Name!')
            ->expectsOutput('Theme name must contain only letters, numbers, spaces, and hyphens.')
            ->expectsQuestion('What is the theme name?', 'Valid Theme')
            ->expectsQuestion('What is the primary color (OKLCH format)?', '0.7 0.15 220')
            ->expectsQuestion('What is the secondary color (OKLCH format)?', '0.8 0.1 240')
            ->expectsQuestion('Is this a dark theme?', false)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_validates_oklch_color_format()
    {
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $this->artisan('crud:create-theme')
            ->expectsQuestion('What is the theme name?', 'Test Theme')
            ->expectsQuestion('What is the primary color (OKLCH format)?', 'invalid color')
            ->expectsOutput('Invalid OKLCH format. Please use format: "L C H" (e.g., "0.7 0.15 220")')
            ->expectsQuestion('What is the primary color (OKLCH format)?', '0.7 0.15 220')
            ->expectsQuestion('What is the secondary color (OKLCH format)?', '0.8 0.1 240')
            ->expectsQuestion('Is this a dark theme?', false)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_frontend_stack_automatically()
    {
        // Create package.json with React
        File::put(base_path('package.json'), json_encode([
            'dependencies' => [
                'react' => '^18.0.0',
                '@inertiajs/react' => '^1.0.0'
            ]
        ]));

        $this->artisan('crud:install-theme-system', ['--force' => true])
            ->assertExitCode(0);

        // Should automatically detect React and create appropriate files
        $this->assertFileExists(resource_path('js/hooks/use-appearance.tsx'));

        // Cleanup
        File::delete(base_path('package.json'));
    }

    /** @test */
    public function it_can_force_reinstall_theme_system()
    {
        // Install once
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $originalTime = File::lastModified(config_path('themes.php'));

        // Wait a moment to ensure different timestamp
        sleep(1);

        // Install again with force
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $newTime = File::lastModified(config_path('themes.php'));

        $this->assertGreaterThan($originalTime, $newTime);
    }

    /** @test */
    public function theme_config_has_correct_structure()
    {
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        $config = include config_path('themes.php');

        $this->assertArrayHasKey('default_theme', $config);
        $this->assertArrayHasKey('persistence', $config);
        $this->assertArrayHasKey('themes', $config);
        $this->assertArrayHasKey('css_variables', $config);

        // Check default theme structure
        $defaultTheme = $config['themes']['default'];
        $this->assertArrayHasKey('name', $defaultTheme);
        $this->assertArrayHasKey('colors', $defaultTheme);
        $this->assertArrayHasKey('primary', $defaultTheme['colors']);
        $this->assertArrayHasKey('secondary', $defaultTheme['colors']);
    }

    /** @test */
    public function react_components_have_correct_content()
    {
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        // Check themes.ts file
        $themesContent = File::get(resource_path('js/types/themes.ts'));
        $this->assertStringContainsString('export interface Theme', $themesContent);
        $this->assertStringContainsString('ThemeColors', $themesContent);
        $this->assertStringContainsString('availableThemes', $themesContent);

        // Check use-appearance hook
        $hookContent = File::get(resource_path('js/hooks/use-appearance.tsx'));
        $this->assertStringContainsString('useAppearance', $hookContent);
        $this->assertStringContainsString('applyTheme', $hookContent);
        $this->assertStringContainsString('useState', $hookContent);

        // Check theme selector component
        $selectorContent = File::get(resource_path('js/Components/ui/theme-selector.tsx'));
        $this->assertStringContainsString('ThemeSelector', $selectorContent);
        $this->assertStringContainsString('useAppearance', $selectorContent);
    }

    /** @test */
    public function it_handles_existing_theme_overwrite()
    {
        $this->artisan('crud:install-theme-system', ['--force' => true]);

        // Create a theme
        $this->artisan('crud:create-theme')
            ->expectsQuestion('What is the theme name?', 'Sunset')
            ->expectsQuestion('What is the primary color (OKLCH format)?', '0.7 0.15 30')
            ->expectsQuestion('What is the secondary color (OKLCH format)?', '0.8 0.1 45')
            ->expectsQuestion('Is this a dark theme?', false)
            ->assertExitCode(0);

        // Try to create the same theme again
        $this->artisan('crud:create-theme')
            ->expectsQuestion('What is the theme name?', 'Sunset')
            ->expectsOutput('Theme "sunset" already exists.')
            ->expectsQuestion('Do you want to overwrite it?', 'yes')
            ->expectsQuestion('What is the primary color (OKLCH format)?', '0.6 0.2 25')
            ->expectsQuestion('What is the secondary color (OKLCH format)?', '0.7 0.15 40')
            ->expectsQuestion('Is this a dark theme?', true)
            ->assertExitCode(0);

        // Verify the theme was updated
        $themesContent = File::get(resource_path('js/types/themes.ts'));
        $this->assertStringContainsString('0.6 0.2 25', $themesContent);
    }
}
