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
        Schema::table('schedule_occurrences', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'scheduled_date'], 'sched_occ_user_status_date_idx');
            $table->index(['user_id', 'task_id', 'status', 'scheduled_date'], 'sched_occ_user_task_status_date_idx');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->index(['user_id', 'task_id', 'started_at'], 'work_sessions_user_task_started_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_occurrences', function (Blueprint $table) {
            $table->dropIndex('sched_occ_user_status_date_idx');
            $table->dropIndex('sched_occ_user_task_status_date_idx');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex('work_sessions_user_task_started_idx');
        });
    }
};
