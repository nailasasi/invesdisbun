{{--
    UI bukti otorisasi: jam real-time (WIB) + kamera bukti.

    TAHAP 3 — terintegrasi backend.
    - Jam ditampilkan dari waktu server (data-server-ms) lalu berjalan di
      browser; ini hanya indikator tampilan. Waktu RESMI aktivitas tetap
      ditetapkan server saat data diproses.
    - Foto hasil kamera disimpan pada input tersembunyi
      `foto_bukti_otentikasi` (data URL). Server memvalidasi ISI gambar,
      menyimpan ke disk privat, dan mencatatnya di riwayat.
    - Foto WAJIB: pengiriman formulir dicegah bila belum ada foto yang
      "digunakan" (klien), dan server tetap memvalidasi ulang (sumber
      kebenaran). Field ini tidak pernah di-flash ke session.
--}}
<div class="mt-5 space-y-5 text-sm" data-otorisasi-bukti data-server-ms="{{ now()->getTimestampMs() }}">

    {{-- ===== Jam real-time ===== --}}
    <div class="rounded-xl border border-amber-200 bg-white/70 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waktu Saat Ini (WIB)</p>

        <p data-jam class="mt-1 font-mono text-3xl font-bold tabular-nums text-slate-900">--:--:--</p>
        <p data-tanggal class="mt-0.5 text-sm font-medium text-slate-600">&mdash;</p>

        <p class="mt-1 text-[11px] text-slate-400">
            Waktu berjalan otomatis. Waktu resmi aktivitas ditetapkan oleh server saat data diproses.
        </p>
    </div>

    {{-- ===== Kamera bukti ===== --}}
    <div class="rounded-xl border border-amber-200 bg-white/70 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Foto Bukti (Kamera Langsung)</p>
        <p class="mt-1 text-[11px] text-slate-500">
            Ambil foto langsung melalui kamera perangkat. Foto dari galeri atau file lama tidak dianggap sebagai bukti kamera.
        </p>

        {{-- Area pratinjau --}}
        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
            <div data-video-wrap class="hidden aspect-video w-full bg-slate-900">
                <video data-video class="h-full w-full object-cover" autoplay playsinline muted></video>
            </div>

            <img data-preview class="hidden aspect-video w-full object-cover" alt="Pratinjau foto bukti">

            <div data-placeholder class="flex aspect-video w-full items-center justify-center bg-slate-50">
                <p class="text-sm text-slate-400">Belum ada foto</p>
            </div>
        </div>

        {{-- Tombol aksi (muncul bertahap) --}}
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" data-buka
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                Buka Kamera
            </button>

            <button type="button" data-ambil
                    class="hidden rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                Ambil Foto
            </button>

            <button type="button" data-ulang
                    class="hidden rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Ambil Ulang
            </button>

            <button type="button" data-gunakan
                    class="hidden rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                Gunakan Foto
            </button>

            <button type="button" data-tutup
                    class="hidden rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Tutup Kamera
            </button>
        </div>

        <p data-status class="mt-2 text-xs font-semibold text-slate-500">Belum ada foto</p>

        <p data-error class="mt-1 hidden rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600"></p>

        {{-- Nilai foto kamera (data URL). Divalidasi & disimpan server; tidak pernah di-flash. --}}
        <input type="hidden" name="foto_bukti_otentikasi" data-input value="">
    </div>
</div>

