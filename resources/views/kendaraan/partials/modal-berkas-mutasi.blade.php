{{-- Modal Sukses Mutasi Kendaraan & Unduh BAST --}}
<div id="mutasi-sukses-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" onclick="window.location.reload()"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex flex-col items-center text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900">Mutasi Kendaraan Berhasil</h3>
            <p id="mutasi-sukses-message" class="mt-1 text-sm text-slate-500">Mutasi pemegang kendaraan berhasil disimpan.</p>
            <div class="mt-5 w-full space-y-2">
                <a id="btn-download-bast" href="#" target="_blank"
                   class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 7l-4-4-4 4M12 3v13"/></svg>
                    Unduh Dokumen BAST (.docx)
                </a>
            </div>
            <button type="button" onclick="window.location.reload()" class="mt-4 rounded-xl bg-slate-100 px-5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                Tutup / Selesai
            </button>
        </div>
    </div>
</div>
