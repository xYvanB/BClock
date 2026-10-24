<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Allocations\RegenerateAllocations;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Enum\HolidayKind;
use App\Enum\Role;
use App\Enum\TimeEntrySource;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dati dimostrativi per provare a mano calendario, Gantt e dashboard: tre aree con una ventina di persone, sette progetti
 * con attività concatenate, ore registrate nelle ultime settimane, assenze in tutti gli stati e i festivi.
 * Le date sono relative a oggi (la settimana in corso parte dal lunedì). Si aggiunge ai dati di DatabaseSeeder e non
 * duplica nulla se viene rieseguito. Solo per sviluppo: le password sono "password".
 */
class DemoDataSeeder extends Seeder
{
    private CarbonImmutable $week0;

    public function run(): void
    {
        if (User::where('email', 'sara.gallo@azienda.it')->exists()) {
            return;
        }

        mt_srand(2026);
        $this->week0 = CarbonImmutable::today(config('app.local_timezone'))->startOfWeek(CarbonInterface::MONDAY);

        // tutto o niente: se manca un dato di base (le aree o gli utenti di DatabaseSeeder) non resta nulla a metà
        DB::transaction(function (): void {
            $design = Area::where('name', 'Design')->firstOrFail();
            $development = Area::where('name', 'Sviluppo')->firstOrFail();
            $marketing = Area::firstOrCreate(['name' => 'Marketing'], ['description' => 'Contenuti e campagne']);

            $people = $this->people($design, $development, $marketing);
            $this->holidays();
            $this->projects($people);
            $this->timeEntries();
            $this->absences($people);

            // festivi e assenze confermate cambiano le quote: si ricalcolano una volta sola, alla fine
            app(RegenerateAllocations::class)->handle();
        });
    }

    /**
     * @return array<string, User> Persone per chiave breve (nome in minuscolo)
     */
    private function people(Area $design, Area $development, Area $marketing): array
    {
        $people = [
            'mario' => User::where('email', 'mario.rossi@azienda.it')->firstOrFail(),
            'giulia' => User::where('email', 'giulia.verdi@azienda.it')->firstOrFail(),
            'luca' => User::where('email', 'luca.neri@azienda.it')->firstOrFail(),
            'anna' => User::where('email', 'anna.bianchi@azienda.it')->firstOrFail(),
        ];

        foreach ([
            ['sara', 'Sara', 'Gallo', Role::AreaHead, $design],
            ['elena', 'Elena', 'Costa', Role::Employee, $design],
            ['marco', 'Marco', 'Riva', Role::Employee, $design],
            ['paolo', 'Paolo', 'Fontana', Role::Employee, $development],
            ['chiara', 'Chiara', 'Moretti', Role::Employee, $development],
            ['davide', 'Davide', 'Greco', Role::Employee, $development],
            ['irene', 'Irene', 'Sala', Role::Employee, $development],
            ['valeria', 'Valeria', 'Conti', Role::AreaHead, $marketing],
            ['tommaso', 'Tommaso', 'Bruno', Role::Employee, $marketing],
            ['marta', 'Marta', 'Leone', Role::Employee, $marketing],
        ] as [$key, $name, $surname, $role, $area]) {
            $people[$key] = User::factory()->create([
                'name' => $name,
                'surname' => $surname,
                'email' => strtolower("{$name}.{$surname}").'@azienda.it',
                'role' => $role,
                'area_id' => $area->id,
            ]);
        }

        // Marco lavora part-time: 6 ore al giorno
        $people['marco']->update(['daily_capacity' => 6]);

        return $people;
    }

    private function holidays(): void
    {
        foreach ([
            ['2026-11-01', 'Ognissanti'],
            ['2026-12-08', 'Immacolata Concezione'],
            ['2026-12-07', 'Ponte di dicembre', HolidayKind::Closure],
            ['2026-12-25', 'Natale'],
            ['2026-12-26', 'Santo Stefano'],
            ['2027-01-01', 'Capodanno'],
            ['2027-01-06', 'Epifania'],
        ] as $row) {
            Holiday::firstOrCreate(['date' => $row[0]], ['name' => $row[1], 'kind' => $row[2] ?? HolidayKind::Holiday]);
        }
    }

