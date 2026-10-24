<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('due_on');
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->string('status')->default(WorkStatus::Todo->value);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('project_contacts', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'client_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contacts');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