@once
    @push('scripts')
    <script>
        (function () {
            var roots = document.querySelectorAll('[data-otorisasi-bukti]');
            if (!roots.length) return;

            var waktuFmt = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
            });
            var tanggalFmt = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
            });

            function bagian(parts, tipe) {
                for (var i = 0; i < parts.length; i++) {
                    if (parts[i].type === tipe) return parts[i].value;
                }
                return '';
            }

            roots.forEach(function (root) {
                // ------- Jam real-time (berbasis waktu server) -------
                var serverMs = parseInt(root.getAttribute('data-server-ms'), 10);
                if (isNaN(serverMs)) serverMs = Date.now();
                var offset = serverMs - Date.now();

                var jamEl = root.querySelector('[data-jam]');
                var tglEl = root.querySelector('[data-tanggal]');

                function tick() {
                    var kini = new Date(Date.now() + offset);
                    var wp = waktuFmt.formatToParts(kini);
                    if (jamEl) jamEl.textContent = bagian(wp, 'hour') + ':' + bagian(wp, 'minute') + ':' + bagian(wp, 'second');
                    var dp = tanggalFmt.formatToParts(kini);
                    if (tglEl) tglEl.textContent = bagian(dp, 'weekday') + ', ' + bagian(dp, 'day') + ' ' + bagian(dp, 'month') + ' ' + bagian(dp, 'year');
                }
                tick();
                setInterval(tick, 1000);

                // ------- Kamera bukti -------
                var videoWrap = root.querySelector('[data-video-wrap]');
                var video = root.querySelector('[data-video]');
                var preview = root.querySelector('[data-preview]');
                var placeholder = root.querySelector('[data-placeholder]');
                var statusEl = root.querySelector('[data-status]');
                var errorEl = root.querySelector('[data-error]');
                var input = root.querySelector('[data-input]');
                var btnBuka = root.querySelector('[data-buka]');
                var btnAmbil = root.querySelector('[data-ambil]');
                var btnUlang = root.querySelector('[data-ulang]');
                var btnGunakan = root.querySelector('[data-gunakan]');
                var btnTutup = root.querySelector('[data-tutup]');
                var canvas = document.createElement('canvas');
                var stream = null;

                function tampil(el, on) { if (el) el.classList.toggle('hidden', !on); }

                function pesanError(msg) {
                    if (!errorEl) return;
                    if (msg) { errorEl.textContent = msg; tampil(errorEl, true); }
                    else { errorEl.textContent = ''; tampil(errorEl, false); }
                }

                function setStatus(text, ok) {
                    if (!statusEl) return;
                    statusEl.textContent = text;
                    statusEl.classList.toggle('text-emerald-600', !!ok);
                    statusEl.classList.toggle('text-slate-500', !ok);
                }

                function stopStream() {
                    if (stream) {
                        stream.getTracks().forEach(function (t) { t.stop(); });
                        stream = null;
                    }
                    if (video) video.srcObject = null;
                }

                function resetTombol() {
                    tampil(btnBuka, true);
                    tampil(btnAmbil, false);
                    tampil(btnUlang, false);
                    tampil(btnGunakan, false);
                    tampil(btnTutup, false);
                }

                function resetPreview() {
                    if (preview) { preview.classList.add('hidden'); preview.removeAttribute('src'); }
                    tampil(placeholder, true);
                    tampil(videoWrap, false);
                }

                function pesanKamera(e) {
                    var nama = e && e.name ? e.name : '';
                    if (nama === 'NotAllowedError' || nama === 'SecurityError') {
                        return 'Akses kamera ditolak. Izinkan akses kamera pada browser, lalu coba lagi.';
                    }
                    if (nama === 'NotFoundError' || nama === 'DevicesNotFoundError' || nama === 'OverconstrainedError') {
                        return 'Kamera tidak ditemukan pada perangkat ini.';
                    }
                    if (nama === 'NotReadableError' || nama === 'TrackStartError') {
                        return 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut, lalu coba lagi.';
                    }
                    return 'Kamera tidak dapat dibuka. Pastikan perangkat memiliki kamera dan situs diakses melalui HTTPS.';
                }

                function bukaKamera() {
                    pesanError('');
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        pesanError('Perangkat/browser ini tidak mendukung akses kamera. Fitur kamera memerlukan koneksi HTTPS.');
                        resetTombol();
                        return;
                    }
                    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                        .then(function (s) {
                            stream = s;
                            video.srcObject = s;
                            return video.play();
                        })
                        .then(function () {
                            tampil(placeholder, false);
                            if (preview) preview.classList.add('hidden');
                            tampil(videoWrap, true);
                            tampil(btnBuka, false);
                            tampil(btnAmbil, true);
                            tampil(btnTutup, true);
                            tampil(btnUlang, false);
                            tampil(btnGunakan, false);
                            setStatus('Kamera aktif. Ambil foto bila sudah siap.');
                        })
                        .catch(function (e) {
                            stopStream();
                            resetTombol();
                            resetPreview();
                            setStatus('Belum ada foto');
                            pesanError(pesanKamera(e));
                        });
                }

                function ambilFoto() {
                    if (!stream || !video.videoWidth) {
                        pesanError('Kamera belum siap. Tunggu sebentar, lalu ambil foto kembali.');
                        return;
                    }
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    var dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                    stopStream();
                    tampil(videoWrap, false);
                    preview.src = dataUrl;
                    tampil(preview, true);
                    tampil(placeholder, false);
                    tampil(btnBuka, false);
                    tampil(btnAmbil, false);
                    tampil(btnTutup, false);
                    tampil(btnUlang, true);
                    tampil(btnGunakan, true);
                    setStatus('Pratinjau foto. Pilih "Gunakan Foto" atau "Ambil Ulang".');
                    pesanError('');
                }

                function gunakanFoto() {
                    if (!preview || !preview.getAttribute('src')) {
                        pesanError('Belum ada foto untuk digunakan.');
                        return;
                    }
                    if (input) input.value = preview.getAttribute('src');
                    tampil(btnBuka, false);
                    tampil(btnAmbil, false);
                    tampil(btnUlang, true);
                    tampil(btnGunakan, false);
                    tampil(btnTutup, false);
                    setStatus('Foto siap digunakan.', true);
                    pesanError('');
                }

                function ambilUlang() {
                    if (input) input.value = '';
                    setStatus('Belum ada foto');
                    pesanError('');
                    resetPreview();
                    bukaKamera();
                }

                function tutupKamera() {
                    stopStream();
                    resetPreview();
                    resetTombol();
                    setStatus('Belum ada foto');
                    pesanError('');
                }

                btnBuka.addEventListener('click', bukaKamera);
                btnAmbil.addEventListener('click', ambilFoto);
                btnUlang.addEventListener('click', ambilUlang);
                btnGunakan.addEventListener('click', gunakanFoto);
                btnTutup.addEventListener('click', tutupKamera);

                // Foto wajib: cegah kirim formulir bila belum ada foto yang
                // "digunakan". Server tetap memvalidasi ulang (sumber kebenaran).
                var form = root.closest('form');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (!input || !input.value) {
                            e.preventDefault();
                            setStatus('Belum ada foto');
                            pesanError('Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.');
                            root.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }

                window.addEventListener('beforeunload', stopStream);
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden && stream) tutupKamera();
                });
            });
        })();
    </script>
    @endpush
@endonce
