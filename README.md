# BClock

BClock è un'applicazione web per registrare e pianificare le ore di lavoro di un'azienda: clienti, progetti, attività, timer, calendario, Gantt, assenze e dashboard. L'interfaccia è in italiano, con traduzione inglese.

## Cosa fa

- **Clienti e progetti**: anagrafica clienti con referenti, progetti con attività, ore pianificate, date e dipendenze tra attività. I progetti si possono creare da un template.
- **Ore e timer**: registrazione manuale delle ore o con un timer che si avvia e si ferma, con modifica successiva e avviso quando si supera quanto pianificato.
- **Calendario**: vista mensile del carico, con la possibilità di spostare una quota di ore da un giorno all'altro.
- **Gantt**: attività con dipendenze, trascinamento delle barre e spostamento a catena delle attività collegate.
- **Assenze e festivi**: richiesta di ferie e permessi (anche a ore), conferma da parte dell'HR, calendario aziendale con i giorni festivi.
- **Dashboard ed export**: ore lavorate per persona, progetto e cliente, con export CSV.
- **Notifiche**: campanello in tempo reale, pagina con l'elenco e preferenze per tipo, sia nell'app sia via e-mail.
- **Utenti e ruoli**: Capo, Capo area, HR e Dipendente, con permessi diversi. Gli utenti nuovi ricevono un invito via e-mail.
- **Preferenze personali**: lingua, formato dell'ora, carico di lavoro e tema dei colori (compresa una palette personalizzata).
- **App AI collegate**: un server MCP permette ad assistenti come Claude di leggere progetti e ore e di registrare tempo a nome della persona che li ha autorizzati.

## Tecnologie

Laravel 12 (PHP 8.3), Inertia 2 con React 19 e TypeScript, Tailwind CSS 4, Laravel Reverb per il tempo reale, Laravel Passport e Laravel MCP per il collegamento delle app AI, Pest per i test.

## Installazione

Servono PHP 8.3, Composer, Node.js e un database MySQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

In `.env` imposta almeno `DB_USERNAME` e `DB_PASSWORD` (il database predefinito si chiama `bclock`) e le chiavi `REVERB_APP_ID`, `REVERB_APP_KEY` e `REVERB_APP_SECRET` con valori a piacere. Poi:

```bash
php artisan migrate
php artisan passport:keys
php artisan db:seed
php artisan db:seed --class=DemoDataSeeder   # facoltativo: più dati per provare calendario, Gantt e dashboard
```

I dati dei seeder servono solo allo sviluppo. Tutti gli utenti di prova hanno password `password`:

| Ruolo | E-mail |
| --- | --- |
| Capo | `mario.rossi@azienda.it` |
| Capo area | `luca.neri@azienda.it` |
| HR | `anna.bianchi@azienda.it` |
| Dipendente | `giulia.verdi@azienda.it` |

## Avvio

```bash
composer run dev
```

Avvia in un solo comando il server (`http://127.0.0.1:8000`), la coda, i log, Vite e Reverb. Se Reverb non è attivo l'app funziona lo stesso: il campanello si aggiorna alla navigazione successiva invece che subito.

Per le notifiche programmate (promemoria orari e pulizia giornaliera) serve lo scheduler di Laravel, ad esempio con `php artisan schedule:work` in sviluppo.

## Come si usa

1. Accedi con uno degli utenti di prova. Il menu a sinistra porta a Dashboard, Calendario, Gantt, Progetti, Attività, Clienti, Utenti, Template, Aree e Le mie assenze (le voci visibili dipendono dal ruolo).
2. Un Capo crea i clienti, i progetti (da zero o da un template) e le attività, e le assegna alle persone.
3. Ogni persona registra le ore dalla pagina **Le mie ore**, a mano o con il timer. Dal **Calendario** si vede il carico di ogni giorno e si può spostare una quota di ore.
4. Le assenze si chiedono dalla pagina **Assenze**. L'HR le conferma e gestisce i festivi nelle impostazioni del calendario aziendale.
5. Dalle **Impostazioni** ognuno cambia profilo, password, lingua, tema, carico di lavoro e notifiche.

### Collegare un'app AI (MCP)

Il server MCP è raggiungibile all'indirizzo `/mcp/bclock` (ad esempio `http://127.0.0.1:8000/mcp/bclock`). Nell'app AI aggiungi quell'indirizzo come server MCP remoto: al primo collegamento BClock mostra una schermata di consenso in cui accedi e autorizzi l'app. Da quel momento l'app agisce con i tuoi stessi permessi e non può eliminare dati, approvare assenze né cambiare le impostazioni.

Gli strumenti disponibili sono: elenco di progetti, attività, ore, assenze e notifiche, carico di lavoro, registrazione di ore, avvio e arresto del timer, richiesta di un'assenza e lettura delle notifiche. Le app autorizzate si vedono e si revocano da **Impostazioni › App collegate**.

## Test e qualità del codice

```bash
php artisan test --compact       # suite Pest
vendor/bin/pint --dirty          # formattazione PHP
npm run lint                     # ESLint
npm run format                   # Prettier
npm run build                    # build di produzione del frontend
```

## Progetto grafico

Le schermate sono progettate in FigCraft nel file `BClock-wireframe.figcraft`, che è il riferimento per l'interfaccia: una nuova schermata si disegna prima lì e poi si realizza nel codice. Il file contiene anche le pagine Mobile e Logo.
