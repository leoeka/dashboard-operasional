<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MockupTemplate;
use App\Models\ProposalItem;
use App\Models\ActivityLog;
use App\Models\Invoice;



/**
 * @property int $id
 * @property int|null $client_id
 * @property string $code
 * @property string|null $name
 * @property string $client_name
 * @property string|null $type
 * @property int|null $mockup_template_id
 * @property string|null $ai_generated_content
 * @property string $status
 * @property int $progress
 * @property numeric|null $value
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read \App\Models\Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectFile> $files
 * @property-read int|null $files_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read MockupTemplate|null $mockupTemplate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectProposalItem> $proposalItems
 * @property-read int|null $proposal_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Proposal> $proposals
 * @property-read int|null $proposals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectTask> $tasks
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereAiGeneratedContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereClientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereMockupTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereProgress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereValue($value)
 * @mixin \Eloquent
 */
class Project extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->code)) {
                $project->code = strtoupper(\Illuminate\Support\Str::random(3)) . '-' . random_int(1000, 9999);
            }
        });
    }

    protected $fillable = [
        'client_id',
        'code',
        'name',
        'client_name',
        'type',
        'mockup_template_id',
        'status',
        'progress',
        'deadline',
        'ai_generated_content',
    ];

    protected $casts = [
        'deadline' => 'date',
        'progress' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class)->orderBy('position');
    }

    public function files()
    {
        return $this->hasMany(ProjectFile::class)->latest();
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'request' => 'slate',
            'proposal' => 'purple',
            'mockup' => 'blue',
            'development' => 'amber',
            'qa' => 'pink',
            'active', 'done' => 'emerald',
            default => 'slate',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'request' => 'Request',
            'proposal' => 'Proposal',
            'mockup' => 'Mockup',
            'development' => 'Development',
            'qa' => 'QA',
            'active' => 'Aktif',
            'done' => 'Selesai',
            default => ucfirst($this->status),
        };
    }

    public function recalculateProgress(): int
    {
        $total = $this->tasks()->count();
        $progress = $total === 0 ? 0 : (int) round(($this->tasks()->where('is_done', true)->count() / $total) * 100);

        $this->update(['progress' => $progress]);

        return $progress;
    }

    // Ditulis ke tabel activity_logs yang sudah ada (kolom: action, status), bukan tabel baru
    public function logActivity(string $description): void
    {
        $this->activityLogs()->create([
            'client_name' => $this->client_name,
            'action' => $description,
            'status' => 'info',
        ]);
    }

    public function mockupTemplate()
    {
        return $this->belongsTo(MockupTemplate::class, 'mockup_template_id');
    }

    public function latestProposal()
    {
        return $this->hasOne(Proposal::class)->latestOfMany();
    }
}