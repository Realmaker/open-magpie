<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\OpenAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private int $eventId
    ) {}

    public function handle(OpenAiService $openAiService): void
    {
        $event = Event::find($this->eventId);

        if (!$event) {
            Log::warning('GenerateSummary: Event not found', ['event_id' => $this->eventId]);
            return;
        }

        // Only generate if content is long enough and summary is still empty
        if (strlen($event->content) <= 500 || $event->summary !== null) {
            return;
        }

        $summary = $openAiService->summarize($event->content);

        if (!empty($summary)) {
            $event->update(['summary' => $summary]);
        }
    }
}
