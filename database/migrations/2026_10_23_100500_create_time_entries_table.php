<?php

declare(strict_types=1);

use App\Enum\TimeEntrySource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('started_at');
            $table->time('ended_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('description')->nullable();
            $table->string('source')->default(TimeEntrySource::Manual->value);
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
