<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Absences\ConfirmAbsence;
use App\Actions\Absences\DeleteAbsence;
use App\Actions\Absences\RejectAbsence;
use App\Actions\Absences\SaveAbsence;
use App\Enum\Role;
use App\Http\Requests\DeleteAbsenceRequest;
use App\Http\Requests\RejectAbsenceRequest;
use App\Http\Requests\SaveAbsenceRequest;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AbsenceController extends Controller
{
    /**
     * L'HR vede le assenze di tutti (per confermarle); il Capo le proprie e quelle dell'HR (che deve confermare);
     * il Capo area le proprie e quelle della sua area; il Dipendente le proprie. I festivi li vedono tutti.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isHr = $user->hasRole(Role::Hr);

        $absences = Absence::query()
            ->with('user')
            ->when(! $isHr, fn ($query) => $query->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->hasRole(Role::AreaHead) && $user->area_id !== null) {
                    $query->orWhereIn('user_id', User::where('area_id', $user->area_id)->select('id'));
                }

                if ($user->hasRole(Role::Boss)) {
                    $query->orWhereIn('user_id', User::where('role', Role::Hr)->select('id'));
                }
            }))
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Absence $absence): array => [
                'id' => $absence->id,
                'user_name' => $absence->user->fullName(),
                'type' => $absence->type->value,
                'starts_on' => $absence->starts_on->toDateString(),
                'ends_on' => $absence->ends_on->toDateString(),
                'starts_time' => $absence->timeLabel($absence->starts_time),
                'ends_time' => $absence->timeLabel($absence->ends_time),
                'hours' => $absence->leaveHours(),
                'needs_area_head' => $absence->requiresAreaHead(),
                'hr_approved' => $absence->isApprovedByHr(),
                'approver_label' => $absence->finalApproverRole() === Role::Boss ? 'Capo' : 'HR',
                'area_head_approved' => $absence->isApprovedByAreaHead(),
                'note' => $absence->note,
                'rejection_reason' => $absence->rejection_reason,
                'is_own' => $absence->user_id === $user->id,
                'status' => $absence->status->value,
                'can_update' => $user->can('update', $absence),
                'can_delete' => $user->can('delete', $absence),
                'can_confirm' => $user->can('confirm', $absence),
                'can_approve' => $user->can('approve', $absence),
                'can_reject' => $user->can('reject', $absence),
            ]);

        return Inertia::render('absences/index', [
            'absences' => $absences,
            'holidays' => Holiday::query()
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'name' => $holiday->name,
                    'kind' => $holiday->kind->value,
                ]),
            'can_manage_holidays' => $user->can('create', Holiday::class),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveAbsenceRequest $request, SaveAbsence $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toDto());

        return to_route('absences.index')->with('success', 'Richiesta inviata.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveAbsenceRequest $request, Absence $absence, SaveAbsence $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toDto(), $absence);

        return to_route('absences.index')->with('success', 'Assenza aggiornata.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(DeleteAbsenceRequest $request, Absence $absence, DeleteAbsence $action): RedirectResponse
    {
        $action->handle($request->user(), $absence, $request->reason());

        return to_route('absences.index')->with('success', 'Assenza eliminata.');
    }

    /**
     * @throws Throwable
     */
    public function confirm(Request $request, Absence $absence, ConfirmAbsence $action): RedirectResponse
    {
        $this->authorize('confirm', $absence);

        $action->handle($request->user(), $absence);

        return to_route('absences.index')->with('success', 'Conferma registrata.');
    }

    /**
     * Approvazione del Capo area per un'assenza di un dipendente della sua area.
     *
     * @throws Throwable
     */
    public function approve(Request $request, Absence $absence, ConfirmAbsence $action): RedirectResponse
    {
        $this->authorize('approve', $absence);

        $action->handle($request->user(), $absence);

        return to_route('absences.index')->with('success', 'Assenza approvata.');
    }

    /**
     * @throws Throwable
     */
    public function reject(RejectAbsenceRequest $request, Absence $absence, RejectAbsence $action): RedirectResponse
    {
        $action->handle($request->user(), $absence, $request->reason());

        return to_route('absences.index')->with('success', 'Assenza rifiutata.');
    }
}
