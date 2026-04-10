<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DynamicDatabaseService
{
    private const DYNAMIC_CONNECTION_NAME = 'dynamic_mysql';

    public function getActiveConnectionModel(): ?DatabaseConnection
    {
        $user = Auth::user();
        $connectionId = session('active_connection_id');

        if (! $user || ! $connectionId) {
            return null;
        }

        return $user->databaseConnections()->find($connectionId);
    }

    public function setActiveConnection(DatabaseConnection $databaseConnection): void
    {
        session(['active_connection_id' => $databaseConnection->id]);
    }

    public function clearActiveConnection(): void
    {
        session()->forget('active_connection_id');
    }

    public function getConnection(?DatabaseConnection $databaseConnection = null): ConnectionInterface
    {
        $databaseConnection ??= $this->getActiveConnectionModel();

        if (! $databaseConnection) {
            throw new RuntimeException('No active database connection selected.');
        }

        config([
            'database.connections.'.self::DYNAMIC_CONNECTION_NAME => [
                'driver' => 'mysql',
                'host' => $databaseConnection->host,
                'port' => $databaseConnection->port,
                'database' => $databaseConnection->database_name,
                'username' => $databaseConnection->username,
                'password' => $databaseConnection->password,
                'unix_socket' => env('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql')
                    ? array_filter([
                        \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    ])
                    : [],
            ],
        ]);

        DB::purge(self::DYNAMIC_CONNECTION_NAME);

        return DB::connection(self::DYNAMIC_CONNECTION_NAME);
    }

    public function testConnection(DatabaseConnection $databaseConnection): void
    {
        $this->getConnection($databaseConnection)->getPdo();
    }
}

