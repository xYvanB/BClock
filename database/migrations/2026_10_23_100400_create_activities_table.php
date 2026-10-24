<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('due_on');
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->string('status')->default(WorkStatus::Todo->value);
            $table->timestamps();
        });

        Schema::create('activity_assignees', function (Blueprint $table): void {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['activity_id', 'user_id']);
        });

        Schema::create('activity_dependencies', function (Blueprint $table): void {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('activities')->cascadeOnDelete();
            $table->primary(['activity_id', 'depends_on_id']);
        });

        // Quote giornaliere salvate (decisione 2026-10-23): si sposta solo la quota di un giorno
        // e la quota di un'attività si divide tra gli assegnatari.
        Schema::create('activity_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->timestamps();
            $table->unique(['activity_id', 'user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_allocations');
        Schema::dropIfExists('activity_dependencies');
        Schema::dropIfExists('activity_assignees');
        Schema::dropIfExists('activities');
    }
};
