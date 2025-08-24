<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;

class InstallCommand extends GeneratorCommand implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'getic:install {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    /**
     * The console command description.
     */
    protected $description = 'Cria um CRUD moderno com React.js e sistema de temas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table = $this->getNameInput();
        $this->stack = $this->template ?? 'react';
        $this->nameTable = $this->table;

        // Check if table exists
        if (!$this->tableExists()) {
            $this->components->error("Esta tabela `{$this->table}` não existe");
            return self::FAILURE;
        }

        // Check Laravel version
        if (!$this->isLaravel12OrHigher()) {
            $this->components->error('Este pacote requer Laravel 12 ou superior');
            return self::FAILURE;
        }

        $this->name = $this->_buildClassName();

        info('🚀 Gerador de CRUD Laravel 12 + React.js em execução...');

        // Generate components
        $this->buildOptions()
            ->buildController()
            ->buildModel()
            ->buildViews()
            ->buildRouter();

        // Generate API if requested
        if ($this->option('api')) {
            $this->buildApiController()
                ->buildApiRoutes()
                ->buildApiResources()
                ->buildFormRequest();
        }

        info('✅ CRUD criado com sucesso!');

        $this->components->info('Arquivos gerados:');
        $this->components->bulletList([
            "Controller: app/Http/Controllers/{$this->name}Controller.php",
            "Model: app/Models/{$this->name}.php",
            "React Components: resources/js/Pages/{$this->name}/",
            "Routes: routes/" . Str::lower($this->name) . ".php",
            "Web.php: Require adicionado"
        ]);

        return self::SUCCESS;
    }

    /**
     * Check if Laravel 12 or higher.
     */
    protected function isLaravel12OrHigher(): bool
    {
        return version_compare(app()->version(), '12.0', '>=');
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn() => select(
                label: 'Qual tabela você deseja?',
                options: $this->getAllTableNames(),
            )
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     */
    protected function afterPromptingForMissingArguments($input, $output): void
    {
        // Frontend stack selection
        $this->template = select(
            label: 'Qual stack frontend deseja usar?',
            options: [
                'react' => 'React.js com Inertia.js (Recomendado)',
                'vue' => 'Vue.js com Inertia.js',
                'blade' => 'Blade tradicional',
            ],
            default: 'react',
            hint: 'React.js é o padrão para Laravel 12'
        );

        // Theme integration
        if ($this->template === 'react' && confirm('Deseja incluir sistema de temas dinâmicos?')) {
            $this->options['theme'] = true;

            if (!app('crud')->isThemeSystemInstalled()) {
                if (confirm('Sistema de temas não detectado. Instalar agora?')) {
                    $this->call('crud:install-theme-system');
                }
            }
        }

        // API generation
        if (confirm('Deseja gerar endpoints de API RESTful?')) {
            $this->options['api'] = true;
        }

        // Relationship logic
        if (confirm('Deseja estabelecer um relacionamento?')) {
            $relatedTable = select(
                label: 'Com qual tabela você deseja estabelecer o relacionamento?',
                options: $this->getAllTableNames($this->getNameInput()),
                hint: "Estabelecer relação da tabela {$this->getNameInput()}?",
            );
            $this->relationship = $relatedTable;
        }
    }

    /**
     * Build the Controller Class and save in app/Http/Controllers.
     */
    protected function buildController(): self
    {
        $controllerPath = $this->_getControllerPath($this->name);

        if ($this->files->exists($controllerPath) && !confirm(
            label: 'Este Controller já existe. Você quer sobrescrever?',
            default: false,
            hint: 'Mesmo que opte por não sobrescrever, o fluxo seguirá normalmente'
        )) {
            return $this;
        }

        info('Criando Controller...');

        $replace = $this->buildReplacements();
        $stubName = $this->template === 'react' ? 'InertiaController' : 'Controller';

        $controllerTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub($stubName)
        );

        $this->write($controllerPath, $controllerTemplate);

        return $this;
    }

    /**
     * Build API Controller.
     */
    protected function buildApiController(): self
    {
        $controllerPath = $this->_getApiControllerPath($this->name);

        if ($this->files->exists($controllerPath) && !confirm(
            label: 'Este API Controller já existe. Você quer sobrescrever?',
            default: false
        )) {
            return $this;
        }

        info('Criando API Controller...');

        $replace = $this->buildReplacements();

        $controllerTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('ApiController')
        );

        $this->write($controllerPath, $controllerTemplate);

        return $this;
    }

    /**
     * Build Model.
     */
    protected function buildModel(): self
    {
        $modelPath = $this->_getModelPath($this->name);

        if ($this->files->exists($modelPath) && !confirm(
            label: 'Esta Model já existe. Você quer sobrescrever?',
            default: false,
            hint: 'Mesmo que opte por não sobrescrever, o fluxo seguirá normalmente'
        )) {
            return $this;
        }

        info('Criando Model...');

        $relatedTablePlural = $this->relationship ? Str::camel($this->relationship) : '';
        $relatedTable = $this->relationship ? Str::studly(Str::singular($this->relationship)) : '';
        $relacao = $this->relationship ? $this->getRelations($relatedTablePlural, $relatedTable, 'relations') : '';

        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());
        $replace['{{relations}}'] = $relacao;

        $modelTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('Model')
        );

        $this->write($modelPath, $modelTemplate);

        return $this;
    }

    /**
     * Build Views/React Components.
     */
    protected function buildViews(): self
    {
        info("Criando componentes {$this->template}...");

        if ($this->template === 'react') {
            return $this->buildReactComponents();
        } elseif ($this->template === 'vue') {
            return $this->buildVueComponents();
        } else {
            return $this->buildBladeViews();
        }
    }

    /**
     * Build React Components.
     */
    protected function buildReactComponents(): self
    {
        $tableHead = $this->generateTableHeaders();
        $formFields = $this->generateFormFields();
        $showFields = $this->generateShowFields();

        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeaders}}' => $tableHead,
            '{{formFields}}' => $formFields,
            '{{showFields}}' => $showFields,
            '{{themeImports}}' => $this->option('theme') ? $this->getThemeImports() : '',
            '{{themeComponents}}' => $this->option('theme') ? $this->getThemeComponents() : '',
        ]);

        $components = ['Index', 'Create', 'Edit', 'Show', 'FormField'];

        foreach ($components as $component) {
            $componentPath = $this->_getReactComponentPath($component);

            $componentTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("react/{$component}")
            );

            $this->write($componentPath, $componentTemplate);
        }

        return $this;
    }

    /**
     * Build Router.
     */
    public function buildRouter(): self
    {
        info('Criando rotas...');

        // Create separate route file for this model
        $routeFileName = Str::lower($this->name) . '.php';
        $routePath = base_path("routes/{$routeFileName}");

        // Generate model-specific routes
        $stubContent = $this->getStub('ModelRoutes');
        $replacements = $this->buildReplacements();
        $stubContent = str_replace(array_keys($replacements), array_values($replacements), $stubContent);

        // Write the route file
        $this->write($routePath, $stubContent);

        // Add require to web.php
        $webPath = base_path('routes/web.php');
        $webContent = $this->files->get($webPath);

        $requireStatement = "require __DIR__ . '/{$routeFileName}';";

        // Check if require statement already exists
        if (strpos($webContent, $requireStatement) === false) {
            $newWebContent = $webContent . "\n" . $requireStatement;
            $this->files->put($webPath, $newWebContent);
        }

        info("Rotas criadas em: routes/{$routeFileName}");
        info("Require adicionado ao web.php");

        return $this;
    }

    /**
     * Build API Routes.
     */
    protected function buildApiRoutes(): self
    {
        if (!$this->option('api')) {
            return $this;
        }

        info('Criando rotas de API...');

        $apiPath = base_path('routes/api.php');

        if (!$this->files->exists($apiPath)) {
            $this->files->put($apiPath, "<?php\n\nuse Illuminate\Http\Request;\nuse Illuminate\Support\Facades\Route;\n\n");
        }

        $apiContent = $this->files->get($apiPath);
        $stubContent = $this->getStub('ApiRoutes');

        $replacements = $this->buildReplacements();
        $stubContent = str_replace(array_keys($replacements), array_values($replacements), $stubContent);

        $newApiContent = $apiContent . "\n" . $stubContent;
        $this->files->put($apiPath, $newApiContent);

        return $this;
    }

    /**
     * Build API Resources.
     */
    protected function buildApiResources(): self
    {
        info('Criando API Resources...');

        $resourcePath = $this->_getApiResourcePath($this->name);
        $collectionPath = $this->_getApiResourceCollectionPath($this->name);

        // Generate Resource
        $replace = $this->buildReplacements();
        $resourceTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('ApiResource')
        );
        $this->write($resourcePath, $resourceTemplate);

        // Generate Resource Collection
        $collectionTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('ApiResourceCollection')
        );
        $this->write($collectionPath, $collectionTemplate);

        return $this;
    }

    /**
     * Generate table headers for React components.
     */
    protected function generateTableHeaders(): string
    {
        $headers = [];
        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));
            $headers[] = "        <th className=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">\n            {$title}\n        </th>";
        }
        return implode("\n", $headers);
    }

    /**
     * Generate form fields for React components.
     */
    protected function generateFormFields(): string
    {
        $fields = [];
        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));
            $fields[] = $this->getReactFormField($title, $column);
        }
        return implode("\n", $fields);
    }

    /**
     * Generate show fields for React components.
     */
    protected function generateShowFields(): string
    {
        $fields = [];
        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));
            $fields[] = $this->getReactShowField($title, $column);
        }
        return implode("\n", $fields);
    }

    /**
     * Get React form field.
     */
    protected function getReactFormField(string $title, string $column): string
    {
        return <<<JSX
        <div className="mb-4">
            <label htmlFor="{$column}" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {$title}
            </label>
            <input
                type="text"
                id="{$column}"
                name="{$column}"
                value={data.{$column} || ''}
                onChange={(e) => setData('{$column}', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
            {errors.{$column} && <p className="mt-1 text-sm text-red-600">{errors.{$column}}</p>}
        </div>
JSX;
    }

    /**
     * Get React show field.
     */
    protected function getReactShowField(string $title, string $column): string
    {
        return <<<JSX
        <div className="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg mb-4">
            <div className="px-4 py-5 sm:p-6">
                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">{$title}</dt>
                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{{{{modelNameLowerCase}}.{$column}}}</dd>
            </div>
        </div>
JSX;
    }

    /**
     * Get theme imports for React components.
     */
    protected function getThemeImports(): string
    {
        return "import { useAppearance } from '@/hooks/use-appearance';\nimport ThemeSelector from '@/components/theme-selector';";
    }

    /**
     * Get theme components for React.
     */
    protected function getThemeComponents(): string
    {
        return <<<JSX
            <div className="mb-4 flex justify-end">
                <ThemeSelector />
            </div>
JSX;
    }

    /**
     * Get React component path.
     */
    protected function _getReactComponentPath(string $component): string
    {
        $name = Str::studly($this->name);
        return $this->makeDirectory(resource_path("js/Pages/{$name}/{$component}.tsx"));
    }

    /**
     * Get API controller path.
     */
    protected function _getApiControllerPath(string $name): string
    {
        return $this->makeDirectory(app_path("Http/Controllers/Api/{$name}Controller.php"));
    }

    /**
     * Get API resource path.
     */
    protected function _getApiResourcePath(string $name): string
    {
        return $this->makeDirectory(app_path("Http/Resources/{$name}Resource.php"));
    }

    /**
     * Get API resource collection path.
     */
    protected function _getApiResourceCollectionPath(string $name): string
    {
        return $this->makeDirectory(app_path("Http/Resources/{$name}Collection.php"));
    }

    /**
     * Make the class name from table name.
     */
    private function _buildClassName(): string
    {
        return Str::studly(Str::singular($this->table));
    }

    /**
     * Build Blade views (fallback).
     */
    protected function buildBladeViews(): self
    {
        // Implementation for Blade views...
        return $this;
    }

    /**
     * Build Vue components (future implementation).
     */
    protected function buildVueComponents(): self
    {
        // Implementation for Vue components...
        return $this;
    }

    /**
     * Build API Resource (required by GeneratorCommand).
     */
    protected function buildApiResource(): self
    {
        return $this->buildApiResources();
    }

    /**
     * Build Form Request (required by GeneratorCommand).
     */
    protected function buildFormRequest(): self
    {
        if (!$this->option('api')) {
            return $this;
        }

        info('Criando Form Request...');

        $requestPath = $this->_getFormRequestPath($this->name);

        if ($this->files->exists($requestPath) && !confirm(
            label: 'Este Form Request já existe. Você quer sobrescrever?',
            default: false
        )) {
            return $this;
        }

        $replace = $this->buildReplacements();
        $requestTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('FormRequest')
        );

        $this->write($requestPath, $requestTemplate);

        return $this;
    }

    /**
     * Build enhanced replacements for React components.
     */
    protected function buildReplacements()
    {
        $baseReplacements = parent::buildReplacements();

        // Add enhanced replacements for Laravel 12 + React
        $enhancedReplacements = [
            '{{fillableColumns}}' => $this->getJavaScriptFormFields(),
            '{{editFillableColumns}}' => $this->getJavaScriptEditFormFields(),
            '{{typeScriptColumns}}' => $this->getTypeScriptInterfaceFields(),
            '{{tableCells}}' => $this->getTableCells(),
            '{{showFieldsReact}}' => $this->getShowFieldsForReact(),
            '{{modelRoutePlural}}' => Str::kebab(Str::plural($this->name)),
            '{{modelTitlePlural}}' => Str::title(Str::snake(Str::plural($this->name), ' ')),
            '{{modelCamel}}' => Str::camel($this->name),
            '{{tableName}}' => $this->table,
        ];

        return array_merge($baseReplacements, $enhancedReplacements);
    }
    /**
     * Get Form Request path.
     */
    protected function _getFormRequestPath(string $name): string
    {
        return $this->makeDirectory(app_path("Http/Requests/{$name}Request.php"));
    }

    /**
     * Get filtered columns formatted for JavaScript useForm.
     */
    protected function getJavaScriptFormFields(bool $isEdit = false): string
    {
        $fillableFields = $this->getFilteredColumns();

        // Convert to JavaScript object format for useForm
        $jsFields = [];
        foreach ($fillableFields as $field) {
            if ($isEdit) {
                $jsFields[] = "            {$field}: {{modelCamel}}.{$field} || ''";
            } else {
                $jsFields[] = "            {$field}: ''";
            }
        }

        return implode(",\n", $jsFields);
    }

    /**
     * Get JavaScript form fields for edit forms.
     */
    protected function getJavaScriptEditFormFields(): string
    {
        return $this->getJavaScriptFormFields(true);
    }

    /**
     * Get TypeScript interface fields.
     */
    protected function getTypeScriptInterfaceFields(): string
    {
        $fillableFields = $this->getFilteredColumns();

        // Convert to TypeScript interface format
        $tsFields = [];
        foreach ($fillableFields as $field) {
            $tsFields[] = "    {$field}: string;";
        }

        return implode("\n", $tsFields);
    }

    /**
     * Get table cells for React components.
     */
    protected function getTableCells(): string
    {
        $fillableFields = $this->getFilteredColumns();

        // Convert to table cells format
        $cells = [];
        foreach ($fillableFields as $field) {
            $cells[] = "                                                <td className=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100\">\n                                                    {{{modelNameLowerCase}}.{$field}}\n                                                </td>";
        }

        return implode("\n", $cells);
    }

    /**
     * Get show fields for React components.
     */
    protected function getShowFieldsForReact(): string
    {
        $fillableFields = $this->getFilteredColumns();

        // Convert to show fields format
        $fields = [];
        foreach ($fillableFields as $field) {
            $label = Str::title(str_replace('_', ' ', $field));
            $fields[] = "                                    <div>\n                                        <dt className=\"text-sm font-medium text-gray-500 dark:text-gray-400\">\n                                            {$label}\n                                        </dt>\n                                        <dd className=\"mt-1 text-sm text-gray-900 dark:text-gray-100\">\n                                            {{{modelCamel}}.{$field} || '-'}\n                                        </dd>\n                                    </div>";
        }

        return implode("\n", $fields);
    }
}
