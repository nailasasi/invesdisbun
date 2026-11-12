{{-- Modal Mutasi Aset --}}
<div id="mutasi-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" data-mutasi-close></div>
    <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Mutasi Aset</h3>
                <p class="mt-0.5 text-sm text-slate-500">Pilih jenis mutasi untuk aset ini.</p>
            </div>
            <button type="button" data-mutasi-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="mutasi-form" method="POST" autocomplete="off" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" id="mutasi-aset-id" name="id_aset" value="">
            <div class="space-y-1.5">
                <label for="mutasi-aset-name" class="block text-sm font-medium text-slate-700">Aset</label>
                <div id="mutasi-aset-name" class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700"></div>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Jenis Mutasi <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" id="tipe-pegawai" data-tipe="pegawai" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition">
                        Ganti Pemegang
                    </button>
                    <button type="button" id="tipe-ruangan" data-tipe="ruangan" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition">
                        Pindah Ruangan
                    </button>
                </div>
                <input type="hidden" id="mutasi-tipe" name="tipe" value="">
                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tipe"></p>
            </div>

            <div id="mutasi-field-pegawai" class="space-y-1.5 hidden">
                <label for="mutasi-pegawai" class="block text-sm font-medium text-slate-700">Pemegang Baru <span class="text-red-500">*</span></label>
                <select id="mutasi-pegawai" name="id_pegawai" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <option value="" selected>-- Pilih Pemegang Baru --</option>
                    @foreach ($allPegawai as $p)
                        <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}</option>
                    @endforeach
                </select>
                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai"></p>
                <p class="text-xs text-slate-400">Aset akan otomatis mengikuti ruangan kerja pemegang baru.</p>
            </div>

            <div id="mutasi-field-ruangan" class="space-y-1.5 hidden">
                <label for="mutasi-ruangan" class="block text-sm font-medium text-slate-700">Ruangan Tujuan <span class="text-red-500">*</span></label>
                <select id="mutasi-ruangan" name="id_ruangan" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <option value="" selected>-- Pilih Ruangan Tujuan --</option>
                    @foreach ($ruanganOptions as $r)
                        <option value="{{ $r->id_ruangan }}" data-lokasi="{{ $r->id_lokasi ?? '' }}" data-lokasi-nama="{{ $r->lokasi?->nama_lokasi ?? '' }}" data-skpd-nama="{{ $r->skpd?->nama_skpd ?? '' }}">{{ $r->nama_ruangan }}</option>
                    @endforeach
                </select>
                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_ruangan"></p>
            </div>

            <div id="mutasi-field-skpd" class="space-y-1.5 hidden">
                <label for="mutasi-skpd" class="block text-sm font-medium text-slate-700">SKPD <span class="text-xs font-normal text-slate-400">(informasi)</span></label>
                <input
                    type="text"
                    id="mutasi-skpd"
                    readonly
                    disabled
                    placeholder="-- Mengikuti ruangan tujuan --"
                    class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-sm focus:outline-none transition"
                >
                <p class="text-xs text-slate-400">Read-only. Untuk aset tanpa pemegang, SKPD dan Lokasi Fisik mengikuti ruangan tujuan: <span class="font-mono">ruangan.id_skpd</span> dan <span class="font-mono">ruangan.id_lokasi</span>. Untuk Ganti Pemegang, keduanya mengikuti master pegawai baru.</p>
            </div>

            <div class="space-y-1.5">
                <label for="mutasi-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                <textarea id="mutasi-keterangan" name="keterangan" rows="2" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: serah terima antar pegawai"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" data-mutasi-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-mutasi-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    Simpan Mutasi
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Sukses Mutasi & Unduh BAST --}}
<div id="mutasi-sukses-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" onclick="window.location.reload()"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex flex-col items-center text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900">Mutasi Aset Berhasil</h3>
            <p id="mutasi-sukses-message" class="mt-1 text-sm text-slate-500">Mutasi pemegang aset berhasil disimpan.</p>
            <p id="mutasi-sukses-warning" class="mt-2 hidden text-xs text-amber-700 bg-amber-50 rounded-lg p-2.5 border border-amber-200"></p>
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

