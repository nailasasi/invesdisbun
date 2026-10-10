@php
    $pegawai = $pegawai ?? null;
    $skpdList = $skpdList ?? collect();
    $ruanganList = $ruanganList ?? collect();
    $lokasiDinas = $lokasiDinas ?? null;

    $oldSkpd = old('id_skpd', $pegawai->id_skpd ?? '');
    $oldRuangan = old('id_ruangan', $pegawai->id_ruangan ?? '');
@endphp

<x-card>
    <div class="max-w-xl space-y-5">
        <x-input
            label="NIP"
            name="nip"
            value="{{ old('nip', $pegawai->nip ?? '') }}"
            required
            placeholder="contoh: 198001012010011001"
            help="NIP dipakai sebagai username & password awal saat membuat akun."
        />
        <x-input
            label="Nama Pegawai"
            name="nama_pegawai"
            value="{{ old('nama_pegawai', $pegawai->nama_pegawai ?? '') }}"
            required
            placeholder="contoh: Drs. Budi Santoso"
        />
        <x-input
            label="Jabatan"
            name="jabatan"
            value="{{ old('jabatan', $pegawai->jabatan ?? '') }}"
            placeholder="contoh: Kepala Dinas"
        />

        <div class="space-y-1.5">
            <label for="id_skpd" class="block text-sm font-medium text-slate-700">
                SKPD <span class="text-red-500">*</span>
            </label>
            <select id="id_skpd" name="id_skpd" required
                    class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_skpd') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                <option value="" disabled {{ $oldSkpd === '' ? 'selected' : '' }}>-- Pilih SKPD --</option>
                @foreach ($skpdList as $skpd)
                    @php
                        $lokasiSkpd = $skpd->isUpt() ? $skpd->lokasi : $lokasiDinas;
                    @endphp
                    <option
                        value="{{ $skpd->id_skpd }}"
                        data-lokasi-id="{{ $lokasiSkpd?->id_lokasi ?? '' }}"
                        data-lokasi-nama="{{ $lokasiSkpd?->nama_lokasi ?? '' }}"
                        @selected((string) $oldSkpd === (string) $skpd->id_skpd)
                    >{{ $skpd->nama_skpd }} ({{ $skpd->jenis_skpd }})</option>
                @endforeach
            </select>
            @error('id_skpd')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-1.5">
            <label for="lokasi" class="block text-sm font-medium text-slate-700">Lokasi</label>
            <input
                type="text"
                id="lokasi"
                readonly
                value="{{ \App\Models\Lokasi::untukSkpd($oldSkpd !== '' && $oldSkpd !== null ? (int) $oldSkpd : null)?->nama_lokasi ?? '' }}"
                placeholder="-- Pilih SKPD dulu --"
                class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-sm focus:outline-none transition"
            >
            <p class="text-xs text-slate-400">Turunan dari SKPD: Sekretariat &amp; Bidang → Kantor Dinas; UPT → kantor UPT-nya. Bukan input manual.</p>
        </div>

        <div class="space-y-1.5">
            <label for="id_ruangan" class="block text-sm font-medium text-slate-700">Ruangan</label>
            <select id="id_ruangan" name="id_ruangan"
                    class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_ruangan') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                <option value="">-- Pilih Ruangan --</option>
                @foreach ($ruanganList as $ruangan)
                    <option
                        value="{{ $ruangan->id_ruangan }}"
                        data-lokasi="{{ $ruangan->id_lokasi ?? '' }}"
                        @if ($ruangan->id_skpd === null) data-shared="1" @endif
                        @selected((string) $oldRuangan === (string) $ruangan->id_ruangan)
                    >{{ $ruangan->nama_ruangan }}</option>
                @endforeach
            </select>
            @error('id_ruangan')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs text-slate-400">Ruang bersama tersedia untuk semua SKPD.</p>
        </div>
    </div>
</x-card>

@push('scripts')
<script>
    (function () {
        const fieldSkpd = document.getElementById('id_skpd');
        const fieldLokasi = document.getElementById('lokasi');
        const fieldRuangan = document.getElementById('id_ruangan');
        const stickyRuangan = @json($oldRuangan);

        if (!fieldSkpd || !fieldRuangan) { return; }

        function applyRuanganFilter(keepValue) {
            const lokasiId = fieldSkpd.options[fieldSkpd.selectedIndex]?.dataset.lokasiId || '';
            let hasOption = false;

            fieldRuangan.querySelectorAll('option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                const match = opt.dataset.shared === '1' || (lokasiId && opt.dataset.lokasi === lokasiId);
                opt.style.display = match ? '' : 'none';
                if (match) hasOption = true;
            });

            const keep = keepValue || '';
            fieldRuangan.value = keep && hasOption ? keep : '';
            fieldRuangan.querySelector('option[value=""]').textContent = hasOption
                ? '-- Pilih Ruangan --'
                : (lokasiId ? '-- Tidak ada ruangan untuk lokasi ini --' : '-- Pilih SKPD dulu --');
        }

        function applyLokasiDisplay() {
            const opt = fieldSkpd.options[fieldSkpd.selectedIndex];
            if (fieldLokasi) {
                fieldLokasi.value = opt && opt.value ? (opt.dataset.lokasiNama || '-') : '';
                fieldLokasi.placeholder = opt && opt.value ? '' : '-- Pilih SKPD dulu --';
            }
            applyRuanganFilter('');
        }

        applyRuanganFilter(stickyRuangan);
        fieldSkpd.addEventListener('change', applyLokasiDisplay);
    })();
</script>
@endpush
