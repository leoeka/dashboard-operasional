<x-card>
    <div class="space-y-4">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 border border-slate-300 flex items-center justify-center shrink-0">
                    <i class='bx bx-file-blank text-xl'></i>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-base">
                        Project Proposal
                    </h2>
                    <p class="text-xs text-slate-600">
                        Analysis & proposal document
                    </p>
                </div>
            </div>
        </div>
        {{-- Description --}}
        <p class="text-xs text-slate-500 leading-relaxed">
            @if ($proposal)
                The proposal has been successfully created based on the project request data. Open it to
                read the analysis and the document.
            @else
                Generate a proposal based on the project request data.
                The system will prepare a needs analysis, website strategy,
                target market, and website structure.
            @endif
        </p>
    </div>

    {{-- Actions --}}
    <div class="pt-4 mt-4 border-t border-slate-100 space-y-2">
        @if ($proposal)
            <a href="{{ route('pages.projects.proposal.preview', $project) }}"
                class="min-h-11 w-full inline-flex items-center justify-center gap-2 bg-brand-600 text-white hover:bg-brand-700 text-xs font-semibold px-4 py-3 rounded-md active:scale-95 transition">
                <i class='bx bx-show'></i>
                Lihat Proposal
            </a>
            @if ($proposal->pdf_path)
                <a href="{{ route('pages.projects.proposal.download', $project) }}"
                    class="min-h-11 w-full inline-flex items-center justify-center gap-2 border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold px-4 py-3 rounded-md active:scale-95 transition">
                    <i class='bx bx-download'></i>
                    Download Proposal PDF
                </a>
            @endif
        @else
            {{-- Generate pertama kali: JS-driven, memicu job async + progress bar --}}
            <button type="button" id="generate-proposal-btn"
                onclick="startGenerateProposal({{ $project->id }})"
                class="w-full min-h-11 inline-flex items-center justify-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-3 rounded-md hover:bg-brand-700 active:scale-95 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                <i class='bx bx-magic-wand text-sm'></i>
                <span>Generate</span>
            </button>
        @endif
    </div>
</x-card>
