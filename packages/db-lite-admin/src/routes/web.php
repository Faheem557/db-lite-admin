<?php

use DbLiteAdmin\Http\Controllers\AuthController;
use DbLiteAdmin\Http\Controllers\DashboardController;
use DbLiteAdmin\Http\Controllers\DatabaseConnectionController;
use DbLiteAdmin\Http\Controllers\ExplorerController;
use DbLiteAdmin\Http\Controllers\QueryController;
use DbLiteAdmin\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('db-lite-admin.route_prefix', 'db-lite-admin'))
    ->name('db-lite-admin.')
    ->group(function () {
        Route::redirect('/', 'dashboard');

        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
            Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        });

        Route::middleware('auth')->group(function (): void {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            Route::post('/add-database', [DatabaseConnectionController::class, 'store'])->name('database.store');
            Route::get('/databases', [DatabaseConnectionController::class, 'index'])->name('database.index');
            Route::post('/connect', [DatabaseConnectionController::class, 'connect'])->name('database.connect');
            Route::post('/query', [QueryController::class, 'execute'])->name('query.execute');
            Route::get('/tables', [ExplorerController::class, 'tables'])->name('tables.index');
            Route::get('/columns', [ExplorerController::class, 'columns'])->name('tables.columns');
            Route::get('/query-history', [QueryController::class, 'history'])->name('query.history');
            Route::get('/table-data', [TableController::class, 'preview'])->name('table.preview');
            Route::post('/tables/create', [TableController::class, 'create'])->name('tables.create');
            Route::delete('/tables/{table}', [TableController::class, 'drop'])->name('tables.drop');
            Route::post('/tables/{table}/columns', [TableController::class, 'addColumn'])->name('tables.columns.add');
            Route::put('/tables/{table}/columns/{column}', [TableController::class, 'modifyColumn'])->name('tables.columns.modify');
            Route::delete('/tables/{table}/columns/{column}', [TableController::class, 'removeColumn'])->name('tables.columns.remove');
            Route::get('/tables/{table}/export', [TableController::class, 'export'])->name('tables.export');
            Route::get('/tables/{table}/primary-key', [TableController::class, 'getPrimaryKey'])->name('tables.primary-key');
            Route::put('/tables/{table}/rows', [TableController::class, 'updateRow'])->name('tables.rows.update');
            Route::delete('/tables/{table}/rows', [TableController::class, 'deleteRow'])->name('tables.rows.delete');
        });
    });
