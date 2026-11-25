@push('scripts')
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const loginUrl = @json(route('login'));

        function openModal(el) {
            if (!el) return;
            el.classList.remove('hidden');
            el.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(el) {
            if (!el) return;
            el.classList.add('hidden');
            el.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function bindClose(modalEl, closeSelector) {
            if (!modalEl) return;
            modalEl.querySelectorAll(closeSelector).forEach(el => {
                el.addEventListener('click', () => closeModal(modalEl));
            });
        }

        @if ($bolehCetakDokumen)
        // ---- Cakupan Cetak Label Ruangan ----
        const labelScopeModal = document.getElementById('label-scope-modal');
        const labelScopeForm = document.getElementById('label-scope-form');
        const btnLabelScope = document.getElementById('btn-label-scope');
        const labelCetakAset = document.getElementById('label-cetak-aset');
        const labelCetakInventaris = document.getElementById('label-cetak-inventaris');
        const labelScopeError = document.getElementById('label-scope-error');

        if (btnLabelScope && labelScopeModal && labelScopeForm) {
            btnLabelScope.addEventListener('click', () => {
                if (labelScopeError) labelScopeError.classList.add('hidden');
                openModal(labelScopeModal);
            });
            bindClose(labelScopeModal, '[data-label-scope-close]');
            labelScopeForm.addEventListener('submit', (e) => {
                if (!labelCetakAset?.checked && !labelCetakInventaris?.checked) {
                    e.preventDefault();
                    if (labelScopeError) labelScopeError.classList.remove('hidden');
                }
            });
        }
        @endif

        @if ($bolehKelolaRuangan)
        // ---- Inventaris Lokal (Kelola & Mutasi) ----
        const invStoreUrl = @json(route('aset-ruangan.inventaris.store', $ruangan->id_ruangan));
        const invUpdateUrl = @json(route('aset-ruangan.inventaris.update', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));
        const invMoveUrl = @json(route('aset-ruangan.inventaris.move', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));
        const invDelUrl = @json(route('aset-ruangan.inventaris.destroy', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));

        const invModal = document.getElementById('inv-modal');
        const invForm = document.getElementById('inv-form');
        const invMethod = document.getElementById('inv-method');
        const invTitle = document.getElementById('inv-modal-title');
        const invKodeInput = document.getElementById('inv-kode');
        const invKodeNote = document.getElementById('inv-kode-note');

        function clearInvErrors() {
            if (!invForm) return;
            invForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }

        function showInvErrors(errors, form) {
            if (!form) return;
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors || {}).forEach(field => {
                const el = form.querySelector('[data-inv-error="' + field + '"]');
                if (el) {
                    el.textContent = errors[field][0];
                    el.classList.remove('hidden');
                }
            });
        }

        // Tambah Inventaris
        const btnAdd = document.getElementById('btn-inv-add');
        if (btnAdd && invForm && invModal) {
            btnAdd.addEventListener('click', () => {
                invForm.reset();
                invForm.action = invStoreUrl;
                if (invMethod) invMethod.value = 'POST';
                if (invTitle) invTitle.textContent = 'Tambah Inventaris';
                if (invKodeInput) invKodeInput.value = '';
                if (invKodeNote) invKodeNote.textContent = 'Kosongkan agar kode dibuat otomatis (contoh: RUA-INV-001).';
                clearInvErrors();
                openModal(invModal);
            });
        }

        // Ubah Inventaris
        document.querySelectorAll('[data-inv-edit]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!invForm || !invModal) return;
                invForm.reset();
                invForm.action = invUpdateUrl.replace('__ID__', btn.dataset.invEdit);
                if (invMethod) invMethod.value = 'PUT';
                if (invTitle) invTitle.textContent = 'Ubah Inventaris';
                if (invKodeInput) invKodeInput.value = btn.dataset.invKode || '';
                if (invKodeNote) invKodeNote.textContent = 'Kosongkan untuk mempertahankan kode saat ini.';
                const elNama = document.getElementById('inv-nama');
                const elMerk = document.getElementById('inv-merk');
                const elKondisi = document.getElementById('inv-kondisi');
                const elKeterangan = document.getElementById('inv-keterangan');
                if (elNama) elNama.value = btn.dataset.invNama || '';
                if (elMerk) elMerk.value = btn.dataset.invMerk || '';
                if (elKondisi) elKondisi.value = btn.dataset.invKondisi || 'Baik';
                if (elKeterangan) elKeterangan.value = btn.dataset.invKeterangan || '';
                clearInvErrors();
                openModal(invModal);
            });
        });

        if (invModal) bindClose(invModal, '[data-inv-close]');

        if (invForm) {
            invForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const kodeRaw = invKodeInput ? invKodeInput.value : '';
                if (kodeRaw !== '' && kodeRaw.trim() === '') {
                    showInvErrors({ kode_inventaris: ['Kode inventaris tidak boleh hanya berisi spasi.'] }, invForm);
                    return;
                }

                const submit = document.getElementById('btn-inv-submit');
                const original = submit ? submit.textContent : 'Simpan';
                if (submit) {
                    submit.textContent = 'Menyimpan...';
                    submit.disabled = true;
                }

                try {
                    const res = await fetch(invForm.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(invForm),
                    });
                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        showInvErrors(data.errors || {}, invForm);
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }
                    if (res.ok) { window.location.reload(); return; }
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Gagal menyimpan inventaris.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                }
            });
        }

        // Mutasi Ruangan (Inventaris Lokal)
        const moveModal = document.getElementById('inv-move-modal');
        const moveForm = document.getElementById('inv-move-form');
        document.querySelectorAll('[data-inv-move]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!moveForm || !moveModal) return;
                moveForm.reset();
                moveForm.action = invMoveUrl.replace('__ID__', btn.dataset.invMove);
                const tujuan = document.getElementById('inv-tujuan');
                if (tujuan) tujuan.value = '';
                const aset = document.getElementById('inv-move-aset');
                if (aset) aset.textContent = btn.dataset.invNama || '-';
                const kib = document.getElementById('inv-move-kib');
                if (kib) kib.textContent = btn.dataset.invKode || '-';
                const keterangan = document.getElementById('inv-move-keterangan');
                if (keterangan) keterangan.value = btn.dataset.invKeterangan || '';
                moveForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                openModal(moveModal);
            });
        });
        if (moveModal) bindClose(moveModal, '[data-inv-move-close]');

        if (moveForm) {
            moveForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-inv-move-submit');
                const original = submit ? submit.textContent : 'Pindahkan';
                if (submit) {
                    submit.textContent = 'Memindahkan...';
                    submit.disabled = true;
                }
                try {
                    const res = await fetch(moveForm.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(moveForm),
                    });
                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        moveForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                        const el = moveForm.querySelector('[data-inv-error="id_ruangan_tujuan"]');
                        if (el) {
                            el.textContent = (data.errors && data.errors.id_ruangan_tujuan ? data.errors.id_ruangan_tujuan[0] : (data.message || 'Ruangan tujuan tidak valid.'));
                            el.classList.remove('hidden');
                        }
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }
                    if (res.ok) { window.location.reload(); return; }
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Gagal memindahkan inventaris.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                }
            });
        }

        // Hapus Inventaris Lokal
        const delModal = document.getElementById('inv-del-modal');
        const delForm = document.getElementById('inv-del-form');
        document.querySelectorAll('[data-inv-del]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!delForm || !delModal) return;
                delForm.action = invDelUrl.replace('__ID__', btn.dataset.invDel);
                const elName = document.getElementById('inv-del-name');
                if (elName) elName.textContent = btn.dataset.invNama || '';
                openModal(delModal);
            });
        });
        if (delModal) bindClose(delModal, '[data-inv-del-close]');
        @endif

        @if ($isAdminAset || $bolehKelolaRuangan)
        // ---- Keluarkan Aset (Admin Aset & Admin UPT) ----
        const detachModal = document.getElementById('detach-modal');
        const detachForm = document.getElementById('detach-form');
        const detachUrl = @json(route('aset-ruangan.aset.detach', ['ruangan' => $ruangan->id_ruangan, 'aset' => '__ID__']));

        document.querySelectorAll('[data-detach-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!detachForm || !detachModal) return;
                detachForm.action = detachUrl.replace('__ID__', btn.dataset.detachTarget);
                const elName = document.getElementById('detach-name');
                if (elName) elName.textContent = btn.dataset.detachName || '';
                openModal(detachModal);
            });
        });
        if (detachModal) bindClose(detachModal, '[data-detach-close]');
        @endif

        @if ($isAdminAset)
        // ---- Pasang Aset ke Ruangan (Admin Aset Only) ----
        const pasangModal = document.getElementById('pasang-modal');
        const pasangForm = document.getElementById('pasang-form');
        const btnPasang = document.getElementById('btn-pasang');
        const pasangKandidatUrl = @json(route('aset-ruangan.aset.pasang.kandidat', ['ruangan' => $ruangan->id_ruangan]));
        const pasangMassalUrl = @json(route('aset-ruangan.aset.pasang-massal', ['ruangan' => $ruangan->id_ruangan]));
        const jenisSelect = document.getElementById('field-jenis-select');
        const jenisSearch = document.getElementById('field-jenis-search');
        const jumlahInput = document.getElementById('field-jumlah');
        const pasangTersedia = document.getElementById('pasang-tersedia');
        const pasangRingkasan = document.getElementById('pasang-ringkasan');
        const ringkasanJenis = document.getElementById('ringkasan-jenis');
        const ringkasanJumlah = document.getElementById('ringkasan-jumlah');

        let pasangKandidat = [];

        function clearPasangErrors() {
            if (!pasangForm) return;
            pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }

        function showPasangErrors(errors) {
            if (!pasangForm) return;
            pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors || {}).forEach(field => {
                const err = pasangForm.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function filterJenisOptions() {
            if (!jenisSelect) return;
            const term = jenisSearch ? jenisSearch.value.toLowerCase().trim() : '';
            Array.from(jenisSelect.options).forEach(opt => {
                if (opt.disabled && !opt.value) return;
                opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
            });
        }

        if (jenisSearch && jenisSelect) {
            jenisSearch.addEventListener('input', filterJenisOptions);
        }

        function refreshRingkasan() {
            const opt = jenisSelect && jenisSelect.selectedIndex >= 0 ? jenisSelect.options[jenisSelect.selectedIndex] : null;
            const idBarang = (opt && opt.value) ? opt.value : '';
            const ditemukan = pasangKandidat.find(k => String(k.id_barang) === String(idBarang));
            const tersedia = ditemukan ? ditemukan.tersedia : 0;

            if (pasangTersedia) pasangTersedia.textContent = tersedia;
            if (jumlahInput && tersedia > 0) jumlahInput.max = tersedia;

            const jumlah = jumlahInput && parseInt(jumlahInput.value, 10) > 0 ? parseInt(jumlahInput.value, 10) : 0;

            if (pasangRingkasan && jumlah > 0 && opt && opt.value) {
                pasangRingkasan.classList.remove('hidden');
                ringkasanJenis.textContent = opt.dataset.nama || opt.textContent;
                ringkasanJumlah.textContent = jumlah;
            } else if (pasangRingkasan) {
                pasangRingkasan.classList.add('hidden');
            }
        }

        async function muatKandidat() {
            if (!jenisSelect) return;
            try {
                const res = await fetch(pasangKandidatUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const data = await res.json().catch(() => ({}));
                pasangKandidat = data.kandidat || [];

                const current = jenisSelect.value;
                jenisSelect.innerHTML = '';
                if (pasangKandidat.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.disabled = true;
                    opt.textContent = '-- Tidak ada barang yang dapat dipasang --';
                    jenisSelect.appendChild(opt);
                } else {
                    pasangKandidat.forEach(k => {
                        const opt = document.createElement('option');
                        opt.value = k.id_barang;
                        opt.textContent = k.nama_barang + ' (' + k.tersedia + ' tersedia)';
                        opt.setAttribute('data-nama', k.nama_barang);
                        opt.className = 'py-1 px-2 cursor-pointer hover:bg-slate-100';
                        jenisSelect.appendChild(opt);
                    });
                }

                if (current) {
                    jenisSelect.value = current;
                }

                filterJenisOptions();
                refreshRingkasan();
            } catch (err) {
                pasangKandidat = [];
            }
        }

        function openPasangModal() {
            if (!pasangModal) return;
            openModal(pasangModal);
            clearPasangErrors();
            if (jenisSearch) {
                jenisSearch.value = '';
            }
            if (jenisSelect) {
                jenisSelect.value = '';
                Array.from(jenisSelect.options).forEach(o => o.style.display = '');
            }
            if (jumlahInput) jumlahInput.value = '';
            refreshRingkasan();
            muatKandidat();
            setTimeout(() => {
                if (jenisSearch) jenisSearch.focus();
            }, 50);
        }

        function closePasangModal() {
            if (!pasangModal) return;
            closeModal(pasangModal);
            clearPasangErrors();
        }

        if (btnPasang && pasangModal) {
            btnPasang.addEventListener('click', openPasangModal);
            bindClose(pasangModal, '[data-pasang-close]');
            muatKandidat();
        }

        if (jenisSelect) {
            jenisSelect.addEventListener('change', refreshRingkasan);
            jenisSelect.addEventListener('click', refreshRingkasan);
        }
        if (jumlahInput) jumlahInput.addEventListener('input', refreshRingkasan);

        if (pasangForm) {
            pasangForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-pasang-submit');
                const original = submit ? submit.textContent : 'Pasang';

                const idBarang = jenisSelect ? jenisSelect.value : '';
                const jumlah = jumlahInput ? parseInt(jumlahInput.value, 10) : 0;

                if (!idBarang) {
                    showPasangErrors({ id_barang: ['Pilih jenis barang terlebih dahulu.'] });
                    return;
                }
                if (!jumlah || jumlah < 1) {
                    showPasangErrors({ jumlah: ['Jumlah minimal 1.'] });
                    return;
                }

                if (submit) {
                    submit.textContent = 'Memasang...';
                    submit.disabled = true;
                }
                try {
                    const body = new FormData();
                    body.append('id_barang', idBarang);
                    body.append('jumlah', jumlah);

                    const res = await fetch(pasangMassalUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body,
                    });

                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        showPasangErrors(data.errors || {});
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        window.location.reload();
                        return;
                    }

                    alert(data.message || 'Gagal memasang aset.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                }
            });
        }

        // ---- Mutasi Ruangan (Aset Melekat - Admin Aset Only) ----
        const mutasiRuanganModal = document.getElementById('mutasi-ruangan-modal');
        const mutasiRuanganForm = document.getElementById('mutasi-ruangan-form');
        const mutasiRuanganUrl = @json(route('aset-ruangan.aset.mutasi-ruangan', ['ruangan' => $ruangan->id_ruangan, 'aset' => '__ID__']));
        const mutasiRuanganAset = document.getElementById('mutasi-ruangan-aset');
        const mutasiRuanganKib = document.getElementById('mutasi-ruangan-kib');

        document.querySelectorAll('[data-mutasi-ruangan]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!mutasiRuanganModal || !mutasiRuanganForm) return;
                mutasiRuanganForm.action = mutasiRuanganUrl.replace('__ID__', btn.getAttribute('data-mutasi-ruangan'));
                if (mutasiRuanganAset) mutasiRuanganAset.textContent = btn.getAttribute('data-aset-nama') || '-';
                if (mutasiRuanganKib) mutasiRuanganKib.textContent = btn.getAttribute('data-aset-kib') || '-';
                const elTujuan = document.getElementById('mutasi-ruangan-tujuan');
                const elKet = document.getElementById('mutasi-ruangan-keterangan');
                if (elTujuan) elTujuan.value = '';
                if (elKet) elKet.value = '';
                openModal(mutasiRuanganModal);
            });
        });
        if (mutasiRuanganModal) bindClose(mutasiRuanganModal, '[data-mutasi-ruangan-close]');

        if (mutasiRuanganForm) {
            mutasiRuanganForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-mutasi-ruangan-submit');
                const original = submit ? submit.textContent : 'Pindahkan';
                if (submit) {
                    submit.textContent = 'Memindahkan...';
                    submit.disabled = true;
                }

                try {
                    const res = await fetch(mutasiRuanganForm.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(mutasiRuanganForm),
                    });

                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        const first = data.errors ? (Object.values(data.errors)[0]?.[0] || 'Validasi gagal.') : 'Validasi gagal.';
                        alert(first);
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        window.location.reload();
                        return;
                    }

                    if (res.status === 401 || res.status === 419) {
                        alert(data.message || 'Sesi Anda telah berakhir. Silakan masuk kembali.');
                        window.location.href = loginUrl;
                        return;
                    }

                    if (res.status === 403) {
                        alert(data.message || 'Anda tidak memiliki hak akses untuk memindahkan aset ini.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }

                    if (res.status === 404) {
                        alert(data.message || 'Endpoint mutasi tidak ditemukan. Muat ulang halaman lalu coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }

                    if (res.status >= 500) {
                        alert(data.message || 'Terjadi kesalahan di server saat memindahkan aset. Silakan coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                        return;
                    }

                    alert(data.message || 'Gagal memindahkan aset.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                } catch (err) {
                    console.error('Mutasi Ruangan gagal:', err);
                    const pesanJaringan = (err instanceof TypeError) && /fetch|network|Failed to fetch/i.test(err.message || '');
                    alert(pesanJaringan
                        ? 'Koneksi bermasalah. Coba lagi.'
                        : 'Terjadi kesalahan pada halaman ini. Muat ulang halaman (Ctrl+F5) lalu coba lagi.');
                    if (submit) { submit.textContent = original; submit.disabled = false; }
                }
            });
        }
        @endif
    })();
</script>
@endpush

