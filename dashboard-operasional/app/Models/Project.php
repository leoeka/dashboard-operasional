<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
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
        return $this->hasMany(ActivityLog::class);
    }

    // Warna badge status untuk dipakai di view
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

    public function logActivity(string $description): void
    {
        $this->activityLogs()->create(['description' => $description]);
    }


}
