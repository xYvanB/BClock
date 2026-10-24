<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\Role;
use App\Enum\TimeEntrySource;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dati dimostrativi presi dal wireframe (Mario Rossi, Giulia Verdi, Sito Rossi…).
 * Solo per sviluppo: le password degli utenti di prova sono "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $design = Area::create(['name' => 'Design', 'description' => 'Team grafica e UX']);
        $development = Area::create(['name' => 'Sviluppo', 'description' => 'Team sviluppo web']);

        $mario = User::factory()->create(['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'mario.rossi@azienda.it', 'role' => Role::Boss]);
        $giulia = User::factory()->create(['name' => 'Giulia', 'surname' => 'Verdi', 'email' => 'giulia.verdi@azienda.it', 'role' => Role::Employee, 'area_id' => $design->id]);
        User::factory()->create(['name' => 'Luca', 'surname' => 'Neri', 'email' => 'luca.neri@azienda.it', 'role' => Role::AreaHead, 'area_id' => $development->id]);
        User::factory()->create(['name' => 'Anna', 'surname' => 'Bianchi', 'email' => 'anna.bianchi@azienda.it', 'role' => Role::Hr]);

        $rossi = Client::create(['company_name' => 'Rossi Srl']);
        $rossi->contacts()->create(['name' => 'Paolo Rossi', 'email' => 'paolo@rossi.it', 'phone' => '+39 011 000000', 'is_primary' => true]);
        $bianchi = Client::create(['company_name' => 'Bianchi SpA']);

        $template = ProjectTemplate::create(['name' => 'Sito vetrina']);
        foreach ([
            ['Analisi e struttura', 'Raccolta requisiti e mappa del sito', 8, 1, 2, null],
            ['Design grafico', 'Mockup delle pagine principali', 24, 3, 7, $design->id],
            ['Sviluppo', 'Realizzazione del sito', 40, 8, 17, $development->id],
        ] as $position => [$name, $description, $hours, $startDay, $endDay, $areaId]) {
            $template->activities()->create([
                'name' => $name,
                'description' => $description,
                'planned_hours' => $hours,
                'start_day' => $startDay,
                'end_day' => $endDay,
                'position' => $position + 1,
                'suggested_area_id' => $areaId,
            ]);
        }

        $siteRossi = Project::create([
            'client_id' => $rossi->id,
            'name' => 'Sito Rossi',
            'description' => 'Nuovo sito vetrina',
            'starts_on' => '2026-10-12',
            'due_on' => '2026-10-30',
            'planned_hours' => 40,
            'status' => WorkStatus::InProgress,
        ]);
        $siteRossi->members()->attach([$mario->id, $giulia->id]);

        $appBianchi = Project::create([
            'client_id' => $bianchi->id,
            'name' => 'App Bianchi',
            'starts_on' => '2026-10-05',
            'due_on' => '2026-11-09',
            'planned_hours' => 120,
            'status' => WorkStatus::InProgress,
        ]);

        $requirements = Activity::create([
            'project_id' => $siteRossi->id, 'name' => 'Analisi requisiti',
            'starts_on' => '2026-10-12', 'due_on' => '2026-10-14', 'planned_hours' => 40, 'status' => WorkStatus::Done,
        ]);
        $wireframe = Activity::create([
            'project_id' => $siteRossi->id, 'name' => 'Wireframe home',
            'starts_on' => '2026-10-15', 'due_on' => '2026-10-23', 'planned_hours' => 40, 'status' => WorkStatus::InProgress,
        ]);
        $login = Activity::create([
            'project_id' => $appBianchi->id, 'name' => 'Sviluppo login',
            'starts_on' => '2026-10-19', 'due_on' => '2026-10-30', 'planned_hours' => 40, 'status' => WorkStatus::Todo,
        ]);

        $requirements->assignees()->attach($mario->id);
        $wireframe->assignees()->attach([$mario->id, $giulia->id]);
        $wireframe->dependencies()->attach($requirements->id);
        $login->assignees()->attach($giulia->id);

        foreach ([
            ['2026-10-19', '09:00', '12:30', 210, $wireframe, 'Revisione con il cliente'],
            ['2026-10-19', '13:30', '18:00', 270, $login, 'Implementazione form'],
            ['2026-10-20', '09:00', '13:00', 240, $wireframe, 'Correzioni layout'],
        ] as [$date, $start, $end, $minutes, $activity, $description]) {
            TimeEntry::create([
                'user_id' => $mario->id,
                'activity_id' => $activity->id,
                'date' => $date,
                'started_at' => $start,
                'ended_at' => $end,
                'duration_minutes' => $minutes,
                'description' => $description,
                'source' => TimeEntrySource::Manual,
            ]);
        }

        // più persone, progetti, ore e assenze per provare calendario, Gantt e dashboard
        $this->call(DemoDataSeeder::class);
    }
}
