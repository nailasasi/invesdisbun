@php
    $pegawai = $pegawai ?? null;
    $skpdList = $skpdList ?? collect();
    $ruanganList = $ruanganList ?? collect();
    $lokasiDinas = $lokasiDinas ?? null;
    $mode = $pegawai ? 'edit' : 'create';
@endphp

<x-card>
    <div class="max-w-xl">
        @include('master-data.pegawai.partials.form', [
            'mode' => $mode,
            'pegawai' => $pegawai,
            'skpdList' => $skpdList,
            'ruanganList' => $ruanganList,
            'lokasiDinas' => $lokasiDinas,
            'prefix' => '',
        ])
    </div>
</x-card>

@push('scripts')
<script>
    (function () {
        const fieldSkpd = document.getElementById('id_skpd');
        const fieldLokasi = document.getElementById('lokasi');
        const fieldRuangan = document.getElementById('id_ruangan');
        const stickyRuangan = @json(old('id_ruangan', $pegawai->id_ruangan ?? ''));

        if (!fieldSkpd || !fieldRuangan) { return; }

        function applyRuanganFilter(keepValue) {
            const lokasiId = fieldSkpd.options[fieldSkpd.selectedIndex]?.dataset.lokasiId ? String(fieldSkpd.options[fieldSkpd.selectedIndex].dataset.lokasiId) : '';
            let hasOption = false;

            fieldRuangan.querySelectorAll('option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                const match = lokasiId !== '' && String(opt.dataset.lokasi) === lokasiId;
                opt.style.display = match ? '' : 'none';
                if (match) hasOption = true;
            });

            const keep = keepValue ? String(keepValue) : '';
            const keepOpt = Array.from(fieldRuangan.options).find(o => o.value === keep);
            if (keep && keepOpt && keepOpt.style.display !== 'none') {
                fieldRuangan.value = keep;
            } else {
                fieldRuangan.value = '';
            }
            const placeholder = fieldRuangan.querySelector('option[value=""]');
            if (placeholder) {
                placeholder.textContent = hasOption
                    ? '-- Pilih Ruangan --'
                    : (lokasiId ? '-- Tidak ada ruangan untuk lokasi ini --' : '-- Pilih SKPD dulu --');
            }
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
