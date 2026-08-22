@props(['name' => 'progress-modal', 'title' => 'Processing...'])

{{--
Komponen reusable: popup progress berbentuk lingkaran (circular progress
ring pakai SVG), dibangun di atas <x-modal> Breeze/Jetstream yang sudah ada.

CARA PAKAI DI HALAMAN LAIN:
1. Taruh <x-progress-modal name="nama-proses-kamu" title="Judul Proses" />
   di mana saja dalam halaman (posisi tidak masalah, ini modal/popup).
2. Buka & update dari JavaScript pakai helper global window.ProgressModal:

   ProgressModal.open('nama-proses-kamu');
   ProgressModal.update('nama-proses-kamu', {
       percent: 45,                 // 0-100
       message: 'Sedang memproses...',
       status: 'processing',        // 'processing' | 'done' | 'failed'
   });
   ProgressModal.close('nama-proses-kamu');

`name` HARUS unik per proses dalam satu halaman (dipakai sebagai prefix id
elemen-elemen di dalamnya).
--}}

<x-modal :name="$name" maxWidth="sm" focusable>
    <div class="p-8 flex flex-col items-center text-center">
        <h3 class="text-sm font-semibold text-slate-700 mb-5">{{ $title }}</h3>

        <div class="relative w-40 h-40">
            <svg class="w-40 h-40 -rotate-90" viewBox="0 0 160 160">
                <circle cx="80" cy="80" r="70" stroke-width="12" fill="none" class="stroke-slate-100" />
                <circle id="{{ $name }}-ring" cx="80" cy="80" r="70" stroke-width="12" fill="none"
                    stroke-linecap="round" stroke-dasharray="439.8" stroke-dashoffset="439.8"
                    class="stroke-blue-600 transition-all duration-500 ease-out"></circle>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span id="{{ $name }}-percent" class="text-2xl font-bold text-slate-800">0%</span>
                <span id="{{ $name }}-status" class="text-[11px] text-slate-400 mt-1">Processing</span>
            </div>
        </div>

        <p id="{{ $name }}-message" class="text-xs text-slate-500 mt-4 max-w-xs min-h-[2rem]"></p>

        <button type="button" id="{{ $name }}-close-btn" x-on:click="$dispatch('close')"
            class="hidden mt-4 text-xs bg-slate-700 text-white rounded-lg px-4 py-2 hover:bg-slate-800 transition">
            Close
        </button>
    </div>
</x-modal>

@once
    <script>
        window.ProgressModal = {
            open(name) {
                window.dispatchEvent(new CustomEvent('open-modal', { detail: name }));
            },
            close(name) {
                window.dispatchEvent(new CustomEvent('close-modal', { detail: name }));
            },
            /**
             * @param {string} name
             * @param {Object} [data={}]
             * @param {number} [data.percent=0]
             * @param {string} [data.message='']
             * @param {'processing'|'done'|'failed'} [data.status='processing']
             */
            update(name, { percent = 0, message = '', status = 'processing' } = {}) {
                const CIRCUMFERENCE = 439.8;
                const ring = document.getElementById(`${name}-ring`);
                const percentEl = document.getElementById(`${name}-percent`);
                const statusEl = document.getElementById(`${name}-status`);
                const messageEl = document.getElementById(`${name}-message`);
                const closeBtn = document.getElementById(`${name}-close-btn`);

                if (ring) {
                    const clamped = Math.max(0, Math.min(100, percent));
                    ring.style.strokeDashoffset = CIRCUMFERENCE - (clamped / 100) * CIRCUMFERENCE;
                    ring.classList.remove('stroke-blue-600', 'stroke-emerald-500', 'stroke-red-500');
                    ring.classList.add(
                        status === 'done' ? 'stroke-emerald-500' :
                        status === 'failed' ? 'stroke-red-500' : 'stroke-blue-600'
                    );
                }
                if (percentEl) percentEl.innerText = Math.round(percent) + '%';
                if (statusEl) {
                    statusEl.innerText = status === 'done' ? 'Done' : status === 'failed' ? 'Failed' : 'Processing';
                }
                if (messageEl) messageEl.innerText = message;
                if (closeBtn) closeBtn.classList.toggle('hidden', status === 'processing');
            },
        };
    </script>
@endonce