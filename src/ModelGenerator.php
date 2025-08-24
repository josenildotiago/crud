<?php

namespace Crud;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Builder;

/**
 * Class ModelGenerator.
 */
class ModelGenerator
{
    private ?string $functions = null;
    private string $table;
    private ?string $properties = null;
    private string $modelNamespace = 'App\Models';
    private string $connection;

    /**
     * ModelGenerator constructor.
     */
    public function __construct(string $table, string $properties, string $modelNamespace, ?string $connection = null)
    {
        $this->table = $table;
        $this->properties = $properties;
        $this->modelNamespace = $modelNamespace;
        $this->connection = $connection ?? config('database.default');
        $this->_init();
    }

    /**
     * Get all the eloquent relations.
     */
    public function getEloquentRelations(): array
    {
        return [$this->functions, $this->properties];
    }

    private function _init(): void
    {
        foreach ($this->_getTableRelations() as $relation) {
            if ($relation->ref) {
                $tableKeys = $this->_getTableKeys($relation->ref_table);
                $eloquent = $this->_getEloquent($relation, $tableKeys);
            } else {
                $eloquent = 'hasOne';
            }

            $this->functions .= $this->_getFunction($eloquent, $relation->ref_table, $relation->foreign_key, $relation->local_key);
        }
    }

    /**
     * Determine eloquent relationship type based on table keys.
     */
    private function _getEloquent($relation, $tableKeys): string
    {
        $eloquent = '';
        foreach ($tableKeys as $tableKey) {
            if ($relation->foreign_key == $tableKey->Column_name) {
                $eloquent = 'hasMany';

                if ($tableKey->Key_name == 'PRIMARY') {
                    $eloquent = 'hasOne';
                } elseif ($tableKey->Non_unique == 0 && $tableKey->Seq_in_index == 1) {
                    $eloquent = 'hasOne';
                }
            }
        }

        return $eloquent;
    }

    /**
     * Generate relationship function.
     */
    private function _getFunction(string $relation, string $table, string $foreign_key, string $local_key): string
    {
        list($model, $relationName) = $this->_getModelName($table, $relation);
        $relClass = ucfirst($relation);

        switch ($relation) {
            case 'hasOne':
                $this->properties .= "\n * @property $model $$relationName";
                break;
            case 'hasMany':
                $this->properties .= "\n * @property " . $model . "[] $$relationName";
                break;
        }

        return '
    /**
     * @return \Illuminate\Database\Eloquent\Relations\\' . $relClass . '
     */
    public function ' . $relationName . '()
    {
        return $this->' . $relation . '(\'' . $this->modelNamespace . '\\' . $model . '\', \'' . $foreign_key . '\', \'' . $local_key . '\');
    }
    ';
    }

    /**
     * Get the name relation and model.
     */
    private function _getModelName(string $name, string $relation): array
    {
        $class = Str::studly(Str::singular($name));
        $relationName = '';

        switch ($relation) {
            case 'hasOne':
                $relationName = Str::camel(Str::singular($name));
                break;
            case 'hasMany':
                $relationName = Str::camel(Str::plural($name));
                break;
        }

        return [$class, $relationName];
    }

    /**
     * Get all relations from Table with multi-database support.
     */
    private function _getTableRelations(): array
    {
        $driver = DB::connection($this->connection)->getDriverName();

        return match ($driver) {
            'mysql' => $this->_getMySQLRelations(),
            'pgsql' => $this->_getPostgreSQLRelations(),
            'sqlite' => $this->_getSQLiteRelations(),
            'sqlsrv' => $this->_getSQLServerRelations(),
            default => []
        };
    }

    /**
     * Get MySQL relations using INFORMATION_SCHEMA.
     */
    private function _getMySQLRelations(): array
    {
        $db = DB::connection($this->connection)->getDatabaseName();
        $sql = <<<SQL
SELECT TABLE_NAME ref_table, COLUMN_NAME foreign_key, REFERENCED_COLUMN_NAME local_key, '1' ref 
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
  WHERE REFERENCED_TABLE_NAME = ? AND TABLE_SCHEMA = ?
UNION
SELECT REFERENCED_TABLE_NAME ref_table, REFERENCED_COLUMN_NAME foreign_key, COLUMN_NAME local_key, '0' ref 
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
  WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL 

ORDER BY ref_table ASC
SQL;

        return DB::connection($this->connection)->select($sql, [$this->table, $db, $this->table, $db]);
    }

    /**
     * Get PostgreSQL relations using information_schema.
     */
    private function _getPostgreSQLRelations(): array
    {
        $schema = DB::connection($this->connection)->getConfig('schema') ?? 'public';
        $sql = <<<SQL
SELECT 
    tc.table_name as ref_table,
    kcu.column_name as foreign_key,
    ccu.column_name as local_key,
    '1' as ref
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' 
  AND ccu.table_name = ?
  AND tc.table_schema = ?

UNION

SELECT 
    ccu.table_name as ref_table,
    ccu.column_name as foreign_key,
    kcu.column_name as local_key,
    '0' as ref
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' 
  AND tc.table_name = ?
  AND tc.table_schema = ?

ORDER BY ref_table ASC
SQL;

        return DB::connection($this->connection)->select($sql, [$this->table, $schema, $this->table, $schema]);
    }

    /**
     * Get SQLite relations using pragma commands.
     */
    private function _getSQLiteRelations(): array
    {
        $relations = [];

        // Get foreign keys for this table
        $foreignKeys = DB::connection($this->connection)->select("PRAGMA foreign_key_list({$this->table})");

        foreach ($foreignKeys as $fk) {
            $relations[] = (object) [
                'ref_table' => $fk->table,
                'foreign_key' => $fk->to,
                'local_key' => $fk->from,
                'ref' => '0'
            ];
        }

        // Get tables that reference this table
        $tables = DB::connection($this->connection)->select("SELECT name FROM sqlite_master WHERE type='table'");

        foreach ($tables as $table) {
            if ($table->name === $this->table) continue;

            $fks = DB::connection($this->connection)->select("PRAGMA foreign_key_list({$table->name})");
            foreach ($fks as $fk) {
                if ($fk->table === $this->table) {
                    $relations[] = (object) [
                        'ref_table' => $table->name,
                        'foreign_key' => $fk->from,
                        'local_key' => $fk->to,
                        'ref' => '1'
                    ];
                }
            }
        }

        return $relations;
    }

    /**
     * Get SQL Server relations using sys tables.
     */
    private function _getSQLServerRelations(): array
    {
        $sql = <<<SQL
SELECT 
    OBJECT_NAME(fk.parent_object_id) as ref_table,
    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) as foreign_key,
    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) as local_key,
    '1' as ref
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
WHERE OBJECT_NAME(fkc.referenced_object_id) = ?

UNION

SELECT 
    OBJECT_NAME(fkc.referenced_object_id) as ref_table,
    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) as foreign_key,
    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) as local_key,
    '0' as ref
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
WHERE OBJECT_NAME(fkc.parent_object_id) = ?

