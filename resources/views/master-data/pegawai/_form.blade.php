@props(['pegawai' => null, 'skpdList' => []])

<x-card>
    <div class="max-w-xl space-y-5">
        <x-input
            label="NIP"
            name="nip"
            value="{{ old('nip', $pegawai->nip ?? '') }}"
            required
            placeholder="contoh: 198001012010011001"
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
        <x-select
            label="SKPD"
            name="id_skpd"
            :required="true"
            :value="old('id_skpd', $pegawai->id_skpd ?? '')"
            :options="$skpdList->pluck('nama_skpd', 'id_skpd')->all()"
        />
    </div>
</x-card>
