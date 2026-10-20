<?php

use App\Models\Project;
use App\Models\User;

/**
 * The edit screen is the only place the team can change a project's services,
 * its design reference and its SEO/backlink requirements after the request
 * form is gone. Saving rewrites the same JSON blobs the AI jobs write to, so
 * these tests pin the two halves that used to fight each other: the form must
 * come back pre-ticked with the services this project already has, and saving
 * it must not destroy analysis the workspace depends on.
 */
function editProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'name' => 'Nusa Trails Web',
        'client_name' => 'Nusa Trails',
        'code' => 'EDT-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'type' => 'Travel',
        'description' => 'Menjual paket trekking ke wisatawan yang mencari lewat Google.',
        'website_features' => 'Beranda, daftar paket, booking, kontak.',
        'has_client_assets' => true,
        'status' => 'in_progress',
        'wants_seo' => true,
        'wants_backlink' => true,
        'seo_requirements' => [
            'target_url' => 'https://nusatrails.example',
            'cms_platform' => 'wordpress',
            'location' => 'Denpasar',
            'competitors' => "a.example\nb.example",
            'ai_recommendations' => ['main_keywords' => [['keyword' => 'kopibali']]],
            'ai_identified_topics' => ['core_topics' => ['kopi']],
            'pagespeed' => ['overall_score' => 91],
            'search_console' => ['clicks' => 10],
            'google_analytics' => ['sessions' => 4],
            'onpage_audit' => ['score' => 70],
            'manual_screenshots' => [['id' => 1]],
            'selected_competitors' => ['a.example'],
        ],
        'backlink_requirements' => [
            'target_url' => 'https://nusatrails.example',
            'quantity' => 10,
            'niche' => 'Travel',
            'priority' => 'quantity',
        ],
    ]);
}

function editGet(Project $project): string
{
    return test()->actingAs(User::factory()->create())
        ->get(route('pages.projects.edit', $project))
        ->assertOk()
        ->getContent();
}

function editPut(Project $project, array $data)
{
    return test()->actingAs(User::factory()->create())
        ->put(route('pages.projects.update', $project), $data);
}

function renderedServiceTypes(string $html): array
{
    preg_match_all('/name="service_type\[\]" value="([a-z]+)"[^>]*\bchecked\b/', $html, $m);

    return $m[1];
}

function renderedSelect(string $html, string $name): ?string
{
    preg_match('/<select[^>]*\bname="' . $name . '"[^>]*>.*?<\/select>/s', $html, $m);

    if (!preg_match('/<option value="([a-z_]+)"[^>]*\bselected\b/', $m[0] ?? '', $o)) {
        return null;
    }

    return $o[1];
}

function formHtml(Project $project): string
{
    $html = editGet($project);
    $start = strpos($html, '<form method="POST" id="projectForm"');
    $end = strpos($html, '</form>', $start);

    return substr($html, $start, $end - $start);
}

function editPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nusa Trails Web',
        'client_name' => 'Nusa Trails',
        'type' => 'Travel',
        'status' => 'in_progress',
        // Tiga isian bagian website wajib selama tile "Create Website"
        // dicentang, sama seperti di form Request.
        'user_story' => 'Menjual paket trekking ke wisatawan yang mencari lewat Google.',
        'website_features' => 'Beranda, daftar paket, booking, kontak.',
        'has_client_assets' => '1',
    ], $overrides);
}

it('opens the edit form with the services this project already has ticked', function () {
    $html = editGet(editProject());

    expect(renderedServiceTypes($html))->toBe(['website', 'seo', 'backlink'])
        ->and(renderedSelect($html, 'backlink_priority'))->toBe('quantity');
});

it('keeps SEO and backlink on when the form is saved exactly as it renders', function () {
    $project = editProject();
    $html = editGet($project);

    editPut($project, editPayload([
        'service_type' => renderedServiceTypes($html),
        'seo_target_url' => 'https://nusatrails.example',
        'seo_cms_platform' => 'wordpress',
        'backlink_target_url' => 'https://nusatrails.example',
        'backlink_quantity' => 12,
        'backlink_priority' => renderedSelect($html, 'backlink_priority'),
    ]))->assertRedirect();

    $fresh = $project->fresh();

    expect($fresh->wants_seo)->toBeTrue()
        ->and($fresh->wants_backlink)->toBeTrue()
        ->and($fresh->backlink_requirements['quantity'])->toBe(12)
        ->and($fresh->backlink_requirements['priority'])->toBe('quantity');
});

