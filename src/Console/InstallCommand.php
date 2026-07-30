<?php

namespace Crud\Console;

use Composer\InstalledVersions;
use Crud\NavigationRegion;
use Crud\TableInspection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class InstallCommand extends GeneratorCommand implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'getic:install {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--theme : Include theme-aware components}';

    /**
     * The console command description.
     */
    protected $description = 'Cria um CRUD moderno com React.js e sistema de temas';

    /**
     * Stacks frontend aceitas pelo gerador.
     */
    protected const STACKS = ['react', 'vue', 'blade'];

    /**
     * Helpers de rota aceitos pelos componentes gerados.
     */
    protected const ROUTE_HELPERS = ['ziggy', 'wayfinder'];

    /**
     * Símbolos que cada componente React importa de `@/routes/{rota}` no modo wayfinder.
     *
     * @var array<string, array<int, string>>
     */
    protected const WAYFINDER_IMPORTS = [
        'Index' => ['index', 'create', 'show', 'edit', 'destroy', 'bulkDestroy'],
        'Create' => ['store'],
        'Edit' => ['index', 'update'],
        'Show' => ['index', 'edit', 'destroy'],
        'FormField' => [],
        'ModelList' => ['edit', 'destroy'],
    ];

    /**
     * Helper de rota resolvido para esta execução. Ziggy é o padrão histórico.
     */
    protected string $routeHelper = 'ziggy';

    /**
     * Quantos avisos o pré-voo levantou, para o resumo do fim da execução.
     */
    protected int $preflightWarnings = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table = $this->getNameInput();
        $this->nameTable = $this->table;

        // A stack escolhida no prompt interativo tem precedência; fora dele, vale --stack.
        $this->template ??= $this->option('stack');

        if (!in_array($this->template, self::STACKS, true)) {
            $this->components->error(
                "Stack `{$this->template}` inválida. Opções: " . implode(', ', self::STACKS)
            );
            return self::FAILURE;
        }

        if (!$this->resolveRouteHelper()) {
            return self::FAILURE;
        }

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

        if (!$this->preflightTable()) {
            return self::FAILURE;
        }

        $this->name = $this->_buildClassName();

        info("🚀 Gerador de CRUD Laravel 12 ({$this->template}) em execução...");

        // Generate components
        $this->buildOptions()
            ->buildController()
            ->buildModel()
            ->buildViews()
            ->buildRouter();

        $this->generateWayfinderRoutes();

        info('✅ CRUD criado com sucesso!');

        $generated = [
            "Controller: app/Http/Controllers/{$this->name}Controller.php",
            "Model: app/Models/{$this->name}.php",
        ];

        if ($this->template === 'react') {
            $generated[] = "React Components: resources/js/pages/{$this->name}/";
        }

        $generated[] = "Routes: routes/" . Str::lower($this->name) . ".php";
        $generated[] = "Web.php: Require adicionado";

        $this->components->info('Arquivos gerados:');
        $this->components->bulletList($generated);

        if ($this->preflightWarnings > 0) {
            warning(sprintf(
                'Gerado com %d %s sobre `%s` (acima). Revise antes de usar.',
                $this->preflightWarnings,
                $this->preflightWarnings === 1 ? 'aviso' : 'avisos',
                $this->table
            ));
        }

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
     * Avisa sobre a tabela antes de escrever qualquer arquivo.
     *
     * Avisa, não bloqueia: a tabela é do usuário, e gerar em cima de uma tabela torta
     * para ajustar o Model à mão depois é caso legítimo. Devolve `false` só quando a
     * pessoa responde que não quer seguir.
     */
    protected function preflightTable(): bool
    {
        $findings = (new TableInspection())->inspect($this->getColumns());

        if ($findings === []) {
            return true;
        }

        $this->preflightWarnings = count($findings);

        warning(sprintf(
            '%d %s sobre a tabela `%s`:',
            $this->preflightWarnings,
            $this->preflightWarnings === 1 ? 'aviso' : 'avisos',
            $this->table
        ));

        foreach ($findings as $finding) {
            $this->line('   • ' . $this->preflightMessage($finding));
        }

        // Sem terminal interativo não há como confirmar, e a postura é não bloquear:
        // segue, mas dizendo que segue por conta e risco de quem chamou.
        if (!$this->input->isInteractive()) {
            $this->line('   Modo não interativo: seguindo por sua conta e risco.');

            return true;
        }

        if (confirm('Gerar mesmo assim?', default: true)) {
            return true;
        }

        info('Geração cancelada.');

        return false;
    }

    /**
     * Frase em português para cada achado do pré-voo.
     *
     * @param array{code: string, columns: array<int, string>} $finding
     */
    protected function preflightMessage(array $finding): string
    {
        return match ($finding['code']) {
            'timestamps' => 'Sem `created_at`/`updated_at`: a listagem gerada ordena por `created_at` e vai falhar no banco.',
            'primary-key-missing' => 'Sem chave primária declarada: o `Index` usa `id` para a key da linha e para os links de ver/editar/excluir, e ele vai vir nulo.',
            'primary-key-not-id' => sprintf(
                'A chave primária é `%s`, não `id`: o Model gerado não declara `$primaryKey`, então `id` vai vir nulo no `Index`.',
                $finding['columns'][0]
            ),
            'column-identifier' => count($finding['columns']) === 1
                ? sprintf(
                    'A coluna `%s` não é um identificador válido: o Controller e o tipo TypeScript gerados não vão compilar.',
                    $finding['columns'][0]
                )
                : sprintf(
                    'As colunas %s não são identificadores válidos: o Controller e o tipo TypeScript gerados não vão compilar.',
                    implode(', ', array_map(static fn (string $column): string => "`{$column}`", $finding['columns']))
                ),
        };
    }

    /**
     * Define como os componentes gerados resolvem URLs de rota.
     *
     * Precedência: --routes > config('crud.inertia.route_helper') > detecção automática.
     */
    protected function resolveRouteHelper(): bool
    {
        $flag = $this->input->hasOption('routes') ? (string) $this->option('routes') : '';

        if ($flag !== '') {
            if (!in_array($flag, self::ROUTE_HELPERS, true)) {
                $this->components->error(
                    "Helper de rotas `{$flag}` inválido. Opções: " . implode(', ', self::ROUTE_HELPERS)
                );

                return false;
            }

            $this->routeHelper = $flag;

            return true;
        }

        $configured = (string) config('crud.inertia.route_helper', 'auto');

        if (!in_array($configured, ['auto', ...self::ROUTE_HELPERS], true)) {
            $this->components->error(
                "crud.inertia.route_helper `{$configured}` inválido. Opções: auto, "
                    . implode(', ', self::ROUTE_HELPERS)
            );

            return false;
        }

        $this->routeHelper = $configured === 'auto'
            ? ($this->wayfinderIsInstalled() ? 'wayfinder' : 'ziggy')
            : $configured;

        return true;
    }

    /**
     * O pacote laravel/wayfinder está instalado no projeto do usuário?
     */
    protected function wayfinderIsInstalled(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('laravel/wayfinder');
    }

    /**
     * Está gerando componentes que importam funções de rota do wayfinder?
     */
    protected function usesWayfinder(): bool
    {
        return $this->routeHelper === 'wayfinder';
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
        // Frontend stack selection — --stack explícito na linha de comando vence o prompt.
        if ($input->hasParameterOption('--stack')) {
            $this->template = $this->option('stack');
        } else {
            $this->template = select(
                label: 'Qual stack frontend deseja usar?',
                options: [
                    'react' => 'React.js com Inertia.js (Recomendado)',
                    'vue' => 'Vue.js com Inertia.js',
                    'blade' => 'Blade tradicional',
                ],
                default: $this->option('stack'),
                hint: 'React.js é o padrão para Laravel 12'
            );
        }

        // Theme integration
        if ($this->template === 'react' && confirm('Deseja incluir sistema de temas dinâmicos?')) {
            $this->options['theme'] = true;

            if (!app('crud')->isThemeSystemInstalled()) {
                if (confirm('Sistema de temas não detectado. Instalar agora?')) {
                    $this->call('crud:install-theme-system');
                }
            }
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
        $this->buildListComponent()->buildTypeScriptTypes()->buildUiComponents()->buildSidebarNavigation();

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

            // Cada componente importa só as funções de rota que usa.
            $replace['{{routeImports}}'] = $this->getRouteImports($component);

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
     * Opções do `wayfinder:generate` que preservam o formato já usado pelo app.
     *
     * O comando reescreve `resources/js/routes` inteiro, não apenas o recurso gerado.
     * Rodá-lo sem `--with-form` num app que gera com `formVariants: true` — o default
     * do vite.config.ts dos starter kits — apaga os `x.form = xForm` das rotas do
     * próprio starter kit, e as páginas de auth/settings dele param de compilar com
     * `Property 'form' does not exist`. Gerar um CRUD não pode estreitar a saída
     * existente, então o formato é lido do que está lá.
     *
     * A varredura cobre os subdiretórios: o wayfinder distribui as rotas por namespace,
     * e olhar só o `index.ts` da raiz daria falso negativo num app cujas rotas de raiz
     * não tenham variante de form.
     *
     * @return array<int, string>
     */
    protected function wayfinderArguments(): array
    {
        $dir = resource_path('js/routes');

        if (!$this->files->isDirectory($dir)) {
            return [];
        }

        foreach ($this->files->allFiles($dir) as $file) {
            if ($file->getExtension() !== 'ts') {
                continue;
            }

            if (preg_match('/^\s*\w+\.form\s*=/m', $this->files->get($file->getPathname())) === 1) {
                return ['--with-form'];
            }
        }

        return [];
    }

    /**
     * Regenera `resources/js/routes` para que os imports `@/routes/{rota}` existam.
     *
     * O require recém-acrescentado ao web.php não entra no roteador já carregado
     * deste processo, então o wayfinder roda num processo novo. Sem isso, quem
     * usa só o Artisan (sem `npm run dev`) fica com import quebrado.
     */
    protected function generateWayfinderRoutes(): self
    {
        if ($this->template !== 'react' || !$this->usesWayfinder()) {
            return $this;
        }

        $application = $this->getApplication();

        if (!$application || !$application->has('wayfinder:generate')) {
            warning(
                'Componentes gerados importam de `@/routes/` mas o comando `wayfinder:generate` '
                    . 'não foi encontrado. Instale laravel/wayfinder ou use --routes=ziggy.'
            );

            return $this;
        }

        info('Gerando rotas TypeScript (wayfinder)...');

        $artisan = base_path('artisan');

        $process = new Process(
            [PHP_BINARY, $artisan, 'wayfinder:generate', ...$this->wayfinderArguments()],
            base_path()
        );
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            warning(
                'Falha ao executar `wayfinder:generate`. Rode manualmente antes do build: '
                    . 'php artisan wayfinder:generate'
            );
        }

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
                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{{{modelNameLowerCase}}.{$column}}</dd>
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
     * Make the class name from table name.
     */
    private function _buildClassName(): string
    {
        return Str::studly(Str::singular($this->table));
    }

    /**
     * Build Blade views (ainda não implementado).
     */
    protected function buildBladeViews(): self
    {
        $this->components->warn(
            'A stack `blade` ainda não gera views — nenhum arquivo de view foi criado.'
        );

        return $this;
    }

    /**
     * Build Vue components (ainda não implementado).
     */
    protected function buildVueComponents(): self
    {
        $this->components->warn(
            'A stack `vue` ainda não gera componentes — nenhum arquivo de view foi criado.'
        );

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

        $typesDir = resource_path('js/types');

        if (!$this->files->exists($typesDir)) {
            $this->files->makeDirectory($typesDir, 0755, true);
        }

        // A partir do Laravel 13 o starter kit organiza resources/js/types como um
        // barrel: index.ts reexportando auth.ts, navigation.ts, ui.ts... Nesse layout
        // o index.d.ts que escrevíamos nunca era lido, porque o TypeScript resolve
        // index.ts antes de index.d.ts.
        if ($this->files->exists($typesDir . '/index.ts')) {
            return $this->buildTypeScriptTypesBarrel($typesDir);
        }

        return $this->buildTypeScriptTypesLegacy($typesDir . '/index.d.ts');
    }

    /**
     * Layout Laravel 13: um arquivo de tipos por model ao lado do barrel index.ts.
     */
    protected function buildTypeScriptTypesBarrel(string $typesDir): self
    {
        $barrelPath = $typesDir . '/index.ts';
        $fileName = $this->_getTypeFileName();

        // Paginated<T> é compartilhado por todos os models: escrito uma única vez.
        $paginatedPath = $typesDir . '/paginated.ts';

        if (!$this->files->exists($paginatedPath)) {
            $this->write($paginatedPath, $this->getStubOrPackageDefault('react/TypesPaginated'));
            info('Tipo compartilhado criado: resources/js/types/paginated.ts');
        }

        $this->registerTypeInBarrel($barrelPath, 'paginated');

        $modelPath = $typesDir . "/{$fileName}.ts";

        // Regerar a mesma tabela substitui o arquivo — nada de {Model}2, {Model}3.
        if ($this->files->exists($modelPath) && !confirm(
            label: "O arquivo resources/js/types/{$fileName}.ts já existe. Deseja sobrescrevê-lo?",
            default: true,
            hint: 'O conteúdo é derivado do schema; sobrescrever mantém os tipos em dia'
        )) {
            $this->registerTypeInBarrel($barrelPath, $fileName);

            return $this;
        }

        $replace = $this->buildReplacements();

        $this->write($modelPath, str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStubOrPackageDefault('react/TypesModel')
        ));

        $this->registerTypeInBarrel($barrelPath, $fileName);

        info("Tipos TypeScript criados: resources/js/types/{$fileName}.ts");

        return $this;
    }

    /**
     * Layout Laravel 12: arquivo único resources/js/types/index.d.ts.
     */
    protected function buildTypeScriptTypesLegacy(string $typesPath): self
    {
        $replace = $this->buildReplacements();
        $typesContent = $this->getStub('react/Types');

        if (!$this->files->exists($typesPath)) {
            $this->write($typesPath, str_replace(
                array_keys($replace),
                array_values($replace),
                $typesContent
            ));

            info("Arquivo de tipos TypeScript criado: {$this->name}");

            return $this;
        }

        $existingContent = $this->files->get($typesPath);

        // Paginated<T> só entra uma vez no arquivo.
        if (str_contains($existingContent, 'export interface Paginated<T>')) {
            $typesContent = $this->removePaginatedFromStub($typesContent);
        }

        // Regerar a mesma tabela substitui o bloco antigo. Antes a interface nova era
        // renomeada para {Model}2, {Model}3... e nenhum componente importava esse nome.
        if (str_contains($existingContent, "export interface {$this->name} {")) {
            if (!confirm(
                label: "A interface {$this->name} já existe em resources/js/types/index.d.ts. Deseja sobrescrevê-la?",
                default: true,
                hint: 'O conteúdo é derivado do schema; sobrescrever mantém os tipos em dia'
            )) {
                return $this;
            }

            $cleanedContent = $this->removeModelTypesFromContent($existingContent, $this->name);

            if ($cleanedContent === $existingContent) {
                warning("Não foi possível localizar o bloco de {$this->name} para substituir. Arquivo mantido como está.");

                return $this;
            }

            $existingContent = $cleanedContent;
        }

        $newTypesContent = str_replace(
            array_keys($replace),
            array_values($replace),
            $typesContent
        );

        $this->files->put($typesPath, rtrim($existingContent, "\n") . "\n\n" . $newTypesContent);

        info("Tipos TypeScript atualizados no arquivo existente: {$this->name}");

        return $this;
    }

    /**
     * Registra o arquivo de tipos no barrel resources/js/types/index.ts.
     *
     * Idempotente: rodar o gerador de novo não duplica a linha — mesmo cuidado que
     * buildRouter() toma com o require do web.php.
     */
    protected function registerTypeInBarrel(string $barrelPath, string $fileName): void
    {
        $barrelContent = $this->files->get($barrelPath);

        if (preg_match('#from\s+[\'"]\./' . preg_quote($fileName, '#') . '[\'"]#', $barrelContent)) {
            return;
        }

        $export = "export type * from './{$fileName}';";

        $this->files->put($barrelPath, rtrim($barrelContent, "\n") . "\n" . $export . "\n");

        info("Registrado em resources/js/types/index.ts: {$export}");
    }

    /**
     * Identificador do ícone dentro do arquivo do usuário.
     *
     * O import é apelidado em vez de trazer `List` direto: num app que já importa
     * `List` do lucide-react, uma segunda ligação do mesmo identificador não é
     * duplicação cosmética de import — é erro de compilação em TS e SyntaxError em
     * ES modules, ou seja, o build do usuário para. O nome prefixado torna a colisão
     * improvável por construção.
     */
    protected const NAV_ICON = 'CrudNavIcon';

    /**
     * Arquivo de navegação e âncora de cada stack.
     *
     * O comentário acompanha a sintaxe do arquivo. Só a react está mapeada; as demais
     * entram junto com a implementação do respectivo builder.
     *
     * @var array<string, array{file: string, open: string, start: string, end: string, import: string}>
     */
    protected const SIDEBARS = [
        'react' => [
            'file' => 'js/components/app-sidebar.tsx',
            'open' => '/^const mainNavItems\s*:/',
            'start' => '// crud:nav:start',
            'end' => '// crud:nav:end',
            'import' => "import { List as " . self::NAV_ICON . " } from 'lucide-react';",
        ],
    ];

    /**
     * Insere o link do CRUD gerado na sidebar do projeto.
     *
     * Nunca aborta a geração: qualquer impedimento vira aviso mais o trecho para o
     * usuário colar onde quiser.
     */
    protected function buildSidebarNavigation(): self
    {
        if (!config('crud.navigation.sidebar', true)) {
            return $this;
        }

        $config = self::SIDEBARS[$this->template] ?? null;

        if ($config === null) {
            return $this;
        }

        $path = resource_path($config['file']);
        $item = sprintf(
            "{ title: '%s', href: '/%s', icon: %s },",
            Str::title(Str::snake(Str::plural($this->name), ' ')),
            $this->routeSegment(),
            self::NAV_ICON
        );

        if (!$this->files->exists($path)) {
            warning("Sidebar não encontrada em {$config['file']}. Adicione o item à mão:");
            $this->line($item);

            return $this;
        }

        $region = new NavigationRegion($config['start'], $config['end']);
        $content = $this->files->get($path);
        $key = sprintf("'/%s'", $this->routeSegment());

        if (!str_contains($content, $config['start'])) {
            if (!confirm('Adicionar o link na sidebar?', default: true)) {
                $this->line($item);

                return $this;
            }

            $installed = $region->install($content, $config['open'], $config['import']);

            if ($installed === null) {
                warning('Não consegui localizar a navegação em ' . $config['file'] . '. Adicione o item à mão:');
                $this->line($item);

                return $this;
            }

            $content = $installed;
        }

        $updated = $region->upsert($content, $key, $item, $config['import']);

        if ($updated === null) {
            warning($region->hasMultilineItem($content)
                ? 'O item da navegação em ' . $config['file'] . ' foi reformatado em várias'
                    . ' linhas e não dá para atualizá-lo com segurança. Nada foi alterado;'
                    . ' ajuste a linha à mão:'
                : 'Marcadores de navegação malformados em ' . $config['file'] . '. Nada foi alterado.');
            $this->line($item);

            return $this;
        }

        $this->write($path, $updated);
        info('Link adicionado à sidebar.');

        return $this;
    }

    /**
     * Componentes shadcn/ui que os stubs react importam mas que os starter kits
     * do Laravel não trazem instalados.
     *
     * Ambos são copiados do próprio pacote em vez de exigirem `shadcn add`, porque
     * nenhum dos dois precisa de dependência npm nova: `table` usa só `cn`, e
     * `pagination` usa `buttonVariants` do ui/button mais ícones do lucide-react.
     */
    protected const UI_COMPONENTS = ['table', 'pagination'];

    /**
     * Instala os componentes shadcn/ui que faltam no app do usuário.
     *
     * Só escreve o que não existe: o usuário pode ter customizado o table.tsx dele,
     * e um CRUD novo não é motivo para sobrescrever componente compartilhado.
     */
    protected function buildUiComponents(): self
    {
        $uiDir = resource_path('js/components/ui');

        if (!$this->files->exists($uiDir)) {
            $this->files->makeDirectory($uiDir, 0755, true);
        }

        $installed = [];

        foreach (self::UI_COMPONENTS as $component) {
            $path = $uiDir . "/{$component}.tsx";

            if ($this->files->exists($path)) {
                continue;
            }

            $this->write($path, $this->getStubOrPackageDefault("react/ui/{$component}.tsx"));
            $installed[] = $component;
        }

        if ($installed !== []) {
            info('Componentes shadcn/ui instalados: ' . implode(', ', $installed));
        }

        return $this;
    }

    /**
     * Nome do arquivo de tipos do model, em kebab-case (ex.: ordem-servico).
     */
    protected function _getTypeFileName(): string
    {
        return Str::kebab($this->name);
    }

    /**
     * Lê um stub respeitando crud.stub_path, com fallback para os stubs do pacote.
     *
     * Necessário para stubs novos: quem configurou um stub_path customizado antes
     * deles existirem não teria o arquivo, e getStub() estouraria.
     */
    protected function getStubOrPackageDefault(string $type): string
    {
        $path = $this->getStub($type, false);

        if ($this->files->exists($path)) {
            return $this->files->get($path);
        }

        return $this->files->get(__DIR__ . "/../stubs/{$type}.stub");
    }

    /**
     * Remove Paginated interface from stub content.
     */
    protected function removePaginatedFromStub(string $content): string
    {
        // Remove the Paginated interface block
        $pattern = '/export interface Paginated<T> \{.*?\}\n\n/s';
        return preg_replace($pattern, '', $content);
    }

    /**
     * Remove a interface do model e o alias de paginação gerados anteriormente.
     *
     * As interfaces geradas não têm chaves aninhadas, então [^}]* delimita o bloco.
     */
    protected function removeModelTypesFromContent(string $content, string $modelName): string
    {
        $name = preg_quote($modelName, '#');

        $content = preg_replace('#export interface ' . $name . ' \{[^}]*\}\n*#', '', $content);
        $content = preg_replace('#export type Paginated' . $name . 'List = Paginated<' . $name . '>;\n*#', '', $content);

        return $content;
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
            '{{routeImports}}' => $this->getRouteImports('ModelList'),
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
            $cells[] = "                                <TableCell>{{$modelVarName}.{$field}}</TableCell>";
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
            // Mesmo segmento das rotas: é com este placeholder que os breadcrumbs dos
            // componentes react são escritos, e recalculá-lo aqui fazia `--route` mover
            // as rotas e deixar o breadcrumb apontando para a URL antiga.
            '{{modelRoutePlural}}' => $this->routeSegment(),
            '{{modelTitlePlural}}' => Str::title(Str::snake(Str::plural($this->name), ' ')),
            '{{modelCamel}}' => Str::camel($this->name),
            '{{tableName}}' => $this->table,
            '{{formFields}}' => $this->generateFormFields(), // For Create/Edit forms
        ];

        return array_merge(
            $baseReplacements,
            $enhancedReplacements,
            $this->navigationReplacements($baseReplacements['{{modelRoute}}'])
        );
    }

    /**
     * Expressões de navegação dos componentes React.
     *
     * Um único conjunto de stubs atende os dois helpers: o stub carrega o
     * placeholder, este mapa decide se ele vira `route('x.show', y.id)` (ziggy)
     * ou `showRoute(y.id)` (wayfinder).
     *
     * @return array<string, string>
     */
    protected function navigationReplacements(string $route): array
    {
        // Mesmo valor de {{modelNameLowerCase}} e {{modelCamel}}: a variável do
        // registro dentro do componente.
        $model = Str::camel($this->name);

        if (!$this->usesWayfinder()) {
            return [
                '{{routeImports}}' => '',
                '{{routeIndex}}' => "route('{$route}.index')",
                '{{routeCreate}}' => "route('{$route}.create')",
                '{{routeBulkDestroy}}' => "route('{$route}.bulk-destroy')",
                '{{routeShowModel}}' => "route('{$route}.show', {$model}.id)",
                '{{routeEditModel}}' => "route('{$route}.edit', {$model}.id)",
                '{{routeDestroyModel}}' => "route('{$route}.destroy', {$model}.id)",
                '{{routeDestroyId}}' => "route('{$route}.destroy', id)",
                '{{formStoreMethod}}' => 'post',
                '{{formStoreCall}}' => "post(route('{$route}.store'))",
                '{{formUpdateMethod}}' => 'put',
                '{{formUpdateCall}}' => "put(route('{$route}.update', {$model}.id))",
            ];
        }

        // O sufixo Route evita colisão com identificadores locais dos componentes:
        // ModelList recebe uma prop booleana `edit` e Index tem um parâmetro `index`
        // no map da paginação.
        return [
            // Sobrescrito por componente em buildReactComponents()/buildListComponent().
            '{{routeImports}}' => '',
            '{{routeIndex}}' => 'indexRoute()',
            '{{routeCreate}}' => 'createRoute()',
            '{{routeBulkDestroy}}' => 'bulkDestroyRoute()',
            '{{routeShowModel}}' => "showRoute({$model}.id)",
            '{{routeEditModel}}' => "editRoute({$model}.id)",
            '{{routeDestroyModel}}' => "destroyRoute({$model}.id)",
            '{{routeDestroyId}}' => 'destroyRoute(id)',
            // useForm().post()/put() só aceitam string; submit() aceita o
            // { url, method } que o wayfinder devolve.
            '{{formStoreMethod}}' => 'submit',
            '{{formStoreCall}}' => 'submit(storeRoute())',
            '{{formUpdateMethod}}' => 'submit',
            '{{formUpdateCall}}' => "submit(updateRoute({$model}.id))",
        ];
    }

    /**
     * Linha de import das funções de rota do wayfinder para um componente.
     *
     * Vem colada ao fim do último import do stub, então precisa trazer o próprio
     * "\n". Assim o modo ziggy devolve string vazia sem deixar linha em branco.
     */
    protected function getRouteImports(string $component): string
    {
        if (!$this->usesWayfinder()) {
            return '';
        }

        $symbols = self::WAYFINDER_IMPORTS[$component] ?? [];

        if ($symbols === []) {
            return '';
        }

        $aliased = array_map(
            fn(string $symbol): string => "{$symbol} as {$symbol}Route",
            $symbols
        );

        return "\nimport { " . implode(', ', $aliased) . " } from '@/routes/{$this->routeSegment()}';";
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
