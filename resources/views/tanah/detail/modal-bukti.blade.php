{{-- Modal Lihat Bukti Otorisasi --}}
<div id="bukti-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-bukti-close></div>
    <div class="relative my-auto flex w-full max-w-lg max-h-[90vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        {{-- Header (Fixed) --}}
        <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Bukti Otorisasi</h3>
                <p class="text-xs text-slate-400" data-bukti-aktivitas>—</p>
            </div>
            <button type="button" data-bukti-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Scrollable Content Body --}}
        <div class="flex-1 overflow-y-auto space-y-4 px-6 py-4">
            {{-- Foto Bukti (Tinggi Terbatas & Tidak Terpotong) --}}
            <div class="flex items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-900/5 p-2">
                <img data-bukti-foto src="" alt="Foto bukti otorisasi"
                     class="mx-auto max-h-48 sm:max-h-56 w-auto max-w-full rounded-lg object-contain">
            </div>

            {{-- Metadata Informasi --}}
            <dl class="grid grid-cols-1 gap-2.5 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5 text-xs sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Aktor</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800" data-bukti-aktor>—</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Aktivitas</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800" data-bukti-aktivitas-2>—</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Objek</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800" data-bukti-objek>—</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Waktu (server)</dt>
                    <dd class="mt-0.5 font-mono font-medium text-slate-800" data-bukti-waktu>—</dd>
                </div>
            </dl>

            {{-- Tabel Perubahan Data --}}
            <div data-bukti-perubahan-wrap class="hidden space-y-1.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Perubahan Data</p>
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">Field</th>
                                <th class="px-3 py-2 font-medium">Sebelum</th>
                                <th class="px-3 py-2 font-medium">Sesudah</th>
                            </tr>
                        </thead>
                        <tbody data-bukti-perubahan class="divide-y divide-slate-100 bg-white"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Footer (Fixed & Aksesibel) --}}
        <div class="flex shrink-0 items-center justify-end border-t border-slate-100 bg-slate-50/70 px-6 py-3">
            <button type="button" data-bukti-close
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Tutup
            </button>
        </div>
    </div>
</div>
