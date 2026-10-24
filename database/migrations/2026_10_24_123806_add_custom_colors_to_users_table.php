<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colors chosen by the person for the custom palette (key → #rrggbb, see Palette::CUSTOM_KEYS).
        Schema::table('users', function (Blueprint $table): void {
            $table->json('custom_colors')->nullable()->after('palette');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('custom_colors');
        });
    }
};
