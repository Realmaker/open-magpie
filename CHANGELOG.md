# Changelog

## [2026-03-24] - Snapshots, Admin-Features, Mail-Test, Skill v2

### Hinzugefuegt
- **Mail-Test Seite** (`/settings/mail-test`): SMTP-Config anzeigen, Test-Mail senden, SSL-Dokumentation, nur fuer Admins
- **User-Verwaltung** (`/settings/users`): Team-Mitglieder verwalten, Rollen aendern, User hinzufuegen/entfernen
- **Team-Rollen-Check**: `User::isTeamAdmin()`, Sharing nur fuer Team-Owner/Admins
- **Auto-Accept**: Registrierte User erhalten sofort Zugriff beim Sharing (kein Email-Klick)
- **Brain Skill v2**: `/brain snapshot`, `/brain share`, `/brain install-notes`, `/brain tasks`, `/brain decision`
- **Onboarding Step 4**: Feature-Uebersicht (Snapshots, Sharing, User-Management, API-Security)

### Geaendert
- **HandleInertiaRequests**: `isTeamAdmin` als globaler Shared Prop
- **OnboardingController**: `isTeamAdmin` mitgeben
- **Globale CLAUDE.md**: Install-Notes Endpunkt, erweiterte "Wann dokumentieren" Tabelle
- **Onboarding CLAUDE.md-Snippet**: Install-Notes + Snapshot Befehle
- **DownloadController**: README mit vollstaendiger Befehlsliste v2
- **AuthenticatedLayout**: "Users" + "Mail" Nav-Links (nur Admins)

### Neue Dateien
- `app/Http/Controllers/Web/MailTestController.php`
- `app/Http/Controllers/Web/UserManagementController.php`
- `resources/js/Pages/Settings/MailTest.vue`
- `resources/js/Pages/Settings/Users.vue`

---

## [2026-03-24] - Projekt-Snapshots (File-Management)

### Hinzugefuegt
- **Projekt-Snapshots**: ZIP-Upload mit Versionierung fuer Projektdateien
  - `project_snapshots` Tabelle mit Version, File-Tree (JSON), Groesse, Change-Note
  - `install_notes` Feld auf `projects` Tabelle (persistente Installationsanleitung)
  - ZIP wird gespeichert, File-Tree wird aus ZIP-Index gelesen (ohne Entpacken)
  - Automatische Excludes: node_modules, vendor, .git, .env, logs, cache etc.
  - **API-Endpoints**: Upload, Liste, Latest, Download, File-Preview, Install-Notes
  - **Web**: Files-Tab in Projekt-Detail mit File-Tree, Upload-Modal, Versionsliste
  - **Claude Code Integration**: Snapshot-Push Anleitung in globaler CLAUDE.md
- **FileTree.vue**: Aufklappbare Baumansicht mit Ordner/Datei-Icons, Dateigroessen, Syntax-Farben
- **SnapshotUploadModal.vue**: Drag&Drop ZIP-Upload mit Change-Note
- **ProjectSnapshot Model**: buildFileTreeFromZip(), readFileFromZip(), Exclude-Pattern-Matching
- **SnapshotController (API)**: Vollstaendiger CRUD mit File-Preview aus ZIP

### Geaendert
- **Projects/Show.vue**: Neuer "Files" Tab zwischen Notes und Worker
- **Project Model**: snapshots() Relationship, install_notes Feld
- **Globale CLAUDE.md**: Snapshot-Push Instruktionen fuer automatisches Backup nach Meilensteinen

### Neue Dateien
- `database/migrations/2026_03_24_000001_create_project_snapshots_table.php`
- `app/Models/ProjectSnapshot.php`
- `app/Http/Controllers/Api/SnapshotController.php`
- `app/Http/Resources/ProjectSnapshotResource.php`
- `resources/js/Components/Snapshots/FileTree.vue`
- `resources/js/Components/Snapshots/SnapshotUploadModal.vue`

---

## [2026-03-22] - Sicherheitsaudit + Projekt-Sharing

### Sicherheitsfixes
- **ApiTokenAuth**: `$request->merge()` entfernt, nur noch sichere `$request->attributes` (verhindert team_id-Spoofing)
- **CheckApiAbility Middleware**: Token-Abilities werden jetzt auf allen API-Routen geprueft (z.B. `projects:read`, `events:write`)
- **Rate-Limiting**: 60 req/min API, 10 req/min AI-Endpoints, 20 Shares/Stunde - mit korrektem Error-Response
- **IDOR-Fix NoteController**: Note-Lookup jetzt team-scoped statt global (verhindert Cross-Team-Probing)
- **Team-Switching**: `current_team_id` auf User-Model statt non-deterministischem `teams()->first()`
- **LIKE-Wildcard-Escape**: Alle Suchfelder escapen jetzt `%` und `_` in User-Input
- **Content-Limits**: Max-Laengen fuer Event-Content (500KB), Document-Content (1MB), Notes (64KB), AI-Text (100KB)

