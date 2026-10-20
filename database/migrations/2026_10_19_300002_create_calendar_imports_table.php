<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outlook meetings the member already handled (imported as a time entry or ignored).
 * event_key = iCalUId + start (UTC); prevents importing the same meeting twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('member_id');
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('event_key', 512);
            $table->uuid('time_entry_id')->nullable();
            $table->foreign('time_entry_id')
                ->references('id')
                ->on('time_entries')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            // imported | ignored
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['member_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_imports');
    }
};
