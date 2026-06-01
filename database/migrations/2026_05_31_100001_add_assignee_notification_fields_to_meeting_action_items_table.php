<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_action_items', function (Blueprint $table) {
            $table->timestamp('assignee_notified_at')->nullable()->after('completed_at');
            $table->uuid('assignee_notification_id')->nullable()->after('assignee_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_action_items', function (Blueprint $table) {
            $table->dropColumn(['assignee_notified_at', 'assignee_notification_id']);
        });
    }
};
