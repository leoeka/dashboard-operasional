{{-- Page 4: Website development cost --}}
<div class="content-page">
    <div class="header-band">
        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
        <span class="date">{{ now()->format('n/j/Y') }}</span>
    </div>

    <h2 class="section-title">Website Development Services COST</h2>

    <table class="cost-table">
        <thead>
            <tr>
                <th style="width:28%">SERVICES</th>
                <th style="width:20%">COST</th>
                <th>DESCRIPTION</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Website Development<br>Business Package</td>
                <td>{{ $project->value ? 'IDR ' . number_format($project->value, 0, ',', '.') : 'IDR 5.000.000' }}</td>
                <td>
                    <ul class="feature-list">
                        <li>Mobile friendly site</li>
                        <li>Include Hosting 1000Mb</li>
                        <li>Premium SEO onPage Plugin</li>
                        <li>SSL Certificate (HTTPS security)</li>
                        <li>WhatsApp Chat Integration</li>
                        <li>Free domain (.com / .id) for 1 year</li>
                        <li>Page Speed Compression</li>
                        <li>Setting Keyword onpage</li>
                        <li>Google Search Console Setup</li>
                        <li>Google Analytics Setup</li>
                        <li>Email Accounts (business email)</li>
                        <li>Backup System (weekly backup)</li>
                        <li>Secret Indexing Method</li>
                        <li>SEO Friendly</li>
                        <li>Tutorial content web management</li>
                        <li>Registration Tripadvisor</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    <span class="page-number">3</span>
</div>

{{-- Page 5: First other-services page --}}
<div class="content-page">
    <div class="header-band">
        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
        <span class="date">{{ now()->format('n/j/Y') }}</span>
    </div>

    <table class="cost-table">
        <thead>
            <tr>
                <th style="width:28%">OTHER SERVICES</th>
                <th style="width:20%">COST</th>
                <th>DESCRIPTION</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Setup Goole Ads +<br>Management Ads</td>
                <td>IDR 3.000.000 (30 Hari) + 20% (Fee Management Meta Ads) = IDR 3.600.000</td>
                <td>
                    <ul class="feature-list">
                        <li>Full campaign setup di Google Ads</li>
                        <li>Advanced keyword research (high-converting keywords)</li>
                        <li>High-converting ad copy (headline + CTA optimized)</li>
                        <li>Smart bidding &amp; budget optimization</li>
                        <li>Conversion tracking (Leads, WhatsApp, calls)</li>
                        <li>Audience targeting yang lebih spesifik &amp; tepat</li>
                        <li>Continuous optimization untuk hasil maksimal</li>
                        <li>Detailed monthly report + strategic insights</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>Setup Meta Ads (Facebook &amp; Instagram Ads)</td>
                <td>IDR 750.000 (One Time Payment)</td>
                <td>
                    <ul class="feature-list">
                        <li>1 Video Ads (siap tayang)</li>
                        <li>Setup campaign di Meta Ads Manager</li>
                        <li>2 materi iklan:<br>- 1 Video Ads<br>- 1 Carousel Ads (gambar)</li>
                        <li>Targeting audience (lokasi, minat, demografi)</li>
                        <li>2 copywriting (caption + CTA)</li>
                        <li>Setup budget &amp; penjadwalan iklan</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    <span class="page-number">4</span>
</div>

{{-- Page 6: Second other-services page --}}
<div class="content-page">
    <div class="header-band">
        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
        <span class="date">{{ now()->format('n/j/Y') }}</span>
    </div>

    <table class="cost-table">
        <thead>
            <tr>
                <th style="width:28%">OTHER SERVICES</th>
                <th style="width:20%">COST</th>
                <th>DESCRIPTION</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Setup Meta Ads (Facebook<br>&amp; Instagram Ads) +<br>Management Ads</td>
                <td>IDR 3.000.000 (30 Hari) + 20% (Fee Management Meta Ads) = IDR 3.600.000</td>
                <td>
                    <ul class="feature-list">
                        <li>1 Video Ads (siap tayang)</li>
                        <li>Setup campaign di Meta Ads Manager</li>
                        <li>2 materi iklan:<br>- 1 Video Ads<br>- 1 Carousel Ads (gambar)</li>
                        <li>Targeting audience (lokasi, minat, demografi)</li>
                        <li>2 copywriting (caption + CTA)</li>
                        <li>Setup budget &amp; penjadwalan iklan</li>
                        <li>Management &amp; optimasi iklan harian</li>
                        <li>Monitoring performa campaign</li>
                        <li>Improvement targeting &amp; ads</li>
                        <li>Laporan performa berkala</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>Optimasi SEO</td>
                <td>IDR 4.000.000 / Bulan</td>
                <td>
                    <ul class="feature-list">
                        <li>Minimum 3 words/long tail keywords</li>
                        <li>10 keywords (including Main Keywords and Derivative Keywords)</li>
                        <li>Keyword recommendations based on Keyword Research</li>
                        <li>Website Audit/Fixing SEO Technical</li>
                        <li>On Page SEO</li>
                        <li>Page Structure Optimization</li>
                        <li>Internal Linking Building</li>
                        <li>SEO Articles (10 updated articles/month, 1000 words maximum)</li>
                        <li>Link Building Booster (Backlink)</li>
                        <li>Monthly Report</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    <span class="page-number">5</span>
</div>

{{-- Page 7: Terms, portfolio and closing --}}
<div class="content-page">
    <div class="header-band">
        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
        <span class="date">{{ now()->format('n/j/Y') }}</span>
    </div>

    <table class="cost-table">
        <tbody>
            <tr>
                <td style="width:28%">&nbsp;</td>
                <td style="width:20%">&nbsp;</td>
                <td>
                    <ul class="feature-list">
                        <li>100% White Hat SEO Technique</li>
                        <li>Secret SEO Method Indexer Technique</li>
                        <li>Internal linking</li>
                        <li>Title, Description, Heading optimization</li>
                        <li>Plugin Optimization</li>
                        <li>Keyword Density</li>
                        <li>Multiple keywords optimization</li>
                        <li>Index in Google</li>
                        <li>Plugin SEO optimization (Wordpress only)</li>
                        <li>Security Optimization (Wordpress only)</li>
                        <li>Caching Optimization (Wordpress only)</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>

    <ul class="terms">
        <li><strong>Agreement Contract must be done</strong> before we do any work related to website development.</li>
        <li>Deposit (Down Payment) <strong>must be paid minimum 50%</strong> of total invoice.</li>
        <li>Due to our full booked schedule, please consult to us about development time.</li>
        <li>Price stated in this quotation might be subject to changes according to client's requests of website's system and featured.</li>
    </ul>

    <div class="clients-block">
        <div class="label">Our Client</div>
        @if (file_exists(public_path('images/clients/logos-strip.png')))
            <img src="{{ public_path('images/clients/logos-strip.png') }}" alt="Our clients">
        @endif
    </div>

    <div class="portfolio-block">
        <div class="label">Our Portfolio :</div>
        <a href="https://www.exitobali.com/our-client-portfolio">https://www.exitobali.com/our-client-portfolio</a>
    </div>

    <div class="thank-you">Thank you for your interest</div>
    <span class="page-number">6</span>
</div>