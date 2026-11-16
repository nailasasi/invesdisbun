{{-- MODAL PAJAK & PRATINJAU BUKTI PAJAK --}}
{{-- Modal Kelola / Tambah / Edit Pajak Kendaraan --}}
<div id="pajak-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-hidden bg-slate-900/50 p-3 sm:p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-modal-close></div>

    {{-- Modal Card Container: Flex column, max-height terikat viewport, layout responsif --}}
    <div class="relative flex w-full max-w-lg sm:max-w-xl flex-col rounded-2xl bg-white shadow-2xl overflow-hidden max-h-[calc(100vh-32px)]" style="max-height: calc(100vh - 32px);">
        {{-- 1. HEADER (Fixed/Sticky top, flex-shrink-0) --}}
        <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4 bg-white z-10">
            <h3 id="pajak-modal-title" class="text-lg font-semibold text-slate-900">Tambah Transaksi Pajak</h3>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" aria-label="Tutup modal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- FORM WRAPPER (Flex column, min-height 0 agar child scroll bekerja sempurna) --}}
        <form id="pajak-form" method="POST" class="flex flex-1 flex-col min-h-0" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="pajak-method" value="">

            {{-- 2. BODY (Scrollable area mandiri dengan overflow-y: auto dan padding cukup) --}}
            <div id="pajak-modal-body" class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
                {{-- General Alert Box --}}
                <div id="pajak-form-alert" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700"></div>

                {{-- Pilihan Jenis Transaksi Pajak --}}
                <div id="pajak-jenis-wrapper" class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Jenis Transaksi Pajak <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-[:checked]:border-disbun-600 has-[:checked]:bg-disbun-50/50 transition">
                            <input type="radio" name="pajak_5_tahunan" id="pajak-tipe-tahunan" value="0" checked class="h-4 w-4 text-disbun-600 focus:ring-disbun-500">
                            <div>
                                <span class="block text-xs font-semibold text-slate-800">Pajak Tahunan</span>
                                <span class="block text-[11px] text-slate-500">Jatuh tempo PKB tahunan</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-[:checked]:border-disbun-600 has-[:checked]:bg-disbun-50/50 transition">
                            <input type="radio" name="pajak_5_tahunan" id="pajak-tipe-5tahunan" value="1" class="h-4 w-4 text-disbun-600 focus:ring-disbun-500">
                            <div>
                                <span class="block text-xs font-semibold text-slate-800">Pajak 5 Tahunan</span>
                                <span class="block text-[11px] text-slate-500">PKB + Masa STNK 5 Thn</span>
                            </div>
                        </label>
                    </div>
                    <p id="pajak-jenis-locked-note" class="hidden text-xs text-slate-400">Jenis transaksi tidak dapat diubah setelah transaksi dibuat.</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="pajak_5_tahunan"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-tahun" class="block text-sm font-medium text-slate-700">Tahun <span class="text-red-500">*</span></label>
                        <input type="number" id="pajak-tahun" name="tahun" min="2000" max="2100" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-500 focus:ring-2 focus:ring-disbun-400 transition" value="{{ date('Y') }}">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tahun"></p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="pajak-bayar" class="block text-sm font-medium text-slate-700">Tanggal Bayar <span class="text-red-500">*</span></label>
                        <input type="date" id="pajak-bayar" name="tanggal_bayar" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-500 focus:ring-2 focus:ring-disbun-400 transition" value="{{ date('Y-m-d') }}">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_bayar"></p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="pajak-berakhir" class="block text-sm font-medium text-slate-700">Tanggal Jatuh Tempo PKB Berikutnya <span class="text-red-500">*</span></label>
                    <input type="date" id="pajak-berakhir" name="tanggal_berakhir" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-500 focus:ring-2 focus:ring-disbun-400 transition">
                    <p class="text-xs text-slate-500">Diinput manual sesuai penetapan resmi dokumen Samsat.</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_berakhir"></p>
                </div>

                {{-- Kolom Tambahan Khusus Pajak 5 Tahunan --}}
                <div id="wrap-5tahunan" class="space-y-4 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 hidden">
                    <div class="space-y-1.5">
                        <label for="pajak-berakhir-stnk" class="block text-sm font-medium text-indigo-950">Tanggal Berakhir STNK (Masa Berlaku 5 Tahunan) <span class="text-red-500">*</span></label>
                        <input type="date" id="pajak-berakhir-stnk" name="tanggal_berakhir_stnk" class="block w-full rounded-xl border border-indigo-200 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition">
                        <p class="text-[11px] text-indigo-700">Tanggal akhir masa berlaku STNK pada lembar STNK resmi.</p>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_berakhir_stnk"></p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="plat-baru" class="block text-sm font-medium text-indigo-950">Nomor Plat Setelah Pembayaran <span class="text-red-500">*</span></label>
                        <input type="text" id="plat-baru" name="nomor_plat_baru" maxlength="20" class="block w-full rounded-xl border border-indigo-200 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition" placeholder="contoh: {{ $kendaraan->platAktif?->nomor_plat ?? 'L 1234 AA' }}" value="{{ $kendaraan->platAktif?->nomor_plat ?? '' }}">
                        <p class="text-[11px] text-indigo-700">Ketik nomor plat saat ini bila nomor polisi tidak berubah.</p>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_plat_baru"></p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="pajak-nominal" class="block text-sm font-medium text-slate-700">Nominal Total Pembayaran (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" id="pajak-nominal" name="nominal" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-500 focus:ring-2 focus:ring-disbun-400 transition" placeholder="contoh: 1500000">
                    <p class="text-xs text-slate-500">Satu nominal total pembayaran (tanpa rincian PKB, SWDKLLJ, Opsen, PNBP, dsb).</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nominal"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="pajak-bukti" class="block text-sm font-medium text-slate-700">Bukti Pembayaran Pajak <span id="pajak-bukti-wajib" class="text-red-500">*</span></label>
                    <input type="file" id="pajak-bukti" name="bukti_pajak" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-disbun-700 hover:file:bg-disbun-100 focus:outline-none focus:border-disbun-500 focus:ring-2 focus:ring-disbun-400 transition">
                    <p id="pajak-bukti-hint" class="text-xs text-slate-500">Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.</p>
                    <p id="pajak-bukti-existing" class="hidden text-xs font-medium text-slate-600"></p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="bukti_pajak"></p>
                </div>
            </div>

            {{-- 3. FOOTER (Fixed/Sticky bottom, flex-shrink-0) --}}
            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">Batal</button>
                <button type="submit" id="pajak-btn-submit" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800 disabled:opacity-50 transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL PRATINJAU BUKTI PAJAK --}}
<div id="bukti-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">Pratinjau Bukti Pajak</h3>
                <p id="bukti-nama" class="mt-0.5 truncate text-xs text-slate-500"></p>
            </div>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-5 py-4">
            <div id="bukti-image-wrap" class="hidden">
                <img id="bukti-image" src="" alt="Pratinjau bukti pajak" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-xl border border-slate-200 bg-white object-contain">
            </div>
            <div id="bukti-pdf-wrap" class="hidden">
                <iframe id="bukti-pdf" src="" title="Pratinjau PDF bukti pajak" class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white"></iframe>
                <p class="mt-2 text-center text-xs text-slate-500">Jika pratinjau tidak tampil, gunakan tombol Download di bawah.</p>
            </div>
            <div id="bukti-fallback-wrap" class="hidden py-10 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                <p id="bukti-fallback-nama" class="mt-3 text-sm font-medium text-slate-700"></p>
                <p class="mt-1 text-xs text-slate-500">Pratinjau tidak didukung untuk jenis berkas ini. Silakan unduh untuk membuka.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Tutup</button>
            <a id="bukti-download" href="#" class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                Download
            </a>
        </div>
    </div>
</div>
