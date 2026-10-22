<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu proses pembangunan tema WordPress dari satu kandidat terpilih.
 * "ready" hanya setelah meja periksa lolos; hasil periksa tersimpan apa adanya.
 */
class WpBuild extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_BUILDING = 'building';
    public const STATUS_INSPECTION = 'inspection';
    public const STATUS_READY = 'ready';
    public const STATUS_INSPECTION_FAILED = 'inspection_failed';

    protected $fillable = [
        'project_id',
        'mockup_candidate_id',
        'folder',
        'zip_path',
        'status',
        'inspection_results',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'inspection_results' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function candidate()
    {
        return $this->belongsTo(MockupCandidate::class, 'mockup_candidate_id');
    }
}
