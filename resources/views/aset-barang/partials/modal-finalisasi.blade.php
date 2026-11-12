{{-- Modal Konfirmasi Finalisasi Penempatan --}}
<div id="finalisasi-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" data-finalisasi-close></div>
    <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">Finalisasi Penempatan</h3>
                <p class="mt-1 text-sm text-slate-500">
                    <span id="finalisasi-aset-nama" class="font-medium text-slate-700"></span> akan ditandai <span class="font-medium text-slate-700">Sudah Difinalisasi</span> dan tidak dapat diedit lagi.
                </p>
                <p id="finalisasi-pesan-extra" class="mt-2 hidden rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-finalisasi-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
            <button type="button" id="btn-finalisasi-confirm" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                <span id="btn-finalisasi-label">Finalisasi</span>
                <svg id="btn-finalisasi-spinner" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Finalisasi Massal --}}
<div id="finalisasi-massal-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" data-finalisasi-massal-close></div>
    <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">Finalisasi Massal</h3>
                <p id="finalisasi-massal-pesan" class="mt-1 text-sm text-slate-500">
                    <span id="finalisasi-massal-jumlah" class="font-medium text-slate-700"></span> akan ditandai <span class="font-medium text-slate-700">Sudah Difinalisasi</span> dan tidak dapat diedit lagi.
                </p>
            </div>
        </div>

        {{-- Hasil proses: ditampilkan setelah request selesai --}}
        <div id="finalisasi-massal-hasil" class="mt-4 hidden space-y-2"></div>

        <div class="mt-6 flex items-center justify-end gap-2.5">
            <button type="button" data-finalisasi-massal-close id="btn-finalisasi-massal-batal" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
            <button type="button" id="btn-finalisasi-massal-confirm" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 whitespace-nowrap">
                <span id="btn-finalisasi-massal-label">Finalisasi</span>
                <svg id="btn-finalisasi-massal-spinner" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>
    </div>
</div>

