<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DownloadController extends Controller
{
    public function skill(Request $request): StreamedResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $apiUrl = rtrim(config('app.url'), '/') . '/api/v1';

        return response()->streamDownload(function () use ($apiUrl) {
            $zip = new ZipArchive();
            $tmpFile = tempnam(sys_get_temp_dir(), 'brain-skill-');
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // plugin.json
            $pluginJson = json_encode([
                'name' => 'brain',
                'version' => '1.0.0',
                'description' => 'Claude Code Brain Integration - Dokumentiert Projekte, Events, Tasks und Dokumente automatisch im Brain Dashboard',
                'author' => ['name' => 'Claude Code Brain', 'email' => 'hello@realmaker.de'],
                'license' => 'MIT',
                'keywords' => ['brain', 'documentation', 'project-management', 'changelog', 'session', 'events'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('brain/.claude-plugin/plugin.json', $pluginJson);

            // SKILL.md - with dynamic API URL
            $skillPath = storage_path('app/downloads/brain-skill/skills/brain/SKILL.md');
            if (file_exists($skillPath)) {
                $skillContent = file_get_contents($skillPath);
                $skillContent = str_replace('https://brain.realmaker.de/api/v1', $apiUrl, $skillContent);
                $zip->addFromString('brain/skills/brain/SKILL.md', $skillContent);
            }

            // Get hostname for permissions
            $host = parse_url($apiUrl, PHP_URL_HOST) ?: 'brain.realmaker.de';

            // README
            $readme = "# Brain Skill fuer Claude Code\n\n";
            $readme .= "## Installation\n\n";
            $readme .= "1. Entpacke dieses Archiv nach `~/.claude/plugins/dev/`\n";
            $readme .= "2. Stelle sicher, dass der Ordner `~/.claude/plugins/dev/brain/` heisst\n";
            $readme .= "3. Berechtigungen setzen (siehe unten)\n";
            $readme .= "4. Claude Code neu starten\n\n";
            $readme .= "## Berechtigungen\n\n";
            $readme .= "Damit Claude Code ohne Rueckfrage auf die Brain-API zugreifen kann,\n";
            $readme .= "fuege diese Zeilen in `~/.claude/settings.json` unter `permissions.allow` hinzu:\n\n";
            $readme .= "```json\n";
            $readme .= "\"Bash(curl*{$host}*)\",\n";
            $readme .= "\"Bash(python*{$host}*)\",\n";
            $readme .= "\"Bash(python*urllib*{$host}*)\"\n";
            $readme .= "```\n\n";
            $readme .= "## Verwendung\n\n";
            $readme .= "```\n/brain status         - Projekt-Status anzeigen\n";
            $readme .= "/brain log            - Changelog-Event erstellen\n";
            $readme .= "/brain task           - Task anlegen\n";
            $readme .= "/brain tasks          - Offene Tasks anzeigen\n";
            $readme .= "/brain doc            - Dokument hochladen\n";
            $readme .= "/brain snapshot       - Projekt-Snapshot (ZIP) hochladen\n";
            $readme .= "/brain install-notes  - Installationsanleitung setzen\n";
            $readme .= "/brain share          - Projekt mit User teilen\n";
            $readme .= "/brain summary        - Session-Zusammenfassung senden\n";
            $readme .= "/brain search         - Im Brain suchen\n";
            $readme .= "/brain decision       - Architektur-Entscheidung dokumentieren\n";
            $readme .= "/brain init           - Projekt im Brain anlegen\n```\n\n";
            $readme .= "## API-URL\n\n`{$apiUrl}`\n";
            $zip->addFromString('brain/README.md', $readme);

            $zip->close();
            readfile($tmpFile);
            unlink($tmpFile);
        }, 'brain-skill.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function worker(Request $request): StreamedResponse
    {
        $apiUrl = rtrim(config('app.url'), '/') . '/api/v1';

        return response()->streamDownload(function () use ($apiUrl) {
            $zip = new ZipArchive();
            $tmpFile = tempnam(sys_get_temp_dir(), 'brain-worker-');
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $workerDir = storage_path('app/downloads/brain-worker');
            $files = [
                'worker.py',
                'api_client.py',
                'config.py',
                'heartbeat.py',
                'job_executor.py',
                'logger_setup.py',
                'requirements.txt',
            ];

            foreach ($files as $file) {
                $filePath = $workerDir . '/' . $file;
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, 'worker/' . $file);
                }
            }

            // .env.example with dynamic API URL
            $envExample = "WORKER_API_URL={$apiUrl}\n";
            $envExample .= "WORKER_API_TOKEN=DEIN_API_TOKEN_HIER\n";
            $envExample .= "WORKER_NAME=Mein-PC\n";
            $envExample .= "WORKER_WORK_DIR=C:\\Projects\n";
            $envExample .= "WORKER_MAX_PARALLEL=2\n";
            $envExample .= "WORKER_POLL_INTERVAL=10\n";
            $envExample .= "WORKER_HEARTBEAT_INTERVAL=30\n";
            $envExample .= "WORKER_CLAUDE_PATH=claude\n";
            $envExample .= "WORKER_LOG_FILE=worker.log\n";
            $zip->addFromString('worker/.env.example', $envExample);

            // README
            $readme = "# Brain Worker\n\n";
            $readme .= "Fuehrt Claude Code Jobs automatisch aus, gesteuert ueber das Brain Dashboard.\n\n";
            $readme .= "## Installation\n\n";
            $readme .= "```bash\ncd worker\npip install -r requirements.txt\ncp .env.example .env\n```\n\n";
            $readme .= "## Konfiguration\n\n";
            $readme .= "Bearbeite die `.env` Datei:\n";
            $readme .= "- `WORKER_API_URL` - Brain API URL (bereits gesetzt)\n";
            $readme .= "- `WORKER_API_TOKEN` - Dein API Token aus dem Brain Dashboard\n";
            $readme .= "- `WORKER_NAME` - Name fuer diesen Worker\n";
            $readme .= "- `WORKER_WORK_DIR` - Arbeitsverzeichnis fuer Projekte\n";
            $readme .= "- `WORKER_CLAUDE_PATH` - Pfad zur Claude CLI\n\n";
            $readme .= "## Starten\n\n";
            $readme .= "```bash\npython worker.py\n```\n\n";
            $readme .= "Der Worker registriert sich automatisch im Brain und wartet auf Jobs.\n";
            $readme .= "Stoppen mit `Ctrl+C`.\n\n";
            $readme .= "## API-URL\n\n`{$apiUrl}`\n";
            $zip->addFromString('worker/README.md', $readme);

            $zip->close();
            readfile($tmpFile);
            unlink($tmpFile);
        }, 'brain-worker.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
