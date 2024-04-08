<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'getic:install')]
class InstallCommand extends GeneratorCommand implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getic:install {name : Table name}
                                            {--stack : name da stack}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria um simples template com base no banco de dados';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $this->table = $this->getNameInput();
        $this->stack = $this->template;
        $this->nameTable = $this->table;
        $this->nameStack = $this->stack;

        // If table not exist in DB return
        if (!$this->tableExists()) {
            $this->components->error("Esta tabela `{$this->table}` nao existe");

            return false;
        }

        $this->name = $this->_buildClassName();

        $this->components->info('Gerador de Crud da GETIC em execução');
        $this->buildOptions()
            ->buildController()
            ->buildModel()
            ->buildViews()
            ->buildRouter();
        $this->components->info('Criado com sucesso');
        return 1;
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return void
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        $process->run(function ($type, $line) {
            $this->output->write('    ' . $line);
        });
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => fn () => select(
                label: 'Qual tabela você deseja?',
                options: $this->getAllTableNames(),
            )
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        $input = select(
            label: 'Deseja algum Template?',
            options: [
                'heron' => "Padrão GETIC",
                'blade-bootstrap' => 'Blade com bootstrap',
                'blade-tailwind' => 'Blade com tailwind',
                'vue-bootstrap' => 'Vue com bootstrap',
                'vue-tailwind' => 'Vue com tailwind',
            ],
            default: 'heron',
            hint: 'GETIC é um bootstrap modificado por Heronildes'
        );
        $this->template = $input;

        // Lógica para relacionamento
        if ($input = confirm('Deseja estabelecer um relacionamento?')) {
            $relatedTable = select(
                label: 'Com qual tabela você deseja estabelecer o relacionamento?',
                options: $this->getAllTableNames($this->getNameInput()),
                hint: "Estabelecer relação da tabela {$this->getNameInput()}?",
            );
            // Aqui você pode manipular a tabela relacionada $relatedTable
            $this->relationship = $relatedTable;
        }
    }

    /**
     * Build the Controller Class and save in app/Http/Controllers.
     *
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildController()
    {
        $controllerPath = $this->_getControllerPath($this->name);

        if ($this->files->exists($controllerPath) && !confirm(
            label: 'Este Controlador já existe. Você quer sobrescrever?',
            default: false,
            hint: 'Mesmo que opte por não sobrescrever, o fluxo seguirá normalmente'
        )) {
            return $this;
        }

        $this->components->info('Criando Controller ...');

        $replace = $this->buildReplacements();

        $controllerTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('Controller')
        );

        $this->write($controllerPath, $controllerTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildModel()
    {
        $modelPath = $this->_getModelPath($this->name);

        if ($this->files->exists($modelPath) && !confirm(
            label: 'Esta Model já existe. Você quer sobrescrever?',
            default: false,
            hint: 'Mesmo que opte por não sobrescrever, o fluxo seguirá normalmente'
        )) {
            return $this;
        }

        $this->components->info('Criando Model ...');

        // Make the models attributes and replacement
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

        // Verificar se o relacionamento foi especificado pelo usuário
        if ($this->relationship) {
            // Gerar o código do relacionamento dinamicamente
            $relationshipCode = $this->generateRelationshipCode($this->relationship);
            // Adicionar o código do relacionamento ao array de substituições
            $replace['{{relations}}'] = $relationshipCode;
        } else {
            // Caso o relacionamento não tenha sido especificado, deixe o marcador de posição vazio
            $replace['{{relations}}'] = '';
        }

        $modelTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('Model')
        );

        $this->write($modelPath, $modelTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @throws \Exception
     */
    protected function buildViews()
    {
        $this->components->info("Criando Views $this->nameStack...");

        $tableHead = "\n";
        $tableBody = "\n";
        $viewRows = "\n";
        $form = "\n";

        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));

            $tableHead .= $this->getHead($title);
            $tableBody .= $this->getBody($column);
            $viewRows .= $this->getField($title, $column, 'view-field');
            $form .= $this->getField($title, $column, 'form-field');
        }

        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeader}}' => $tableHead,
            '{{tableBody}}' => $tableBody,
            '{{viewRows}}' => $viewRows,
            '{{form}}' => $form,
        ]);

        // $this->buildLayout();
        switch ($this->nameStack) {
            case 'heron':
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/heron/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
            case 'vue-tailwind':
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/vue-tailwind/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
            case 'vue-bootstrap':
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/vue-bootstrap/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
            case 'blade-bootstrap':
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/blade-bootstrap/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
            case 'blade-tailwind':
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/blade-tailwind/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
            default:
                foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
                    $viewTemplate = str_replace(
                        array_keys($replace),
                        array_values($replace),
                        $this->getStub("views/{$view}")
                    );

                    $this->write($this->_getViewPath($view), $viewTemplate);
                }
                break;
        }
        $this->components->info('View adicionadas com sucesso.');
        return $this;
    }

    /**
     * Make the class name from table name.
     *
     * @return string
     */
    private function _buildClassName()
    {
        return Str::studly(Str::singular($this->table));
    }

    /**
     * Build router.
     *
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    public function buildRouter()
    {
        $this->components->info('Criando rotas ...');
        $webPath = base_path('routes/web.php');

        // Caminho para o stub de rotas
        $stubPath = $this->getStub('routes', false); // Supondo que você tenha um método getStub que retorna o caminho do stub

        // Lê o conteúdo do arquivo web.php
        $webContent = $this->files->get($webPath);

        // Lê o conteúdo do stub de rotas
        $stubContent = $this->files->get($stubPath);

        // Substitui placeholders no stub de rotas com valores reais
        $replacements = $this->buildReplacements(); // Supondo que você tenha um método buildReplacements que retorna um array de substituições
        $stubContent = str_replace(array_keys($replacements), array_values($replacements), $stubContent);

        // Adiciona o conteúdo do stub de rotas ao final do conteúdo do arquivo web.php
        $newWebContent = $webContent . "\n" . $stubContent;

        // Escreve o novo conteúdo de volta ao arquivo web.php
        $this->files->put($webPath, $newWebContent);

        $this->components->info('Rotas adicionadas com sucesso.');
    }

    private function getSpace($no = 1)
    {
        $tabs = '';
        for ($i = 0; $i < $no; $i++) {
            $tabs .= "\t";
        }
        return $tabs;
    }

    // Método para gerar o código do relacionamento
    protected function generateRelationshipCode($relatedTable)
    {
        $relatedModel = Str::studly(Str::singular($relatedTable));
        $relationshipCode = "public function {$relatedTable}() {\n";
        $relationshipCode .=  $this->getSpace(1) . "\treturn \$this->belongsTo({$relatedModel}::class);\n";
        $relationshipCode .= "\t}\n";
        return $relationshipCode;
    }
}
