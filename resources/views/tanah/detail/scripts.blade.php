{{-- JavaScript Interaksi Halaman Detail Tanah --}}
@push('scripts')
<script>
    (function () {
        // Tabs
        const tabBtns = Array.from(document.querySelectorAll('[data-tab-target]'));
        const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

        function activateTab(id) {
            tabBtns.forEach((b) => {
                const on = b.dataset.tabTarget === id;
                b.classList.toggle('bg-disbun-700', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('shadow-xs', on);
                b.classList.toggle('text-slate-600', !on);
            });
            tabPanels.forEach((p) => p.classList.toggle('hidden', p.id !== id));
        }

        tabBtns.forEach((b) => b.addEventListener('click', () => activateTab(b.dataset.tabTarget)));

        // Modal Dokumen PBB
        const pbbModal = document.getElementById('dokumen-pbb-modal');
        if (pbbModal) {
            document.querySelectorAll('[data-open-pbb-modal]').forEach((b) => {
                b.addEventListener('click', () => {
                    pbbModal.classList.remove('hidden');
                    pbbModal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                });
            });
            pbbModal.querySelectorAll('[data-modal-close]').forEach((x) => {
                x.addEventListener('click', () => {
                    pbbModal.classList.add('hidden');
                    pbbModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                });
            });
        }

        // Modal Konfirmasi Hapus Dokumen PBB
        const hapusPbbModal = document.getElementById('hapus-pbb-modal');
        if (hapusPbbModal) {
            const hapusPbbForm = document.getElementById('hapus-pbb-form');
            const tahunPbbEl = hapusPbbModal.querySelector('[data-hapus-pbb-tahun]');

            const bukaHapusPbb = (action, tahun) => {
                if (action) {
                    hapusPbbForm.setAttribute('action', action);
                }
                if (tahun !== undefined && tahun !== null) {
                    tahunPbbEl.textContent = tahun;
                }
                hapusPbbModal.classList.remove('hidden');
                hapusPbbModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('[data-open-hapus-pbb]').forEach((b) => {
                b.addEventListener('click', () => bukaHapusPbb(b.dataset.action, b.dataset.tahun));
            });
            hapusPbbModal.querySelectorAll('[data-modal-close]').forEach((x) => {
                x.addEventListener('click', () => {
                    hapusPbbModal.classList.add('hidden');
                    hapusPbbModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                });
            });

            if (hapusPbbModal.dataset.autoOpen === '1') {
                bukaHapusPbb(null, null);
            }
        }

        // Modal Lihat Bukti Otorisasi
        const buktiModal = document.getElementById('bukti-modal');
        if (buktiModal) {
            const buktiFoto = buktiModal.querySelector('[data-bukti-foto]');
            const buktiTbody = buktiModal.querySelector('[data-bukti-perubahan]');
            const buktiPerubahanWrap = buktiModal.querySelector('[data-bukti-perubahan-wrap]');
            const setText = (sel, val) => {
                const el = buktiModal.querySelector(sel);
                if (el) el.textContent = (val === null || val === undefined || val === '') ? '—' : val;
            };
            const fmt = (v) => (v === null || v === undefined || v === '') ? '—' : String(v);

            const bukaBukti = (data) => {
                buktiFoto.setAttribute('src', data.foto || '');
                setText('[data-bukti-aktivitas]', data.aktivitas);
                setText('[data-bukti-aktivitas-2]', data.aktivitas);
                setText('[data-bukti-aktor]', data.aktor + (data.peran ? ' (' + data.peran + ')' : ''));
                setText('[data-bukti-objek]', data.objek);
                setText('[data-bukti-waktu]', data.waktu);

                buktiTbody.innerHTML = '';
                const perubahan = Array.isArray(data.perubahan) ? data.perubahan : [];
                if (perubahan.length) {
                    perubahan.forEach((p) => {
                        const tr = document.createElement('tr');
                        ['field', 'lama', 'baru'].forEach((k, i) => {
                            const td = document.createElement('td');
                            td.className = 'px-3 py-2 align-top ' + (i === 0 ? 'font-medium text-slate-700' : 'text-slate-600');
                            td.textContent = fmt(p[k]);
                            tr.appendChild(td);
                        });
                        buktiTbody.appendChild(tr);
                    });
                    buktiPerubahanWrap.classList.remove('hidden');
                } else {
                    buktiPerubahanWrap.classList.add('hidden');
                }

                buktiModal.classList.remove('hidden');
                buktiModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('[data-buka-bukti]').forEach((b) => {
                b.addEventListener('click', () => {
                    try {
                        bukaBukti(JSON.parse(b.dataset.bukti));
                    } catch (e) {
                        // data tidak valid: abaikan
                    }
                });
            });
            buktiModal.querySelectorAll('[data-bukti-close]').forEach((x) => {
                x.addEventListener('click', () => {
                    buktiModal.classList.add('hidden');
                    buktiModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                    buktiFoto.setAttribute('src', '');
                });
            });
        }
    })();
</script>
@endpush

