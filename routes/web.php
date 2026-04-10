<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExplorerController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : redirect('/login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Core API endpoints requested in the spec.
    Route::post('/add-database', [DatabaseConnectionController::class, 'store']);
    Route::get('/databases', [DatabaseConnectionController::class, 'index']);
    Route::post('/connect', [DatabaseConnectionController::class, 'connect']);
    Route::post('/query', [QueryController::class, 'execute']);
    Route::get('/tables', [ExplorerController::class, 'tables']);
    Route::get('/columns', [ExplorerController::class, 'columns']);

    // Extra features.
    Route::get('/query-history', [QueryController::class, 'history']);
    Route::get('/table-data', [TableController::class, 'preview']);
    Route::post('/tables/create', [TableController::class, 'create']);
    Route::delete('/tables/{table}', [TableController::class, 'drop']);
    Route::post('/tables/{table}/columns', [TableController::class, 'addColumn']);
    Route::delete('/tables/{table}/columns/{column}', [TableController::class, 'removeColumn']);
    Route::get('/tables/{table}/export', [TableController::class, 'export']);

    // Row CRUD
    Route::get('/tables/{table}/primary-key', [TableController::class, 'getPrimaryKey']);
    Route::put('/tables/{table}/rows', [TableController::class, 'updateRow']);
    Route::delete('/tables/{table}/rows', [TableController::class, 'deleteRow']);
});
