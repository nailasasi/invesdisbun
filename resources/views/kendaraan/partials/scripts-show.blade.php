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

    // Filter tabel Pajak Kendaraan (server-side query melalui parameter URL).
    const pajakFilterTahun = document.getElementById('pajak-filter-tahun');
    const pajakFilterJenis = document.getElementById('pajak-filter-jenis');

    if (pajakFilterTahun && pajakFilterJenis) {
        const handlePajakFilterChange = () => {
            const url = new URL(window.location.href);
            if (pajakFilterTahun.value) {
                url.searchParams.set('pajak_tahun', pajakFilterTahun.value);
            } else {
                url.searchParams.delete('pajak_tahun');
            }
            if (pajakFilterJenis.value) {
                url.searchParams.set('pajak_jenis', pajakFilterJenis.value);
            } else {
                url.searchParams.delete('pajak_jenis');
            }
            url.searchParams.delete('pajak_page'); // Reset ke halaman 1 saat filter berubah
            url.hash = 'pajak-card';
            window.location.href = url.toString();
        };
        pajakFilterTahun.addEventListener('change', handlePajakFilterChange);
        pajakFilterJenis.addEventListener('change', handlePajakFilterChange);
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
    const radioTahunan = document.getElementById('pajak-tipe-tahunan');
    const radio5Tahunan = document.getElementById('pajak-tipe-5tahunan');
    const wrap5Tahunan = document.getElementById('wrap-5tahunan');
    const inputBerakhirStnk = document.getElementById('pajak-berakhir-stnk');
    const inputPlatBaru = document.getElementById('plat-baru');
    const noteLockedType = document.getElementById('pajak-jenis-locked-note');
    const buktiInput = document.getElementById('pajak-bukti');
    const buktiWajib = document.getElementById('pajak-bukti-wajib');
    const buktiHint = document.getElementById('pajak-bukti-hint');
    const buktiExisting = document.getElementById('pajak-bukti-existing');

    function syncTypeVisibility() {
        const is5 = radio5Tahunan && radio5Tahunan.checked;
        if (wrap5Tahunan) {
            wrap5Tahunan.classList.toggle('hidden', !is5);
        }
        if (inputBerakhirStnk) {
            inputBerakhirStnk.required = is5;
        }
        if (inputPlatBaru) {
            inputPlatBaru.required = is5;
        }
    }

    if (radioTahunan) radioTahunan.addEventListener('change', syncTypeVisibility);
    if (radio5Tahunan) radio5Tahunan.addEventListener('change', syncTypeVisibility);

    function clearPajakErrors() {
        if (!pajakForm) return;
        pajakForm.querySelectorAll('.field-error').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        const alertEl = document.getElementById('pajak-form-alert');
        if (alertEl) {
            alertEl.textContent = '';
            alertEl.classList.add('hidden');
        }
    }

    function resetPajakForm() {
        if (!pajakForm) return;
        pajakForm.reset();
        clearPajakErrors();
        const modalBody = document.getElementById('pajak-modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
        if (pajakMethod) pajakMethod.value = '';
        pajakForm.action = pajakStoreUrl.replace('__ID__', kendaraanId);
        if (pajakModalTitle) pajakModalTitle.textContent = 'Tambah Transaksi Pajak';

        if (radioTahunan) {
            radioTahunan.disabled = false;
            radioTahunan.checked = true;
        }
        if (radio5Tahunan) {
            radio5Tahunan.disabled = false;
            radio5Tahunan.checked = false;
        }
        if (noteLockedType) noteLockedType.classList.add('hidden');

        const tahunEl = document.getElementById('pajak-tahun');
        if (tahunEl) tahunEl.value = new Date().getFullYear();
        const bayarEl = document.getElementById('pajak-bayar');
        if (bayarEl) bayarEl.value = new Date().toISOString().split('T')[0];

        if (buktiInput) buktiInput.required = true;
        if (buktiWajib) buktiWajib.classList.remove('hidden');
        if (buktiHint) buktiHint.textContent = 'Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.';
        if (buktiExisting) {
            buktiExisting.classList.add('hidden');
            buktiExisting.textContent = '';
        }

        syncTypeVisibility();
    }

    document.querySelectorAll('[data-pajak-modal]').forEach(b => b.addEventListener('click', () => {
        resetPajakForm();
        if (pajakModal) openModal(pajakModal);
    }));

    document.querySelectorAll('[data-edit-pajak]').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            resetPajakForm();
            if (pajakModalTitle) pajakModalTitle.textContent = 'Edit Transaksi Pajak';
            if (pajakForm) {
                pajakForm.action = pajakUpdateBase.replace('__ID__', kendaraanId).replace('__PAJAK__', d.editPajak);
            }
            if (pajakMethod) pajakMethod.value = 'PUT';

            const tahunEl = document.getElementById('pajak-tahun');
            if (tahunEl) tahunEl.value = d.tahun ?? '';
            const berakhirEl = document.getElementById('pajak-berakhir');
            if (berakhirEl) berakhirEl.value = d.berakhir ?? '';
            const bayarEl = document.getElementById('pajak-bayar');
            if (bayarEl) bayarEl.value = d.bayar ?? '';
            const nominalEl = document.getElementById('pajak-nominal');
            if (nominalEl) nominalEl.value = d.nominal || d.total || '';

            const is5 = d.is5tahunan === '1';
            if (is5) {
                if (radio5Tahunan) radio5Tahunan.checked = true;
                if (radioTahunan) radioTahunan.checked = false;
                if (inputBerakhirStnk) inputBerakhirStnk.value = d.berakhirStnk ?? '';
                if (inputPlatBaru) inputPlatBaru.value = d.plat ?? '';
            } else {
                if (radioTahunan) radioTahunan.checked = true;
                if (radio5Tahunan) radio5Tahunan.checked = false;
            }

            // Kunci jenis transaksi saat edit (tidak boleh diubah)
            if (radioTahunan) radioTahunan.disabled = true;
            if (radio5Tahunan) radio5Tahunan.disabled = true;
            if (noteLockedType) noteLockedType.classList.remove('hidden');

            syncTypeVisibility();

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
            clearPajakErrors();

            const btnSubmit = document.getElementById('pajak-btn-submit');
            const originalText = btnSubmit ? btnSubmit.textContent : 'Simpan';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Menyimpan...';
            }

            const fd = new FormData(pajakForm);

            // Karena radio disabled tidak dikirim oleh browser secara default,
            // pastikan pajak_5_tahunan tetap terkirim saat edit.
            if (pajakMethod && pajakMethod.value === 'PUT') {
                const is5 = radio5Tahunan && radio5Tahunan.checked ? '1' : '0';
                fd.set('pajak_5_tahunan', is5);
            }

            const alertEl = document.getElementById('pajak-form-alert');

            try {
                const res = await fetch(pajakForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: fd,
                });

                if (res.status === 422) {
                    const data = await res.json().catch(() => ({}));
                    if (data.errors) {
                        for (const [field, messages] of Object.entries(data.errors)) {
                            const errorEl = pajakForm.querySelector(`[data-error-for="${field}"]`);
                            if (errorEl) {
                                errorEl.textContent = Array.isArray(messages) ? messages[0] : messages;
                                errorEl.classList.remove('hidden');
                            }
                        }
                    }
                    if (data.message && alertEl) {
                        alertEl.textContent = data.message;
                        alertEl.classList.remove('hidden');
                    }
                    const modalBody = document.getElementById('pajak-modal-body');
                    if (modalBody) {
                        modalBody.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = originalText;
                    }
                    return; // JANGAN reload saat validasi 422 gagal
                }

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    if (alertEl) {
                        alertEl.textContent = data.message || 'Terjadi kesalahan sistem (status ' + res.status + ').';
                        alertEl.classList.remove('hidden');
                    } else {
                        alert(data.message || 'Terjadi kesalahan sistem.');
                    }
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = originalText;
                    }
                    return; // JANGAN reload saat request gagal
                }

                // Sukses: arahkan ke halaman 1 tanpa filter agar transaksi aktif baru langsung tampil paling atas
                const url = new URL(window.location.href);
                url.searchParams.delete('pajak_page');
                url.searchParams.delete('pajak_tahun');
                url.searchParams.delete('pajak_jenis');
                url.hash = 'pajak-card';
                window.location.href = url.toString();
            } catch (err) {
                if (alertEl) {
                    alertEl.textContent = 'Gagal terhubung ke server. Periksa koneksi Anda.';
                    alertEl.classList.remove('hidden');
                } else {
                    alert('Koneksi bermasalah.');
                }
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = originalText;
                }
            }
        });
    }

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

