<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallOnlyServicesCommand extends Command
{
    protected $signature = 'crud:install-only-services';
    protected $description = 'Cria um Service em app/Services/{Nome}/{Nome}Service.php';

    public function handle()
    {
        // Perguntar se quer criar uma pasta específica
        $wantFolder = confirm('Deseja criar o Service dentro de uma pasta específica?');

        $folderName = null;
        if ($wantFolder) {
            $folderName = text(
                'Digite o nome da pasta (ex: Product, Post)',
                required: true,
                validate: function ($value) {
                    if (empty($value)) {
                        return 'O nome da pasta não pode ser vazio.';
                    }
                    if (preg_match('/\s/', $value)) {
                        return 'O nome da pasta não pode conter espaços.';
                    }
                    if (substr($value, 0, 1) === '-' || substr($value, -1) === '-') {
                        return 'O nome da pasta não pode começar ou terminar com traço.';
                    }
                    if (substr_count($value, '-') > 1) {
                        return 'Só é permitido um traço.';
                    }
                    if (preg_match('/-{2,}/', $value)) {
                        return 'Não pode haver traços duplos.';
                    }
                    return null;
                }
            );

            // Formatar nome da pasta para CamelCase
            $parts = explode('-', $folderName);
            $folderName = '';
            foreach ($parts as $part) {
                $folderName .= Str::studly(trim($part));
            }
        }

        // Perguntar qual model escolher
        $modelName = select(
            label: 'Qual modelo deseja usar para o Service?',
            options: $this->getAllTableNames(),
        );

        // Converter nome da tabela para nome do Model
        $modelClass = Str::studly(Str::singular($modelName));

        // Definir estrutura de pastas e nomes
        if ($folderName) {
            // Se escolheu pasta, usar pasta + service baseado na pasta
            $serviceDir = base_path("app/Services/{$folderName}");
            $serviceClass = "{$folderName}Service";
            $namespace = "App\\Services\\{$folderName}";
        } else {
            // Se não escolheu pasta, usar o nome do model
            $serviceDir = base_path("app/Services");
            $serviceClass = "{$modelClass}Service";
            $namespace = "App\\Services";
        }

        $serviceFile = "{$serviceDir}/{$serviceClass}.php";

        // Criar diretórios se não existirem
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0777, true);
        }

        // Não sobrescrever se já existir
        if (file_exists($serviceFile)) {
            $this->error("O arquivo {$serviceClass}.php já existe!");
            return 1;
        }

        // Carregar stub externo
        $stubPath = __DIR__ . '/../stubs/Service.stub';
        if (!file_exists($stubPath)) {
            $this->error('Stub Service.stub não encontrado!');
            return 1;
        }
        $stub = file_get_contents($stubPath);

        // Substituir variáveis no stub
        $stub = str_replace(
            [
                '{{serviceNamespace}}',
                '{{serviceClass}}',
                '{{modelClass}}',
                '{{modelName}}',
                '{{modelNameLowerCase}}'
            ],
            [
                $namespace,
                $serviceClass,
                $modelClass,
                $modelClass,
                Str::camel($modelClass)
            ],
            $stub
        );

        file_put_contents($serviceFile, $stub);

        $this->info("Service criado em: {$serviceFile}");
        return 0;
    }

    /**
     * Get all table names for model selection.
     */
    protected function getAllTableNames(): array
    {
        $tableNames = [];

        // Get all table names from the database
        $tablesInfo = DB::select('SHOW TABLES');

        foreach ($tablesInfo as $tableInfo) {
            $tableName = reset($tableInfo);
            $tableNames[$tableName] = Str::studly(Str::singular($tableName)) . " (tabela: {$tableName})";
        }

        return $tableNames;
    }
}
