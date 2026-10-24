<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le quote spostate a mano nel calendario non vengono più toccate quando l'attività si rigenera.
        // Il giorno di partenza resta come quota manuale a 0 ore, così la rigenerazione non lo riempie di nuovo.
        Schema::table('activity_allocations', function (Blueprint $table): void {
            $table->boolean('is_manual')->default(false)->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('activity_allocations', function (Blueprint $table): void {
            $table->dropColumn('is_manual');
        });
    }
};
