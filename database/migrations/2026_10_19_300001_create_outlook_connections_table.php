<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each user's connection to their own Outlook calendar (Microsoft Graph, delegated flow).
 * Tokens are stored encrypted with APP_KEY (encrypted cast on the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlook_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('account_email', 255)->nullable();
            $table->timestamp('connected_at');
            $table->timestamp('last_import_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlook_connections');
    }
};
