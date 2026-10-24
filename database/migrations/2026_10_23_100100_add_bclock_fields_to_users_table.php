<?php

declare(strict_types=1);

use App\Enum\AppLocale;
use App\Enum\Role;
use App\Enum\TimeFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('surname')->default('')->after('name');
            $table->string('role')->default(Role::Employee->value)->after('email');
            $table->foreignId('area_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->decimal('daily_capacity', 4, 2)->default(8)->after('area_id');
            $table->string('locale', 2)->default(AppLocale::It->value)->after('daily_capacity');
            $table->string('time_format', 3)->default(TimeFormat::Hours24->value)->after('locale');
            $table->timestamp('invited_at')->nullable()->after('time_format');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['surname', 'role', 'daily_capacity', 'locale', 'time_format', 'invited_at']);
        });
    }
};
