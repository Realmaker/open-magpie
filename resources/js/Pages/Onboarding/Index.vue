<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Props {
    apiUrl: string;
    hasTeam: boolean;
    hasToken: boolean;
    userName: string;
    isTeamAdmin: boolean;
}

const props = defineProps<Props>();

const currentStep = ref(1);
const copiedField = ref('');

const totalSteps = 5;

const claudeMdSnippet = computed(() => {
    return `## Claude Code Brain Integration

Dieses Projekt wird im Claude Code Brain dokumentiert.
API-Endpoint: ${props.apiUrl}

### Nach signifikanten Arbeitsschritten automatisch dokumentieren:

1. **Projekt pruefen/anlegen:**
   curl -s -H "Authorization: Bearer \$CLAUDE_HUB_TOKEN" \\
     "${props.apiUrl}/projects?search=PROJEKTNAME"

2. **Aenderungen als Event dokumentieren:**
   curl -s -X POST -H "Authorization: Bearer \$CLAUDE_HUB_TOKEN" \\
     -H "Content-Type: application/json" \\
     -d '{"type": "changelog", "title": "...", "content": "...", "source": "claude-code"}' \\
     "${props.apiUrl}/projects/SLUG/events"

3. **Tasks fuer offene Punkte:**
   curl -s -X POST -H "Authorization: Bearer \$CLAUDE_HUB_TOKEN" \\
     -H "Content-Type: application/json" \\
     -d '{"title": "...", "type": "todo", "source": "claude-code"}' \\
     "${props.apiUrl}/projects/SLUG/tasks"

4. **Installationsanleitung setzen:**
   curl -s -X PATCH -H "Authorization: Bearer \$CLAUDE_HUB_TOKEN" \\
     -H "Content-Type: application/json" -H "Accept: application/json" \\
     -d '{"install_notes": "# Installation\\n\\nSchritte..."}' \\
     "${props.apiUrl}/projects/SLUG/install-notes"

5. **Projekt-Snapshot hochladen (nach Meilensteinen):**
   cd /pfad/zum/projekt
   zip -r /tmp/snapshot.zip . \\
     -x "node_modules/*" "vendor/*" ".git/*" ".env" ".env.*" \\
        "storage/logs/*" "storage/framework/*" "__pycache__/*" \\
        "*.log" ".DS_Store" "public/build/*" ".idea/*" ".vscode/*"
   curl -s -X POST -H "Authorization: Bearer \$CLAUDE_HUB_TOKEN" \\
     -H "Accept: application/json" \\
     -F "file=@/tmp/snapshot.zip" \\
     -F "change_note=Beschreibung" -F "source=claude-code" \\
     "${props.apiUrl}/projects/SLUG/snapshots"
   rm /tmp/snapshot.zip
   rm /tmp/snapshot.zip`;
});

const testCurlCmd = computed(() => {
    return 'curl -s -H "Authorization: Bearer DEIN_TOKEN" ' + props.apiUrl + '/projects';
});

const apiHost = computed(() => {
    try { return new URL(props.apiUrl).hostname; } catch { return 'brain.realmaker.de'; }
});

const settingsSnippet = computed(() => {
    const host = apiHost.value;
    return `// In ~/.claude/settings.json unter "permissions.allow" hinzufuegen:

"Bash(curl*${host}*)",
"Bash(python*${host}*)",
"Bash(python*urllib*${host}*)"`;
});

const workerEnvSnippet = computed(() => {
    return `WORKER_API_URL=${props.apiUrl}
WORKER_API_TOKEN=DEIN_API_TOKEN_HIER
WORKER_NAME=${props.userName}-PC
WORKER_WORK_DIR=C:\\Projects
WORKER_MAX_PARALLEL=2
WORKER_POLL_INTERVAL=10
WORKER_HEARTBEAT_INTERVAL=30
WORKER_CLAUDE_PATH=claude
WORKER_LOG_FILE=worker.log`;
});

function copyToClipboard(text: string, field: string) {
    navigator.clipboard.writeText(text);
    copiedField.value = field;
    setTimeout(() => { copiedField.value = ''; }, 2000);
}