### Hinzugefuegt
- **Projekt-Sharing-System**: Projekte mit anderen Usern per E-Mail teilen
  - `project_shares` Tabelle mit Token-basierter Einladung (SHA-256 gehasht)
  - 3 Berechtigungsstufen: `viewer` (nur lesen), `editor` (lesen + erstellen), `admin` (alles + teilen)
  - E-Mail-Einladung mit 7-Tage-Ablauf via `ProjectShareInvitation` Mailable
  - Accept-Route mit E-Mail-Validierung (Einladung nur fuer richtige Adresse annehmbar)
  - API-Endpoints: `GET/POST /projects/{slug}/shares`, `PATCH/DELETE /projects/{slug}/shares/{id}`, `GET /shared-with-me`
  - Web-Routen: Share-Management in Projekt-Detail + eigene "Shared with me"-Seite
  - Alle API-Controller (`Project`, `Event`, `Task`, `Document`) pruefen jetzt Share-Permissions
  - Activity-Logging fuer alle Share-Aktionen (shared, accepted, permission_changed, revoked)
- **`ProjectShare` Model**: mit `generateInviteToken()`, `findByToken()`, `hasPermission()` Level-Check
- **`FindsProjectWithSharing` Trait**: Zentraler Project-Lookup (Team-owned -> Shared -> 404)
- **`QueryHelper::escapeLike()`**: Wiederverwendbare LIKE-Escape-Funktion

### Geaendert
- **Alle API-Routen**: Rate-Limiting + Ability-Checks (`api.ability:resource:action`)
- **Alle `findProject()` Methoden**: Erweitert um Share-Zugriff mit Permission-Level-Pruefung
- **Schreiboperationen**: Brauchen mindestens `editor`, Loeschen braucht `admin`, Projekt-Delete nur fuer Team-Owner
- **20 Web-Controller-Stellen**: `$user->teams()->first()` -> `$user->currentTeam()` (deterministisch)
- **AppServiceProvider**: Rate-Limiter konfiguriert (api, ai, shares)

### Neue Dateien
- `app/Http/Middleware/CheckApiAbility.php`
- `app/Http/Controllers/Api/ProjectShareController.php`
- `app/Http/Controllers/Web/ProjectShareAcceptController.php`
- `app/Http/Controllers/Concerns/FindsProjectWithSharing.php`
- `app/Http/Controllers/Concerns/ResolvesTeam.php`
- `app/Models/ProjectShare.php`
- `app/Http/Resources/ProjectShareResource.php`
- `app/Http/Requests/Api/StoreProjectShareRequest.php`
- `app/Mail/ProjectShareInvitation.php`
- `app/Helpers/QueryHelper.php`
- `resources/views/mail/project-share-invitation.blade.php`
- `database/migrations/2026_03_22_000001_add_current_team_id_to_users_table.php`
- `database/migrations/2026_03_22_000002_create_project_shares_table.php`

---

## [2026-03-18] - Tasks-Uebersichtsseite

### Hinzugefuegt
- **Globale Tasks-Seite** (`/tasks`): Alle Tasks ueber alle Projekte auf einen Blick
- **Web TaskController**: Laedt alle Tasks des Teams mit Filtern (Status, Priority, Type, Projekt, Suche) und Sortierung
- **Tasks/Index.vue**: Vollstaendige Uebersichtsseite mit:
  - 7 Stats-Karten (Total, Open, In Progress, Done, Deferred, Cancelled, Overdue) - klickbar als Quick-Filter
  - Filter-Leiste (Textsuche, Priority, Type, Projekt-Dropdown, Clear-Button)
  - Sortierbare Tabelle (Title, Project, Status, Priority, Type, Due Date, Created)
  - Quick-Status-Toggle per Checkbox (Open -> In Progress -> Done)
  - Ueberfaellige Tasks rot hervorgehoben
  - Pagination bei >50 Tasks
  - Links zum jeweiligen Projekt

### Geaendert
- **routes/web.php**: Route `/tasks` + TaskController Import
- **AuthenticatedLayout.vue**: "Tasks" NavLink zwischen "Projects" und "Inbox" (Desktop + Mobile)

