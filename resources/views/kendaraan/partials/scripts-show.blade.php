{{-- JavaScript Halaman Detail Kendaraan --}}
@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const kendaraanId = {{ $kendaraan->id_kendaraan }};
    const createDataUrl = @json(route('kendaraan.create'));
    const editUrl = @json(route('kendaraan.edit', ['kendaraan' => '__ID__']));
    const updateUrl = @json(route('kendaraan.update', ['kendaraan' => '__ID__']));
    const pajakStoreUrl = @json(route('kendaraan.pajak.store', ['kendaraan' => '__ID__']));
    const pajakUpdateBase = @json(route('kendaraan.pajak.update', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
    const pajakDeleteBase = @json(route('kendaraan.pajak.destroy', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
    const pajakBuktiBase = @json(route('kendaraan.pajak.bukti', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
    const pajakPreviewBase = @json(route('kendaraan.pajak.preview', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));

    let loadedDropdowns = false;

    // Status Aset = dropdown Aktif/Nonaktif. Bila status yang sedang aktif
    // merupakan status SISTEM (diusulkan_hapus/dihapuskan), dropdown dikunci
    // (disabled) sehingga nilainya tidak dikirim dan backend mempertahankan
    // status sistem tersebut apa adanya.
    function terapkanStatusAset(status) {
        const select = document.getElementById('field-status');
        const note = document.getElementById('field-status-note');
        if (!select) return;

        const nilai = (status ?? '').toString().trim().toLowerCase();
        const bolehUbah = nilai === 'aktif' || nilai === 'nonaktif';

        select.disabled = !bolehUbah;
        select.value = bolehUbah ? nilai : 'aktif';

        if (note) {
            note.textContent = bolehUbah
                ? ''
                : 'Status aset saat ini ("' + ((status ?? '').toString() || 'tidak diketahui') + '") merupakan status sistem sehingga tidak dapat diubah melalui form ini.';
            note.classList.toggle('hidden', bolehUbah);
        }
    }

    function openModal(el) { el.classList.remove('hidden'); el.classList.add('flex'); document.body.classList.add('overflow-hidden'); }
    function closeModal(el) { el.classList.add('hidden'); el.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); }
    function wireClose(modal) {
        modal.querySelectorAll('[data-modal-close]').forEach(x => x.addEventListener('click', () => closeModal(modal)));
    }

    // Filter tabel Pajak Kendaraan (hanya tampilan; tidak mengubah data).
    const pajakFilterTahun = document.getElementById('pajak-filter-tahun');
    const pajakFilterJenis = document.getElementById('pajak-filter-jenis');
    const pajakRows = Array.from(document.querySelectorAll('#pajak-kendaraan tbody tr[data-tahun]'));
    const pajakFilterEmpty = document.getElementById('pajak-filter-empty');

    if (pajakFilterTahun && pajakFilterJenis) {
        const applyPajakFilter = () => {
            let visible = 0;
            pajakRows.forEach(row => {
                const okTahun = !pajakFilterTahun.value || String(row.dataset.tahun) === pajakFilterTahun.value;
                const okJenis = !pajakFilterJenis.value || row.dataset.jenis === pajakFilterJenis.value;
                const show = okTahun && okJenis;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });
            if (pajakFilterEmpty) {
                pajakFilterEmpty.classList.toggle('hidden', !(pajakRows.length > 0 && visible === 0));
            }
        };
        pajakFilterTahun.addEventListener('change', applyPajakFilter);
        pajakFilterJenis.addEventListener('change', applyPajakFilter);
    }

    async function ensureDropdowns() {
        if (loadedDropdowns) return;
        try {
            const res = await fetch(createDataUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const jenis = document.getElementById('field-jenis');
            if (jenis && data.jenisList) {
                const currentVal = jenis.value;
                jenis.innerHTML = '<option value="">-- Pilih Jenis --</option>';
                data.jenisList.forEach(v => {
                    const el = document.createElement('option'); el.value = v; el.textContent = v;
                    jenis.appendChild(el);
                });
                if (currentVal) jenis.value = currentVal;
            }
            const kondisi = document.getElementById('field-kondisi');
            if (kondisi && data.kondisiList) {
                const currentVal = kondisi.value;
                kondisi.innerHTML = '<option value="">-- Pilih Kondisi --</option>';
                data.kondisiList.forEach(v => {
                    const el = document.createElement('option'); el.value = v; el.textContent = v;
                    kondisi.appendChild(el);
                });
                if (currentVal) kondisi.value = currentVal;
            }
            loadedDropdowns = true;
        } catch (err) {
            // Fallback ke opsi statis yang ada
        }
    }

    @if ($isAdmin)
    // Edit kendaraan dari halaman detail
    document.querySelectorAll('[data-edit-kendaraan]').forEach(btn => {
        btn.addEventListener('click', async () => {
            await ensureDropdowns();
            const d = await (await fetch(editUrl.replace('__ID__', kendaraanId), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
            document.getElementById('field-id').value = d.id_kendaraan;
            document.getElementById('field-id-aset').value = d.id_aset;
            document.getElementById('field-nama').value = d.nama_kendaraan ?? '';
            document.getElementById('field-kartu').value = d.nomor_kartu_barang ?? '';
            document.getElementById('field-jenis').value = d.jenis_kendaraan ?? '';
            document.getElementById('field-keterangan').value = d.keterangan ?? '';
            document.getElementById('field-rangka').value = d.nomor_rangka ?? '';
            document.getElementById('field-mesin').value = d.nomor_mesin ?? '';
            document.getElementById('field-merk').value = d.merk ?? '';
            document.getElementById('field-tipe').value = d.tipe ?? '';
            document.getElementById('field-nilai').value = d.nilai_perolehan ?? '';
            document.getElementById('field-kondisi').value = d.kondisi ?? '';
            terapkanStatusAset(d.status_aset ?? '');
            document.getElementById('field-tgl-pengadaan').value = d.tanggal_pengadaan ?? '';
            document.getElementById('field-tgl-habis').value = d.tanggal_habis_pakai ?? '';
            document.getElementById('field-plat').value = d.nomor_plat ?? '';
            document.getElementById('modal-title').textContent = 'Edit Kendaraan';
            const kForm = document.getElementById('kendaraan-form');
            kForm.action = updateUrl.replace('__ID__', kendaraanId);
            const kModal = document.getElementById('kendaraan-modal');
            kModal.querySelectorAll('[data-modal-close]').forEach(x => x.addEventListener('click', () => {
                kModal.style.visibility = 'hidden';
                document.body.classList.remove('overflow-hidden');
            }));
            kModal.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        });
    });

    // Submit edit kendaraan (dari halaman detail)
    const kendaraanForm = document.getElementById('kendaraan-form');
    if (kendaraanForm) {
        kendaraanForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formEl = e.target;
            const submit = document.getElementById('btn-submit');
            const original = submit ? submit.textContent : 'Simpan';
            if (submit) {
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
            }
            const body = new FormData(formEl);
            try {
                const res = await fetch(formEl.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                if (res.status === 422) { alert('Mohon lengkapi data yang wajib diisi.'); if (submit) { submit.textContent = original; submit.disabled = false; } return; }
                if (res.ok) { window.location.reload(); } else { alert('Terjadi kesalahan. Coba lagi.'); if (submit) { submit.textContent = original; submit.disabled = false; } }
            } catch (err) { alert('Koneksi bermasalah.'); if (submit) { submit.textContent = original; submit.disabled = false; } }
        });
    }

    // Pajak
    const pajakModal = document.getElementById('pajak-modal');
    if (pajakModal) {
        wireClose(pajakModal);
    }
    const pajakForm = document.getElementById('pajak-form');
    const pajakModalTitle = document.getElementById('pajak-modal-title');
    const pajakMethod = document.getElementById('pajak-method');
    const cb5Tahunan = document.getElementById('pajak-5tahunan-ganti');
    const platBaruWrap = document.getElementById('plat-baru-wrap');
    const platBaruInput = document.getElementById('plat-baru');
    const block5Tahunan = document.getElementById('pajak-5thn-block');
    const buktiInput = document.getElementById('pajak-bukti');
    const buktiWajib = document.getElementById('pajak-bukti-wajib');
    const buktiHint = document.getElementById('pajak-bukti-hint');
    const buktiExisting = document.getElementById('pajak-bukti-existing');

    function togglePlatBaru() {
        if (!cb5Tahunan || !platBaruWrap || !platBaruInput) return;
        const on = cb5Tahunan.checked;
        platBaruWrap.classList.toggle('hidden', !on);
        platBaruInput.required = on;
        if (!on) platBaruInput.value = '';
    }
    if (cb5Tahunan) {
        cb5Tahunan.addEventListener('change', togglePlatBaru);
    }

    function resetPajakForm() {
        if (!pajakForm) return;
        pajakForm.reset();
        if (pajakMethod) pajakMethod.value = '';
        pajakForm.action = pajakStoreUrl.replace('__ID__', kendaraanId);
        if (pajakModalTitle) pajakModalTitle.textContent = 'Kelola Pajak Kendaraan';
        const jenisEl = document.getElementById('pajak-jenis');
        if (jenisEl) jenisEl.value = 'PKB';
        const tahunEl = document.getElementById('pajak-tahun');
        if (tahunEl) tahunEl.disabled = false;
        const berakhirEl = document.getElementById('pajak-berakhir');
        if (berakhirEl) berakhirEl.disabled = false;
        if (block5Tahunan) block5Tahunan.classList.remove('hidden');
        if (cb5Tahunan) cb5Tahunan.disabled = false;
        if (buktiInput) buktiInput.required = true;
        if (buktiWajib) buktiWajib.classList.remove('hidden');
        if (buktiHint) buktiHint.textContent = 'Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.';
        if (buktiExisting) {
            buktiExisting.classList.add('hidden');
            buktiExisting.textContent = '';
        }
        togglePlatBaru();
    }

    document.querySelectorAll('[data-pajak-modal]').forEach(b => b.addEventListener('click', () => {
        resetPajakForm();
        if (pajakModal) openModal(pajakModal);
    }));

    document.querySelectorAll('[data-edit-pajak]').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            resetPajakForm();
            if (pajakModalTitle) pajakModalTitle.textContent = 'Edit Pajak Kendaraan';
            if (pajakForm) {
                pajakForm.action = pajakUpdateBase.replace('__ID__', kendaraanId).replace('__PAJAK__', d.editPajak);
            }
            if (pajakMethod) pajakMethod.value = 'PUT';

            const jenisEl = document.getElementById('pajak-jenis');
            if (jenisEl) jenisEl.value = d.jenis ?? '';

            // Tahun awal dihitung dari tanggal jatuh tempo (TIDAK dari nilai
            // tersimpan): Tahunan = tahun(tanggal_berakhir) - 1, 5 Tahunan
            // = tahun(tanggal_berakhir) - 5. Fallback ke nilai tersimpan
            // hanya bila tanggal jatuh tempo kosong.
            const berakhirY = d.berakhir ? parseInt(d.berakhir.slice(0, 4), 10) : NaN;
            const tahunAwal = Number.isInteger(berakhirY) ? berakhirY - (d.is5tahunan === '1' ? 5 : 1) : (d.tahun ?? '');
            const tahunEl = document.getElementById('pajak-tahun');
            if (tahunEl) tahunEl.value = tahunAwal ?? '';
            const berakhirEl = document.getElementById('pajak-berakhir');
            if (berakhirEl) berakhirEl.value = d.berakhir ?? '';
            const bayarEl = document.getElementById('pajak-bayar');
            if (bayarEl) bayarEl.value = d.bayar ?? '';
            const nominalEl = document.getElementById('pajak-nominal');
            if (nominalEl) nominalEl.value = d.nominal ?? '';
            const totalEl = document.getElementById('pajak-total');
            if (totalEl) totalEl.value = d.total ?? '';

            // Seluruh field pajak dapat dikoreksi di sini (jenis, tahun,
            // tanggal bayar/jatuh tempo, nominal, total, bukti). Blok
            // 5 Tahunan/Ganti Plat sengaja tidak ditampilkan saat edit:
            // perubahan ke/dari 5 tahunan diatur lewat Tambah atau Hapus.
            if (block5Tahunan) block5Tahunan.classList.add('hidden');

            if (buktiInput) buktiInput.required = false;
            if (buktiWajib) buktiWajib.classList.add('hidden');
            if (buktiHint) buktiHint.textContent = 'Biarkan kosong bila tidak mengganti berkas. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.';
            if (d.bukti === '1' && d.buktiNama && buktiExisting) {
                buktiExisting.textContent = 'Bukti saat ini: ' + d.buktiNama;
                buktiExisting.classList.remove('hidden');
            }

            if (pajakModal) openModal(pajakModal);
        });
    });

    if (pajakForm) {
        pajakForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch(pajakForm.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: fd });
            if (res.ok || res.status === 422) { window.location.reload(); }
        });
    }

    document.querySelectorAll('[data-delete-pajak]').forEach(b => {
        b.addEventListener('click', async () => {
            const pajakId = b.dataset.deletePajak;
            if (!confirm('Hapus pajak ' + b.dataset.pajakName + '?')) return;
            const res = await fetch(pajakDeleteBase.replace('__ID__', kendaraanId).replace('__PAJAK__', pajakId), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            if (res.ok) window.location.reload();
        });
    });

    // Pratinjau bukti pajak (modal, bukan unduh langsung).
    const buktiModal = document.getElementById('bukti-modal');
    if (buktiModal) {
        wireClose(buktiModal);
        const buktiImageWrap = document.getElementById('bukti-image-wrap');
        const buktiImage = document.getElementById('bukti-image');
        const buktiPdfWrap = document.getElementById('bukti-pdf-wrap');
        const buktiPdf = document.getElementById('bukti-pdf');
        const buktiFallbackWrap = document.getElementById('bukti-fallback-wrap');
        const buktiFallbackNama = document.getElementById('bukti-fallback-nama');
        const buktiNamaEl = document.getElementById('bukti-nama');
        const buktiDownload = document.getElementById('bukti-download');

        function resetBuktiPreview() {
            if (buktiImage) buktiImage.removeAttribute('src');
            if (buktiPdf) buktiPdf.removeAttribute('src');
            if (buktiImageWrap) buktiImageWrap.classList.add('hidden');
            if (buktiPdfWrap) buktiPdfWrap.classList.add('hidden');
            if (buktiFallbackWrap) buktiFallbackWrap.classList.add('hidden');
        }

        document.querySelectorAll('[data-lihat-bukti]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.lihatBukti;
                const nama = btn.dataset.buktiNama || 'Bukti pajak';
                const ext = (nama.split('.').pop() || '').toLowerCase();
                const previewUrl = pajakPreviewBase.replace('__ID__', kendaraanId).replace('__PAJAK__', id);
                const downloadUrl = pajakBuktiBase.replace('__ID__', kendaraanId).replace('__PAJAK__', id);

                resetBuktiPreview();
                if (buktiNamaEl) buktiNamaEl.textContent = nama;
                if (buktiDownload) buktiDownload.setAttribute('href', downloadUrl);

                if (['jpg', 'jpeg', 'png'].includes(ext)) {
                    if (buktiImage) buktiImage.src = previewUrl;
                    if (buktiImageWrap) buktiImageWrap.classList.remove('hidden');
                } else if (ext === 'pdf') {
                    if (buktiPdf) buktiPdf.src = previewUrl;
                    if (buktiPdfWrap) buktiPdfWrap.classList.remove('hidden');
                } else {
                    if (buktiFallbackNama) buktiFallbackNama.textContent = nama;
                    if (buktiFallbackWrap) buktiFallbackWrap.classList.remove('hidden');
                }

                openModal(buktiModal);
            });
        });

        buktiModal.querySelectorAll('[data-modal-close]').forEach(el => {
            el.addEventListener('click', resetBuktiPreview);
        });
    }
    @endif
</script>
@endpush

