<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scelte della persona per tipo di notifica: tipo → {app: bool, mail: bool}; i tipi mancanti usano i valori di default.
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable()->after('custom_colors');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
