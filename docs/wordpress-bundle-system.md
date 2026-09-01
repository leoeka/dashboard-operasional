# WordPress Bundle System Flow

## Tujuan

Sistem ini bertujuan menggantikan dependency ke ZipWP dengan alur internal produksi website WordPress siap install.

Alur yang dipakai:

```text
Project Request
   ↓
Client Brief
   ↓
Gemini Analysis
   ↓
GPT Content Generation
   ↓
Mockup / Design Selection
   ↓
Client Approval
   ↓
Build Bundle
   ↓
Export ZIP
   ↓
Upload to WordPress
   ↓
Website Ready
```

## Prinsip arsitektur

Sistem dibagi ke dalam tiga layer utama:

1. Intelligence Layer
   - Gemini: menganalisis bisnis, target market, tone brand, struktur halaman
   - GPT: menulis hero, CTA, about, footer, FAQ, SEO copy

2. Production Layer
   - Approved GPT mockup and content
   - Client asset references
   - Bundle builder

3. Output Layer
   - WordPress parent/child theme
   - plugin pendukung
   - Elementor template JSON
   - assets
   - ZIP bundle siap install

## Peran masing-masing AI

### Gemini

Fungsi utama:
- membaca client brief
- menentukan audience dan positioning
- menentukan tone visual
- menyusun halaman utama yang relevan
- memberikan arahan untuk struktur website

Output yang diharapkan:
- business summary
- target market
- tone brand
- recommended page structure
- content priorities

### GPT

Fungsi utama:
- menulis hero section
- CTA text
- about section
- services copy
- footer copy
- FAQ copy
- SEO metadata

Output yang diharapkan:
- hero_title
- hero_subtitle
- hero_cta
- footer_text
- faq_items
- seo_title
- seo_description

### Claude / Builder AI

Fungsi utama:
- memilih template
- menyiapkan child theme
- menempatkan brand data
- membangun halaman demo
- mengisi content ke Elementor templates
- menyiapkan zip export

Output yang diharapkan:
- zip theme
- zip plugin
- elementor template JSON
- install guide
- demo importer

## Arsitektur kelas utama

```text
app/
  Models/
    Project.php
    TemplateBundle.php
    ProjectBrand.php
    ProjectContent.php
    ProjectBundle.php

  Services/
    GeminiBriefAnalyzer.php
    GptContentService.php
    BundleBuilderService.php
    BundleExporterService.php

  Jobs/
    BuildProjectBundleJob.php

  Http/
    Controllers/
      BundleController.php
```

## Flow kerja sistem

### 1. Project Request

User mengisi brief project:
- nama client
- jenis usaha
- lokasi
- kebutuhan website
- halaman yang dibutuhkan
- preferensi style

### 2. Client Brief

Data ini diproses untuk menghasilkan outline website.

### 3. Gemini Analysis

Gemini menilai:
- industri
- target audiens
- positioning
- style tone
- page structure
- CTA strategy

### 4. GPT Content Generation

Output content dibuat dari hasil analisis Gemini.

Contoh output:

```json
{
  "hero": {
    "title": "Premium Wedding Experience in Bali",
    "subtitle": "Create unforgettable moments with custom planning and timeless design.",
    "cta_primary": "Book a Consultation",
    "cta_secondary": "View Portfolio"
  },
  "about": {
    "title": "About Us",
    "content": "We design meaningful events for modern couples..."
  },
  "footer": {
    "text": "Crafting memorable experiences with care and elegance."
  }
}
```

### 5. Template Recommendation

Sistem memilih template master yang paling cocok berdasarkan:
- industry
- target audience
- visual tone
- page requirements

### 6. Mockup / Design Selection

User dapat memilih mockup setelah rekomendasi muncul.

### 7. Brand Input

Brand detail diinput:
- logo
- primary color
- secondary color
- font family
- social links
- phone number
- address

### 8. Content Input

Konten final siap dipublish:
- headline
- paragraphs
- services list
- FAQs
- gallery list

### 9. Build Bundle

Sistem memadukan:
- base theme
- template
- brand data
- content
- plugin support

### 10. Export ZIP

Output bundle dibuat sebagai ZIP.

### 11. Upload to WordPress

WordPress user upload zip dan install:
- child theme
- plugin
- template import
- demo importer

### 12. Website Ready

Website siap jadi starting point untuk client.

## Model data utama

### Project

Project menyimpan status pipeline dan data utama project.

### TemplateBundle

Template bundle berisi master template yang bisa dipakai ulang.

Field umum:
- id
- name
- slug
- category
- template_type
- preview_url
- package_path
- is_active

### ProjectBrand

Field umum:
- project_id
- company_name
- logo_path
- primary_color
- secondary_color
- font_primary
- font_secondary
- phone
- email
- address
- whatsapp

### ProjectContent

Field umum:
- project_id
- hero_title
- hero_subtitle
- about_title
- about_content
- faq_json
- footer_text
- seo_title
- seo_description

### ProjectBundle

Field umum:
- project_id
- template_bundle_id
- bundle_path
- zip_path
- status
- built_at
- exported_at

## Service breakdown

### GeminiBriefAnalyzer

```php
public function analyze(Project $project): array
{
    return [
        'business_summary' => '...',
        'target_market' => '...',
        'tone' => 'premium',
        'recommended_pages' => ['home', 'about', 'services', 'contact'],
    ];
}
```

### GptContentService

```php
public function generate(Project $project, array $analysis): array
{
    return [
        'hero' => [...],
        'about' => [...],
        'services' => [...],
        'faq' => [...],
        'footer' => [...],
    ];
}
```

### BundleBuilderService

```php
public function build(Project $project): array
{
    $template = $this->resolveTemplate($project);
    $brand = $this->resolveBrand($project);
    $content = $this->resolveContent($project);

    $bundle = [
        'theme' => $this->buildThemePackage($template, $brand),
        'plugin' => $this->buildPluginPackage(),
        'elementor' => $this->buildElementorTemplates($template, $content),
        'assets' => $this->copyAssetFiles($project),
    ];

    return $bundle;
}
```

### BundleExporterService

```php
public function export(array $bundle, string $outputDir): string
{
    // zip bundle lalu return path
}
```

## Output bundle final

```text
client-project-name/
├── theme/
│   └── exito-child/
├── plugin/
│   └── exito-core/
├── elementor/
│   ├── home.json
│   ├── about.json
│   ├── contact.json
│   └── faq.json
├── assets/
│   ├── logo.png
│   ├── hero.jpg
│   └── gallery/
├── content/
│   └── website-data.json
├── README.md
├── setup-guide.md
└── client-project-name.zip
```

## Keuntungan dari pendekatan ini

- tidak bergantung pada satu provider eksternal seperti ZipWP
- bundle bisa dibuat ulang dengan skala lebih besar
- template dapat dibangun secara modular
- brand dan konten dipisah dari struktur template
- workflow lebih mudah dikontrol oleh dashboard

## Kesimpulan

Alur ini sangat layak diterapkan. Yang paling penting adalah membagi pekerjaan sesuai perannya:

- Gemini: analisis brief & target
- GPT: generate copy & content
- Builder AI / system: render bundle WordPress siap install

Dengan pola ini, sistem Anda akan bergerak dari sekadar proposal generator menjadi real production system untuk website bundle.
