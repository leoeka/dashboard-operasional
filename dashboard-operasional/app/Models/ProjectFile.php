<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $project_id
 * @property string $original_name
 * @property string $file_path
 * @property string|null $category
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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