<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Log an activity.
     */
    public static function log(
        string $action,
        Model $subject,
        ?int $teamId = null,
        ?int $userId = null,
        ?int $projectId = null,
        ?array $properties = null,
        ?string $ipAddress = null
    ): ActivityLog {
        return ActivityLog::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'project_id' => $projectId,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'properties' => $properties,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Log an activity from a request.
     */
    public static function logFromRequest(
        Request $request,
        string $action,
        Model $subject,
        ?int $projectId = null,
        ?array $properties = null
    ): ActivityLog {
        $teamId = $request->attributes->get('team_id') ?? null;
        $userId = auth()->id();

        return self::log(
            $action,
            $subject,
            $teamId,
            $userId,
            $projectId,
            $properties,
            $request->ip()
        );
    }
}
