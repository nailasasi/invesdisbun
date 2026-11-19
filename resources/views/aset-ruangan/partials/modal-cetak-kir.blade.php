@if ($bolehCetakDokumen)
{{-- Modal Pengaturan Cetak KIR (Kartu Inventaris Ruangan).
     Dapat diakses oleh Admin Aset dan Admin UPT. Pejabat penandatangan diisi secara manual. --}}
<div id="kir-scope-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-kir-scope-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Cetak Kartu Inventaris Ruangan (KIR)</h3>
                    <p class="text-xs text-slate-500">Ruangan: <span class="font-semibold text-slate-700">{{ $ruangan->nama_ruangan }}</span></p>
                </div>
            </div>
            <button type="button" data-kir-scope-close class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-200 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="kir-scope-form" method="GET" action="{{ route('aset-ruangan.kir.download', $ruangan->id_ruangan) }}">
            <div class="space-y-4 px-6 py-5 max-h-[75vh] overflow-y-auto">
                @if (($jumlahItemKIR ?? 0) === 0)
                    {{-- Alert Peringatan Jika Tidak Ada Barang yang Memenuhi Syarat Cetak --}}
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 shadow-xs">
                        <div class="flex items-start gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-800">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="space-y-1">
                                <p class="font-bold text-amber-900">Belum Ada Barang yang Memenuhi Syarat Cetak KIR</p>
                                <p class="text-amber-800 leading-relaxed">
                                    Ruangan ini belum memiliki aset melekat yang <strong>sudah difinalisasi</strong> atau inventaris lokal aktif.
                                    Dokumen KIR hanya dapat dicetak apabila minimal terdapat 1 barang yang memenuhi kualifikasi.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-3 text-xs text-slate-600 leading-relaxed flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span>Data pejabat penandatangan bersifat opsional. Jika nama atau NIP dikosongkan, dokumen Word akan mencetak garis titik-titik untuk tanda tangan manual basah.</span>
                        <span class="inline-flex shrink-0 items-center self-start sm:self-center rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-bold text-sky-800">
                            {{ $jumlahItemKIR }} Barang Siap Cetak
                        </span>
                    </div>
                @endif

                {{-- Tanggal Dokumen --}}
                <div class="space-y-1">
                    <label for="kir-tanggal-cetak" class="block text-xs font-semibold text-slate-700">Tanggal Cetak Dokumen</label>
                    <input type="date" id="kir-tanggal-cetak" name="tanggal_cetak" value="{{ now()->toDateString() }}"
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 shadow-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-400 transition">
                </div>

                {{-- Seksi Pejabat 1 --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-700">1</span>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pejabat 1 (Mengetahui / Atasan)</h4>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <label for="kir-nama-pejabat-1" class="block text-[11px] font-medium text-slate-600">Nama Pejabat</label>
                            <input type="text" id="kir-nama-pejabat-1" name="nama_pejabat_1" autocomplete="off" maxlength="150"
                                   placeholder="contoh: Drs. H. Ahmad Subagio, M.Si"
                                   class="mt-0.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-400 transition">
                        </div>
                        <div>
                            <label for="kir-nip-pejabat-1" class="block text-[11px] font-medium text-slate-600">NIP Pejabat</label>
                            <input type="text" id="kir-nip-pejabat-1" name="nip_pejabat_1" autocomplete="off" maxlength="50"
                                   placeholder="contoh: 19750815 199803 1 004"
                                   class="mt-0.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-400 transition">
                        </div>
                    </div>
                </div>

                {{-- Seksi Pejabat 2 --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-700">2</span>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pejabat 2 (Pengurus Barang / Pengelola)</h4>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <label for="kir-nama-pejabat-2" class="block text-[11px] font-medium text-slate-600">Nama Pejabat</label>
                            <input type="text" id="kir-nama-pejabat-2" name="nama_pejabat_2" autocomplete="off" maxlength="150"
                                   placeholder="contoh: Siti Aminah, S.Kom"
                                   class="mt-0.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-400 transition">
                        </div>
                        <div>
                            <label for="kir-nip-pejabat-2" class="block text-[11px] font-medium text-slate-600">NIP Pejabat</label>
                            <input type="text" id="kir-nip-pejabat-2" name="nip_pejabat_2" autocomplete="off" maxlength="50"
                                   placeholder="contoh: 19820420 200604 2 011"
                                   class="mt-0.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-400 transition">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4 bg-slate-50">
                <button type="button" data-kir-scope-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 shadow-xs hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit"
                    @disabled(($jumlahItemKIR ?? 0) === 0)
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-sky-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-sky-600"
                    @if (($jumlahItemKIR ?? 0) === 0) title="Ruangan ini belum memiliki barang memenuhi syarat untuk dicetak pada KIR" @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Unduh Dokumen KIR</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

