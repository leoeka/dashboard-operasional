<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MockupTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'preview_image',
        'theme_slug',
        'source_url',
        'site_uuid',
        'description',
        'zipwp_deleted_at',
    ];

    protected $casts = [
        'zipwp_deleted_at' => 'datetime',
    ];

    public static function categories(): array
    {
        return [
            'company_profile' => 'Company Profile',
            'ecommerce' => 'E-commerce',
            'travel' => 'Travel & Tourism',
            'restaurant' => 'Restaurant & Cafe',
            'portfolio' => 'Portfolio',
            'landing_page' => 'Landing Page',
            'education' => 'Education',
            'real_estate' => 'Real Estate',
            'other' => 'Lainnya',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? ucfirst($this->category);
    }

    public function previewUrl(): ?string
    {
        return $this->preview_image ? Storage::disk('public')->url($this->preview_image) : null;
    }

    /**
     * True kalau situs ini masih hidup di ZipWP (belum pernah dihapus).
     * Dipakai buat nentuin tampil-tidaknya tombol live preview/wp-admin/delete.
     */
    public function isLiveOnZipWp(): bool
    {
        return !empty($this->site_uuid) && is_null($this->zipwp_deleted_at);
    }
}