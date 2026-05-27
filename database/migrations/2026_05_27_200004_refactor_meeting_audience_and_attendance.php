<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'target_role_slugs')) {
                $table->json('target_role_slugs')->nullable()->after('agenda_notes');
            }

            if (! Schema::hasColumn('meetings', 'minutes')) {
                $table->text('minutes')->nullable()->after('target_role_slugs');
            }

            if (! Schema::hasColumn('meetings', 'minutes_finalized_at')) {
                $table->timestamp('minutes_finalized_at')->nullable()->after('minutes');
            }

            if (! Schema::hasColumn('meetings', 'minutes_finalized_by_user_id')) {
                $table->foreignId('minutes_finalized_by_user_id')->nullable()->after('minutes_finalized_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('meetings', 'invitations_sent_at')) {
                $table->timestamp('invitations_sent_at')->nullable()->after('minutes_finalized_by_user_id');
            }
        });

        if (! Schema::hasTable('meeting_attendances')) {
            return;
        }

        if (Schema::hasColumn('meeting_attendances', 'user_id')) {
            return;
        }

        Schema::table('meeting_attendances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('meeting_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('meeting_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_attendances', 'voter_unit_type')) {
                $table->dropUnique('meeting_attendances_unit_unique');
                $table->dropIndex(['voter_unit_type', 'voter_unit_id']);
                $table->dropColumn(['voter_unit_type', 'voter_unit_id']);
            }

            $table->unique(['meeting_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_attendances') || ! Schema::hasColumn('meeting_attendances', 'user_id')) {
            return;
        }

        Schema::table('meeting_attendances', function (Blueprint $table) {
            $table->dropUnique(['meeting_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->string('voter_unit_type')->nullable();
            $table->unsignedBigInteger('voter_unit_id')->nullable();
        });
    }
};
