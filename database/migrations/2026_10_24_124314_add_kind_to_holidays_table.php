<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every non-working day is a holiday or an extra closure decided by the company (bridge days, collective vacations).
        Schema::table('holidays', function (Blueprint $table): void {
            $table->string('kind', 20)->default('festivo')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
