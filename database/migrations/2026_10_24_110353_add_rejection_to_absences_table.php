<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Motivo del rifiuto dell'HR, mostrato alla persona che ha fatto la richiesta.
        Schema::table('absences', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropColumn('rejection_reason');
        });
    }
};
