@extends('layouts.app')
@section('title', 'About')

@section('content')

    <x-page-header title="About & Help" />

    <div class="space-y-6 max-w-3xl">

        {{-- APP IDENTITY --}}
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                <div class="w-16 h-16 rounded-2xl grad-purple flex items-center justify-center text-white font-bold text-2xl shrink-0">
                    {{ substr($appName, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-bold text-slate-800 text-lg">{{ $appName }}</p>
                        <x-badge color="purple">{{ $appVersion }}</x-badge>
                        <x-badge color="{{ $appEnvironment === 'production' ? 'emerald' : 'amber' }}">
                            {{ ucfirst($appEnvironment) }}
                        </x-badge>
                    </div>
                    <p class="text-sm text-slate-400 mt-1.5 leading-relaxed">{{ $appDescription }}</p>
                </div>
            </div>
        </x-card>

        {{-- USER GUIDE --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-1">User Guide</h2>
            <p class="text-sm text-slate-400 mb-5">What each menu is for and when to use it.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ([
                    ['icon' => 'bx-grid-alt', 'iconClass' => 'bg-blue-50 text-blue-500', 'title' => 'Dashboard', 'desc' => 'A quick overview of new requests, proposals, active projects, and unpaid invoices.'],
                    ['icon' => 'bx-group', 'iconClass' => 'bg-purple-100 text-purple-600', 'title' => 'CRM', 'desc' => 'Browse client records and see each client\'s full project history.'],
                    ['icon' => 'bx-briefcase', 'iconClass' => 'bg-emerald-50 text-emerald-500', 'title' => 'Project', 'desc' => 'Track every project through its pipeline, from request to completion.'],
                    ['icon' => 'bx-list-plus', 'iconClass' => 'bg-blue-50 text-blue-500', 'title' => 'Request Order', 'desc' => 'Create a new client and draft a project request, including website, SEO, and backlink needs.'],
                    ['icon' => 'bx-cube', 'iconClass' => 'bg-pink-100 text-pink-600', 'title' => 'Workspace', 'desc' => 'The project workspace: proposal, mockup, and AI-assisted SEO & backlink analysis, all in one place.'],
                    ['icon' => 'bx-shape-square', 'iconClass' => 'bg-purple-100 text-purple-600', 'title' => 'Mockup', 'desc' => 'Manage the AI-generated website mockup templates used in proposals.'],
                    ['icon' => 'bx-wallet', 'iconClass' => 'bg-emerald-50 text-emerald-500', 'title' => 'Finance', 'desc' => 'Create invoices, mark them as paid, and send payment reminders to clients.'],
                    ['icon' => 'bx-bar-chart-alt-2', 'iconClass' => 'bg-amber-50 text-amber-500', 'title' => 'Reports', 'desc' => 'View project and financial summaries, and download them as PDF or Excel.'],
                ] as $item)
                    <div class="flex items-start gap-3 rounded-xl border border-slate-100 p-4 hover:border-slate-200 hover:bg-slate-50/60 transition-colors">
                        <div class="w-10 h-10 rounded-xl {{ $item['iconClass'] }} flex items-center justify-center shrink-0">
                            <i class='bx {{ $item['icon'] }} text-lg'></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">{{ $item['title'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">{{ $item['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- FAQ / HELP --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-1">Frequently Asked Questions</h2>
            <p class="text-sm text-slate-400 mb-4">Common questions about using {{ $appName }}.</p>

            <div class="space-y-2">
                @foreach ([
                    ['q' => 'How do I start a new project?', 'a' => 'Go to Request Order, add or select a client, choose the services needed (Website, SEO, and/or Backlink), and save the draft. The project will then appear on the Project page.'],
                    ['q' => 'Where can I find a generated proposal?', 'a' => 'Open the project from the Project page — you\'ll be taken to its Workspace, where the generated proposal PDF and mockup preview are shown once ready.'],
                    ['q' => 'How do I run an SEO or backlink analysis?', 'a' => 'Open Workspace, select the project, and run the analysis from the Summary or Performance tab. Results are saved automatically to the project.'],
                    ['q' => 'How do I record a payment?', 'a' => 'Go to Finance, find the related invoice, and use "Mark as Paid" once payment is received.'],
                    ['q' => 'How do I get a report for a client or period?', 'a' => 'Go to Reports and use "Download PDF" or "Download Excel" to export a summary of projects and finances.'],
                ] as $faq)
                    <details class="group rounded-xl border border-slate-100 open:bg-slate-50/60 open:border-slate-200 transition-colors">
                        <summary class="flex items-center justify-between gap-3 cursor-pointer px-4 py-3 text-sm font-medium text-slate-700 list-none marker:content-none">
                            {{ $faq['q'] }}
                            <i class='bx bx-chevron-down text-lg text-slate-400 shrink-0 group-open:rotate-180 transition-transform'></i>
                        </summary>
                        <p class="text-xs text-slate-500 leading-relaxed px-4 pb-4 -mt-1">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </x-card>

        {{-- HELP BANNER --}}
        <div class="rounded-2xl bg-slate-800 p-6 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white shrink-0">
                <i class='bx bx-support text-xl'></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Still stuck?</p>
                <p class="text-xs text-slate-300 mt-0.5">Reach out to your system administrator for further help.</p>
            </div>
        </div>

    </div>

@endsection
