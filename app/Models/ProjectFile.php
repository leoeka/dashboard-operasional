<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProjectFile extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'original_name', 'file_path', 'category'];

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
            'logo' => 'Logo',
            'company_profile' => 'Company Profile',
            'foto' => 'Foto',
            'dokumen' => 'Dokumen',
            'pendukung' => 'File Pendukung',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? 'Lainnya';
    }
}