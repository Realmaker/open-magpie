<?php

namespace App\Services;

use App\Models\Project;
use Carbon\Carbon;

class ProjectHealthService
{
    /**
     * Calculate health score for a project (0-100).
     *
     * Rules from CLAUDE.md:
     * - Basis: 100 Punkte
     * - -5 pro Tag ohne Aktivitaet (ab Tag 3)
     * - -10 fuer jedes ueberfaellige Task
     * - -5 wenn Verhaeltnis offene/erledigte Tasks > 3:1
     * - Minimum: 0, Maximum: 100
     */
    public static function calculate(Project $project): int
    {
        $score = 100;

        // Deduction for inactivity (after 3 days, -5 per day)
        if ($project->last_activity_at) {
            $daysSinceActivity = (int) Carbon::parse($project->last_activity_at)->diffInDays(now());
            if ($daysSinceActivity > 3) {
                $score -= ($daysSinceActivity - 3) * 5;
            }
        } else {
            // No activity ever recorded - penalize heavily
            $daysSinceCreation = (int) $project->created_at->diffInDays(now());
            if ($daysSinceCreation > 3) {
                $score -= ($daysSinceCreation - 3) * 5;
            }
        }

        // Deduction for overdue tasks (-10 each)
        $overdueTasks = $project->tasks()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();
        $score -= $overdueTasks * 10;

        // Deduction if open/done ratio > 3:1
        $openTasks = $project->tasks()
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
        $doneTasks = $project->tasks()
            ->where('status', 'done')
            ->count();

        if ($doneTasks > 0 && ($openTasks / $doneTasks) > 3) {
            $score -= 5;
        } elseif ($doneTasks === 0 && $openTasks > 3) {
            $score -= 5;
        }

        return max(0, min(100, $score));
    }

    /**
     * Update health scores for all projects of a team.
     */
    public static function updateForTeam(int $teamId): void
    {
        $projects = Project::where('team_id', $teamId)->get();
        foreach ($projects as $project) {
            $newScore = self::calculate($project);
            if ($project->health_score !== $newScore) {
                $project->update(['health_score' => $newScore]);
            }
        }
    }
}
