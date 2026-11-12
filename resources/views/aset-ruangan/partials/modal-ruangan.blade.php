@if ($bolehKelolaRuangan)
{{-- Modal Tambah / Edit Ruangan --}}
<div id="ruangan-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah Ruangan</h3>
                <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Lengkapi data ruangan.</p>
            </div>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="ruangan-form" method="POST" action="{{ route('aset-ruangan.store') }}" autocomplete="off">
            @csrf
            <input type="hidden" id="modal-id" value="">
            <div class="space-y-5 px-6 py-6">
                <x-input label="Nama Ruangan" name="nama_ruangan" id="field-nama" placeholder="contoh: Ruang Kepala" required maxlength="100" />
                <x-input label="Lantai" name="lantai" id="field-lantai" placeholder="contoh: 2" maxlength="20" />
                <x-select label="Lokasi" name="id_lokasi" id="field-lokasi" :options="$lokasiOptions->pluck('nama_lokasi', 'id_lokasi')" placeholder="-- Pilih Lokasi --" required help="Letak fisik ruangan. Opsi difilter sesuai cakupan akses Anda." />
                <x-select label="SKPD" name="id_skpd" id="field-skpd" :options="$skpdOptions->pluck('nama_skpd', 'id_skpd')" placeholder="-- Pilih SKPD --" />
                <p id="form-error" class="hidden rounded-xl bg-red-50 px-4 py-2 text-xs font-medium text-red-600"></p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const storeUrl = @json(route('aset-ruangan.store'));
    const updateUrl = @json(route('aset-ruangan.update', ['ruangan' => '__ID__']));

    const modal = document.getElementById('ruangan-modal');
    const form = document.getElementById('ruangan-form');
    const fieldLokasi = document.getElementById('field-lokasi');
    const fieldSkpd = document.getElementById('field-skpd');

    // Simpan snapshot opsi asli SKPD saat halaman pertama kali dimuat
    const defaultSkpdOptions = fieldSkpd ? Array.from(fieldSkpd.options).map(opt => ({
        value: opt.value,
        text: opt.textContent,
        disabled: opt.disabled
    })) : [];

    // Opsi khusus Skenario 1: Kantor Dinas Perkebunan Provinsi Jawa Timur (id_lokasi = 1)
    const dinasSkpdOptions = [
        { value: '1', text: 'Sekretariat' },
        { value: '2', text: 'Bidang Perlindungan Perkebunan' },
        { value: '3', text: 'Bidang Pengelolahan dan Pemasaran Hasil' },
        { value: '4', text: 'Bidang Produksi Tanaman Semusim' },
        { value: '5', text: 'Bidang Produksi Tanaman Tahunan' },
        { value: '', text: 'Tanpa SKPD' }
    ];

    function applyLokasiSkpdFilter(targetSkpdVal) {
        if (!fieldLokasi || !fieldSkpd) return;

        const lokasiVal = String(fieldLokasi.value || '');

        if (lokasiVal === '1') {
            fieldSkpd.innerHTML = '';
            dinasSkpdOptions.forEach(opt => {
                const optionEl = document.createElement('option');
                optionEl.value = opt.value;
                optionEl.textContent = opt.text;
                fieldSkpd.appendChild(optionEl);
            });

            if (targetSkpdVal !== undefined && targetSkpdVal !== null && String(targetSkpdVal) !== '') {
                fieldSkpd.value = String(targetSkpdVal);
            } else {
                fieldSkpd.value = '';
            }
        } else {
            fieldSkpd.innerHTML = '';
            defaultSkpdOptions.forEach(opt => {
                const optionEl = document.createElement('option');
                optionEl.value = opt.value;
                optionEl.textContent = opt.text;
                if (opt.disabled) optionEl.disabled = true;
                fieldSkpd.appendChild(optionEl);
            });

            if (targetSkpdVal !== undefined && targetSkpdVal !== null) {
                fieldSkpd.value = String(targetSkpdVal);
            }
        }
    }

    if (fieldLokasi) {
        fieldLokasi.addEventListener('change', () => {
            applyLokasiSkpdFilter();
        });
    }

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        document.getElementById('form-error').classList.add('hidden');
    }

    const btnTambah = document.getElementById('btn-tambah');
    if (btnTambah) {
        btnTambah.addEventListener('click', () => {
            form.reset();
            document.getElementById('modal-id').value = '';
            form.action = storeUrl;
            document.getElementById('modal-title').textContent = 'Tambah Ruangan';
            document.getElementById('modal-subtitle').textContent = 'Lengkapi data ruangan.';
            document.getElementById('btn-submit').textContent = 'Simpan';
            applyLokasiSkpdFilter();
            openModal();
        });
    }

    document.querySelectorAll('[data-edit-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.editModal);
            document.getElementById('modal-id').value = data.id;
            document.getElementById('field-nama').value = data.nama_ruangan ?? '';
            document.getElementById('field-lantai').value = data.lantai ?? '';
            document.getElementById('field-lokasi').value = data.id_lokasi ?? '';
            applyLokasiSkpdFilter(data.id_skpd);
            form.action = updateUrl.replace('__ID__', data.id);
            document.getElementById('modal-title').textContent = 'Edit Ruangan';
            document.getElementById('modal-subtitle').textContent = 'Perbarui data ruangan.';
            document.getElementById('btn-submit').textContent = 'Perbarui';
            openModal();
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submit = document.getElementById('btn-submit');
        const original = submit.textContent;
        const errBox = document.getElementById('form-error');
        errBox.classList.add('hidden');
        submit.textContent = 'Menyimpan...';
        submit.disabled = true;
        const body = new FormData(form);
        try {
            const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
            if (res.status === 422) {
                const data = await res.json();
                const errors = Object.values(data.errors || {}).flat();
                if (errors.length) { errBox.textContent = errors[0]; errBox.classList.remove('hidden'); }
                submit.textContent = original;
                submit.disabled = false;
                return;
            }
            if (res.ok) { window.location.reload(); }
            else if (res.status === 403) { alert('Anda tidak memiliki hak akses.'); submit.textContent = original; submit.disabled = false; }
            else { alert('Terjadi kesalahan. Coba lagi.'); submit.textContent = original; submit.disabled = false; }
        } catch (err) {
            alert('Koneksi bermasalah. Coba lagi.');
            submit.textContent = original;
            submit.disabled = false;
        }
    });

    modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
</script>
@endpush
@endif