    /**
     * @param  array<string, User>  $people
     */
    private function projects(array $people): void
    {
        $verdi = Client::create(['company_name' => 'Verdi Moda']);
        $verdi->contacts()->create(['name' => 'Laura Verdi', 'email' => 'laura@verdimoda.it', 'phone' => '+39 02 111111', 'is_primary' => true]);
        $neri = Client::create(['company_name' => 'Neri Studio']);
        $neri->contacts()->create(['name' => 'Stefano Neri', 'email' => 'stefano@neristudio.it', 'is_primary' => true]);
        $gallo = Client::create(['company_name' => 'Gallo & C']);
        $gallo->contacts()->create(['name' => 'Rita Gallo', 'email' => 'rita@gallo.it', 'is_primary' => true]);
        $bianchi = Client::where('company_name', 'Bianchi SpA')->firstOrFail();
        $rossi = Client::where('company_name', 'Rossi Srl')->firstOrFail();

        // [nome, descrizione, cliente, stato progetto, attività]
        // attività: [nome, [settimana, giorno] di inizio, [settimana, giorno] di fine, ore, stato, assegnatari, dipende da]
        // le settimane si contano da quella in corso (0), i giorni da 1 (lunedì) a 5 (venerdì)
        $projects = [
            ['E-commerce Verdi', 'Negozio online con catalogo e pagamenti', $verdi, WorkStatus::InProgress, [
                ['Analisi e requisiti', [-3, 1], [-3, 3], 16, WorkStatus::Done, ['sara', 'elena'], []],
                ['UX e wireframe shop', [-3, 4], [-1, 2], 40, WorkStatus::Done, ['elena', 'marco'], ['Analisi e requisiti']],
                ['Design grafico shop', [-1, 3], [1, 2], 48, WorkStatus::InProgress, ['elena', 'marco', 'giulia'], ['UX e wireframe shop']],
                ['Catalogo prodotti', [-2, 1], [1, 5], 80, WorkStatus::InProgress, ['paolo', 'chiara'], ['Analisi e requisiti']],
                ['Checkout e pagamenti', [0, 1], [3, 5], 80, WorkStatus::InProgress, ['paolo', 'davide'], ['Catalogo prodotti']],
                ['Test e collaudo', [4, 1], [5, 5], 40, WorkStatus::Todo, ['davide', 'irene'], ['Checkout e pagamenti']],
                ['Go-live', [6, 1], [6, 5], 16, WorkStatus::Todo, ['luca', 'paolo'], ['Test e collaudo']],
            ]],
            ['Rebranding Neri', 'Nuova identità visiva', $neri, WorkStatus::InProgress, [
                ['Naming e strategia', [-1, 1], [-1, 5], 24, WorkStatus::Done, ['sara', 'elena'], []],
                ['Logo e identità', [0, 1], [1, 5], 56, WorkStatus::InProgress, ['giulia', 'marco'], ['Naming e strategia']],
                ['Brand book', [2, 1], [3, 5], 48, WorkStatus::WaitingClient, ['giulia', 'sara'], ['Logo e identità']],
                ['Kit social', [3, 1], [5, 3], 32, WorkStatus::Todo, ['marta', 'marco'], ['Logo e identità']],
            ]],
            ['Campagna Primavera', 'Campagna di lancio collezione', $bianchi, WorkStatus::InProgress, [
                ['Strategia e piano editoriale', [0, 1], [1, 3], 32, WorkStatus::InProgress, ['valeria', 'tommaso'], []],
                ['Contenuti', [1, 4], [4, 5], 96, WorkStatus::Todo, ['tommaso', 'marta'], ['Strategia e piano editoriale']],
                ['Landing page', [3, 1], [5, 5], 60, WorkStatus::Todo, ['irene', 'giulia'], ['Strategia e piano editoriale']],
                ['Report risultati', [7, 1], [8, 5], 24, WorkStatus::Todo, ['valeria'], ['Contenuti']],
            ]],
            ['Portale Clienti', 'Area riservata per i clienti di Gallo & C', $gallo, WorkStatus::Done, [
                ['Analisi portale', [-8, 1], [-7, 3], 24, WorkStatus::Done, ['paolo'], []],
                ['Sviluppo portale', [-7, 4], [-4, 5], 120, WorkStatus::Done, ['paolo', 'chiara', 'davide'], ['Analisi portale']],
                ['Collaudo portale', [-3, 1], [-2, 5], 40, WorkStatus::Done, ['irene', 'davide'], ['Sviluppo portale']],
            ]],
            ['Manutenzione Q4', 'Manutenzione e supporto del sito Rossi', $rossi, WorkStatus::InProgress, [
                ['Aggiornamenti di sicurezza', [-2, 1], [9, 5], 60, WorkStatus::InProgress, ['davide', 'irene'], []],
                ['Supporto e correzioni', [-2, 1], [9, 5], 50, WorkStatus::InProgress, ['chiara'], []],
            ]],
        ];

        foreach ($projects as [$name, $description, $client, $status, $activities]) {
            $starts = collect($activities)->map(fn (array $a): string => $this->day(...$a[1]))->min();
            $due = collect($activities)->map(fn (array $a): string => $this->day(...$a[2]))->max();

            $project = Project::create([
                'client_id' => $client->id,
                'name' => $name,
                'description' => $description,
                'starts_on' => $starts,
                'due_on' => $due,
                'planned_hours' => collect($activities)->sum(3),
                'status' => $status,
            ]);

            $project->members()->attach(collect($activities)->flatMap(fn (array $a): array => $a[5])->unique()->map(fn (string $key): int => $people[$key]->id)->all());

            $created = [];
            foreach ($activities as [$activityName, $start, $end, $hours, $activityStatus, $assignees, $dependsOn]) {
                $activity = Activity::create([
                    'project_id' => $project->id,
                    'name' => $activityName,
                    'starts_on' => $this->day(...$start),
                    'due_on' => $this->day(...$end),
                    'planned_hours' => $hours,
                    'status' => $activityStatus,
                ]);
                $activity->assignees()->attach(array_map(fn (string $key): int => $people[$key]->id, $assignees));
                $activity->dependencies()->attach(array_map(fn (string $dependency): int => $created[$dependency]->id, $dependsOn));
                $created[$activityName] = $activity;
            }
        }
    }

