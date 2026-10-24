<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leaves have a time (within the same day); employees' absences also have
        // the area head's approval, besides HR's confirmation (`confirmed_by`/`confirmed_at`).
        Schema::table('absences', function (Blueprint $table): void {
            $table->time('starts_time')->nullable()->after('ends_on');
            $table->time('ends_time')->nullable()->after('starts_time');
            $table->foreignId('area_head_confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('area_head_confirmed_at')->nullable()->after('area_head_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('area_head_confirmed_by');
            $table->dropColumn(['starts_time', 'ends_time', 'area_head_confirmed_at']);
        });
    }
};
