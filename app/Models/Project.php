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

        static::saved(function (Project $project) {
            if ($project->wants_seo || !empty($project->attributes['seo_requirements'])) {
                $raw = $project->attributes['seo_requirements'] ?? null;
                $data = is_string($raw) ? json_decode($raw, true) : ($raw ?? []);
                if (!empty($data)) {
                    ProjectSeo::updateOrCreate(
                        ['project_id' => $project->id],
                        [
                            'target_url' => $data['target_url'] ?? null,
                            'location' => $data['location'] ?? null,
                            'keywords' => is_array($data['keywords'] ?? null) ? implode(', ', $data['keywords']) : ($data['keywords'] ?? null),
                            'competitors' => is_array($data['competitors'] ?? null) ? implode("\n", $data['competitors']) : ($data['competitors'] ?? null),
                            'cms_platform' => $data['cms_platform'] ?? null,
                            'data' => $data,
                        ]
                    );
                }
            }

            if ($project->wants_backlink || !empty($project->attributes['backlink_requirements'])) {
                $raw = $project->attributes['backlink_requirements'] ?? null;
                $data = is_string($raw) ? json_decode($raw, true) : ($raw ?? []);
                if (!empty($data)) {
                    ProjectBacklink::updateOrCreate(
                        ['project_id' => $project->id],
                        [
                            'target_url' => $data['target_url'] ?? null,
                            'quantity' => isset($data['quantity']) ? (string) $data['quantity'] : null,
                            'anchor_text' => is_array($data['anchor_text'] ?? null) ? implode(', ', $data['anchor_text']) : ($data['anchor_text'] ?? null),
                            'niche' => $data['niche'] ?? null,
                            'anchor_type' => $data['anchor_type'] ?? null,
                            'priority' => $data['priority'] ?? null,
                            'data' => $data,
                        ]
                    );
                }
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

    public function seo()
    {
        return $this->hasOne(ProjectSeo::class);
    }

    public function backlink()
    {
        return $this->hasOne(ProjectBacklink::class);
    }

    public function mockupAssets()
    {
        return $this->hasMany(MockupAsset::class)->latest();
    }

    public function files()
    {
        return $this->mockupAssets();
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

    public function getSeoRequirementsAttribute($value)
    {
        $seo = $this->relationLoaded('seo') ? $this->getRelation('seo') : $this->seo()->first();
        if ($seo) {
            $data = $seo->data ?? [];
            if ($seo->target_url !== null) $data['target_url'] = $seo->target_url;
            if ($seo->keywords !== null && !isset($data['keywords'])) $data['keywords'] = $seo->keywords;
            if ($seo->location !== null && !isset($data['location'])) $data['location'] = $seo->location;
            if ($seo->competitors !== null && !isset($data['competitors'])) $data['competitors'] = $seo->competitors;
            if ($seo->cms_platform !== null && !isset($data['cms_platform'])) $data['cms_platform'] = $seo->cms_platform;
            return $data;
        }

        return is_string($value) ? json_decode($value, true) : ($value ?? []);
    }

    public function setSeoRequirementsAttribute($value)
    {
        $data = is_string($value) ? json_decode($value, true) : ($value ?? []);
        $this->attributes['seo_requirements'] = is_array($value) ? json_encode($value) : $value;

        if ($this->exists && is_array($data) && !empty($data)) {
            ProjectSeo::updateOrCreate(
                ['project_id' => $this->id],
                [
                    'target_url' => $data['target_url'] ?? null,
                    'location' => $data['location'] ?? null,
                    'keywords' => is_array($data['keywords'] ?? null) ? implode(', ', $data['keywords']) : ($data['keywords'] ?? null),
                    'competitors' => is_array($data['competitors'] ?? null) ? implode("\n", $data['competitors']) : ($data['competitors'] ?? null),
                    'cms_platform' => $data['cms_platform'] ?? null,
                    'data' => $data,
                ]
            );
        }
    }

    public function getBacklinkRequirementsAttribute($value)
    {
        $bl = $this->relationLoaded('backlink') ? $this->getRelation('backlink') : $this->backlink()->first();
        if ($bl) {
            $data = $bl->data ?? [];
            if ($bl->target_url !== null) $data['target_url'] = $bl->target_url;
            if ($bl->quantity !== null && !isset($data['quantity'])) $data['quantity'] = is_numeric($bl->quantity) ? (int) $bl->quantity : $bl->quantity;
            if ($bl->anchor_text !== null && !isset($data['anchor_text'])) $data['anchor_text'] = $bl->anchor_text;
            if ($bl->niche !== null && !isset($data['niche'])) $data['niche'] = $bl->niche;
            if ($bl->anchor_type !== null && !isset($data['anchor_type'])) $data['anchor_type'] = $bl->anchor_type;
            if ($bl->priority !== null && !isset($data['priority'])) $data['priority'] = $bl->priority;
            return $data;
        }

        return is_string($value) ? json_decode($value, true) : ($value ?? []);
    }

    public function setBacklinkRequirementsAttribute($value)
    {
        $data = is_string($value) ? json_decode($value, true) : ($value ?? []);
        $this->attributes['backlink_requirements'] = is_array($value) ? json_encode($value) : $value;

        if ($this->exists && is_array($data) && !empty($data)) {
            ProjectBacklink::updateOrCreate(
                ['project_id' => $this->id],
                [
                    'target_url' => $data['target_url'] ?? null,
                    'quantity' => isset($data['quantity']) ? (string) $data['quantity'] : null,
                    'anchor_text' => is_array($data['anchor_text'] ?? null) ? implode(', ', $data['anchor_text']) : ($data['anchor_text'] ?? null),
                    'niche' => $data['niche'] ?? null,
                    'anchor_type' => $data['anchor_type'] ?? null,
                    'priority' => $data['priority'] ?? null,
                    'data' => $data,
                ]
            );
        }
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
