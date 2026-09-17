<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\PipelineCheckpoint;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * Lets the AI pipeline stop safely and pick up where it left off.
 *
 * A provider failing used to lose the whole run: the job threw, and the next
 * attempt restarted at the business analysis — paying Gemini and the designer
 * again to get back to the same failed step, and burning image credits on
 * candidates that had already been produced once.
 *
 * Each stage now records its result the moment it succeeds. A retry replays the
 * completed stages from that record and only re-runs the one that failed. That
 * also keeps retries idempotent: nothing upstream is recomputed, so nothing
 * upstream can produce a second proposal, a second set of assets or a different
 * blueprint than the one already frozen.
 */
class PipelineCheckpointService
{
    /** Ordered — each stage consumes what the stage before it stored. */
    public const STAGES = [
        'competitor_research',
        'gemini_analysis',
        'design_blueprint',
        'mockup_assets',
        'proposal_document',
    ];

    /**
     * Returns the stage's stored result if it has already succeeded; otherwise
     * runs it, stores the result and returns it.
     *
     * A ProviderException is recorded against the stage (classification only —
     * never a key, see ProviderException::sanitise()) and rethrown, so the
     * caller can report it and the next run knows where to resume.
     */
    public function remember(Project $project, string $stage, \Closure $work): mixed
    {
        $existing = $this->completed($project, $stage);

        if ($existing !== null) {
            Log::info('Pipeline stage dilewati, sudah selesai sebelumnya.', [
                'project_id' => $project->id,
                'stage' => $stage,
            ]);

            return $existing['payload'];
        }

        try {
            $result = $work();
        } catch (\Throwable $e) {
            $this->recordFailure($project, $stage, ProviderException::fromThrowable($this->providerFor($stage), $e));

            throw $e;
        }

        $this->recordSuccess($project, $stage, $result);

        return $result;
    }

    /** @return array{payload: mixed}|null */
    public function completed(Project $project, string $stage): ?array
    {
        $checkpoint = PipelineCheckpoint::where('project_id', $project->id)
            ->where('stage', $stage)
            ->where('status', PipelineCheckpoint::COMPLETED)
            ->first();

        return $checkpoint ? ['payload' => $checkpoint->payload] : null;
    }

    public function recordSuccess(Project $project, string $stage, mixed $payload): void
    {
        PipelineCheckpoint::updateOrCreate(
            ['project_id' => $project->id, 'stage' => $stage],
            [
                'status' => PipelineCheckpoint::COMPLETED,
                'payload' => $payload,
                'error_code' => null,
                'error_message' => null,
                'error_detail' => null,
            ]
        );
    }

    public function recordFailure(Project $project, string $stage, ProviderException $e): void
    {
        PipelineCheckpoint::updateOrCreate(
            ['project_id' => $project->id, 'stage' => $stage],
            [
                'status' => PipelineCheckpoint::FAILED,
                'payload' => null,
                'error_code' => $e->errorCode,
                'error_message' => $e->getMessage(),
                'error_detail' => $e->detail,
            ]
        );

        Log::error('Pipeline stage gagal.', array_merge(
            ['project_id' => $project->id, 'stage' => $stage],
            $e->context()
        ));
    }

    /** The stage a retry would resume at, or null when everything is done. */
    public function nextStage(Project $project): ?string
    {
        foreach (self::STAGES as $stage) {
            if ($this->completed($project, $stage) === null) {
                return $stage;
            }
        }

        return null;
    }

    /** The failure a person needs to see, if the last run stopped on one. */
    public function failure(Project $project): ?PipelineCheckpoint
    {
        return PipelineCheckpoint::where('project_id', $project->id)
            ->where('status', PipelineCheckpoint::FAILED)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Clears the record so the next run starts from scratch. Only for a
     * deliberate "generate again from the beginning" — an ordinary retry must
     * NOT call this, or it would pay for every stage a second time.
     */
    public function reset(Project $project): void
    {
        PipelineCheckpoint::where('project_id', $project->id)->delete();
    }

    /** Which provider a stage talks to, for classifying an unclassified throwable. */
    private function providerFor(string $stage): string
    {
        return match ($stage) {
            'gemini_analysis', 'competitor_research' => 'gemini',
            'design_blueprint', 'mockup_assets' => 'openai',
            default => 'pipeline',
        };
    }
}
