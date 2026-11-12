{{-- JavaScript Halaman Index Kendaraan (digabung dari 4 blok script sebelumnya) --}}
@push('scripts')
    {{-- Blok 1: Script Utama (CSRF, Dropdown Data, Modal Tambah/Edit, Usulan Hapus, Modal Import, Mutasi Pemegang, Berkas Modal, Ekspor Kendaraan) --}}
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const storeUrl = @json(route('kendaraan.store'));
        const editUrl = @json(route('kendaraan.edit', ['kendaraan' => '__ID__']));
        const updateUrl = @json(route('kendaraan.update', ['kendaraan' => '__ID__']));
        const createDataUrl = @json(route('kendaraan.create'));

        const modal = document.getElementById('kendaraan-modal');
        const form = document.getElementById('kendaraan-form');
        let loadedDropdowns = false;

        // Pemegang TIDAK lagi bagian form Tambah/Edit. Form hanya mengubah
        // data master kendaraan; pemegang diatur lewat Penempatan Awal / Mutasi.
        //
        // Status Aset = dropdown Aktif/Nonaktif. Bila status yang sedang aktif
        // merupakan status SISTEM (diusulkan_hapus/dihapuskan), dropdown
        // dikunci (disabled) sehingga nilainya tidak dikirim dan backend
        // mempertahankan status sistem tersebut apa adanya.
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

        const importModal = document.getElementById('import-modal');
        if (document.getElementById('btn-import')) {
            document.getElementById('btn-import').addEventListener('click', () => {
                importModal.classList.remove('hidden');
                importModal.classList.add('flex');
            });
        }
        if (importModal) {
            importModal.querySelectorAll('[data-import-close]').forEach(el => el.addEventListener('click', () => {
                importModal.classList.add('hidden');
                importModal.classList.remove('flex');
            }));
        }

        function showErrors(errors) {
            if (!form) return;
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = form.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function fillOptions(select, options, valueKey, labelKey, placeholder) {
            if (!select) return;
            select.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = ''; opt.textContent = placeholder;
            select.appendChild(opt);
            options.forEach(o => {
                const el = document.createElement('option');
                el.value = o[valueKey]; el.textContent = o[labelKey];
                select.appendChild(el);
            });
        }

        function openModal() {
            if (!modal) return;
            modal.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            if (!modal) return;
            modal.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
            if (form) {
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            }
        }
        if (modal) {
            modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
        }

        async function ensureDropdowns() {
            if (loadedDropdowns) return;
            try {
                const res = await fetch(createDataUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                const jenisSelect = document.getElementById('field-jenis');
                const kondisiSelect = document.getElementById('field-kondisi');
                if (jenisSelect && data.jenisList) {
                    const currentVal = jenisSelect.value;
                    fillOptions(jenisSelect, data.jenisList.map(v => ({ value: v })), 'value', 'value', '-- Pilih Jenis --');
                    if (currentVal) jenisSelect.value = currentVal;
                }
                if (kondisiSelect && data.kondisiList) {
                    const currentVal = kondisiSelect.value;
                    fillOptions(kondisiSelect, data.kondisiList.map(v => ({ value: v })), 'value', 'value', '-- Pilih Kondisi --');
                    if (currentVal) kondisiSelect.value = currentVal;
                }
                loadedDropdowns = true;
            } catch (err) {
                // Gunakan fallback statis yang ada
            }
        }

        if (document.getElementById('btn-tambah')) {
            document.getElementById('btn-tambah').addEventListener('click', async () => {
                await ensureDropdowns();
                const titleEl = document.getElementById('modal-title');
                if (titleEl) titleEl.textContent = 'Tambah Kendaraan';
                if (form) {
                    form.reset();
                    document.getElementById('field-id').value = '';
                    document.getElementById('field-id-aset').value = '';
                    terapkanStatusAset('aktif');
                    form.action = storeUrl;
                }
                openModal();
            });
        }

        document.querySelectorAll('[data-edit-target]').forEach(btn => {
            btn.addEventListener('click', async () => {
                await ensureDropdowns();
                const id = btn.dataset.editTarget;
                const res = await fetch(editUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const d = await res.json();
                const titleEl = document.getElementById('modal-title');
                if (titleEl) titleEl.textContent = 'Edit Kendaraan';
                if (document.getElementById('field-id')) document.getElementById('field-id').value = d.id_kendaraan;
                if (document.getElementById('field-id-aset')) document.getElementById('field-id-aset').value = d.id_aset;
                if (document.getElementById('field-nama')) document.getElementById('field-nama').value = d.nama_kendaraan ?? '';
                if (document.getElementById('field-jenis')) document.getElementById('field-jenis').value = d.jenis_kendaraan ?? '';
                if (document.getElementById('field-keterangan')) document.getElementById('field-keterangan').value = d.keterangan ?? '';
                if (document.getElementById('field-kartu')) document.getElementById('field-kartu').value = d.nomor_kartu_barang ?? '';
                if (document.getElementById('field-rangka')) document.getElementById('field-rangka').value = d.nomor_rangka ?? '';
                if (document.getElementById('field-mesin')) document.getElementById('field-mesin').value = d.nomor_mesin ?? '';
                if (document.getElementById('field-merk')) document.getElementById('field-merk').value = d.merk ?? '';
                if (document.getElementById('field-tipe')) document.getElementById('field-tipe').value = d.tipe ?? '';
                if (document.getElementById('field-nilai')) document.getElementById('field-nilai').value = d.nilai_perolehan ?? '';
                if (document.getElementById('field-kondisi')) document.getElementById('field-kondisi').value = d.kondisi ?? '';
                terapkanStatusAset(d.status_aset ?? '');
                if (document.getElementById('field-tgl-pengadaan')) document.getElementById('field-tgl-pengadaan').value = d.tanggal_pengadaan ?? '';
                if (document.getElementById('field-tgl-habis')) document.getElementById('field-tgl-habis').value = d.tanggal_habis_pakai ?? '';
                if (document.getElementById('field-plat')) document.getElementById('field-plat').value = d.nomor_plat ?? '';
                if (form) form.action = updateUrl.replace('__ID__', id);
                openModal();
            });
        });

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-submit');
                const original = submit ? submit.textContent : 'Simpan';
                if (submit) {
                    submit.textContent = 'Menyimpan...';
                    submit.disabled = true;
                }
                const body = new FormData(form);
                try {
                    const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(data.errors);
                        if (submit) {
                            submit.textContent = original;
                            submit.disabled = false;
                        }
                        return;
                    }
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert('Terjadi kesalahan. Coba lagi.');
                        if (submit) {
                            submit.textContent = original;
                            submit.disabled = false;
                        }
                    }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    if (submit) {
                        submit.textContent = original;
                        submit.disabled = false;
                    }
                }
            });
        }

        {{-- Delete handling (usulkan penghapusan kendaraan) --}}
        const deleteModal = document.getElementById('delete-modal');
        const deleteForm = deleteModal ? deleteModal.querySelector('form') : null;
        const closeDeleteModal = () => {
            if (! deleteModal) return;
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
        };
        if (deleteModal) {
            // Komponen x-modal-confirm menandai tombol Batal & backdrop dengan
            // [data-close] (bukan [data-modal-close] seperti modal form
            // kendaraan). Mengikat [data-close] membuat Batal/backdrop
            // benar-benar menutup modal — tanpa mengirim request apa pun.
            deleteModal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeDeleteModal));
        }
        document.querySelectorAll('[data-delete-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.deleteTarget;
                const name = btn.dataset.deleteName;
                if (!deleteModal) return;
                const msgEl = deleteModal.querySelector('[data-confirm-message]');
                if (msgEl) msgEl.textContent = 'Usulkan penghapusan kendaraan "' + name + '"? Kendaraan akan hilang dari daftar aktif hingga proses usulan selesai.';
                deleteForm.action = @json(route('kendaraan.destroy', ['kendaraan' => '__ID__'])).replace('__ID__', id);
                deleteModal.classList.remove('hidden');
                deleteModal.classList.add('flex');
            });
        });
        if (deleteForm) {
            deleteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = deleteForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                try {
                    const res = await fetch(deleteForm.action, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                    let data = null;
                    try { data = await res.json(); } catch (parseErr) { data = null; }
                    if (res.ok && data && data.success) {
                        window.location.reload();
                        return;
                    }
                    alert((data && data.message) || 'Gagal mengajukan usulan penghapusan.');
                    if (submitBtn) submitBtn.disabled = false;
                } catch (err) {
                    alert('Koneksi bermasalah. Usulan tidak tersimpan, coba lagi.');
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }
        // Escape menutup modal usulan hapus (konsisten dengan modal lain di app).
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && deleteModal && ! deleteModal.classList.contains('hidden')) {
                closeDeleteModal();
            }
        });

        {{-- Mutasi pemegang handling --}}
        const mutasiStoreUrl = @json(route('kendaraan.mutasi-pemegang.store', ['kendaraan' => '__ID__']));
        const mutasiModal = document.getElementById('mutasi-modal');
        const mutasiForm = document.getElementById('mutasi-form');

        function openMutasiKendaraan(id) {
            const row = document.querySelector(`[data-mutasi-pemegang="${id}"]`);
            const plat = row.dataset.mutasiPlat || '';
            document.getElementById('mutasi-kendaraan-id').value = id;
            document.getElementById('mutasi-pegawai-lama-id').value = row.dataset.mutasiPemegangId || '';
            document.getElementById('mutasi-kendaraan-name').textContent = (row.dataset.mutasiNama || 'Kendaraan') + (plat ? ' — ' + plat : '');
            document.getElementById('mutasi-pemegang-lama').textContent = row.dataset.mutasiPemegangLama || 'Belum ada pemegang';
            document.getElementById('mutasi-pegawai-baru').value = '';
            document.getElementById('mutasi-tanggal').value = new Date().toISOString().slice(0, 10);
            document.getElementById('mutasi-nomor-surat').value = '';
            document.getElementById('mutasi-keterangan').value = '';
            mutasiForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            mutasiForm.action = mutasiStoreUrl.replace('__ID__', id);
            mutasiModal.classList.remove('hidden');
            mutasiModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function closeMutasiPemegang() {
            mutasiModal.classList.add('hidden');
            mutasiModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
        if (mutasiModal) {
            mutasiModal.querySelectorAll('[data-mutasi-close]').forEach(el => el.addEventListener('click', closeMutasiPemegang));
        }
        if (mutasiForm) {
            mutasiForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = mutasiForm.querySelector('button[type="submit"]');
                const original = submit.textContent;
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
                mutasiForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));

                const body = new FormData(mutasiForm);
                try {
                    const res = await fetch(mutasiForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body
                    });

                    if (res.status === 422) {
                        const data = await res.json();
                        const err = mutasiForm.querySelector('[data-error-for="id_pegawai_baru"]');
                        if (err) {
                            err.textContent = (data.errors && data.errors.id_pegawai_baru)
                                ? data.errors.id_pegawai_baru[0]
                                : (data.message || 'Pilih pemegang baru yang valid.');
                            err.classList.remove('hidden');
                        } else if (data.message) {
                            alert(data.message);
                        }
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }

                    if (res.ok) {
                        const data = await res.json();
                        if (data.success) {
                            closeMutasiPemegang();
                            if (data.download_bast_url) {
                                const suksesModal = document.getElementById('mutasi-sukses-modal');
                                if (suksesModal) {
                                    const btnBast = document.getElementById('btn-download-bast');
                                    if (btnBast) {
                                        btnBast.href = data.download_bast_url;
                                    }
                                    const msgEl = document.getElementById('mutasi-sukses-message');
                                    if (msgEl && data.message) {
                                        msgEl.textContent = data.message;
                                    }
                                    suksesModal.style.visibility = 'visible';
                                    document.body.classList.add('overflow-hidden');
                                    return;
                                }
                            }
                            window.location.reload();
                        } else {
                            alert(data.message || 'Terjadi kesalahan saat memproses mutasi.');
                            submit.textContent = original;
                            submit.disabled = false;
                        }
                    } else {
                        const data = await res.json().catch(() => null);
                        alert((data && data.message) ? data.message : 'Terjadi kesalahan. Coba lagi.');
                        submit.textContent = original;
                        submit.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    submit.textContent = original;
                    submit.disabled = false;
                }
            });
        }

        {{-- Berkas modal sukses mutasi --}}
        const berkasModal = document.getElementById('mutasi-sukses-modal');
        if (berkasModal) {
            berkasModal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', () => {
                berkasModal.style.visibility = 'hidden';
                document.body.classList.remove('overflow-hidden');
                window.location.reload();
            }));
        }

        // ================= Ekspor Kendaraan =================
        (function () {
            // Pilihan disimpan per-konteks filter (search|status). Bila filter
            // berubah, signature berubah sehingga pilihan lama tidak dipakai —
            // tidak ada ekspor diam-diam kendaraan yang tak lagi sesuai.
            const exportFilterSig = @json((string) request('search').'|'.(string) request('status'));
            const STORAGE_KEY = 'kendaraan_export_selection';

            const menuBtn = document.getElementById('btn-export-menu');
            const menu = document.getElementById('export-menu');
            const wrapper = document.getElementById('export-menu-wrapper');
            if (menuBtn && menu) {
                menuBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function (e) {
                    if (wrapper && !wrapper.contains(e.target)) {
                        menu.classList.add('hidden');
                    }
                });
            }

            const rowChecks = Array.from(document.querySelectorAll('.kendaraan-row-check'));
            const checkAll = document.getElementById('kendaraan-check-all');
            const countEl = document.getElementById('export-selected-count');

            function loadIds() {
                try {
                    const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                    if (!data || data.sig !== exportFilterSig || !Array.isArray(data.ids)) {
                        return [];
                    }
                    return data.ids.map(String);
                } catch (e) {
                    return [];
                }
            }
            function saveIds(ids) {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({ sig: exportFilterSig, ids: ids }));
                } catch (e) { /* storage tak tersedia */ }
            }

            let selected = loadIds();

            function applyState() {
                rowChecks.forEach(function (cb) {
                    cb.checked = selected.indexOf(String(cb.value)) !== -1;
                });
                if (checkAll && rowChecks.length) {
                    const checkedCount = rowChecks.filter(function (cb) { return cb.checked; }).length;
                    checkAll.checked = checkedCount === rowChecks.length;
                    checkAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
                }
                if (countEl) {
                    countEl.textContent = selected.length;
                }
            }

            rowChecks.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    const id = String(cb.value);
                    if (cb.checked) {
                        if (selected.indexOf(id) === -1) selected.push(id);
                    } else {
                        selected = selected.filter(function (x) { return x !== id; });
                    }
                    saveIds(selected);
                    applyState();
                });
            });

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    rowChecks.forEach(function (cb) {
                        const id = String(cb.value);
                        if (checkAll.checked) {
                            if (selected.indexOf(id) === -1) selected.push(id);
                        } else {
                            selected = selected.filter(function (x) { return x !== id; });
                        }
                    });
                    saveIds(selected);
                    applyState();
                });
            }

            applyState();

            const exportBtn = document.getElementById('btn-export-selected');
            const exportForm = document.getElementById('export-selected-form');
            const exportInputs = document.getElementById('export-selected-inputs');
            if (exportBtn && exportForm && exportInputs) {
                exportBtn.addEventListener('click', function () {
                    if (menu) menu.classList.add('hidden');
                    if (!selected.length) {
                        window.alert('Belum ada kendaraan yang dipilih. Centang kendaraan terlebih dahulu.');
                        return;
                    }
                    exportInputs.innerHTML = '';
                    selected.forEach(function (id) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        exportInputs.appendChild(input);
                    });
                    exportForm.submit();
                });
            }
        })();
    </script>

    {{-- Blok 2: Penempatan Awal Kendaraan --}}
    <script>
        (function() {
            const penempatanAwalUrl = @json(route('kendaraan.penempatan-awal', ['kendaraan' => '__ID__']));
            const modal = document.getElementById('penempatan-awal-kendaraan-modal');
            const form = document.getElementById('form-penempatan-awal-kendaraan');
            const selectPegawai = document.getElementById('pa-kendaraan-pegawai');
            const searchPegawai = document.getElementById('pa-kendaraan-pegawai-search');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (searchPegawai && selectPegawai) {
                searchPegawai.addEventListener('input', () => {
                    const term = searchPegawai.value.toLowerCase();
                    Array.from(selectPegawai.options).forEach(opt => {
                        opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }

            document.querySelectorAll('[data-penempatan-awal-kendaraan]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-penempatan-awal-kendaraan');
                    if (!modal || !form) return;
                    form.setAttribute('action', penempatanAwalUrl.replace('__ID__', id));
                    if (selectPegawai) { selectPegawai.value = ''; }
                    if (searchPegawai) { searchPegawai.value = ''; Array.from(selectPegawai.options).forEach(o => o.style.display = ''); }
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            document.querySelectorAll('[data-close-pa-kendaraan]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            });

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    try {
                        const res = await fetch(form.getAttribute('action'), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken || '', 'Accept': 'application/json' },
                            body: formData,
                        });
                        const data = await res.json();
                        if (!res.ok || data.success === false) {
                            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                            alert(firstError || data.message || 'Gagal melakukan penempatan awal.');
                            return;
                        }
                        window.location.reload();
                    } catch (err) {
                        alert('Gagal melakukan penempatan awal.');
                    }
                });
            }
        })();
    </script>

    {{-- Blok 3: Edit Pemegang Kendaraan (sebelum finalisasi) --}}
    <script>
        (function() {
            const editPemegangUrl = @json(route('kendaraan.edit-pemegang-penempatan', ['kendaraan' => '__ID__']));
            const modal = document.getElementById('edit-pemegang-kendaraan-modal');
            const form = document.getElementById('form-edit-pemegang-kendaraan');
            const nameEl = document.getElementById('epk-kendaraan-name');
            const selectPegawai = document.getElementById('epk-pegawai');
            const searchPegawai = document.getElementById('epk-pegawai-search');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (searchPegawai && selectPegawai) {
                searchPegawai.addEventListener('input', () => {
                    const term = searchPegawai.value.toLowerCase();
                    Array.from(selectPegawai.options).forEach(opt => {
                        opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }

            document.querySelectorAll('[data-edit-pemegang-kendaraan]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-edit-pemegang-kendaraan');
                    if (!modal || !form) return;
                    const nama = btn.getAttribute('data-nama-kendaraan') || 'Kendaraan';
                    const plat = btn.getAttribute('data-plat-nomor') || '';
                    if (nameEl) nameEl.textContent = nama + (plat ? ' — ' + plat : '');
                    form.setAttribute('action', editPemegangUrl.replace('__ID__', id));
                    if (selectPegawai) { selectPegawai.value = ''; }
                    if (searchPegawai) { searchPegawai.value = ''; Array.from(selectPegawai.options).forEach(o => o.style.display = ''); }
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            document.querySelectorAll('[data-close-epkend]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            });

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (selectPegawai && !selectPegawai.value) {
                        alert('Pilih pemegang terlebih dahulu.');
                        return;
                    }
                    const body = new FormData(form);
                    try {
                        const res = await fetch(form.getAttribute('action'), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken || '', 'Accept': 'application/json' },
                            body,
                        });
                        let data = null;
                        try { data = await res.json(); } catch (parseErr) { data = null; }
                        if (!res.ok || (data && data.success === false)) {
                            const firstError = data && data.errors ? Object.values(data.errors)[0]?.[0] : null;
                            alert(firstError || (data && data.message) || 'Gagal mengubah pemegang kendaraan.');
                            return;
                        }
                        if (data && data.warning) alert(data.warning);
                        window.location.reload();
                    } catch (err) {
                        alert('Gagal mengubah pemegang kendaraan.');
                    }
                });
            }
        })();
    </script>

    {{-- Blok 4: Finalisasi Individual & Massal Kendaraan --}}
    <script>
        (function() {
            const finalisasiUrl = @json(route('kendaraan.finalisasi-penempatan', ['kendaraan' => '__ID__']));
            const verifikasiUrl = @json(route('kendaraan.verifikasi-finalisasi', ['kendaraan' => '__ID__']));
            const finalisasiMassalUrl = @json(route('kendaraan.finalisasi-penempatan-massal'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // ---- Finalisasi Mandiri (Individual) ----
            const finalisasiModalEl = document.getElementById('finalisasi-kendaraan-modal');
            const spanFinalisasiNama = document.getElementById('finalisasi-kendaraan-nama');
            const spanFinalisasiPlat = document.getElementById('finalisasi-kendaraan-plat');
            const spanFinalisasiIdLabel = document.getElementById('finalisasi-kendaraan-id-label');
            const spanFinalisasiPesan = document.getElementById('finalisasi-kendaraan-pesan-extra');
            const btnFinalisasiConfirm = document.getElementById('btn-finalisasi-kendaraan-confirm');
            const btnFinalisasiLabel = document.getElementById('btn-finalisasi-kendaraan-label');
            const btnFinalisasiSpinner = document.getElementById('btn-finalisasi-kendaraan-spinner');

            let finalisasiIdKendaraan = null;
            let finalisasiUrlOverride = null;

            function bukaModalFinalisasi(btn, isVerifikasi = false) {
                if (!finalisasiModalEl || !btnFinalisasiConfirm) return;
                finalisasiIdKendaraan = btn.getAttribute(isVerifikasi ? 'data-kendaraan-verifikasi' : 'data-kendaraan-finalisasi');
                if (!finalisasiIdKendaraan) return;

                finalisasiUrlOverride = isVerifikasi ? verifikasiUrl : finalisasiUrl;
                if (spanFinalisasiNama) {
                    spanFinalisasiNama.textContent = btn.getAttribute('data-kendaraan-nama') || ('Kendaraan #' + finalisasiIdKendaraan);
                }
                if (spanFinalisasiPlat) {
                    spanFinalisasiPlat.textContent = btn.getAttribute('data-kendaraan-plat') || '-';
                }
                if (spanFinalisasiIdLabel) {
                    spanFinalisasiIdLabel.textContent = '#' + (btn.getAttribute('data-kendaraan-id') || finalisasiIdKendaraan);
                }
                if (spanFinalisasiPesan) {
                    if (isVerifikasi) {
                        spanFinalisasiPesan.textContent = 'Penempatan berstatus Perlu Verifikasi. Memverifikasi akan menandai penempatan agar dapat difinalisasi.';
                        spanFinalisasiPesan.classList.remove('hidden');
                    } else {
                        spanFinalisasiPesan.textContent = '';
                        spanFinalisasiPesan.classList.add('hidden');
                    }
                }

                btnFinalisasiConfirm.disabled = false;
                if (btnFinalisasiLabel) {
                    btnFinalisasiLabel.textContent = isVerifikasi ? 'Verifikasi & Finalisasi' : 'Finalisasi';
                }
                if (btnFinalisasiSpinner) {
                    btnFinalisasiSpinner.classList.add('hidden');
                }

                finalisasiModalEl.style.visibility = 'visible';
                document.body.classList.add('overflow-hidden');
            }

            document.querySelectorAll('[data-kendaraan-finalisasi]').forEach(btn => {
                btn.addEventListener('click', () => bukaModalFinalisasi(btn, false));
            });

            document.querySelectorAll('[data-kendaraan-verifikasi]').forEach(btn => {
                btn.addEventListener('click', () => bukaModalFinalisasi(btn, true));
            });

            function closeFinalisasiModal() {
                if (!finalisasiModalEl) return;
                finalisasiModalEl.style.visibility = 'hidden';
                document.body.classList.remove('overflow-hidden');
                finalisasiIdKendaraan = null;
                finalisasiUrlOverride = null;
            }

            document.querySelectorAll('#finalisasi-kendaraan-modal [data-finalisasi-kendaraan-close]').forEach(el => {
                el.addEventListener('click', closeFinalisasiModal);
            });

            if (btnFinalisasiConfirm) {
                btnFinalisasiConfirm.addEventListener('click', async () => {
                    if (!finalisasiIdKendaraan) return closeFinalisasiModal();

                    btnFinalisasiConfirm.disabled = true;
                    if (btnFinalisasiLabel) btnFinalisasiLabel.textContent = 'Memproses...';
                    if (btnFinalisasiSpinner) btnFinalisasiSpinner.classList.remove('hidden');

                    try {
                        const url = (finalisasiUrlOverride || finalisasiUrl).replace('__ID__', finalisasiIdKendaraan);

                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken || '', 'Accept': 'application/json' },
                        });

                        let data = {};
                        try {
                            data = await res.json();
                        } catch (e) {
                            data = {};
                        }

                        if (!res.ok || data.success === false) {
                            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                            const pesan = firstError
                                || data.message
                                || (res.status === 403 ? 'Anda tidak memiliki hak akses untuk memfinalisasi penempatan kendaraan ini.'
                                    : (res.status === 422 ? 'Penempatan kendaraan tidak dapat difinalisasi. Muat ulang halaman lalu coba lagi.'
                                        : 'Gagal memfinalisasi penempatan kendaraan. Coba lagi.'));
                            alert(pesan);
                            return;
                        }
                        closeFinalisasiModal();
                        window.location.reload();
                    } catch (err) {
                        alert('Gagal memfinalisasi penempatan kendaraan. Periksa koneksi Anda lalu coba lagi.');
                    } finally {
                        btnFinalisasiConfirm.disabled = false;
                        if (btnFinalisasiLabel) btnFinalisasiLabel.textContent = 'Finalisasi';
                        if (btnFinalisasiSpinner) btnFinalisasiSpinner.classList.add('hidden');
                    }
                });
            }

            // ---- Finalisasi Massal ----
            const btnMassal = document.getElementById('btn-finalisasi-kendaraan-massal');
            const countEl = document.getElementById('finalisasi-kendaraan-count');
            const checkAll = document.getElementById('kendaraan-check-all');
            const finalisasiMassalModalEl = document.getElementById('finalisasi-kendaraan-massal-modal');
            const btnFinalisasiMassalConfirm = document.getElementById('btn-finalisasi-kendaraan-massal-confirm');
            const btnFinalisasiMassalLabel = document.getElementById('btn-finalisasi-kendaraan-massal-label');
            const btnFinalisasiMassalSpinner = document.getElementById('btn-finalisasi-kendaraan-massal-spinner');
            const btnFinalisasiMassalBatal = document.getElementById('btn-finalisasi-kendaraan-massal-batal');
            const finalisasiMassalPesan = document.getElementById('finalisasi-kendaraan-massal-pesan');
            const finalisasiMassalHasil = document.getElementById('finalisasi-kendaraan-massal-hasil');
            const spanMassalJumlah = document.getElementById('finalisasi-kendaraan-massal-jumlah');
            const rowChecks = Array.from(document.querySelectorAll('.kendaraan-row-check'));

            function eligibleTerpilih() {
                return rowChecks
                    .filter(cb => cb.checked && cb.getAttribute('data-finalisasi-eligible') === '1')
                    .map(cb => cb.value);
            }

            function perbaruiTombolMassal() {
                const jumlah = eligibleTerpilih().length;
                if (countEl) countEl.textContent = jumlah;
                if (btnMassal) btnMassal.disabled = jumlah === 0;
            }

            rowChecks.forEach(cb => cb.addEventListener('change', perbaruiTombolMassal));
            if (checkAll) {
                checkAll.addEventListener('change', () => {
                    setTimeout(perbaruiTombolMassal, 0);
                });
            }
            perbaruiTombolMassal();

            let finalisasiMassalSelesai = false;

            function closeFinalisasiMassalModal() {
                if (!finalisasiMassalModalEl) return;
                if (finalisasiMassalSelesai) {
                    window.location.reload();
                    return;
                }
                finalisasiMassalModalEl.style.visibility = 'hidden';
                document.body.classList.remove('overflow-hidden');
            }

            function bukaModalFinalisasiMassal() {
                const ids = eligibleTerpilih();
                if (ids.length === 0) return;

                finalisasiMassalSelesai = false;
                if (spanMassalJumlah) {
                    spanMassalJumlah.textContent = ids.length;
                }
                if (finalisasiMassalHasil) {
                    finalisasiMassalHasil.innerHTML = '';
                    finalisasiMassalHasil.classList.add('hidden');
                }
                if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Finalisasi';
                if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.add('hidden');
                if (btnFinalisasiMassalConfirm) btnFinalisasiMassalConfirm.disabled = false;
                if (btnFinalisasiMassalBatal) {
                    btnFinalisasiMassalBatal.textContent = 'Batal';
                    btnFinalisasiMassalBatal.classList.remove('hidden');
                }

                finalisasiMassalModalEl.style.visibility = 'visible';
                document.body.classList.add('overflow-hidden');
            }

            if (btnMassal) {
                btnMassal.addEventListener('click', bukaModalFinalisasiMassal);
            }

            document.querySelectorAll('#finalisasi-kendaraan-massal-modal [data-finalisasi-kendaraan-massal-close]').forEach(el => {
                el.addEventListener('click', closeFinalisasiMassalModal);
            });

            function tampilkanHasilMassal(data) {
                if (!finalisasiMassalHasil) return;

                const bagian = [];
                bagian.push('<div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><span class="font-semibold">' + (data.berhasil || 0) + '</span> kendaraan berhasil difinalisasi.</div>');

                const gagal = Array.isArray(data.detail_gagal) ? data.detail_gagal : [];
                if (gagal.length > 0) {
                    bagian.push('<div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-800">');
                    bagian.push('<p class="font-semibold">' + gagal.length + ' kendaraan gagal atau dilewati:</p>');
                    bagian.push('<ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">');
                    gagal.forEach(g => {
                        bagian.push('<li><span class="font-medium">' + (g.nama || ('Kendaraan #' + (g.id_kendaraan || g.id))) + '</span> — ' + (g.alasan || 'Tidak diketahui') + '</li>');
                    });
                    bagian.push('</ul></div>');
                }

                finalisasiMassalHasil.innerHTML = bagian.join('');
                finalisasiMassalHasil.classList.remove('hidden');
            }

            if (btnFinalisasiMassalConfirm) {
                btnFinalisasiMassalConfirm.addEventListener('click', async () => {
                    // Setelah selesai: tombol berfungsi menutup modal + muat ulang
                    if (finalisasiMassalSelesai) {
                        window.location.reload();
                        return;
                    }

                    const ids = eligibleTerpilih();
                    if (ids.length === 0) return closeFinalisasiMassalModal();

                    btnFinalisasiMassalConfirm.disabled = true;
                    if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Memproses...';
                    if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.remove('hidden');

                    try {
                        const body = new FormData();
                        ids.forEach(id => {
                            body.append('ids[]', id);
                            body.append('id_kendaraan[]', id);
                        });

                        const res = await fetch(finalisasiMassalUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken || '', 'Accept': 'application/json' },
                            body,
                        });

                        let data = {};
                        try {
                            data = await res.json();
                        } catch (e) {
                            data = {};
                        }

                        if (!res.ok || data.success === false) {
                            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                            const pesan = firstError
                                || data.message
                                || (res.status === 403 ? 'Anda tidak memiliki hak akses untuk memfinalisasi penempatan kendaraan terpilih.'
                                    : (res.status === 422 ? 'Daftar kendaraan tidak valid. Muat ulang halaman lalu coba lagi.'
                                        : 'Gagal memfinalisasi penempatan kendaraan terpilih. Coba lagi.'));
                            alert(pesan);
                            btnFinalisasiMassalConfirm.disabled = false;
                            if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Finalisasi';
                            if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.add('hidden');
                            return;
                        }

                        // Tampilkan laporan hasil (sukses + gagal/dilewati)
                        tampilkanHasilMassal(data);
                        finalisasiMassalSelesai = true;

                        // Tombol berubah jadi satu tombol hijau "Tutup & Muat Ulang Tabel"
                        btnFinalisasiMassalConfirm.disabled = false;
                        if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Tutup & Muat Ulang Tabel';
                        if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.add('hidden');
                        if (btnFinalisasiMassalBatal) {
                            btnFinalisasiMassalBatal.classList.add('hidden');
                        }
                    } catch (err) {
                        alert('Gagal memfinalisasi penempatan kendaraan. Periksa koneksi Anda lalu coba lagi.');
                        btnFinalisasiMassalConfirm.disabled = false;
                        if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Finalisasi';
                        if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.add('hidden');
                    }
                });
            }
        })();
    </script>
@endpush

