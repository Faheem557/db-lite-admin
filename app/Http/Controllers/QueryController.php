<?php

namespace App\Http\Controllers;

use App\Models\QueryHistory;
use App\Services\DynamicDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class QueryController extends Controller
{
    public function execute(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:50000'],
            'allow_dangerous' => ['nullable', 'boolean'],
        ]);

        $databaseConnection = $dynamicDatabaseService->getActiveConnectionModel();

        if (! $databaseConnection) {
            return response()->json([
                'message' => 'Please connect to a database first.',
            ], 422);
        }

        $sql = trim($validated['sql']);
        $queryType = $this->detectQueryType($sql);
        $start = microtime(true);

        if ($this->hasMultipleStatements($sql)) {
            return response()->json([
                'message' => 'Only one SQL statement is allowed at a time.',
            ], 422);
        }

        if ($this->isDangerousQuery($sql) && ! ($validated['allow_dangerous'] ?? false)) {
            return response()->json([
                'message' => 'Dangerous query blocked. Enable "Allow dangerous queries" to continue.',
                'requires_confirmation' => true,
            ], 422);
        }

        try {
            $connection = $dynamicDatabaseService->getConnection($databaseConnection);
            $responsePayload = $this->runQueryByType($connection, $sql, $queryType);
            $duration = (int) round((microtime(true) - $start) * 1000);

            QueryHistory::create([
                'user_id' => $request->user()->id,
                'database_connection_id' => $databaseConnection->id,
                'sql' => $sql,
                'query_type' => $queryType,
                'status' => 'success',
                'message' => $responsePayload['message'] ?? null,
                'affected_rows' => $responsePayload['affected_rows'] ?? null,
                'duration_ms' => $duration,
                'executed_at' => now(),
            ]);

            return response()->json(array_merge([
                'query_type' => $queryType,
                'duration_ms' => $duration,
            ], $responsePayload));
        } catch (Throwable $throwable) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            QueryHistory::create([
                'user_id' => $request->user()->id,
                'database_connection_id' => $databaseConnection->id,
                'sql' => $sql,
                'query_type' => $queryType,
                'status' => 'error',
                'message' => $throwable->getMessage(),
                'duration_ms' => $duration,
                'executed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Query failed.',
                'error' => $throwable->getMessage(),
            ], 422);
        }
    }

    public function history(Request $request)
    {
        $history = $request->user()
            ->queryHistories()
            ->latest('executed_at')
            ->limit(30)
            ->get([
                'id',
                'database_connection_id',
                'query_type',
                'status',
                'message',
                'affected_rows',
                'duration_ms',
                'executed_at',
                'sql',
            ]);

        return response()->json([
            'history' => $history,
        ]);
    }

    private function runQueryByType($connection, string $sql, string $queryType): array
    {
        $selectTypes = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
        $affectingTypes = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE'];
        $schemaTypes = ['CREATE', 'ALTER', 'DROP', 'RENAME'];

        if (in_array($queryType, $selectTypes, true)) {
            $rows = array_map(static fn ($row) => (array) $row, $connection->select($sql));
            $columns = ! empty($rows) ? array_keys($rows[0]) : [];

            return [
                'result_type' => 'table',
                'columns' => $columns,
                'rows' => $rows,
                'row_count' => count($rows),
                'message' => count($rows).' rows returned.',
            ];
        }

        if (in_array($queryType, $affectingTypes, true)) {
            $affectedRows = $connection->affectingStatement($sql);

            return [
                'result_type' => 'message',
                'affected_rows' => $affectedRows,
                'message' => "{$affectedRows} rows affected.",
            ];
        }

        if (in_array($queryType, $schemaTypes, true)) {
            $connection->statement($sql);

            return [
                'result_type' => 'message',
                'message' => "{$queryType} query executed successfully.",
            ];
        }

        $connection->statement($sql);

        return [
            'result_type' => 'message',
            'message' => 'Query executed successfully.',
        ];
    }

    private function detectQueryType(string $sql): string
    {
        $withoutComments = preg_replace('/^\s*(--[^\n]*\n|\/\*.*?\*\/\s*)*/s', '', $sql) ?? $sql;

        if (preg_match('/^\s*([A-Za-z]+)/', $withoutComments, $matches) === 1) {
            return Str::upper($matches[1]);
        }

        return 'UNKNOWN';
    }

    private function hasMultipleStatements(string $sql): bool
    {
        $trimmed = trim($sql);

        if (str_ends_with($trimmed, ';')) {
            $trimmed = rtrim(substr($trimmed, 0, -1));
        }

        return str_contains($trimmed, ';');
    }

    private function isDangerousQuery(string $sql): bool
    {
        $patterns = [
            '/\bDROP\s+DATABASE\b/i',
            '/\bDROP\s+TABLE\b/i',
            '/\bTRUNCATE\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }
}
