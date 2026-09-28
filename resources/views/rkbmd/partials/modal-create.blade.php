{{-- Partials: Modal Form Input Usulan RKBMD (create/edit) --}}
<div id="modalUsulan"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 overflow-y-auto sm:p-6 backdrop-blur-xs"
     data-modal>

    <div class="relative flex flex-col w-full max-w-3xl max-h-[90vh] rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">

        {{-- Header (sticky) --}}
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h3 id="usulan-modal-title" class="text-sm font-bold leading-tight text-slate-800">Input Usulan RKBMD</h3>
                    <p class="text-[11px] text-slate-400">Rencana Kebutuhan Barang Milik Daerah</p>
                </div>
            </div>
            <button type="button" data-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Form --}}
        <form id="usulan-form" method="POST" action="{{ route('rkbmd.store') }}" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <div id="usulan-method-holder"></div>

            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4 text-xs">
                {{-- 1. INFORMASI UMUM --}}
                <div>
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Informasi Umum</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label for="usulan-jenis" class="mb-1 block font-semibold text-slate-700">
                                Jenis Usulan RKBMD <span class="text-rose-500">*</span>
                            </label>
                            <select id="usulan-jenis" name="jenis_usulan" required
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <option value="" disabled selected>-- Pilih Jenis --</option>
                                @foreach ($jenisList as $jenis)
                                    <option value="{{ $jenis }}" @selected(old('jenis_usulan') === $jenis)>{{ $jenis === 'Pengadaan' ? 'Usulan RKBMD ' . $jenis : 'Usulan RKBMD ' . $jenis }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="usulan-id-skpd" class="mb-1 block font-semibold text-slate-700">
                                Bidang / Unit Kerja <span class="text-rose-500">*</span>
                            </label>
                            @if ($isAdmin)
                                <select id="usulan-id-skpd" name="id_skpd" required
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                    <option value="" disabled selected>-- Pilih Unit Kerja --</option>
                                    @foreach ($bidangOptions as $opt)
                                        <option value="{{ $opt->id_skpd }}" @selected(old('id_skpd') == $opt->id_skpd)>{{ $opt->nama_skpd }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" value="{{ $mySkpd?->nama_skpd ?? 'Unit tidak terdeteksi' }}"
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500" disabled>
                            @endif
                        </div>

                        <div>
                            <label for="usulan-tahun" class="mb-1 block font-semibold text-slate-700">
                                Tahun Anggaran <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" id="usulan-tahun" name="tahun_anggaran" min="2000" max="2100"
                                   value="{{ old('tahun_anggaran', now()->year + 1) }}" required
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label for="usulan-program" class="mb-1 block font-semibold text-slate-700">Program / Kegiatan / Output</label>
                            <input type="text" id="usulan-program" name="program_kegiatan" maxlength="500"
                                   placeholder="Program Penunjang Urusan Pemerintahan..."
                                   value="{{ old('program_kegiatan') }}"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- 2. DATA USULAN BMD --}}
                <div>
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Data Usulan BMD</p>
                    <div class="space-y-2.5">
                        <div>
                            <label for="usulan-cari" class="mb-1 block font-semibold text-slate-700">Cari Master Kode Barang</label>
                            <input type="text" id="usulan-cari" list="master-barang-list" autocomplete="off"
                                   placeholder="Ketik kode / nama barang master..."
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <datalist id="master-barang-list">
                                @foreach ($masterBarangList as $mb)
                                    <option value="{{ $mb->kode_barang }} - {{ $mb->nama_barang }}">{{ $mb->satuan }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label for="usulan-kode" class="mb-1 block font-semibold text-slate-700">Kode Barang</label>
                                <input type="text" id="usulan-kode" name="kode_barang" maxlength="50"
                                       placeholder="5.02.02.02.001"
                                       value="{{ old('kode_barang') }}"
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label for="usulan-nama" class="mb-1 block font-semibold text-slate-700">
                                    Nama Barang <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="usulan-nama" name="nama_barang" maxlength="255"
                                       placeholder="Laptop Notebook Core i5"
                                       value="{{ old('nama_barang') }}" required
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label for="usulan-jumlah" class="mb-1 block font-semibold text-slate-700">
                                    Jumlah <span class="text-rose-500">*</span>
                                </label>
                                <input type="number" id="usulan-jumlah" name="jumlah" min="1" value="{{ old('jumlah', 1) }}" required
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label for="usulan-satuan" class="mb-1 block font-semibold text-slate-700">
                                    Satuan <span class="text-rose-500">*</span>
                                </label>
                                <select id="usulan-satuan" name="satuan" required
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                    @foreach ($satuanList as $satuan)
                                        <option value="{{ $satuan }}" @selected(old('satuan') === $satuan)>{{ $satuan }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- 3. OPTIMALISASI & KEBUTUHAN RIIL --}}
                <div>
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Optimalisasi &amp; Kebutuhan Riil</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-3">
                            <span class="mb-2 block text-[11px] font-bold text-slate-600">Kebutuhan Maksimum</span>
                            <div class="flex gap-2">
                                <input type="number" id="usulan-kebutuhan-maks" name="kebutuhan_maksimum" min="0"
                                       value="{{ old('kebutuhan_maksimum') }}" placeholder="Jumlah"
                                       class="w-2/3 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <input type="text" id="usulan-satuan-maks" value="Unit" readonly
                                       class="w-1/3 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500">
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-3">
                            <span class="mb-2 block text-[11px] font-bold text-slate-600">Kebutuhan Riil</span>
                            <div class="flex gap-2">
                                <input type="number" id="usulan-kebutuhan-riil" name="kebutuhan_riil" min="0"
                                       value="{{ old('kebutuhan_riil') }}" placeholder="Jumlah"
                                       class="w-2/3 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <input type="text" id="usulan-satuan-riil" value="Unit" readonly
                                       class="w-1/3 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500">
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-2 rounded-2xl border border-slate-100 bg-slate-50/60 p-3">
                        <span class="block text-[11px] font-bold text-slate-600">Barang yang Dapat Dioptimalkan</span>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <input type="text" id="usulan-opt-kode" name="opt_kode" maxlength="50"
                                   placeholder="Kode Barang" value="{{ old('opt_kode') }}"
                                   class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <input type="text" id="usulan-opt-nama" name="opt_nama" maxlength="255"
                                   placeholder="Nama Barang Lama" value="{{ old('opt_nama') }}"
                                   class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <input type="number" id="usulan-opt-jumlah" name="opt_jumlah" min="1"
                                   placeholder="Jumlah" value="{{ old('opt_jumlah') }}"
                                   class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <select id="usulan-opt-satuan" name="opt_satuan"
                                    class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <option value="">-- Satuan --</option>
                                @foreach ($satuanList as $satuan)
                                    <option value="{{ $satuan }}" @selected(old('opt_satuan') === $satuan)>{{ $satuan }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- 4. KETERANGAN --}}
                <div>
                    <label for="usulan-alasan" class="mb-1 block font-semibold text-slate-700">
                        Keterangan / Alasan Kebutuhan <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="usulan-alasan" name="alasan_kebutuhan" rows="2" maxlength="2000" required
                              placeholder="Tuliskan justifikasi kebutuhan riil..."
                              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">{{ old('alasan_kebutuhan') }}</textarea>
                </div>
            </div>

            {{-- Footer (sticky) --}}
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-3.5 shrink-0">
                <button type="button" data-close
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                    Batal
                </button>
                <button type="submit"
                        class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
                    Simpan Usulan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        // --- Pencarian Master Kode Barang: pilih dari datalist mengisi kode/nama/satuan ---
        const masterBarang = @json($masterBarangList);

        const cariEl = document.getElementById('usulan-cari');
        if (cariEl) {
            cariEl.addEventListener('input', function () {
                const val = cariEl.value;
                const match = masterBarang.find(function (m) {
                    const opt = m.kode_barang + ' - ' + m.nama_barang;
                    return val.trim() === opt;
                });
                if (match) {
                    const namaEl = document.getElementById('usulan-nama');
                    const kodeEl = document.getElementById('usulan-kode');
                    const satEl = document.getElementById('usulan-satuan');
                    if (namaEl) namaEl.value = match.nama_barang || '';
                    if (kodeEl) kodeEl.value = match.kode_barang || '';
                    if (satEl && match.satuan) {
                        [...satEl.options].forEach(function (o) {
                            if (o.value === match.satuan) o.selected = true;
                        });
                    }
                }
            });
        }

        // --- Sinkronisasi satuan pada Kebutuhan Maksimum & Riil ---
        const satuanUsulan = document.getElementById('usulan-satuan');
        if (satuanUsulan) {
            satuanUsulan.addEventListener('change', function () {
                const s = satuanUsulan.value;
                const a = document.getElementById('usulan-satuan-maks');
                const b = document.getElementById('usulan-satuan-riil');
                if (a) a.value = s;
                if (b) b.value = s;
            });
        }

        // --- Mode EDIT: pra-isi form dari data-attribute tombol Edit ---
        const usulanForm = document.getElementById('usulan-form');

        document.querySelectorAll('[data-edit-usulan]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!usulanForm) return;

                usulanForm.setAttribute('action', btn.getAttribute('data-action'));
                document.getElementById('usulan-method-holder').innerHTML =
                    '<input type="hidden" name="_method" value="PATCH">';
                document.getElementById('usulan-modal-title').textContent = 'Edit Usulan RKBMD';

                const setVal = function (id, v) {
                    const el = document.getElementById(id);
                    if (el) el.value = (v === null || v === undefined) ? '' : String(v);
                };

                setVal('usulan-jenis', btn.getAttribute('data-jenis'));
                setVal('usulan-id-skpd', btn.getAttribute('data-id-skpd'));
                setVal('usulan-tahun', btn.getAttribute('data-tahun'));
                setVal('usulan-program', btn.getAttribute('data-program'));
                setVal('usulan-kode', btn.getAttribute('data-kode'));
                setVal('usulan-nama', btn.getAttribute('data-nama'));
                setVal('usulan-jumlah', btn.getAttribute('data-jumlah'));
                setVal('usulan-satuan', btn.getAttribute('data-satuan'));
                setVal('usulan-kebutuhan-maks', btn.getAttribute('data-kebutuhan-maks'));
                setVal('usulan-kebutuhan-riil', btn.getAttribute('data-kebutuhan-riil'));
                setVal('usulan-alasan', btn.getAttribute('data-alasan'));

                const optRaw = btn.getAttribute('data-opt');
                let opt = null;
                try { opt = optRaw ? JSON.parse(optRaw) : null; } catch (e) { opt = null; }
                setVal('usulan-opt-kode', opt ? opt.kode : '');
                setVal('usulan-opt-nama', opt ? opt.nama : '');
                setVal('usulan-opt-jumlah', opt ? opt.jumlah : '');
                setVal('usulan-opt-satuan', opt ? opt.satuan : '');

                // Sinkronkan satuan maksimum/riil.
                const s = document.getElementById('usulan-satuan').value;
                const ma = document.getElementById('usulan-satuan-maks');
                const ri = document.getElementById('usulan-satuan-riil');
                if (ma) ma.value = s;
                if (ri) ri.value = s;
            });
        });

        // --- Mode CREATE: reset form saat tombol "Buat Usulan" ---
        const buatBtn = document.querySelector('[data-open-modal="modalUsulan"]');
        if (buatBtn && usulanForm) {
            buatBtn.addEventListener('click', function () {
                usulanForm.setAttribute('action', @json(route('rkbmd.store')));
                document.getElementById('usulan-method-holder').innerHTML = '';
                document.getElementById('usulan-modal-title').textContent = 'Input Usulan RKBMD';
                usulanForm.reset();
                document.getElementById('usulan-tahun').value = {{ now()->year + 1 }};
                document.getElementById('usulan-jumlah').value = 1;
                const s = document.getElementById('usulan-satuan').value;
                const ma = document.getElementById('usulan-satuan-maks');
                const ri = document.getElementById('usulan-satuan-riil');
                if (ma) ma.value = s;
                if (ri) ri.value = s;
            });
        }
    </script>
@endpush