---

## [2026-02-11] - Worker-System

### Hinzugefuegt
- **Worker-System**: Komplettes Job-Ausfuehrungs-System mit Python-Worker und Portal-UI
- **3 Datenbank-Migrations**: workers, worker_jobs Tabellen + worker_config Spalte auf projects
- **2 neue Models**: Worker (mit isOnline(), Heartbeat-Check) und WorkerJob (mit Status-Konstanten, isTerminal())
- **Project Model erweitert**: worker_config (JSON), workerJobs() Relationship
- **2 API Resources**: WorkerResource (mit computed is_online), WorkerJobResource (mit Relationships)
- **5 Form Requests**: WorkerHeartbeat, ClaimWorkerJob, CompleteWorkerJob, StoreWorkerJob, UpdateWorkerJob
- **2 API Controller**:
  - WorkerController: heartbeat, index, pendingJobs, claimJob, startJob, completeJob
  - WorkerJobController: index, store, show, update, approve, cancel, retry
- **13 neue API-Routen**: 6 Worker-Endpoints + 7 Job-CRUD-Endpoints
- **Web WorkerController**: index, createJob, showJob, approveJob, cancelJob, retryJob, updateProjectWorkerConfig
- **7 neue Web-Routen**: Workers-Seite, Job-CRUD, Worker-Config pro Projekt
- **3 Vue-Komponenten**:
  - WorkerStatusCard.vue: Worker-Status-Karte mit Online/Offline-Dot, Heartbeat-Info
  - JobStatusBadge.vue: Farbige Status-Badges (8 Status inkl. animate-pulse fuer running)
  - JobFormModal.vue: Modal zum Job erstellen (Projekt-Auswahl, Type, Priority, Prompt)
- **2 Vue-Seiten**:
  - Workers/Index.vue: Worker-Panel, Stats-Row, Filter, Job-Tabelle mit Aktionen
  - Workers/JobDetail.vue: Vollstaendige Detail-Ansicht mit Timeline, Output, Error-Output
- **Python Worker** (worker/ Verzeichnis): 6 Module
  - worker.py: Main Loop mit Polling, Shutdown-Handling, Thread-Pool
  - config.py: .env-basierte Konfiguration mit Machine-ID-Generierung
  - api_client.py: REST API Client mit Bearer-Auth
  - job_executor.py: 3 Execution-Modi (code_change, new_project, prepared)
  - heartbeat.py: HeartbeatThread + JobTracker (thread-safe)
  - logger_setup.py: Console + File Logging

### Geaendert
- **AuthenticatedLayout.vue**: "Workers" NavLink nach "Activity" (Desktop + Mobile)
- **Dashboard/Index.vue**: Worker-Stats-Karte (X Workers online, Y Jobs running, Z pending)
- **DashboardController**: Worker-Stats Query (online Workers, running/pending Jobs)
- **Projects/Show.vue**: Neuer "Worker" Tab mit Job-Liste und JobStatusBadge
- **Web ProjectController::show()**: workerJobs Prop mitgegeben
- **routes/api.php**: 13 neue Worker-Routen registriert
- **routes/web.php**: 7 neue Worker-Web-Routen + Import
- **config/claude-hub.php**: Worker-Config-Abschnitt (heartbeat_timeout, default_requires_approval, max_job_runtime)
- **.env.example**: Worker-Variablen hinzugefuegt

### Job-Workflow
1. Job erstellen (Portal oder API) -> pending_approval oder direkt queued
2. Freigabe (falls noetig) -> queued
3. Worker pollt -> claimed (atomar)
4. Worker startet -> running
5. Worker fertig -> done/failed + Timeline-Event erstellt

### Offene Punkte
- Phase 5: Automatisierung & Polish (Daily Digest, Stale-Warnings, Export)
- Feature-Tests fuer Worker-Endpoints
- Rate-Limiting auf API-Endpoints
- Dark Mode
- AiChat.vue Komponente

---

## [2026-02-11] - Phase 4: KI-Integration

### Hinzugefuegt
- **OpenAI PHP SDK**: `openai-php/client` v0.19.0 installiert
- **OpenAiService**: Zentraler Service fuer OpenAI API-Calls mit 3 Methoden:
  - `summarize()` - Text zusammenfassen (konfigurierbare Sprache und Laenge)
  - `extractTasks()` - Tasks aus Freitext extrahieren (JSON-Parsing mit Validierung)
  - `projectSummary()` - Projekt-Zusammenfassung generieren (Events, Tasks, Docs)