ORDER BY ref_table ASC
SQL;

        return DB::connection($this->connection)->select($sql, [$this->table, $this->table]);
    }

    /**
     * Get all Keys from table with multi-database support.
     */
    private function _getTableKeys(string $table): array
    {
        $driver = DB::connection($this->connection)->getDriverName();

        return match ($driver) {
            'mysql' => DB::connection($this->connection)->select("SHOW KEYS FROM {$table}"),
            'pgsql' => $this->_getPostgreSQLKeys($table),
            'sqlite' => $this->_getSQLiteKeys($table),
            'sqlsrv' => $this->_getSQLServerKeys($table),
            default => []
        };
    }

    /**
     * Get PostgreSQL table keys.
     */
    private function _getPostgreSQLKeys(string $table): array
    {
        $sql = <<<SQL
SELECT 
    i.relname as "Key_name",
    a.attname as "Column_name",
    CASE WHEN i.indisprimary THEN 'PRIMARY' ELSE 'INDEX' END as "Key_type",
    CASE WHEN i.indisunique THEN 0 ELSE 1 END as "Non_unique",
    a.attnum as "Seq_in_index"
FROM pg_index i
JOIN pg_class t ON t.oid = i.indrelid
JOIN pg_class idx ON idx.oid = i.indexrelid
JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(i.indkey)
WHERE t.relname = ?
ORDER BY i.relname, a.attnum
SQL;

        return DB::connection($this->connection)->select($sql, [$table]);
    }

    /**
     * Get SQLite table keys.
     */
    private function _getSQLiteKeys(string $table): array
    {
        $indexes = DB::connection($this->connection)->select("PRAGMA index_list({$table})");
        $keys = [];

        foreach ($indexes as $index) {
            $indexInfo = DB::connection($this->connection)->select("PRAGMA index_info({$index->name})");
            foreach ($indexInfo as $info) {
                $keys[] = (object) [
                    'Key_name' => $index->name,
                    'Column_name' => $info->name,
                    'Non_unique' => $index->unique ? 0 : 1,
                    'Seq_in_index' => $info->seqno + 1
                ];
            }
        }

        return $keys;
    }

    /**
     * Get SQL Server table keys.
     */
    private function _getSQLServerKeys(string $table): array
    {
        $sql = <<<SQL
SELECT 
    i.name as "Key_name",
    c.name as "Column_name",
    CASE WHEN i.is_primary_key = 1 THEN 'PRIMARY' ELSE 'INDEX' END as "Key_type",
    CASE WHEN i.is_unique = 1 THEN 0 ELSE 1 END as "Non_unique",
    ic.key_ordinal as "Seq_in_index"
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID(?)
ORDER BY i.name, ic.key_ordinal
SQL;

        return DB::connection($this->connection)->select($sql, [$table]);
    }
}
