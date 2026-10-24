@php
    $palette = $user->palette;
    $roleLabel = __(match ($user->role->value) {
        'capo' => 'Capo',
        'capo_area' => 'Capo area',
        'hr' => 'HR',
        default => 'Dipendente',
    });
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="{{ $palette->value }}" @class(['dark' => $palette->isDark()]) style="{{ $palette->customStyle($user->custom_colors) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('Collegare un\'app AI') }} - {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />

    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-background text-foreground">
<div class="min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-lg rounded-lg border bg-card text-card-foreground shadow-sm p-7 space-y-4">
        <p class="text-sm font-semibold">{{ config('app.name') }}</p>

        <h1 class="text-xl font-semibold leading-tight">{{ __('Collegare «:app» a BClock?', ['app' => $client->name]) }}</h1>

        <p class="text-sm">{{ __('Stai accedendo come :name (:role).', ['name' => trim($user->name.' '.$user->surname), 'role' => $roleLabel]) }}</p>

        <div class="text-sm space-y-1">
            <p>{{ __('Questa app potrà, a tuo nome e con i tuoi stessi permessi:') }}</p>
            <ul class="list-disc pl-5 text-muted-foreground">
                <li>{{ __('leggere progetti, attività, ore, assenze, calendario e notifiche') }}</li>
                <li>{{ __('registrare ore, avviare e fermare timer, chiedere assenze') }}</li>
            </ul>
        </div>

        <p class="text-sm">{{ __('Non potrà eliminare dati, approvare assenze o cambiare le impostazioni.') }}</p>

        <div class="flex items-center gap-3 pt-2">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                    {{ __('Annulla') }}
                </button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                @csrf
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    {{ __('Autorizza') }}
                </button>
            </form>
        </div>

        <p class="text-xs text-muted-foreground">{{ __('Puoi revocare l\'accesso in ogni momento da Impostazioni › App collegate.') }}</p>
    </main>
</div>
</body>
</html>
