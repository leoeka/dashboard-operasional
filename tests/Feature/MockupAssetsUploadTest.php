<?php

use App\Models\Client;
use App\Models\MockupAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('it stores client logo and asset files into mockup_assets upon request order creation', function () {
    $user = User::factory()->create();

    $logo = UploadedFile::fake()->image('client-logo.png', 200, 200);
    $assetPhoto = UploadedFile::fake()->image('store-front.jpg', 600, 400);
    $assetDoc = UploadedFile::fake()->create('company-profile.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)->post(route('clients.store'), [
        'company_name' => 'PT Mitra Sejahtera',
        'client_name' => 'Budi Santoso',
        'email' => 'budi@mitra.test',
        'phone' => '081234567890',
        'project_name' => 'Website Profil Mitra',
        'service_type' => ['website'],
        'website_type' => 'Company',
        'user_story' => 'Website korporat modern untuk PT Mitra Sejahtera.',
        'website_features' => 'Halaman Utama, Tentang Kami, Layanan, Kontak',
        'has_client_assets' => '1',
        'client_logo' => $logo,
        'client_asset_files' => [$assetPhoto, $assetDoc],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $project = Project::where('name', 'Website Profil Mitra')->first();
    expect($project)->not->toBeNull();

    // Verifikasi relasi mockupAssets
    $assets = $project->mockupAssets;
    expect($assets->count())->toBe(3);

    // Verifikasi ada kategori logo, foto, dan dokumen
    expect($assets->pluck('category')->all())->toContain('logo', 'foto', 'dokumen');

    // Verifikasi storage & helper
    foreach ($assets as $asset) {
        Storage::disk('public')->assertExists($asset->file_path);
        expect($asset->url())->toBeString();
        expect($asset->file_size)->toBeGreaterThan(0);
    }
});

test('it allows uploading additional mockup assets via project files store endpoint', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'code' => 'PRJ-TEST-01',
        'name' => 'Website Bakery',
        'client_name' => 'Bakery Sejahtera',
        'status' => 'mockup',
    ]);

    $file = UploadedFile::fake()->create('menu-list.pdf', 300, 'application/pdf');

    $response = $this->actingAs($user)->post(route('pages.projects.files.store', $project), [
        'file' => $file,
        'category' => 'dokumen',
    ]);

    $response->assertRedirect();

    $asset = MockupAsset::where('project_id', $project->id)->first();
    expect($asset)->not->toBeNull();
    expect($asset->original_name)->toBe('menu-list.pdf');
    expect($asset->category)->toBe('dokumen');
    expect($asset->categoryLabel())->toBe('Dokumen');

    Storage::disk('public')->assertExists($asset->file_path);
});

test('it allows deleting mockup assets via destroy endpoint', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'code' => 'PRJ-TEST-02',
        'name' => 'Website Kuliner',
        'client_name' => 'Kuliner Nusantara',
        'status' => 'mockup',
    ]);

    $file = UploadedFile::fake()->image('banner.jpg');
    $path = $file->store('mockup-assets/' . $project->id, 'public');

    $asset = MockupAsset::create([
        'project_id' => $project->id,
        'category' => 'foto',
        'original_name' => 'banner.jpg',
        'file_path' => $path,
        'file_size' => 1024,
        'mime_type' => 'image/jpeg',
    ]);

    Storage::disk('public')->assertExists($path);

    $response = $this->actingAs($user)->delete(route('pages.projects.files.destroy', [$project, $asset]));
    $response->assertRedirect();

    expect(MockupAsset::find($asset->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

