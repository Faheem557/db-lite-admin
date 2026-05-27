<?php

namespace DbLiteAdmin\Http\Controllers;

use DbLiteAdmin\Services\DynamicDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableController extends Controller
{
    public function preview(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $validated = $request->validate([
            'table' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $table = $validated['table'];
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 50;
        $offset = ($page - 1) * $perPage;
        $quotedTable = $this->quoteIdentifier($table);

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $countResult = $connection->selectOne("SELECT COUNT(*) AS aggregate FROM {$quotedTable}");
        $totalRows = (int) ($countResult->aggregate ?? 0);

        $rows = array_map(
            static fn ($row) => (array) $row,
            $connection->select("SELECT * FROM {$quotedTable} LIMIT {$perPage} OFFSET {$offset}")
        );

        $columns = ! empty($rows) ? array_keys($rows[0]) : [];

        return response()->json([
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => max(1, (int) ceil($totalRows / $perPage)),
            ],
        ]);
    }

    public function create(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $validated = $request->validate([
            'table_name' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.name' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'columns.*.type' => ['required', 'string', Rule::in($this->allowedTypes())],
            'columns.*.length' => ['nullable', 'integer', 'between:1,65535'],
            'columns.*.scale' => ['nullable', 'integer', 'between:0,30'],
            'columns.*.nullable' => ['nullable', 'boolean'],
            'columns.*.default' => ['nullable', 'string', 'max:255'],
            'columns.*.primary' => ['nullable', 'boolean'],
            'columns.*.auto_increment' => ['nullable', 'boolean'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $tableName = $validated['table_name'];
        $quotedTable = $this->quoteIdentifier($tableName);
        $primaryKeys = [];
        $columnSql = [];

        foreach ($validated['columns'] as $column) {
            $columnSql[] = $this->compileColumnDefinition($connection, $column, $primaryKeys);
        }

        if (! empty($primaryKeys)) {
            $primaryColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $primaryKeys));
            $columnSql[] = "PRIMARY KEY ({$primaryColumns})";
        }

        $createSql = "CREATE TABLE {$quotedTable} (".implode(', ', $columnSql).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $connection->statement($createSql);

        return response()->json([
            'message' => "Table {$tableName} created successfully.",
        ], 201);
    }

    public function drop(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table)
    {
        $validated = $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        $this->assertIdentifier($table);

        if ($validated['confirm_name'] !== $table) {
            return response()->json([
                'message' => 'Confirmation name does not match table name.',
            ], 422);
        }

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $dynamicDatabaseService
            ->getConnection($databaseConnection)
            ->statement('DROP TABLE '.$this->quoteIdentifier($table));

        return response()->json([
            'message' => "Table {$table} dropped successfully.",
        ]);
    }

    public function addColumn(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table)
    {
        $this->assertIdentifier($table);

        $validated = $request->validate([
            'name' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'type' => ['required', 'string', Rule::in($this->allowedTypes())],
            'length' => ['nullable', 'integer', 'between:1,65535'],
            'scale' => ['nullable', 'integer', 'between:0,30'],
            'nullable' => ['nullable', 'boolean'],
            'default' => ['nullable', 'string', 'max:255'],
            'auto_increment' => ['nullable', 'boolean'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $definition = $this->compileColumnDefinition($connection, $validated, $primaryKeys = []);
        $sql = 'ALTER TABLE '.$this->quoteIdentifier($table).' ADD COLUMN '.$definition;
        $connection->statement($sql);

        return response()->json([
            'message' => "Column {$validated['name']} added to {$table}.",
        ]);
    }

    public function modifyColumn(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table, string $column)
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($column);

        $validated = $request->validate([
            'new_name' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'type' => ['required', 'string', Rule::in($this->allowedTypes())],
            'length' => ['nullable', 'integer', 'between:1,65535'],
            'scale' => ['nullable', 'integer', 'between:0,30'],
            'nullable' => ['nullable', 'boolean'],
            'default' => ['nullable', 'string', 'max:255'],
            'auto_increment' => ['nullable', 'boolean'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $definition = $this->compileColumnDefinition($connection, array_merge($validated, ['name' => $validated['new_name']]), $primaryKeys = []);

        $quotedTable = $this->quoteIdentifier($table);
        $quotedOldCol = $this->quoteIdentifier($column);

        $sql = "ALTER TABLE {$quotedTable} CHANGE COLUMN {$quotedOldCol} {$definition}";
        $connection->statement($sql);

        return response()->json([
            'message' => "Column '{$column}' modified successfully.",
        ]);
    }

    public function removeColumn(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table, string $column)
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $this->assertIdentifier($table);
        $this->assertIdentifier($column);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $sql = 'ALTER TABLE '.$this->quoteIdentifier($table).' DROP COLUMN '.$this->quoteIdentifier($column);
        $dynamicDatabaseService->getConnection($databaseConnection)->statement($sql);

        return response()->json([
            'message' => "Column {$column} removed from {$table}.",
        ]);
    }

    public function export(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table): StreamedResponse
    {
        $this->assertIdentifier($table);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            throw ValidationException::withMessages([
                'connection' => ['Please connect to a database first.'],
            ]);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $quotedTable = $this->quoteIdentifier($table);
        $rows = array_map(
            static fn ($row) => (array) $row,
            $connection->select("SELECT * FROM {$quotedTable} LIMIT 5000")
        );

        $filename = $table.'_'.Str::lower(now()->format('Ymd_His')).'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            if (! empty($rows)) {
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function updateRow(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table)
    {
        $this->assertIdentifier($table);

        $validated = $request->validate([
            'primary_key' => ['required', 'string'],
            'primary_key_value' => ['required'],
            'column' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'value' => ['nullable', 'string'],
        ]);

        $this->assertIdentifier($validated['primary_key']);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();
        if (! $databaseConnection) {
            return response()->json(['message' => 'Please connect to a database first.'], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $quotedTable = $this->quoteIdentifier($table);
        $quotedCol = $this->quoteIdentifier($validated['column']);
        $quotedPk = $this->quoteIdentifier($validated['primary_key']);

        $connection->update(
            "UPDATE {$quotedTable} SET {$quotedCol} = ? WHERE {$quotedPk} = ?",
            [$validated['value'], $validated['primary_key_value']]
        );

        return response()->json(['message' => 'Row updated successfully.']);
    }

    public function deleteRow(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table)
    {
        $this->assertIdentifier($table);

        $validated = $request->validate([
            'primary_key' => ['required', 'string'],
            'primary_key_value' => ['required'],
        ]);

        $this->assertIdentifier($validated['primary_key']);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();
        if (! $databaseConnection) {
            return response()->json(['message' => 'Please connect to a database first.'], 422);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $quotedTable = $this->quoteIdentifier($table);
        $quotedPk = $this->quoteIdentifier($validated['primary_key']);

        $connection->delete(
            "DELETE FROM {$quotedTable} WHERE {$quotedPk} = ? LIMIT 1",
            [$validated['primary_key_value']]
        );

        return response()->json(['message' => 'Row deleted successfully.']);
    }

    public function getPrimaryKey(Request $request, DynamicDatabaseService $dynamicDatabaseService, string $table)
    {
        $this->assertIdentifier($table);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json(['primary_key' => null]);
        }

        $connection = $dynamicDatabaseService->getConnection($databaseConnection);
        $dbName = $databaseConnection->database_name;

        $row = $connection->selectOne(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_key = 'PRI'
             ORDER BY ordinal_position LIMIT 1",
            [$dbName, $table]
        );

        $pk = $row ? ((array) $row)['column_name'] ?? ((array) $row)['COLUMN_NAME'] ?? null : null;

        return response()->json(['primary_key' => $pk]);
    }

    private function allowedTypes(): array
    {
        return [
            'INT',
            'BIGINT',
            'VARCHAR',
            'TEXT',
            'DATE',
            'DATETIME',
            'TIMESTAMP',
            'BOOLEAN',
            'DECIMAL',
            'FLOAT',
            'DOUBLE',
        ];
    }

    private function compileColumnDefinition($connection, array $column, array &$primaryKeys): string
    {
        $name = $this->quoteIdentifier($column['name']);
        $type = strtoupper($column['type']);
        $definition = "{$name} ".$this->compileSqlType($type, $column);
        $autoIncrement = $column['auto_increment'] ?? false;
        $nullable = $column['nullable'] ?? false;
        $default = $column['default'] ?? null;

        if ($autoIncrement && ! in_array($type, ['INT', 'BIGINT'], true)) {
            throw ValidationException::withMessages([
                'columns' => ['Auto increment is only supported for INT and BIGINT columns.'],
            ]);
        }

        if ($autoIncrement) {
            $definition .= ' NOT NULL AUTO_INCREMENT';
        } else {
            $definition .= $nullable ? ' NULL' : ' NOT NULL';
        }

        if ($default !== null && $default !== '') {
            $definition .= ' DEFAULT '.$connection->getPdo()->quote($default);
        }

        if ($column['primary'] ?? false) {
            $primaryKeys[] = $column['name'];
        }

        return $definition;
    }

    private function compileSqlType(string $type, array $column): string
    {
        return match ($type) {
            'VARCHAR' => 'VARCHAR('.($column['length'] ?? 255).')',
            'DECIMAL' => 'DECIMAL('.($column['length'] ?? 10).', '.($column['scale'] ?? 2).')',
            'INT', 'BIGINT', 'FLOAT', 'DOUBLE' => isset($column['length'])
                ? "{$type}({$column['length']})"
                : $type,
            default => $type,
        };
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '`'.$identifier.'`';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw ValidationException::withMessages([
                'identifier' => ['Invalid identifier provided.'],
            ]);
        }
    }
}
