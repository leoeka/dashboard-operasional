<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ActivityLog;
use App\Models\Invoice;

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
        'website_features',
        'has_client_assets',
        'website_name',
        'value',
        'status',
        'description', // sebelumnya: 'ai_generated_content'
        'design_reference_type',
        'design_reference_url',
        'design_reference_path',
        'target_market',
        'wants_seo',
        'wants_backlink',
        'seo_requirements',
        'backlink_requirements',
    ];

    protected $casts = [
        'wants_seo' => 'boolean',
        'wants_backlink' => 'boolean',
        'has_client_assets' => 'boolean',
        'seo_requirements' => 'array',
        'backlink_requirements' => 'array',
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

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function mockupCandidates()
    {
        return $this->hasMany(MockupCandidate::class)->orderBy('candidate_number');
    }

    public function clientProposals()
    {
        return $this->hasMany(ClientProposal::class)->latest();
    }

    public function wpBuild()
    {
        return $this->hasOne(WpBuild::class)->latestOfMany();
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'request' => 'slate',
            'in_progress' => 'blue',
            'completed' => 'emerald',
            default => 'slate',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'request' => 'Request',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }

    public function serviceTypes(): array
    {
        return array_values(array_filter([
            ($this->type || (!$this->wants_seo && !$this->wants_backlink)) ? 'website' : null,
            $this->wants_seo ? 'seo' : null,
            $this->wants_backlink ? 'backlink' : null,
        ]));
    }

    public function serviceTypeLabel(): string
    {
        $labels = ['website' => 'Web', 'seo' => 'SEO', 'backlink' => 'Backlink'];
        $types = array_map(fn ($type) => $labels[$type], $this->serviceTypes());

        return implode(' + ', $types) ?: 'Project';
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

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
