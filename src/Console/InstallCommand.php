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

        // Adicionar temporariamente para debug
        $this->debugColumns();

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
            "React Components: resources/js/pages/{$this->name}/",
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
        $this->buildListComponent()->buildTypeScriptTypes();

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
     * Generate form fields for React components using shadcn/ui.
     */
    protected function generateFormFields(): string
    {
        $fields = [];
        foreach ($this->getFilteredColumns() as $column) {
            $label = Str::title(str_replace('_', ' ', $column));
            $placeholder = $this->generatePlaceholder($column, $label);

            $fieldTemplate = str_replace(
                ['{{column}}', '{{label}}', '{{placeholder}}'],
                [$column, $label, $placeholder],
                $this->getStub('react/FormFieldReact')
            );

            $fields[] = $fieldTemplate;
        }
        return implode("\n", $fields);
    }

    /**
     * Generate appropriate placeholder text for field.
     */
    protected function generatePlaceholder(string $column, string $label): string
    {
        // Generate smart placeholders based on field names
        $placeholders = [
            'name' => 'Digite o nome',
            'email' => 'exemplo@email.com',
            'phone' => '(11) 99999-9999',
            'description' => 'Digite a descrição',
            'title' => 'Digite o título',
            'address' => 'Digite o endereço',
            'city' => 'Digite a cidade',
            'state' => 'Digite o estado',
            'zip' => '00000-000',
            'website' => 'https://exemplo.com',
            'price' => '0,00',
            'quantity' => '0',
            'code' => 'Digite o código',
            'number' => 'Digite o número',
        ];

        // Check for exact matches
        if (isset($placeholders[$column])) {
            return $placeholders[$column];
        }

        // Check for partial matches
        foreach ($placeholders as $key => $placeholder) {
            if (strpos($column, $key) !== false) {
                return $placeholder;
            }
        }

        // Default placeholder
        return "Digite " . strtolower($label);
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
        $pagesPath = resource_path('js/pages');

        // Ensure the pages directory exists
        if (!$this->files->exists($pagesPath)) {
            $this->files->makeDirectory($pagesPath, 0755, true);
        }

        // Create the model-specific directory
        $modelPath = "{$pagesPath}/{$name}";
        if (!$this->files->exists($modelPath)) {
            $this->files->makeDirectory($modelPath, 0755, true);
        }

        return "{$modelPath}/{$component}.tsx";
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
     * Gera campos de busca dinâmicos para o controller
     */
    protected function getSearchableFields(): string
    {
        $columns = $this->getFilteredColumns();
        $searchFields = [];

        foreach ($columns as $column) {
            // Verificar se é array ou objeto e extrair o nome corretamente
            if (is_array($column)) {
                $columnName = $column['name'] ?? $column['Field'] ?? $column;
                $type = $column['type'] ?? $column['Type'] ?? 'varchar';
            } elseif (is_object($column)) {
                $columnName = $column->Field ?? $column->name ?? (string)$column;
                $type = $column->Type ?? $column->type ?? 'varchar';
            } else {
                // Se for string diretamente
                $columnName = (string)$column;
                $type = 'varchar'; // Assumir como texto se não tiver tipo
            }

            // Pular campos que não queremos na busca
            if (in_array(strtolower($columnName), ['id', 'created_at', 'updated_at', 'deleted_at', 'password'])) {
                continue;
            }

            // Apenas incluir campos de texto na busca
            if (
                str_contains(strtolower($type), 'varchar') ||
                str_contains(strtolower($type), 'text') ||
                str_contains(strtolower($type), 'char')
            ) {
                $searchFields[] = $columnName;
            }
        }

        // Limitar a 3 campos principais para não sobrecarregar
        $searchFields = array_slice($searchFields, 0, 3);

        if (empty($searchFields)) {
            return '                    $q->where("id", "like", "%{$search}%");';
        }

        $searchCode = [];
        foreach ($searchFields as $index => $field) {
            if ($index === 0) {
                $searchCode[] = "                    \$q->where('$field', 'like', \"%{\$search}%\")";
            } else {
                $searchCode[] = "                      ->orWhere('$field', 'like', \"%{\$search}%\")";
            }
        }

        return implode("\n", $searchCode) . ';';
    }

    /**
     * Gera placeholder para busca
     */
    protected function getSearchPlaceholder(): string
    {
        $columns = $this->getFilteredColumns();
        $searchFields = [];

        foreach ($columns as $column) {
            // Verificar se é array ou objeto e extrair o nome corretamente
            if (is_array($column)) {
                $columnName = $column['name'] ?? $column['Field'] ?? $column;
                $type = $column['type'] ?? $column['Type'] ?? 'varchar';
            } elseif (is_object($column)) {
                $columnName = $column->Field ?? $column->name ?? (string)$column;
                $type = $column->Type ?? $column->type ?? 'varchar';
            } else {
                // Se for string diretamente
                $columnName = (string)$column;
                $type = 'varchar'; // Assumir como texto se não tiver tipo
            }

            // Pular campos que não queremos na busca
            if (in_array(strtolower($columnName), ['id', 'created_at', 'updated_at', 'deleted_at', 'password'])) {
                continue;
            }

            // Apenas incluir campos de texto na busca
            if (
                str_contains(strtolower($type), 'varchar') ||
                str_contains(strtolower($type), 'text') ||
                str_contains(strtolower($type), 'char')
            ) {
                $searchFields[] = $this->formatFieldName($columnName);
            }
        }

        // Limitar a 3 campos principais
        $searchFields = array_slice($searchFields, 0, 3);

        if (empty($searchFields)) {
            return "Buscar...";
        }

        return "Buscar por " . implode(" ou ", $searchFields) . "...";
    }

    /**
     * Formata nome do campo para exibição
     */
    protected function formatFieldName(string $fieldName): string
    {
        // Converter snake_case para formato legível
        return str_replace('_', ' ', strtolower($fieldName));
    }

    /**
     * Gera cabeçalhos de tabela para o componente de listagem
     */
    protected function getTableHeadersForList(): string
    {
        $columns = $this->getFilteredColumns();
        $headers = [];

        foreach ($columns as $column) {
            // Verificar se é array ou objeto e extrair o nome corretamente
            if (is_array($column)) {
                $columnName = $column['name'] ?? $column['Field'] ?? $column;
            } elseif (is_object($column)) {
                $columnName = $column->Field ?? $column->name ?? (string)$column;
            } else {
                $columnName = (string)$column;
            }

            // Pular campos de sistema
            if (in_array(strtolower($columnName), ['id', 'created_at', 'updated_at', 'deleted_at', 'password'])) {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $columnName));
            $headers[] = "                            <TableHead>$label</TableHead>";
        }

        return implode("\n", $headers);
    }

    /**
     * Gera células de tabela para o componente de listagem
     */
    protected function getTableCellsForList(): string
    {
        $columns = $this->getFilteredColumns();
        $cells = [];

        foreach ($columns as $column) {
            // Verificar se é array ou objeto e extrair o nome corretamente
            if (is_array($column)) {
                $columnName = $column['name'] ?? $column['Field'] ?? $column;
            } elseif (is_object($column)) {
                $columnName = $column->Field ?? $column->name ?? (string)$column;
            } else {
                $columnName = (string)$column;
            }

            // Pular campos de sistema
            if (in_array(strtolower($columnName), ['id', 'created_at', 'updated_at', 'deleted_at', 'password'])) {
                continue;
            }

            $modelVar = strtolower($this->name);
            $cells[] = "                                    <TableCell>{{$modelVar}.{$columnName}}</TableCell>";
        }

        return implode("\n", $cells);
    }

    protected function buildTypeScriptTypes(): self
    {
        info('Criando tipos TypeScript...');

        $typesPath = resource_path('js/types/index.d.ts');

        if (!$this->files->exists(dirname($typesPath))) {
            $this->files->makeDirectory(dirname($typesPath), 0755, true);
        }

        $replace = $this->buildReplacements();

        // Get the stub content
        $typesContent = $this->getStub('react/Types');

        // Check if types file exists and handle duplications
        if ($this->files->exists($typesPath)) {
            $existingContent = $this->files->get($typesPath);

            // Check if Paginated2 already exists
            $hasPaginated2 = strpos($existingContent, 'export interface Paginated2<T>') !== false;

            // Check if the model interface already exists
            $modelName = $this->name;
            $hasModelInterface = strpos($existingContent, "export interface {$modelName}") !== false;

            // Remove Paginated2 from stub if it already exists
            if ($hasPaginated2) {
                $typesContent = $this->removePaginated2FromStub($typesContent);
            }

            // Handle model interface duplication
            if ($hasModelInterface) {
                $modelName = $this->getUniqueModelName($existingContent, $modelName);
                $replace['{{modelName}}'] = $modelName;
            }

            // Replace placeholders
            $newTypesContent = str_replace(
                array_keys($replace),
                array_values($replace),
                $typesContent
            );

            // Append new types to existing file
            $this->files->put($typesPath, $existingContent . "\n" . $newTypesContent);

            info("Tipos TypeScript adicionados ao arquivo existente: {$modelName}");
        } else {
            // Create new types file
            $newTypesContent = str_replace(
                array_keys($replace),
                array_values($replace),
                $typesContent
            );

            $this->write($typesPath, $newTypesContent);
            info("Arquivo de tipos TypeScript criado: {$this->name}");
        }

        return $this;
    }

    /**
     * Remove Paginated2 interface from stub content.
     */
    protected function removePaginated2FromStub(string $content): string
    {
        // Remove the Paginated2 interface block
        $pattern = '/export interface Paginated2<T> \{.*?\}\n\n/s';
        return preg_replace($pattern, '', $content);
    }

    /**
     * Get unique model name if interface already exists.
     */
    protected function getUniqueModelName(string $existingContent, string $modelName): string
    {
        $counter = 2;
        $newModelName = $modelName;

        while (strpos($existingContent, "export interface {$newModelName}") !== false) {
            $newModelName = $modelName . $counter;
            $counter++;
        }

        return $newModelName;
    }

    /**
     * Calcula colspan para tabela vazia
     */
    protected function getColSpan(): int
    {
        $columns = $this->getFilteredColumns();
        $visibleColumns = 0;

        foreach ($columns as $column) {
            // Verificar se é array ou objeto e extrair o nome corretamente
            if (is_array($column)) {
                $columnName = $column['name'] ?? $column['Field'] ?? $column;
            } elseif (is_object($column)) {
                $columnName = $column->Field ?? $column->name ?? (string)$column;
            } else {
                $columnName = (string)$column;
            }

            // Contar apenas colunas visíveis
            if (!in_array(strtolower($columnName), ['created_at', 'updated_at', 'deleted_at', 'password'])) {
                $visibleColumns++;
            }
        }

        return $visibleColumns + 1; // +1 para coluna de Ações
    }

    /**
     * Adicionar método de debug para verificar estrutura dos dados
     */
    protected function debugColumns(): void
    {
        $columns = $this->getFilteredColumns();

        $this->info("Debugando estrutura das colunas:");
        foreach ($columns as $index => $column) {
            $this->info("Coluna $index:");
            if (is_array($column)) {
                $this->info("  Tipo: Array");
                $this->info("  Dados: " . json_encode($column));
            } elseif (is_object($column)) {
                $this->info("  Tipo: Object");
                $this->info("  Propriedades: " . json_encode(get_object_vars($column)));
            } else {
                $this->info("  Tipo: " . gettype($column));
                $this->info("  Valor: $column");
            }
        }
    }

    protected function _getListComponentPath(string $name): string
    {
        $componentsPath = resource_path('js/components');

        if (!$this->files->exists($componentsPath)) {
            $this->files->makeDirectory($componentsPath, 0755, true);
        }

        return "{$componentsPath}/{$name}List.tsx";
    }

    /**
     * Constrói o componente de listagem
     */
    protected function buildListComponent(): self
    {
        $listPath = $this->_getReactComponentPath($this->name . 'List');
        $listPath = $this->_getListComponentPath($this->name);

        if ($this->files->exists($listPath) && !confirm(
            "O componente {$this->name}List já existe. Deseja sobrescrevê-lo?",
            default: false
        )) {
            return $this;
        }

        info("Criando componente de listagem {$this->name}List...");

        $replace = array_merge($this->buildReplacements(), [
            '{{searchPlaceholder}}' => $this->getSearchPlaceholder(),
            '{{tableHeaders}}' => $this->getTableHeadersForList(),
            '{{tableCells}}' => $this->getTableCellsForList(),
            '{{colSpan}}' => $this->getColSpan(),
        ]);

        $content = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('react/ModelList')
        );

        $this->write($listPath, $content);

        return $this;
    }

    /**
     * Get table headers for React components with shadcn/ui Table.
     */
    protected function getTableHeadersForIndex(): string
    {
        $headers = [];
        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));
            $headers[] = "                        <TableHead>{$title}</TableHead>";
        }
        return implode("\n", $headers);
    }

    /**
     * Get table cells for React components with shadcn/ui Table.
     */
    protected function getTableCellsForIndex(): string
    {
        $cells = [];
        $modelVarName = Str::camel($this->name);

        foreach ($this->getFilteredColumns() as $field) {
            $cells[] = "                                <TableCell>{{{$modelVarName}}.{$field}}</TableCell>";
        }

        return implode("\n", $cells);
    }

    /**
     * Get colspan for empty table message.
     */
    protected function getTableColSpan(): int
    {
        $filteredColumns = $this->getFilteredColumns();
        return count($filteredColumns) + 2; // +1 for checkbox, +1 for actions
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
            '{{tableCells}}' => $this->getTableCellsForIndex(), // Updated for Index
            '{{tableHeaders}}' => $this->getTableHeadersForIndex(), // Updated for Index
            '{{colSpan}}' => $this->getTableColSpan(),
            '{{showFieldsReact}}' => $this->getShowFieldsForReact(),
            '{{searchableFields}}' => $this->getSearchableFields(),
            '{{controllerFields}}' => $this->getControllerFieldsWithModel(),
            '{{modelTable}}' => $this->table,
            '{{modelRoutePlural}}' => Str::kebab(Str::plural($this->name)),
            '{{modelTitlePlural}}' => Str::title(Str::snake(Str::plural($this->name), ' ')),
            '{{modelCamel}}' => Str::camel($this->name),
            '{{tableName}}' => $this->table,
            '{{formFields}}' => $this->generateFormFields(), // For Create/Edit forms
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

    /**
     * Get controller field mappings for index method.
     */
    protected function getControllerFields(): string
    {
        $fillableFields = $this->getFilteredColumns();
        $modelVarName = '{{modelNameLowerCase}}';

        // Convert to controller field mappings
        $fields = [];
        foreach ($fillableFields as $field) {
            $fields[] = "                '{$field}' => \${$modelVarName}->{$field},";
        }

        return implode("\n", $fields);
    }

    /**
     * Get controller field mappings with resolved model name.
     */
    protected function getControllerFieldsWithModel(): string
    {
        $fillableFields = $this->getFilteredColumns();
        $modelVarName = Str::camel($this->name);

        // Convert to controller field mappings with resolved model name
        $fields = [];
        foreach ($fillableFields as $field) {
            $fields[] = "                '{$field}' => \${$modelVarName}->{$field},";
        }

        return implode("\n", $fields);
    }
}
