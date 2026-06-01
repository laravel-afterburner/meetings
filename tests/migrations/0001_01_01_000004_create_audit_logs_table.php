<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('impersonated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->string('category');
            $table->string('event_name');
            $table->nullableMorphs('auditable');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
