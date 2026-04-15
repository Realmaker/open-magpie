<div align="center">

# Open Magpie

**A session memory & project hub for terminal AI coding sessions.**

Magpies collect bright, useful things and build a nest from them.
Open Magpie does the same with your AI coding sessions — picking out the
changelogs, decisions, tasks, and summaries that would otherwise be lost
the moment the terminal closes.

[Features](#features) ·
[Why](#why-open-magpie) ·
[How it works](#how-it-works) ·
[Installation](#installation) ·
[API](#api-reference) ·
[Worker](#remote-worker)

</div>

---

## Why Open Magpie

If you use [Claude Code](https://claude.com/claude-code), Codex CLI, Aider,
or any other terminal AI coding tool, you know the pain:

- A session produces **great artifacts** — architecture decisions,
  changelogs, TODOs, documentation — and then it all lives in a terminal
  scrollback that gets closed tomorrow.
- The next session has **no memory of the last one**. You paste context
  back in, re-explain decisions, re-discover the same TODOs.
- Across **dozens of parallel projects**, there's no single place that
  answers *"what happened last week on project X?"* or *"what's the
  current state of everything I'm working on?"*.

Open Magpie is the place your AI sessions write to. It's a self-hostable
hub that keeps the **signal** from every session — the parts worth
remembering — as structured, searchable, versioned data.

## Features

- **Structured session memory** — changelogs, decisions, milestones,
  issues, session summaries, each with their own semantic type.
- **Versioned documentation** — every document write keeps full history
  with change notes.
- **Task tracking** — AI-discovered TODOs become real tasks, with status,
  priority, labels, and due dates.
- **Project snapshots** — zip the project state at milestones and browse
  it in the dashboard, complete with install notes.
- **Multi-user, multi-team** — role-based access, project sharing,
  granular API-token abilities.
- **Full-text search** across events, documents, tasks, and notes.
- **AI features** (optional) — OpenAI-powered summarization, task
  extraction from free-form text, project digests.
- **Remote worker system** — queue jobs for a Python worker running on
  another machine to pick up and execute.
- **Simple HTTP API** — so any tool (Claude Code, a shell script, a
  cron job) can write to it with a single `curl`.

## How it works

```
  ┌─────────────────────────┐        ┌──────────────────────────────┐
  │   Claude Code / any     │  HTTP  │      Open Magpie API         │
  │   terminal AI session   │───────▶│   Laravel 12 · MySQL/SQLite  │
  └─────────────────────────┘        │                              │
                                     │  • projects                  │
  ┌─────────────────────────┐        │  • events (timeline)         │
  │   Python remote worker  │◀─poll──│  • documents (versioned)     │
  │   (job executor)        │──push─▶│  • tasks                     │
  └─────────────────────────┘        │  • notes, tags, snapshots    │
                                     │  • full-text search          │
                                     └──────────────┬───────────────┘
                                                    │
                                                    ▼
                                        ┌────────────────────────┐
                                        │   Vue 3 · Inertia.js   │
                                        │   Dashboard (Web UI)   │
                                        └────────────────────────┘
```

Three layers:

1. **REST API.** The write path. Your AI session POSTs structured
   events, documents, and tasks via a Bearer-token-authenticated HTTP
   API. Designed so a one-line `curl` from inside a session is enough.
2. **Web dashboard.** The read path. Vue 3 + Inertia frontend with a
   project overview, per-project timeline, document viewer with version
   history, task board, full-text search, and settings.
3. **Remote worker (optional).** A Python daemon that polls for jobs,
   runs them on a separate machine (e.g. a headless server), and reports
   results back. Useful for scheduled scans, long-running tasks, or
   CI-like automations triggered from the hub.

## Data model (at a glance)

| Entity      | Purpose                                                          |
|-------------|------------------------------------------------------------------|
| `Team`      | Tenant boundary. Users belong to teams with a role.              |
| `Project`   | The unit of work. Has status, priority, tech stack, health score.|
| `Event`     | Timeline entry: `changelog`, `decision`, `milestone`, `issue`, `session_summary`, … |
| `Document`  | Versioned markdown artifact (README, ADRs, specs, guides).       |
| `Task`      | Work item with status, priority, type, labels, assignee.         |
| `Note`      | Polymorphic comment, attachable to any of the above.             |
| `Snapshot`  | Zipped project state with install notes, browseable in dashboard.|
| `ApiToken`  | Auth for the write API. Granular abilities (e.g. `events:write`).|
| `Worker`    | Registered remote job executor.                                  |
| `WorkerJob` | Queued job awaiting (or running on) a worker.                    |

## Installation

### Requirements

- PHP **8.2+** with the usual Laravel extensions (`mbstring`, `xml`,
  `pdo`, `zip`, `fileinfo`, `openssl`, `tokenizer`, `ctype`, `bcmath`)
- Composer 2
- Node.js **20+**
- A database: **SQLite** works out of the box; **MySQL 8.0+** or
  **MariaDB 10.6+** for FULLTEXT search in production
- *(optional)* Redis for queues and cache
- *(optional)* OpenAI API key for AI features

### Setup

```bash
git clone https://github.com/realmaker/open-magpie.git
cd open-magpie

composer install
cp .env.example .env
php artisan key:generate

# SQLite default (quickest):
touch database/database.sqlite
php artisan migrate

# Build frontend:
npm install
npm run build

# Run everything in dev mode (server, queue, logs, vite):
composer dev
```

Open `http://localhost:8000`, register a user, and you're in. Head to
**Settings → API Tokens** to create a token for your AI sessions.

### Production notes

- Set `APP_ENV=production`, `APP_DEBUG=false`.
- Use MySQL or MariaDB for real full-text search.
- Run `npm run build` on deploy, not `dev`.
- Queue worker: `php artisan queue:work` (or Supervisor).
- Scheduler: wire `php artisan schedule:run` into cron for digests and
  stale-project checks.

## API reference

Base URL: `{APP_URL}/api/v1` · Auth: `Authorization: Bearer <token>`

### Quick examples

Find or create a project from a shell session:

```bash
# Does the project exist?
curl -s -H "Authorization: Bearer $MAGPIE_TOKEN" \
     -H "Accept: application/json" \
     "$MAGPIE_URL/api/v1/projects?search=my-project"

# Create it if not.
curl -s -X POST \
     -H "Authorization: Bearer $MAGPIE_TOKEN" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"name":"My Project","tech_stack":["PHP","Laravel"]}' \
     "$MAGPIE_URL/api/v1/projects"
```

Log a changelog entry from the end of a Claude Code session:

```bash
curl -s -X POST \
     -H "Authorization: Bearer $MAGPIE_TOKEN" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "type": "changelog",
       "title": "Authentication system implemented",
       "content": "## Changes\n- Login/register flow\n- API token generation\n- Middleware",
       "source": "claude-code"
     }' \
     "$MAGPIE_URL/api/v1/projects/my-project/events"
```

Create tasks in bulk from discovered TODOs:

```bash
curl -s -X POST \
     -H "Authorization: Bearer $MAGPIE_TOKEN" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "tasks": [
         {"title":"Add rate limiting","priority":"high","type":"feature"},
         {"title":"Write API docs","priority":"medium","type":"todo"}
       ]
     }' \
     "$MAGPIE_URL/api/v1/projects/my-project/tasks/bulk"
```

### Endpoint overview

| Group       | Route                                                  |
|-------------|--------------------------------------------------------|
| Projects    | `GET/POST /projects` · `GET/PATCH/DELETE /projects/{slug}` · `GET /projects/{slug}/stats` |
| Events      | `GET/POST /projects/{slug}/events` · `GET/PATCH/DELETE /projects/{slug}/events/{id}` |
| Documents   | `GET/POST /projects/{slug}/documents` · `GET/PUT/DELETE /projects/{slug}/documents/{docSlug}` · `GET …/versions` |
| Tasks       | `GET/POST /projects/{slug}/tasks` · `POST /projects/{slug}/tasks/bulk` · `GET/PATCH/DELETE /projects/{slug}/tasks/{id}` |
| Notes       | `GET/POST /notes` · `PATCH/DELETE /notes/{id}`          |
| Snapshots   | `GET/POST /projects/{slug}/snapshots` · `GET …/latest` · `GET …/{v}/download` · `PATCH …/install-notes` |
| Project Shares | `GET/POST /projects/{slug}/shares` · `PATCH/DELETE /projects/{slug}/shares/{id}` · `GET /shared-with-me` |
| AI          | `POST /ai/summarize` · `POST /ai/extract-tasks` · `POST /ai/project-summary/{slug}` |
| Workers     | `POST /worker/heartbeat` · `GET /workers` · `GET /worker/jobs/pending` · `POST /worker/jobs/{id}/{claim,start,complete}` |
| Worker jobs | `GET/POST /worker-jobs` · `GET/PATCH /worker-jobs/{id}` · `POST /worker-jobs/{id}/{approve,cancel,retry}` |

Response envelope:

```json
{ "data": { "…": "…" }, "message": "Project created successfully" }
```

Paginated responses include a `meta` block with `current_page`,
`last_page`, `per_page`, `total`. Errors use the standard Laravel JSON
error shape; common codes: `401`, `403`, `404`, `422`, `429`.

### Event types

`changelog` · `documentation` · `decision` · `milestone` · `note` ·
`task_update` · `session_summary` · `deployment` · `issue` · `review`

Use them intentionally — the dashboard groups and filters by type, and
the AI summarizer weighs them differently.

## Remote worker

The `worker/` directory contains a lightweight Python 3 worker that:

1. Registers with the hub via a heartbeat.
2. Polls `/worker/jobs/pending` and claims jobs it can run.
3. Executes them (shell command, Claude Code invocation, whatever you
   configure) with a runtime cap.
4. Reports stdout/stderr/exit code back to the hub.

```bash
cd worker
cp .env.example .env     # set WORKER_API_URL, WORKER_API_TOKEN, …
pip install -r requirements.txt
python worker.py
```

Jobs can require explicit human approval in the dashboard before they
run (`WORKER_DEFAULT_REQUIRES_APPROVAL=true`). Useful when the worker is
allowed to execute AI-driven actions on your behalf.

## Claude Code integration

Point your `CLAUDE.md` at Open Magpie and Claude Code will document its
own work. A minimal snippet for `~/.claude/CLAUDE.md`:

```markdown
## Open Magpie Integration

After every significant step (feature, decision, bugfix, session end):

1. Find or create the project at `$MAGPIE_URL/api/v1/projects`.
2. POST a matching event to `/projects/{slug}/events`:
   - feature implemented → `type: "changelog"`
   - architecture decision → `type: "decision"`
   - bug fixed → `type: "issue"`
   - session ended → `type: "session_summary"`
3. Open TODOs → POST `/projects/{slug}/tasks` (or `/tasks/bulk`).
4. Written docs → POST `/projects/{slug}/documents`.

Auth: `Authorization: Bearer $MAGPIE_TOKEN`. Always send
`Accept: application/json` and `source: "claude-code"`.
```

That's it — your terminal sessions now have a persistent memory.

## Contributing

Issues and pull requests welcome. Please:

1. Open an issue first for anything non-trivial so we can align on
   direction.
2. Run `composer test` and `./vendor/bin/pint` before pushing.
3. Keep commits focused and write in [Conventional Commits](https://www.conventionalcommits.org/) style.

## License

[MIT](LICENSE) © realmaker

---

<sub>Open Magpie is an independent open-source project. "Claude" and
"Claude Code" are trademarks of Anthropic; Open Magpie is not
affiliated with or endorsed by Anthropic.</sub>
