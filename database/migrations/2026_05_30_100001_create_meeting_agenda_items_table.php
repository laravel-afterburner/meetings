<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('section', 32)->nullable();
            $table->nullableMorphs('reference');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
            $table->unique(['meeting_id', 'reference_type', 'reference_id'], 'meeting_agenda_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_agenda_items');
    }
};
