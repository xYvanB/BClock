<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allocations moved by hand in the calendar are no longer touched when the activity is regenerated.
        // The source day stays as a manual allocation of 0 hours, so regeneration does not fill it again.
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
