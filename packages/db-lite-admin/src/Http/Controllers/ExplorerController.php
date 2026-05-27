<?php

namespace DbLiteAdmin\Http\Controllers;

use DbLiteAdmin\Services\DynamicDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class ExplorerController extends Controller
{
    public function tables(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $databaseName = $databaseConnection->database_name;

        $tables = $connection->select(
            'SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name',
            [$databaseName]
        );

        $columns = $connection->select(
            'SELECT table_name, column_name, data_type, is_nullable, column_key
            FROM information_schema.columns
            WHERE table_schema = ?
            ORDER BY table_name, ordinal_position',
            [$databaseName]
        );

        $columnsByTable = collect($columns)->groupBy(function ($column) {
            return (string) $this->rowValue($column, ['table_name']);
        });

        $payload = collect($tables)->map(function ($table) use ($columnsByTable) {
            $tableName = (string) $this->rowValue($table, ['table_name']);
            $tableColumns = $columnsByTable->get($tableName, collect());

            return [
                'name' => $tableName,
                'estimated_rows' => (int) $this->rowValue($table, ['table_rows'], 0),
                'columns' => $this->formatColumns($tableColumns),
            ];
        })->filter(static fn (array $table) => $table['name'] !== '')->values();

        return response()->json([
            'database' => $databaseName,
            'tables' => $payload,
        ]);
    }

    public function columns(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $validated = $request->validate([
            'table' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $databaseName = $databaseConnection->database_name;

        $columns = $connection->select(
            'SELECT column_name, data_type, column_type, is_nullable, column_default, column_key, extra
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ?
            ORDER BY ordinal_position',
            [$databaseName, $validated['table']]
        );

        return response()->json([
            'table' => $validated['table'],
            'columns' => $this->formatColumns(collect($columns)),
        ]);
    }

    private function formatColumns(Collection $columns): array
    {
        return $columns->map(function ($column) {
            return [
                'name' => (string) $this->rowValue($column, ['column_name']),
                'type' => (string) $this->rowValue($column, ['column_type', 'data_type'], ''),
                'nullable' => strtoupper((string) $this->rowValue($column, ['is_nullable'], 'NO')) === 'YES',
                'key' => (string) $this->rowValue($column, ['column_key'], ''),
                'default' => $this->rowValue($column, ['column_default']),
                'extra' => $this->rowValue($column, ['extra']),
            ];
        })->filter(static fn (array $column) => $column['name'] !== '')->values()->all();
    }

    private function rowValue(object $row, array $keys, mixed $default = null): mixed
    {
        $values = (array) $row;

        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                return $values[$key];
            }

            $target = strtolower($key);
            foreach ($values as $column => $value) {
                if (strtolower((string) $column) === $target) {
                    return $value;
                }
            }
        }

        return $default;
    }
}
