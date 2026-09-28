{{-- Modal Import KIB A Tanah --}}
<div id="modalImportTanah"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">

    <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-100">

        {{-- Header Modal --}}
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Import Data Tanah (KIB A)</h3>
                    <p class="text-[11px] text-slate-400">Unggah data inventaris tanah secara massal via Excel</p>
                </div>
            </div>
            <button type="button" onclick="closeModalImportTanah()" class="rounded-xl p-1 text-slate-400 transition hover:text-slate-600" title="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Form Import --}}
        <form action="{{ route('tanah.import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="space-y-4 text-xs">
                {{-- Download Format Template --}}
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/50 p-3">
                    <div>
                        <span class="block font-bold text-slate-700">Belum punya format file?</span>
                        <span class="text-[11px] text-slate-500">Unduh template standar kolom KIB A Tanah</span>
                    </div>
                    <a href="{{ route('tanah.template') }}"
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-emerald-700 shadow-2xs transition hover:bg-emerald-50">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Unduh Format</span>
                    </a>
                </div>

                {{-- Input File --}}
                <div>
                    <label for="file_excel_tanah" class="mb-1 block font-semibold text-slate-700">
                        Pilih Berkas Excel <span class="text-rose-500">*</span>
                    </label>
                    <input id="file_excel_tanah" type="file" name="file_excel" required accept=".xlsx,.xls,.csv"
                           class="w-full cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-2.5 file:py-1 file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    <p class="mt-1 text-[10px] text-slate-400">Maksimal 5 MB. Format didukung: .xlsx atau .xls</p>
                </div>

                {{-- Opsi Duplikasi --}}
                <div>
                    <label for="update_existing" class="mb-1 block font-semibold text-slate-700">Opsi Duplikasi / Pembaruan Data</label>
                    <select id="update_existing" name="update_existing"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="skip">Lewati baris jika Nomor Sertifikat / KIB sudah ada</option>
                        <option value="update">Perbarui data lama jika Nomor Sertifikat / KIB sama</option>
                    </select>
                </div>
            </div>

            {{-- Footer Action --}}
            <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" onclick="closeModalImportTanah()"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Mulai Import</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalImportTanah() {
        const modal = document.getElementById('modalImportTanah');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeModalImportTanah() {
        const modal = document.getElementById('modalImportTanah');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
</script>