- **GenerateSummary Job**: Async Queue-Job fuer Auto-Zusammenfassung von langen Events (>500 Zeichen)
- **AiController**: 3 neue API-Endpoints:
  - `POST /api/v1/ai/summarize` - Text zusammenfassen
  - `POST /api/v1/ai/extract-tasks` - Tasks aus Text extrahieren
  - `POST /api/v1/ai/project-summary/{slug}` - Projekt-Zusammenfassung
- **Form Requests**: SummarizeRequest + ExtractTasksRequest mit Validierung
- **SummaryBadge.vue**: Aufklappbare AI-Summary Anzeige (Violet-Badge mit Toggle)

### Geaendert
- **EventController.store()**: Dispatcht GenerateSummary Job automatisch bei Events mit >500 Zeichen Content
- **Projects/Show.vue**: SummaryBadge in Timeline-Events integriert, Event-Interface um summary erweitert
- **routes/api.php**: 3 neue AI-Routen registriert
- **.env.example**: OPENAI_API_KEY und OPENAI_MODEL Platzhalter hinzugefuegt

### Getestet
- TypeScript-Check erfolgreich (vue-tsc --noEmit)
- Frontend-Build erfolgreich (vite build)
- API-Routen verifiziert (php artisan route:list)

### Offene Punkte
- Phase 5: Automatisierung & Polish (Daily Digest, Stale-Warnings, Export)
- Voice Notes (Whisper-Integration) - bewusst uebersprungen
- Feature-Tests fuer AI-Endpoints
- Rate-Limiting auf AI-Endpoints (10/min)
- Dark Mode
- AiChat.vue Komponente (interaktiver KI-Chat)

---

## [2026-02-11] - Phase 3: Suche, Dashboard & Notizen

### Hinzugefuegt
- **Volltextsuche**: LIKE-basierte Suche ueber Projects, Events, Tasks, Documents mit Typ-Filter
- **SearchController (Web)**: Suchseite mit Debounced-Query und Ergebnis-Karten
- **Search/Index.vue**: Such-UI mit Echtzeit-Ergebnissen und Typ-Filterung
- **Inbox/Feed-View**: Chronologischer Event-Stream mit Typ- und Projekt-Filter, Pagination
- **InboxController**: Feed aller Events ueber alle Projekte
- **ProjectHealthService**: Health-Score-Berechnung (Basis 100, Abzuege fuer Inaktivitaet, ueberfaellige Tasks, Task-Ratio)
- **Dashboard erweitert**: 6 Stat-Karten, Health-Score-Anzeige pro Projekt, Stale-Project-Erkennung (>7 Tage)
- **Activity Log Web-Ansicht**: ActivityLogController + Index.vue mit Timeline-Layout und Filtern
- **Factory-Klassen**: Alle 11 Models haben vollstaendige Factories
- **Notes API**: NoteController mit CRUD (GET/POST/PATCH/DELETE), Team-Isolation, Author-Check
- **StoreNoteRequest**: Validierung fuer Notes (notable_type, notable_id, content, parent_id, source)
- **Notes Web-CRUD**: storeNote, updateNote, destroyNote Methoden im Web ProjectController
- **NoteForm.vue**: Formular-Komponente zum Erstellen von Notizen (Markdown-Support)
- **NoteThread.vue**: Threaded-Kommentare mit Reply, Edit, Delete, Author-Avatare, Source-Badges
- **Notes-Tab**: Neuer Tab in Projects/Show.vue fuer Projekt-Notizen

### Geaendert
- **routes/api.php**: 4 neue Notes-Endpoints (GET/POST/PATCH/DELETE /notes)
- **routes/web.php**: 3 neue Notes-Routen + Inbox, Search, Activity Routen
- **Projects/Show.vue**: 4. Tab "Notes" hinzugefuegt, usePage fuer currentUserId
- **Web ProjectController**: show() laedt jetzt auch Notes mit User + Replies

### Getestet
- TypeScript-Check erfolgreich (vue-tsc --noEmit)
- Frontend-Build erfolgreich (vite build)

### Offene Punkte
- Phase 4: KI-Integration (OpenAI)
- Phase 5: Automatisierung & Polish
- Feature-Tests schreiben
- Dark Mode implementieren
- Rate-Limiting auf API-Endpoints

---

## [2026-02-10] - Phase 2: Dokumente & Tasks

