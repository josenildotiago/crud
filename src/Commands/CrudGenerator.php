<?php

namespace Crud\Commands;

use Illuminate\Support\Str;


class CrudGenerator extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getic:crud
                            {name : Table name}
                            {--route= : Custom route name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Criar operações CRUD de bootstrap';

    /**
     * Execute the console command.
     *
     * @return bool|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    public function handle()
    {
        $this->info('Gerador de Crud da GETIC em execução ...');

        $this->table = $this->getNameInput();
        $this->nameTable = $this->table;

        // If table not exist in DB return
        if (!$this->tableExists()) {
            $this->error("`{$this->table}` tabela nao existe");

            return false;
        }

        // Build the class name from table name
        $this->name = $this->_buildClassName();

        // Generate the crud
        $this->buildOptions()
            ->buildController()
            ->buildModel()
            ->buildViews()
            ->buildRouter();

        $this->info('Criado com sucesso.');

        return true;
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

        if ($this->files->exists($controllerPath) && $this->ask('Já existe Controlador. Deseja substituir (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Criando Controller ...');

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

        if ($this->files->exists($modelPath) && $this->ask('Já existe Model. Você quer sobrescrever (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Criando Model ...');

        // Make the models attributes and replacement
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

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
        $this->info('Criando Views ...');

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

        foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
            $viewTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$view}")
            );

            $this->write($this->_getViewPath($view), $viewTemplate);
        }
        $this->info('View adicionadas com sucesso.');
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
        $this->info('Criando rotas ...');
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

        $this->info('Rotas adicionadas com sucesso.');
    }
}
