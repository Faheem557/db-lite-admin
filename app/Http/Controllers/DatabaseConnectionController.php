<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\DynamicDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class DatabaseConnectionController extends Controller
{
    public function index(Request $request)
    {
        $connections = $request->user()
            ->databaseConnections()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'host',
                'port',
                'database_name',
                'username',
                'created_at',
            ]);

        return response()->json([
            'connections' => $connections,
            'active_connection_id' => session('active_connection_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('database_connections', 'name')
                    ->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database_name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = $request->user()->databaseConnections()->create([
            'name' => $validated['name'],
            'host' => $validated['host'],
            'port' => $validated['port'] ?? 3306,
            'database_name' => $validated['database_name'],
            'username' => $validated['username'],
            'password' => $validated['password'] ?? null,
        ]);

        return response()->json([
            'message' => 'Database connection saved successfully.',
            'connection' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'host' => $connection->host,
                'port' => $connection->port,
                'database_name' => $connection->database_name,
                'username' => $connection->username,
            ],
        ], 201);
    }

    public function connect(Request $request, DynamicDatabaseService $dynamicDatabaseService)
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
        ]);

        /** @var DatabaseConnection|null $connection */
        $connection = $request->user()
            ->databaseConnections()
            ->find($validated['connection_id']);

        if (! $connection) {
            return response()->json([
                'message' => 'The selected database connection was not found.',
            ], 404);
        }

        try {
            $dynamicDatabaseService->testConnection($connection);
            $dynamicDatabaseService->setActiveConnection($connection);

            return response()->json([
                'message' => 'Connected successfully.',
                'active_connection_id' => $connection->id,
                'database' => $connection->database_name,
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'Unable to connect using the saved credentials.',
                'error' => $throwable->getMessage(),
            ], 422);
        }
    }
}
