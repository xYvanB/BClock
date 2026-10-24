<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Riga di attività per le tabelle: richiede `project.client`, `assignees` e `used_minutes` (withSum) già caricati.
 *
 * @mixin Activity
 */
class ActivityListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_id' => $this->project_id,
            'project_name' => $this->project->name,
            'project_due_on' => $this->project->due_on->toDateString(),
            'client_name' => $this->project->client->company_name,
            'assignees' => $this->assignees->map(fn (User $user) => ['id' => $user->id, 'name' => $user->fullName()])->values(),
            'starts_on' => $this->starts_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'planned_hours' => (float) $this->planned_hours,
            'used_minutes' => (int) $this->used_minutes,
            'status' => $this->status->value,
            'can_update' => $request->user()->can('update', $this->resource),
        ];
    }
}