### Hinzugefuegt
- **Markdown-Rendering**: `marked` + `DOMPurify` Libraries fuer sicheres Markdown-Rendering
- **MarkdownRenderer Komponente**: Wiederverwendbare Vue-Komponente mit Styling fuer Headings, Code-Blocks, Listen, Tabellen
- **Document Viewer Modal**: Dokument-Inhalt als gerendertes Markdown anzeigen
- **Document Form Modal**: Dokumente erstellen und neue Versionen speichern (mit Change Notes)
- **Task Detail Modal**: Task-Details anzeigen mit Beschreibung (Markdown), Labels, Status, Priority
- **Task Form Modal**: Tasks erstellen und bearbeiten mit allen Feldern (Status, Priority, Type, Due Date)
- **Task-Filterung**: Dropdown-Filter im Tasks-Tab (All, Open, In Progress, Done, Deferred, Cancelled)
- **Quick Status Change**: "Start" und "Done" Buttons direkt auf Task-Karten im Kanban-Board
- **Confirm Dialog**: Wiederverwendbare Bestaetigungs-Komponente fuer Delete-Aktionen
- **StatusBadge Komponente**: Generische farbige Badge-Komponente (9 Farben)
- **Pagination Komponente**: Laravel-Pagination mit Inertia-Links
- **ActivityLogService**: Zentraler Service fuer Activity-Logging (statische log/logFromRequest Methoden)
- **Activity Log Web-Ansicht**: Eigene Seite mit Timeline-Layout, Projekt- und Aktions-Filter
- **Navigation erweitert**: Activity-Link in Desktop- und Mobile-Navigation

### Geaendert
- **Projects/Show.vue**: Komplett ueberarbeitet mit Modal-Integration, Markdown-Rendering in Timeline, CRUD-Buttons fuer Documents und Tasks, Kanban mit Quick-Actions
- **Web ProjectController**: 6 neue Methoden (storeDocument, updateDocument, destroyDocument, storeTask, updateTask, destroyTask)
- **API Controller** (Project, Event, Task, Document): Activity-Logging bei allen CRUD-Operationen hinzugefuegt
- **Web Routes**: Document/Task CRUD-Routen + Activity Log Route hinzugefuegt
- **API Routes**: Activity Log Endpoint fuer Projekte hinzugefuegt

### Getestet
- Frontend-Build erfolgreich (vue-tsc + vite build)
- Alle TypeScript-Fehler behoben (Modal max-width Typen)

### Offene Punkte
- Factory-Klassen fuer Models (verschoben auf Phase 3)
- Phase 3: Volltextsuche & Dashboard-Ausbau
- Phase 4: KI-Integration (OpenAI)
- Phase 5: Automatisierung & Polish
- Feature-Tests schreiben
- Dark Mode implementieren
- Rate-Limiting auf API-Endpoints

---

## [2026-02-10] - Phase 1: Fundament (MVP)

### Hinzugefuegt
- **Laravel 12 Projekt** mit Breeze Auth, Inertia.js + Vue 3 + TypeScript
- **10 Datenbank-Migrations**: teams, api_tokens, projects, events, documents, document_versions, tasks, notes, tags, taggables, activity_logs, search_index
- **11 Eloquent Models** mit vollstaendigen Relationships: User, Team, ApiToken, Project, Event, Document, DocumentVersion, Task, Note, Tag, ActivityLog
- **API Token System**: SHA-256 gehashte Tokens, Middleware-Authentifizierung, Token-Generierung im Dashboard
- **REST API v1** mit 22 Endpoints:
  - Projects: CRUD + Search + Stats
  - Events: CRUD mit Typ-Filterung
  - Tasks: CRUD + Bulk-Create
  - Documents: CRUD mit Versionierung
- **8 Form Request** Validierungsklassen
- **11 API Resource** Klassen fuer konsistente JSON-Responses
- **Web Dashboard** mit Inertia.js:
  - Dashboard-Uebersicht (Stats, Projekte, Activity Feed)
  - Projektliste mit Suche und Status-Filter
  - Projekt-Detailseite mit Tabs (Timeline, Tasks, Documents)
  - API Token Verwaltung (Erstellen, Anzeigen, Loeschen)
- **Navigation** in AuthenticatedLayout erweitert (Dashboard, Projects, API Tokens)
- **Auto-Team-Erstellung** bei User-Registrierung
- **Flash-Data Sharing** via Inertia Middleware
- **Config-Datei** claude-hub.php fuer App-spezifische Konfiguration

### Getestet
- API-Endpoints manuell verifiziert (Create Project, Create Event, Bulk Tasks, Stats)
- Token-Authentifizierung funktioniert (401 ohne Token)
- Frontend-Build erfolgreich (vue-tsc + vite build)
- Server startet und antwortet korrekt
