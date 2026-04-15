<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAiService
{
    private ?\OpenAI\Client $client = null;

    private function getClient(): \OpenAI\Client
    {
        if ($this->client === null) {
            $apiKey = config('claude-hub.openai_api_key');

            if (empty($apiKey)) {
                throw new \RuntimeException('OpenAI API key is not configured. Set OPENAI_API_KEY in your .env file.');
            }

            $this->client = OpenAI::client($apiKey);
        }

        return $this->client;
    }

    private function getModel(): string
    {
        return config('claude-hub.openai_model', 'gpt-4o');
    }

    /**
     * Summarize a text using OpenAI.
     */
    public function summarize(string $text, int $maxLength = 200, string $language = 'de'): string
    {
        try {
            $languageInstruction = $language === 'de'
                ? 'Antworte auf Deutsch.'
                : 'Respond in English.';

            $response = $this->getClient()->chat()->create([
                'model' => $this->getModel(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Du bist ein präziser Zusammenfasser. Fasse den gegebenen Text in maximal {$maxLength} Zeichen zusammen. Gib nur die Zusammenfassung zurück, keine Einleitung oder Erklärung. {$languageInstruction}",
                    ],
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            return trim($response->choices[0]->message->content ?? '');
        } catch (\Throwable $e) {
            Log::error('OpenAI summarize failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            return '';
        }
    }

    /**
     * Extract task suggestions from a text.
     *
     * @return array<int, array{title: string, priority: string, type: string}>
     */
    public function extractTasks(string $text, string $projectSlug): array
    {
        try {
            $response = $this->getClient()->chat()->create([
                'model' => $this->getModel(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein Projektmanagement-Assistent. Extrahiere aus dem gegebenen Text konkrete Aufgaben/Tasks. Gib ein JSON-Array zurück mit Objekten die folgende Felder haben: "title" (kurzer Task-Titel), "priority" (low/medium/high/critical), "type" (task/bug/feature/improvement/research/todo). Gib NUR das JSON-Array zurück, keinen weiteren Text.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '[]');

            // Strip markdown code fences if present
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $tasks = json_decode($content, true);

            if (!is_array($tasks)) {
                return [];
            }

            // Validate and normalize each task
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            $validTypes = ['task', 'bug', 'feature', 'improvement', 'research', 'todo'];

            return array_values(array_filter(array_map(function ($task) use ($validPriorities, $validTypes) {
                if (!is_array($task) || empty($task['title'])) {
                    return null;
                }

                return [
                    'title' => (string) $task['title'],
                    'priority' => in_array($task['priority'] ?? '', $validPriorities) ? $task['priority'] : 'medium',
                    'type' => in_array($task['type'] ?? '', $validTypes) ? $task['type'] : 'task',
                ];
            }, $tasks)));
        } catch (\Throwable $e) {
            Log::error('OpenAI extractTasks failed', [
                'error' => $e->getMessage(),
                'project_slug' => $projectSlug,
            ]);

            return [];
        }
    }

    /**
     * Generate a project summary based on recent events, tasks, and documents.
     */
    public function projectSummary(Project $project): string
    {
        try {
            // Gather project context
            $recentEvents = $project->events()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(['type', 'title', 'created_at']);

            $openTasks = $project->tasks()
                ->whereIn('status', ['open', 'in_progress'])
                ->get(['title', 'status', 'priority', 'type']);

            $doneTasks = $project->tasks()
                ->where('status', 'done')
                ->count();

            $documents = $project->documents()
                ->get(['title', 'category']);

            // Build context text
            $context = "Projekt: {$project->name}\n";
            $context .= "Status: {$project->status}\n";
            $context .= "Priorität: {$project->priority}\n";

            if ($project->description) {
                $context .= "Beschreibung: {$project->description}\n";
            }

            if ($project->tech_stack) {
                $context .= "Tech-Stack: " . implode(', ', $project->tech_stack) . "\n";
            }

            $context .= "\n--- Letzte Events ---\n";
            foreach ($recentEvents as $event) {
                $context .= "- [{$event->type}] {$event->title} ({$event->created_at->format('d.m.Y')})\n";
            }

            $context .= "\n--- Offene Tasks ({$openTasks->count()}) ---\n";
            foreach ($openTasks as $task) {
                $context .= "- [{$task->status}] [{$task->priority}] {$task->title}\n";
            }

            $context .= "\nErledigte Tasks: {$doneTasks}\n";

            $context .= "\n--- Dokumente ({$documents->count()}) ---\n";
            foreach ($documents as $doc) {
                $context .= "- [{$doc->category}] {$doc->title}\n";
            }

            $response = $this->getClient()->chat()->create([
                'model' => $this->getModel(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein Projektmanagement-Assistent. Erstelle eine kompakte, übersichtliche Zusammenfassung des aktuellen Projektstands auf Deutsch. Die Zusammenfassung sollte enthalten: 1) Aktueller Status und Fortschritt, 2) Wichtigste kürzliche Aktivitäten, 3) Offene Aufgaben und Prioritäten, 4) Empfehlungen für nächste Schritte. Formatiere die Ausgabe als Markdown.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $context,
                    ],
                ],
                'max_tokens' => 1500,
                'temperature' => 0.4,
            ]);

            return trim($response->choices[0]->message->content ?? '');
        } catch (\Throwable $e) {
            Log::error('OpenAI projectSummary failed', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);

            return '';
        }
    }
}
