<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExtractTasksRequest;
use App\Http\Requests\Api\SummarizeRequest;
use App\Models\Project;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        private OpenAiService $openAiService
    ) {}

    /**
     * Summarize a given text.
     *
     * POST /api/v1/ai/summarize
     */
    public function summarize(SummarizeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $summary = $this->openAiService->summarize(
            $data['text'],
            $data['max_length'] ?? 200,
            $data['language'] ?? 'de'
        );

        if (empty($summary)) {
            return response()->json([
                'error' => [
                    'code' => 'summarization_failed',
                    'message' => 'Failed to generate summary. Please check that OPENAI_API_KEY is configured.',
                ],
            ], 500);
        }

        return response()->json([
            'data' => [
                'summary' => $summary,
            ],
            'message' => 'Text summarized successfully.',
        ]);
    }

    /**
     * Extract task suggestions from text.
     *
     * POST /api/v1/ai/extract-tasks
     */
    public function extractTasks(ExtractTasksRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Verify the project exists within the team
        $project = Project::where('team_id', $request->attributes->get('team_id'))
            ->where('slug', $data['project_slug'])
            ->first();

        if (!$project) {
            return response()->json([
                'error' => [
                    'code' => 'project_not_found',
                    'message' => "Project with slug '{$data['project_slug']}' not found.",
                ],
            ], 404);
        }

        $tasks = $this->openAiService->extractTasks($data['text'], $data['project_slug']);

        if (empty($tasks)) {
            return response()->json([
                'data' => [
                    'tasks' => [],
                ],
                'message' => 'No tasks could be extracted from the given text.',
            ]);
        }

        return response()->json([
            'data' => [
                'tasks' => $tasks,
                'project_slug' => $project->slug,
            ],
            'message' => count($tasks) . ' task(s) extracted successfully.',
        ]);
    }

    /**
     * Generate a project summary.
     *
     * POST /api/v1/ai/project-summary/{slug}
     */
    public function projectSummary(Request $request, string $slug): JsonResponse
    {
        $project = Project::where('team_id', $request->attributes->get('team_id'))
            ->where('slug', $slug)
            ->first();

        if (!$project) {
            return response()->json([
                'error' => [
                    'code' => 'project_not_found',
                    'message' => "Project with slug '{$slug}' not found.",
                ],
            ], 404);
        }

        $summary = $this->openAiService->projectSummary($project);

        if (empty($summary)) {
            return response()->json([
                'error' => [
                    'code' => 'summary_generation_failed',
                    'message' => 'Failed to generate project summary. Please check that OPENAI_API_KEY is configured.',
                ],
            ], 500);
        }

        return response()->json([
            'data' => [
                'summary' => $summary,
                'project_slug' => $project->slug,
                'generated_at' => now()->toIso8601String(),
            ],
            'message' => 'Project summary generated successfully.',
        ]);
    }
}
