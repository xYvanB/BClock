<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Durata relativa in giorni lavorativi dall'inizio del progetto (il giorno 1 è la data di inizio).
        Schema::create('template_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->unsignedSmallInteger('start_day');
            $table->unsignedSmallInteger('end_day');
            $table->unsignedSmallInteger('position');
            $table->foreignId('suggested_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('suggested_role')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_activities');
    }
};