    /**
     * Ore registrate nei giorni feriali passati sulle attività già iniziate di ciascuno, senza sovrapposizioni.
     */
    private function timeEntries(): void
    {
        $today = CarbonImmutable::today(config('app.local_timezone'));
        $descriptions = ['Revisione con il team', 'Sviluppo', 'Correzioni dopo i test', 'Riunione con il cliente', 'Documentazione', 'Preparazione materiali', 'Analisi', 'Implementazione'];

        $activities = Activity::with('assignees')->whereIn('status', [WorkStatus::InProgress, WorkStatus::Done, WorkStatus::WaitingClient])->get();

        for ($day = $this->week0->subWeeks(3); $day < $today; $day = $day->addDay()) {
            if ($day->isWeekend() || Holiday::whereDate('date', $day)->exists()) {
                continue;
            }

            $users = $activities->flatMap(fn (Activity $activity) => $activity->assignees)->unique('id');

            foreach ($users as $user) {
                $mine = $activities->filter(fn (Activity $activity): bool => $activity->assignees->contains('id', $user->id)
                    && $activity->starts_on->toDateString() <= $day->toDateString()
                    && $activity->due_on->toDateString() >= $day->toDateString())->values();

                if ($mine->isEmpty() || mt_rand(1, 10) === 1 || TimeEntry::where('user_id', $user->id)->whereDate('date', $day)->exists()) {
                    continue;
                }

                $target = (float) $user->daily_capacity * 60 * (mt_rand(80, 105) / 100);
                $cursor = $day->setTime(9, 0);
                $total = 0;

                for ($slot = 0; $slot < 4 && $total < $target; $slot++) {
                    $minutes = [60, 90, 120, 180, 240][mt_rand(0, 4)];
                    $minutes = (int) min($minutes, max(30, $target - $total));

                    if ($cursor->hour === 13) {
                        $cursor = $cursor->setTime(14, 0);
                    }

                    if ($cursor->addMinutes($minutes)->hour >= 19) {
                        break;
                    }

                    TimeEntry::create([
                        'user_id' => $user->id,
                        'activity_id' => $mine[mt_rand(0, $mine->count() - 1)]->id,
                        'date' => $day->toDateString(),
                        'started_at' => $cursor->format('H:i:s'),
                        'ended_at' => $cursor->addMinutes($minutes)->format('H:i:s'),
                        'duration_minutes' => $minutes,
                        'description' => $descriptions[mt_rand(0, count($descriptions) - 1)],
                        'source' => TimeEntrySource::Manual,
                    ]);

                    $cursor = $cursor->addMinutes($minutes);
                    $total += $minutes;
                }
            }
        }
    }