function nextStep() {
    if (currentStep.value < totalSteps) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

function goToStep(step: number) {
    currentStep.value = step;
}
</script>

<template>
    <Head title="Onboarding" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Onboarding
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-8 text-center">
                    <h1 class="text-3xl font-bold text-gray-900">Willkommen im Brain</h1>
                    <p class="mt-2 text-gray-600">In {{ totalSteps }} Schritten zur automatischen Projekt-Dokumentation</p>
                </div>

                <!-- Step Indicator -->
                <div class="mb-8 flex items-center justify-center space-x-2">
                    <template v-for="step in totalSteps" :key="step">
                        <button
                            @click="goToStep(step)"
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                                currentStep === step
                                    ? 'bg-indigo-600 text-white'
                                    : currentStep > step
                                        ? 'bg-green-500 text-white'
                                        : 'bg-gray-200 text-gray-500'
                            ]"
                        >
                            <span v-if="currentStep > step">&#10003;</span>
                            <span v-else>{{ step }}</span>
                        </button>
                        <div v-if="step < totalSteps" class="h-0.5 w-8 bg-gray-200">
                            <div
                                :class="['h-full transition-all', currentStep > step ? 'bg-green-500' : 'bg-gray-200']"
                                :style="{ width: currentStep > step ? '100%' : '0%' }"
                            ></div>
                        </div>
                    </template>
                </div>

                <!-- Step Content -->
                <div class="overflow-hidden rounded-lg bg-white shadow">

                    <!-- Step 1: API Token -->
                    <div v-if="currentStep === 1" class="p-8">
                        <div class="mb-4 flex items-center space-x-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">1</div>
                            <h2 class="text-xl font-semibold text-gray-900">API Token erstellen</h2>
                        </div>
                        <p class="mb-6 text-gray-600">
                            Du brauchst einen API Token, damit Claude Code mit dem Brain kommunizieren kann.
                        </p>

                        <div v-if="hasToken" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                            <p class="font-medium text-green-800">Du hast bereits einen API Token erstellt.</p>
                            <p class="mt-1 text-sm text-green-600">Falls du einen neuen brauchst, gehe zu
                                <a :href="route('settings.api-tokens')" class="underline">API Tokens</a>.
                            </p>
                        </div>

                        <div v-else class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-medium text-amber-800">Noch kein API Token vorhanden.</p>
                            <p class="mt-1 text-sm text-amber-600">
                                Gehe zu <a :href="route('settings.api-tokens')" class="font-semibold underline">API Tokens</a>
                                und erstelle einen neuen Token. Kopiere ihn — er wird nur einmal angezeigt!
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-gray-700">Deine API-URL:</h3>
                            <div class="flex items-center space-x-2">
                                <code class="flex-1 rounded bg-gray-900 px-3 py-2 text-sm text-green-400">{{ apiUrl }}</code>
                                <button
                                    @click="copyToClipboard(apiUrl, 'apiUrl')"
                                    class="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
                                >
                                    {{ copiedField === 'apiUrl' ? 'Kopiert!' : 'Kopieren' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Skill & CLAUDE.md -->
                    <div v-if="currentStep === 2" class="p-8">
                        <div class="mb-4 flex items-center space-x-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">2</div>
                            <h2 class="text-xl font-semibold text-gray-900">/brain Skill installieren</h2>
                        </div>
                        <p class="mb-6 text-gray-600">
                            Der <code class="rounded bg-gray-100 px-1 text-sm">/brain</code> Skill gibt dir einfache Befehle statt langer curl-Aufrufe.
                        </p>

                        <!-- Download Buttons -->
                        <div class="mb-6 grid gap-4 sm:grid-cols-2">
                            <a :href="route('downloads.skill')"
                                class="flex items-center justify-center rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4 text-center hover:border-indigo-400 hover:bg-indigo-100 transition-colors">
                                <div>
                                    <div class="mb-1 text-2xl">&#128268;</div>
                                    <div class="font-semibold text-indigo-900">/brain Skill herunterladen</div>
                                    <div class="mt-1 text-xs text-indigo-600">brain-skill.zip</div>
                                </div>
                            </a>
                            <a :href="route('downloads.worker')"
                                class="flex items-center justify-center rounded-lg border-2 border-emerald-200 bg-emerald-50 p-4 text-center hover:border-emerald-400 hover:bg-emerald-100 transition-colors">
                                <div>
                                    <div class="mb-1 text-2xl">&#9881;&#65039;</div>
                                    <div class="font-semibold text-emerald-900">Worker herunterladen</div>
                                    <div class="mt-1 text-xs text-emerald-600">brain-worker.zip</div>
                                </div>
                            </a>
                        </div>

                        <!-- Skill Installation -->
                        <div class="mb-6 rounded-lg bg-gray-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-gray-700">Skill installieren:</h3>
                            <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-600">
                                <li>ZIP herunterladen und entpacken</li>
                                <li>Den <code class="rounded bg-gray-200 px-1">brain/</code> Ordner nach <code class="rounded bg-gray-200 px-1">~/.claude/plugins/dev/</code> kopieren</li>
                                <li>Berechtigungen setzen (siehe unten)</li>
                                <li>Claude Code neu starten</li>
                                <li>Mit <code class="rounded bg-gray-200 px-1">/brain status</code> testen</li>
                            </ol>
                        </div>

                        <!-- Permissions -->
                        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-amber-800">Wichtig: Berechtigungen setzen</h3>
                            <p class="mb-3 text-sm text-amber-700">
                                Damit Claude Code ohne Rueckfrage auf die Brain-API zugreifen darf,
                                fuege diese Zeilen in <code class="rounded bg-amber-100 px-1">~/.claude/settings.json</code>
                                unter <code class="rounded bg-amber-100 px-1">"permissions" &gt; "allow"</code> hinzu:
                            </p>
                            <div class="relative">
                                <button
                                    @click="copyToClipboard(settingsSnippet, 'settings')"
                                    class="absolute right-2 top-2 rounded bg-amber-600 px-3 py-1 text-xs text-white hover:bg-amber-700"
                                >
                                    {{ copiedField === 'settings' ? 'Kopiert!' : 'Kopieren' }}
                                </button>
                                <pre class="rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>{{ settingsSnippet }}</code></pre>
                            </div>
                        </div>

                        <!-- CLAUDE.md Alternative -->
                        <details class="rounded-lg border border-gray-200">
                            <summary class="cursor-pointer p-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Alternative: Manuell per CLAUDE.md konfigurieren (inkl. Snapshots)
                            </summary>
                            <div class="border-t p-4">
                                <p class="mb-3 text-sm text-gray-600">
                                    Falls du den Skill nicht nutzen moechtest, fuege diesen Block in deine
                                    <code class="rounded bg-gray-100 px-1">~/.claude/CLAUDE.md</code> ein:
                                </p>
                                <div class="relative">
                                    <button
                                        @click="copyToClipboard(claudeMdSnippet, 'claudeMd')"
                                        class="absolute right-2 top-2 rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700"
                                    >
                                        {{ copiedField === 'claudeMd' ? 'Kopiert!' : 'Kopieren' }}
                                    </button>
                                    <pre class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>{{ claudeMdSnippet }}</code></pre>
                                </div>
                            </div>
                        </details>
                    </div>

                    <!-- Step 3: Worker Setup -->
                    <div v-if="currentStep === 3" class="p-8">
                        <div class="mb-4 flex items-center space-x-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">3</div>
                            <h2 class="text-xl font-semibold text-gray-900">Worker einrichten (optional)</h2>
                        </div>
                        <p class="mb-6 text-gray-600">
                            Der Worker fuehrt Claude Code Jobs automatisch aus. Er pollt das Brain nach neuen Auftraegen
                            und meldet Ergebnisse zurueck.
                        </p>

                        <div class="mb-6 space-y-4">
                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">1. Worker herunterladen</h3>
                                <p class="text-sm text-gray-600">
                                    Der Worker liegt im <code class="rounded bg-gray-100 px-1">worker/</code> Verzeichnis des Projekts.
                                    Kopiere den Ordner auf den Rechner, auf dem Claude Code laeuft.
                                </p>
                            </div>

                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">2. Dependencies installieren</h3>
                                <pre class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-green-400"><code>pip install -r requirements.txt</code></pre>
                            </div>

                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">3. Worker <code>.env</code> konfigurieren</h3>
                                <div class="relative">
                                    <button
                                        @click="copyToClipboard(workerEnvSnippet, 'workerEnv')"
                                        class="absolute right-2 top-2 rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700"
                                    >
                                        {{ copiedField === 'workerEnv' ? 'Kopiert!' : 'Kopieren' }}
                                    </button>
                                    <pre class="rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>{{ workerEnvSnippet }}</code></pre>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">4. Worker starten</h3>
                                <pre class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-green-400"><code>python worker.py</code></pre>
                                <p class="mt-1 text-sm text-gray-500">
                                    Der Worker registriert sich automatisch und wartet auf Jobs.
                                    Status im <a :href="route('workers.index')" class="text-indigo-600 underline">Workers Dashboard</a> pruefen.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Features Overview -->
                    <div v-if="currentStep === 4" class="p-8">
                        <div class="mb-4 flex items-center space-x-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">4</div>
                            <h2 class="text-xl font-semibold text-gray-900">Weitere Features</h2>
                        </div>
                        <p class="mb-6 text-gray-600">
                            Das Brain bietet neben der automatischen Dokumentation noch weitere nuetzliche Features.
                        </p>

                        <div class="space-y-4">
                            <!-- Snapshots -->
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Projekt-Snapshots</h3>
                                        <p class="mt-1 text-sm text-gray-600">
                                            Lade ZIP-Dateien deiner Projekte hoch. Das Brain zeigt die Dateistruktur als
                                            aufklappbaren Baum, speichert Versionen und du kannst eine Installationsanleitung hinterlegen.
                                            Claude Code kann Snapshots auch automatisch nach Meilensteinen pushen.
                                        </p>
                                        <p class="mt-2 text-xs text-gray-500">
                                            Zu finden im <strong>Files-Tab</strong> jedes Projekts.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Sharing -->
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Projekt-Sharing</h3>
                                        <p class="mt-1 text-sm text-gray-600">
                                            Teile Projekte mit anderen Usern per E-Mail. Drei Berechtigungsstufen:
                                            <strong>Betrachter</strong> (nur lesen), <strong>Bearbeiter</strong> (lesen + erstellen),
                                            <strong>Administrator</strong> (alles + weiter teilen).
                                            Registrierte User erhalten sofort Zugriff, andere bekommen eine Einladungs-Mail.
                                        </p>
                                        <p class="mt-2 text-xs text-gray-500">
                                            Ueber den <strong>Teilen-Button</strong> im Projekt-Header (nur fuer Team-Admins).
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- User Management -->
                            <div v-if="isTeamAdmin" class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Benutzerverwaltung</h3>
                                        <p class="mt-1 text-sm text-gray-600">
                                            Als Team-Admin kannst du registrierte User zu deinem Team hinzufuegen,
                                            Rollen aendern (Admin/Mitglied/Betrachter) und User entfernen.
                                        </p>
                                        <a :href="route('settings.users')" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">
                                            Zur Benutzerverwaltung &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- API Abilities -->
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">API-Sicherheit</h3>
                                        <p class="mt-1 text-sm text-gray-600">
                                            API-Tokens koennen mit granularen Berechtigungen eingeschraenkt werden
                                            (z.B. nur <code class="bg-gray-100 px-1 rounded text-xs">projects:read</code>).
                                            Alle Endpoints sind rate-limited (60/min API, 10/min AI).
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Test -->
                    <div v-if="currentStep === 5" class="p-8">
                        <div class="mb-4 flex items-center space-x-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">5</div>
                            <h2 class="text-xl font-semibold text-gray-900">Testen</h2>
                        </div>
                        <p class="mb-6 text-gray-600">
                            Pruefe ob alles funktioniert. Fuehre diesen Befehl in deinem Terminal aus:
                        </p>

                        <div class="mb-6 space-y-4">
                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">API-Verbindung testen</h3>
                                <div class="relative">
                                    <button
                                        @click="copyToClipboard(testCurlCmd, 'testCmd')"
                                        class="absolute right-2 top-2 rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700"
                                    >
                                        {{ copiedField === 'testCmd' ? 'Kopiert!' : 'Kopieren' }}
                                    </button>
                                    <pre class="rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>curl -s -H "Authorization: Bearer DEIN_TOKEN" \
  {{ apiUrl }}/projects</code></pre>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Erwartete Antwort: JSON mit <code class="rounded bg-gray-100 px-1">"data": [...]</code></p>
                            </div>

                            <div>
                                <h3 class="mb-2 text-sm font-semibold text-gray-700">Oder mit dem /brain Skill</h3>
                                <pre class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-green-400"><code>/brain status</code></pre>
                                <p class="mt-1 text-sm text-gray-500">Zeigt den Status des aktuellen Projekts im Brain.</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-center">
                            <div class="mb-2 text-3xl">&#127881;</div>
                            <h3 class="text-lg font-semibold text-green-800">Fertig!</h3>
                            <p class="mt-1 text-sm text-green-600">
                                Ab jetzt dokumentiert Claude Code automatisch alle wichtigen Aenderungen im Brain.
                                Schau dir das <a :href="route('dashboard')" class="font-semibold underline">Dashboard</a> an.
                            </p>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between border-t bg-gray-50 px-8 py-4">
                        <button
                            v-if="currentStep > 1"
                            @click="prevStep"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Zurueck
                        </button>
                        <div v-else></div>
                        <button
                            v-if="currentStep < totalSteps"
                            @click="nextStep"
                            class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Weiter
                        </button>
                        <a
                            v-else
                            :href="route('dashboard')"
                            class="rounded-lg bg-green-600 px-6 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Zum Dashboard
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
