{{-- Tab 1: Data KIB A & Legalitas --}}
<div data-tab-panel id="tab-kib" class="mt-4">
    <x-card :padding="false">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-bold text-slate-900">Data KIB A &amp; Legalitas</h3>
            <p class="text-xs text-slate-400">Informasi dasar, fisik, dan legalitas aset tanah</p>
        </div>
        <div class="grid grid-cols-1 gap-x-6 gap-y-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">KIB</dt>
                <dd class="font-mono text-sm font-bold text-slate-900">{{ $tanah->kib ?: '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Luas Tanah</dt>
                <dd class="text-sm text-slate-900">
                    {{ $tanah->luas_tanah !== null ? number_format((float) $tanah->luas_tanah, 2, ',', '.') . ' m²' : '-' }}
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nilai Perolehan</dt>
                <dd class="text-sm font-semibold text-slate-900">{{ $rupiah((float) ($tanah->nilai_perolehan ?? 0)) }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Hak</dt>
                <dd class="text-sm text-slate-900">{{ $tanah->status_hak ?: '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Sertifikat</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $tanah->nomor_sertifikat ?: '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Sertifikat</dt>
                <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_sertifikat) }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Buku</dt>
                <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_buku) }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Perolehan</dt>
                <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_perolehan) }}</dd>
            </div>
            <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Ketkel / Kecamatan</dt>
                <dd class="text-sm text-slate-900">{{ $tanah->ketkel ?: '-' }}</dd>
            </div>
            <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Alamat Lengkap</dt>
                <dd class="text-sm text-slate-900">{{ $tanah->alamat ?: '-' }}</dd>
            </div>
            <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Deskripsi Objek</dt>
                <dd class="text-sm text-slate-900">{{ $tanah->deskripsi_objek ?: '-' }}</dd>
            </div>
            <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
                <dd class="text-sm text-slate-900">{{ $tanah->keterangan ?: '-' }}</dd>
            </div>
        </div>
    </x-card>
</div>

