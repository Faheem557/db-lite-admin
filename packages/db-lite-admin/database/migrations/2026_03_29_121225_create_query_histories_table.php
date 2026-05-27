<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_connection_id')
                ->nullable()
                ->constrained('database_connections')
                ->nullOnDelete();
            $table->longText('sql');
            $table->string('query_type', 20)->default('UNKNOWN');
            $table->string('status', 20)->default('success');
            $table->text('message')->nullable();
            $table->integer('affected_rows')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['user_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_histories');
    }
};
