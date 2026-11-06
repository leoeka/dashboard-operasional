<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MockupAsset extends Model
{
    use HasFactory;

    protected $table = 'mockup_assets';

    protected $fillable = [
        'project_id',
        'category',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->file_path);
    }

    public static function categoryLabels(): array
    {
        return [
            'logo' => 'Logo Klien',
            'company_profile' => 'Company Profile',
            'foto' => 'Foto / Gambar',
            'dokumen' => 'Dokumen',
            'pendukung' => 'File Pendukung',
            'referensi' => 'Referensi Desain',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? ucfirst($this->category);
    }
}

