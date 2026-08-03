<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property string|null $preview_image
 * @property string|null $theme_slug
 * @property string|null $source_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate wherePreviewImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereSourceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereThemeSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MockupTemplate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MockupTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'preview_image', 'theme_slug', 'source_url'];

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
}