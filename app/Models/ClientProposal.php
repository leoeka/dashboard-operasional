<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu proposal PDF yang diunduh tim dan dikirim ke klien. Pilihan klien
 * dicatat tim di dasbor lewat chosen_candidate_id + chosen_at, bukan lewat
 * permukaan publik apa pun.
 */
class ClientProposal extends Model
{
    protected $fillable = [
        'project_id',
        'chosen_candidate_id',
        'pdf_path',
        'sent_at',
        'chosen_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'chosen_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function chosenCandidate()
    {
        return $this->belongsTo(MockupCandidate::class, 'chosen_candidate_id');
    }
}
