<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('recurring_schedule_id')->nullable()->constrained('recurring_schedules')->nullOnDelete();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->time('end_time');
            $table->string('timezone', 64)->default('UTC');
            $table->dateTime('utc_start_at')->nullable();
            $table->dateTime('utc_end_at')->nullable();
            $table->string('status', 32)->default('pending'); // 'pending', 'completed', 'skipped', 'cancelled'
            $table->boolean('is_exception')->default(false);
            $table->date('original_scheduled_date')->nullable();
            $table->time('original_start_time')->nullable();
            $table->unsignedInteger('original_duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_date']);
            $table->index(['task_id', 'scheduled_date']);
            $table->index(['recurring_schedule_id', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index(['utc_start_at', 'utc_end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_occurrences');
    }
};
