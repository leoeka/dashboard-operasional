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

    /**
     * What this file is FOR, as far as the mockup designer is concerned.
     *
     * There is no role column yet, so this reads the metadata that already
     * exists — the upload category, then the filename the client gave it —
     * and falls back to `general`. It deliberately does not look at the image
     * itself: classifying pictures with a vision model is a separate decision,
     * not something to smuggle in here.
     *
     * The point is that MockupAssetService can ask for a role instead of
     * grabbing whichever photo happens to have been uploaded first, and the
     * answer improves on its own if a real role column is added later.
     */
    public function assetRole(): string
    {
        if ($this->category === 'logo') {
            return 'logo';
        }

        // Honoured if a role column/attribute is ever added; harmless until then.
        $declared = strtolower(trim((string) ($this->attributes['role'] ?? '')));
        if (in_array($declared, self::ASSET_ROLES, true)) {
            return $declared;
        }

        $name = strtolower((string) $this->original_name);

        foreach (self::ROLE_KEYWORDS as $role => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $role;
                }
            }
        }

        return 'general';
    }

    public const ASSET_ROLES = ['logo', 'hero', 'about', 'service', 'product', 'team', 'gallery', 'general'];

    /** Indonesian and English, since clients name their files in both. */
    private const ROLE_KEYWORDS = [
        'logo' => ['logo'],
        'hero' => ['hero', 'banner', 'header', 'cover', 'sampul'],
        'team' => ['team', 'tim', 'staff', 'karyawan', 'founder', 'owner', 'profil-'],
        'product' => ['product', 'produk', 'menu', 'katalog', 'catalog'],
        'service' => ['service', 'layanan', 'jasa'],
        'about' => ['about', 'tentang', 'company', 'perusahaan'],
        'gallery' => ['gallery', 'galeri', 'portfolio', 'portofolio'],
    ];

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? 'Lainnya';
    }
}