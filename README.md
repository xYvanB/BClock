<p align="center">
  <img src="public/favicon.svg" width="128" alt="BClock logo">
</p>

<h1 align="center">BClock</h1>

BClock is a web app for logging and planning a company's working hours: customers, projects, tasks, timers, calendar, Gantt chart, absences and a dashboard. The interface is in Italian by default, with an English translation (switchable per user).

## What it does

- **Customers and projects**: customer records with contacts, projects with tasks, planned hours, dates and dependencies between tasks. Projects can be created from a template.
- **Time entries and timer**: log hours by hand or with a timer you start and stop, edit them afterwards, and get a warning when the planned hours are exceeded.
- **Calendar**: monthly view of the daily load, where you can move a share of hours from one day to another.
- **Gantt chart**: tasks with dependencies, draggable bars, and a chained shift of the linked tasks.
- **Absences and holidays**: leave and time-off requests (including hourly permissions), confirmation by HR, and a company calendar with the holidays.
- **Dashboard and export**: hours worked by person, project and customer, with CSV export.
- **Notifications**: real-time bell, a page with the full list, and preferences per type, both in the app and by e-mail.
- **Users and roles**: Boss, Area head, HR and Employee, each with different permissions. New users receive an invitation by e-mail.
- **Personal preferences**: language, time format, workload and colour theme (including a custom palette).
- **Connected AI apps**: an MCP server lets assistants such as Claude read projects and hours and log time on behalf of the person who authorized them.

## Tech stack

Laravel 13 (PHP 8.4), Inertia 2 with React 19 and TypeScript, Tailwind CSS 4, Laravel Reverb for real time, Laravel Passport and Laravel MCP for connected AI apps, and Pest for tests.

## Installation

You need PHP 8.4, Composer, Node.js and a MySQL database.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

In `.env`, set at least `DB_USERNAME` and `DB_PASSWORD` (the default database is called `bclock`) and the `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` keys with any values you like. Then:

```bash
php artisan migrate
php artisan passport:keys
php artisan db:seed
php artisan db:seed --class=DemoDataSeeder   # optional: more data to try the calendar, Gantt and dashboard
```

The seeders are for development only. All test users have the password `password`:

| Role | E-mail |
| --- | --- |
| Boss | `mario.rossi@azienda.it` |
| Area head | `luca.neri@azienda.it` |
| HR | `anna.bianchi@azienda.it` |
| Employee | `giulia.verdi@azienda.it` |

## Running

```bash
composer run dev
```

One command starts the server (`http://127.0.0.1:8000`), the queue, the logs, Vite and Reverb. If Reverb is not running the app still works: the bell updates on the next navigation instead of instantly.

Scheduled notifications (hourly reminders and the daily clean-up) need Laravel's scheduler, for example `php artisan schedule:work` in development.

## How to use it

1. Sign in with one of the test users. The sidebar leads to the Dashboard, Calendar, Gantt, Projects, Tasks, Customers, Users, Templates, Areas and My absences; which entries you see depends on your role.
2. A Boss creates customers, projects (from scratch or from a template) and tasks, and assigns them to people.
3. Everyone logs hours on the hours page, by hand or with the timer. The **Calendar** shows the load of each day and lets you move a share of hours.
4. Absences are requested from the **Absences** page. HR confirms them and manages the holidays in the company calendar settings.
5. From **Settings** everyone changes profile, password, language, theme, workload and notifications.

### Connecting an AI app (MCP)

The MCP server is available at `/mcp/bclock` (for example `http://127.0.0.1:8000/mcp/bclock`). In your AI app, add that address as a remote MCP server: on the first connection BClock shows a consent screen where you sign in and authorize the app. From then on the app acts with your own permissions, and it cannot delete data, approve absences or change settings.

The available tools are: listing projects, tasks, time entries, absences and notifications, workload, logging time, starting and stopping the timer, requesting an absence, and marking notifications as read. Authorized apps are listed, and can be revoked, in **Settings › Connected apps**.

## Tests and code quality

```bash
php artisan test --compact       # Pest suite
vendor/bin/pint --dirty          # PHP formatting
npm run lint                     # ESLint
npm run format                   # Prettier
npm run build                    # production build of the frontend
```

## Design

The screens are designed in FigCraft, in the file `BClock-wireframe.figcraft`, which is the reference for the interface: a new screen is drawn there first and then built in code. The file also holds the Mobile and Logo pages.

You need FigCraft to open the `.figcraft` file. It is not required to run or develop the app.

## License

BClock is open source software released under the [MIT License](LICENSE). You are free to use, modify and distribute it, as long as you keep the copyright notice and the license text.