it('opens with the website brief already stored on this project', function () {
    $html = editGet(editProject());

    expect($html)->toContain('Menjual paket trekking ke wisatawan')
        ->and($html)->toContain('Beranda, daftar paket, booking, kontak.')
        ->and((bool) preg_match('/name="has_client_assets" value="1"[^>]*\bchecked\b/', $html))->toBeTrue();
});

it('saves the website brief back where the request form put it', function () {
    $project = editProject();

    editPut($project, editPayload([
        'service_type' => ['website'],
        'name' => 'Nusa Trails Booking',
        'user_story' => 'Fokus booking online, bukan profil perusahaan.',
        'website_features' => 'Beranda, booking, galeri, FAQ.',
        'has_client_assets' => '0',
    ]))->assertRedirect();

    $fresh = $project->fresh();

    expect($fresh->name)->toBe('Nusa Trails Booking')
        ->and($fresh->website_name)->toBe('Nusa Trails Booking')
        ->and($fresh->description)->toBe('Fokus booking online, bukan profil perusahaan.')
        ->and($fresh->website_features)->toBe('Beranda, booking, galeri, FAQ.')
        ->and($fresh->has_client_assets)->toBeFalse();
});

it('keeps stored SEO analysis when SEO is saved again', function () {
    $project = editProject();

    editPut($project, editPayload([
        'service_type' => ['website', 'seo'],
        'seo_target_url' => 'https://baru.example',
        'seo_cms_platform' => 'wordpress',
    ]))->assertRedirect();

    $seo = $project->fresh()->seo_requirements;

    expect($seo['target_url'])->toBe('https://baru.example')
        ->and($seo['pagespeed']['overall_score'])->toBe(91)
        ->and($seo['ai_recommendations'])->not->toBeNull()
        ->and($seo['ai_identified_topics'])->not->toBeNull()
        ->and($seo['search_console'])->not->toBeNull()
        ->and($seo['google_analytics'])->not->toBeNull()
        ->and($seo['onpage_audit'])->not->toBeNull()
        ->and($seo['manual_screenshots'])->not->toBeNull()
        ->and($seo['selected_competitors'])->toBe(['a.example'])
        ->and($seo['competitors'])->toBe("a.example\nb.example");
});

it('clears a requirement the operator emptied without touching the reports', function () {
    $project = editProject();

    editPut($project, editPayload([
        'service_type' => ['seo'],
        'seo_target_url' => '',
        'seo_location' => '',
        'seo_cms_platform' => 'wordpress',
    ]))->assertRedirect();

    $seo = $project->fresh()->seo_requirements;

    // Middleware mengubah string kosong jadi null, jadi field yang
    // dikosongkan operator datang sebagai key bernilai null - dan key itu
    // tetap harus menghapus nilai lama.
    expect($seo['target_url'])->toBeNull()
        ->and($seo['location'])->toBeNull()
        ->and($seo['pagespeed'])->not->toBeNull()
        ->and($seo['competitors'])->toBe("a.example\nb.example");
});

it('clears the design reference file when its type no longer points at one', function () {
    $project = editProject();
    $project->update(['design_reference_type' => 'zip', 'design_reference_path' => 'design-references/old.zip']);

    editPut($project, editPayload([
        'design_reference_type' => 'url',
        'design_reference_url' => 'https://ref.example',
    ]))->assertRedirect();

    $fresh = $project->fresh();

    expect($fresh->design_reference_path)->toBeNull()
        ->and($fresh->design_reference_url)->toBe('https://ref.example');
});

it('keeps the stored reference path when the type still uses a file', function () {
    $project = editProject();
    $project->update(['design_reference_type' => 'image', 'design_reference_path' => 'design-references/ref.png']);

    editPut($project, editPayload(['design_reference_type' => 'image']))->assertRedirect();

    expect($project->fresh()->design_reference_path)->toBe('design-references/ref.png');
});

it('hides the reference fields its type does not use', function () {
    $html = editGet(editProject());

    // File dan URL acuan dibungkus supaya label beserta kontrolnya bisa
    // disembunyikan bersamaan, dan yang tersembunyi dimatikan agar isinya
    // tidak ikut terkirim - sama seperti form Request.
    expect($html)->toContain('id="designReferenceFileField"')
        ->and($html)->toContain('id="designReferenceUrlField"')
        ->and((bool) preg_match('/toggleDesignReferenceFields[\s\S]*?fileInput\.disabled[\s\S]*?toggleDesignReferenceFields\(\);/', $html))->toBeTrue();
});

