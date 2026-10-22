<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu kandidat mockup buatan AI untuk satu project - tiga per generasi,
 * dan regenerasi menambah baris baru alih-alih menimpa yang lama.
 */
class MockupCandidate extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_TEAM_APPROVED = 'team_approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_CHOSEN = 'chosen';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'project_id',
        'candidate_number',
        'idea_sentence',
        'folder',
        'status',
        'png_path',
        'critique_rounds',
        'failure_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'critique_rounds' => 0,
    ];

    protected $casts = [
        'candidate_number' => 'integer',
        'critique_rounds' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Baris klaim kerja yang masih dijalankan loop AI: status draft dan
     * belum ada alasan gagal. Baris seperti ini belum boleh disentuh ulang
     * (regenerasi / generasi baru) supaya dua loop tidak menulis folder
     * kandidat yang sama.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->failure_reason === null;
    }

    /**
     * Baris klaim kerja yang loop-nya sudah gagal: status draft dan
     * alasan gagalnya tercatat. Kartunya di gerbang menampilkan alasan itu
     * plus tombol coba lagi, bukan gambar setengah jadi.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->failure_reason !== null;
    }

    public function build()
    {
        return $this->hasOne(WpBuild::class);
    }
}