    /**
     * Assenze in tutti gli stati, compresa la doppia conferma dei dipendenti (Capo area e HR).
     *
     * @param  array<string, User>  $people
     */
    private function absences(array $people): void
    {
        $confirmed = fn (User $owner, array $attributes, bool $areaHead = true): Absence => Absence::create($attributes + [
            'user_id' => $owner->id,
            'status' => AbsenceStatus::Confirmed,
            'confirmed_by' => $people['anna']->id,
            'confirmed_at' => now(),
        ] + ($areaHead ? ['area_head_confirmed_by' => $owner->area?->users()->where('role', Role::AreaHead)->value('id'), 'area_head_confirmed_at' => now()] : []));

        // ferie confermate da entrambi
        $confirmed($people['elena'], ['type' => AbsenceType::Vacation, 'starts_on' => $this->day(1, 1), 'ends_on' => $this->day(1, 3), 'note' => 'Viaggio']);
        // ferie del Capo area: basta l'HR
        $confirmed($people['luca'], ['type' => AbsenceType::Vacation, 'starts_on' => $this->day(3, 1), 'ends_on' => $this->day(3, 5)], false);
        // permesso confermato di poche ore
        $confirmed($people['davide'], ['type' => AbsenceType::Leave, 'starts_on' => $this->day(0, 5), 'ends_on' => $this->day(0, 5), 'starts_time' => '10:00', 'ends_time' => '12:00', 'note' => 'Visita medica']);

        // in attesa: permesso senza approvazioni, ferie con la sola approvazione del Capo area
        Absence::create([
            'user_id' => $people['paolo']->id, 'type' => AbsenceType::Leave, 'status' => AbsenceStatus::Pending,
            'starts_on' => $this->day(1, 2), 'ends_on' => $this->day(1, 2), 'starts_time' => '14:00', 'ends_time' => '16:30',
        ]);
        Absence::create([
            'user_id' => $people['chiara']->id, 'type' => AbsenceType::Vacation, 'status' => AbsenceStatus::Pending,
            'starts_on' => $this->day(2, 3), 'ends_on' => $this->day(2, 5), 'note' => 'Ponte',
            'area_head_confirmed_by' => $people['luca']->id, 'area_head_confirmed_at' => now(),
        ]);

        // rifiutata, con il motivo
        Absence::create([
            'user_id' => $people['marco']->id, 'type' => AbsenceType::Vacation, 'status' => AbsenceStatus::Rejected,
            'starts_on' => $this->day(1, 4), 'ends_on' => $this->day(1, 5), 'rejection_reason' => 'Consegna del logo in quei giorni',
        ]);
    }

    private function day(int $week, int $weekday): string
    {
        return $this->week0->addWeeks($week)->addDays($weekday - 1)->toDateString();
    }
}
