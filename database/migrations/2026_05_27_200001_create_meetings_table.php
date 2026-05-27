<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 32);
            $table->string('status', 32)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->string('virtual_link')->nullable();
            $table->text('agenda_notes')->nullable();
            $table->json('target_role_slugs')->nullable();
            $table->text('minutes')->nullable();
            $table->timestamp('minutes_finalized_at')->nullable();
            $table->foreignId('minutes_finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invitations_sent_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
