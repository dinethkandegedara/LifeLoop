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
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 32); // 'one_time', 'daily', 'weekly', 'monthly_date', 'monthly_day', 'custom_dates'
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('interval')->default(1);
            $table->json('weekdays')->nullable(); // [1..7] for ISO (1=Mon, 7=Sun)
            $table->unsignedTinyInteger('month_day')->nullable(); // 1..31
            $table->tinyInteger('month_week')->nullable(); // 1..4 or -1 (last)
            $table->unsignedTinyInteger('month_weekday')->nullable(); // 1..7 (Mon..Sun)
            $table->json('selected_dates')->nullable(); // ['YYYY-MM-DD']
            $table->json('exclusions')->nullable(); // ['YYYY-MM-DD']
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->date('last_generated_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['task_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};
