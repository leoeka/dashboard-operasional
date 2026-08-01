<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'status',
        'progress',
        'deadline',
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
}