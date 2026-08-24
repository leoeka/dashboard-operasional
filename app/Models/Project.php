<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MockupTemplate;
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
        'business_type',
        'value',
        'mockup_template_id',
        'zipwp_template_uuid',
        'zipwp_template_name',
        'zipwp_template_preview_url',
        'zipwp_site_uuid',
        'zipwp_site_url',
        'status',
        'description', // sebelumnya: 'ai_generated_content'
        'business_goal',
        'wants_seo',
        'wants_backlink',
        'seo_requirements',
        'backlink_requirements',
    ];

    protected $casts = [
        'wants_seo' => 'boolean',
        'wants_backlink' => 'boolean',
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

    public function serviceTypeLabel(): string
    {
        $types = [];

        if ($this->type || (!$this->wants_seo && !$this->wants_backlink)) {
            $types[] = 'Web';
        }
        if ($this->wants_seo) {
            $types[] = 'SEO';
        }
        if ($this->wants_backlink) {
            $types[] = 'Backlink';
        }

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

    public function mockupTemplate()
    {
        return $this->belongsTo(MockupTemplate::class, 'mockup_template_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function latestProposal()
    {
        return $this->hasOne(Proposal::class)->latestOfMany();
    }

    public function autoMatchMockupTemplate(): ?MockupTemplate
    {
        // TODO: nanti diganti pemanggilan AI sungguhan (baca requirement_notes
        // secara semantik, bukan cuma cocokkan kategori). Untuk sekarang,
        // cocokkan berdasarkan kategori dari `type` project ke kategori template.
        $categoryKey = collect(MockupTemplate::categories())->search($this->type);

        if ($categoryKey) {
            $match = MockupTemplate::where('category', $categoryKey)->inRandomOrder()->first();
            if ($match) {
                return $match;
            }
        }

        // Fallback kalau tidak ketemu kategori yang cocok sama sekali
        return MockupTemplate::inRandomOrder()->first();
    }
}