it('keeps no invented example text inside the fields', function () {
    $form = formHtml(editProject());

    // Placeholder satu-satunya adalah awalan https://, yang memang format
    // yang diterima kolom itu. Contoh karangan ("e.g. Travel, Business")
    // hanya mengulang label dan menutupi isi yang tersimpan.
    preg_match_all('/\bplaceholder="([^"]*)"/', $form, $m);

    expect($m[1])->toBe(['https://']);
});

it('warns that filling an empty website URL starts the AI analysis', function () {
    $html = editGet(editProject());

    // Pemilik meminta dua caption ini tetap ada (2026-10-20) setelah semua
    // teks bantu lain dihapus: analisis itu memakan kuota dan berjalan
    // sendiri, jadi tim harus tahu sebelum menekan Save Changes.
    expect($html)->toContain('starts that analysis automatically')
        ->and($html)->toContain('Check keywords and competitors before saving');
});

it('ignores a stale SEO url once the SEO service is unticked', function () {
    $project = Project::create([
        'name' => 'Backlink saja', 'client_name' => 'Nusa Trails', 'code' => 'EDT-BL01',
        'type' => 'Travel', 'status' => 'in_progress', 'wants_backlink' => true,
        'seo_requirements' => ['target_url' => 'https://lama.example', 'pagespeed' => ['overall_score' => 80]],
        'backlink_requirements' => ['quantity' => 5],
    ]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))->assertOk()->getContent();

    expect($html)->toContain('website URL has not been filled in')
        ->and($html)->not->toContain('SEO Requirements');
});

it('uses the backlink url when SEO is off but the backlink url is filled', function () {
    $project = Project::create([
        'name' => 'Backlink siap', 'client_name' => 'Nusa Trails', 'code' => 'EDT-BL02',
        'type' => 'Travel', 'status' => 'in_progress', 'wants_backlink' => true,
        'seo_requirements' => ['target_url' => 'https://lama.example'],
        'backlink_requirements' => ['target_url' => 'https://backlink.example', 'quantity' => 5],
    ]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))->assertOk()->getContent();

    expect($html)->not->toContain('website URL has not been filled in');
});

it('rejects a service_type that is not a list instead of crashing', function () {
    editPut(editProject(), editPayload(['service_type' => 'seo']))->assertSessionHasErrors('service_type');
});

it('points every field label at a control that exists on the page', function () {
    $form = formHtml(editProject());

    preg_match_all('/<label[^>]*\bfor="([^"]+)"/', $form, $wanted);
    preg_match_all('/\bid="([^"]+)"/', $form, $have);

    expect($wanted[1])->not->toBeEmpty()
        ->and(array_diff($wanted[1], $have[1]))->toBe([]);
});

it('keeps every control in the form on the 44px touch floor', function () {
    $form = formHtml(editProject());

    preg_match_all('/<(input|select|button)\b[^>]*>/i', $form, $m);

    $tooSmall = array_values(array_filter($m[0], function (string $tag) {
        // Kotak centang dan pilihan Ya/Tidak diukur lewat kartu labelnya, bukan
        // dirinya, dan hidden input tidak punya bidang sentuh.
        if (preg_match('/type="(hidden|checkbox|radio)"/', $tag)) {
            return false;
        }

        return !str_contains($tag, 'min-h-11');
    }));

    expect($tooSmall)->toBe([]);
});

it('reaches the server with every field the controller cannot save without', function () {
    $html = editGet(editProject());
    $start = strpos($html, '<form method="POST" id="projectForm"');
    $end = strpos($html, '</form>', $start);
    $form = substr($html, $start, $end - $start);
    $outside = substr($html, 0, $start) . substr($html, $end);

    // A control may sit outside <form> as long as it names the form it belongs
    // to. Naming a form that does not exist silently drops the field, and the
    // save then comes back as "The status field is required."
    $unbound = array_values(array_filter(['name', 'client_name', 'status'], function (string $field) use ($form, $outside) {
        if (str_contains($form, 'name="' . $field . '"')) {
            return false;
        }

        $bound = '/name="' . $field . '"[^>]*\bform="projectForm"|form="projectForm"[^>]*\bname="' . $field . '"/';

        return !preg_match($bound, $outside);
    }));

    expect($unbound)->toBe([]);
});
