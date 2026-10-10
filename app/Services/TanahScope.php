<?php

namespace App\Services;

/**
 * Gerbang akses modul Tanah (register global: Tanah, Retribusi, Dokumen PBB).
 *
 * Aturan bisnis Fase 1B:
 *   - Admin Aset        -> SELALU boleh (cakupan penuh).
 *   - Admin UPT P2BTP   -> boleh HANYA bila skpd-nya UPT P2BTP (id_skpd = 6).
 *   - Admin UPT PSBP    -> 403.
 *   - Admin Bidang      -> 403 (sudah dicegah middleware role route).
 *   - Pegawai           -> 403 (sudah dicegah middleware role route).
 *
 * Data tanah tetap register global (tabel `tanah` tanpa kolom unit);
 * gerbang ini hanya membatasi SIAPA yang boleh mengelola modul.
 *
 * Identifikasi UPT P2BTP memakai id_skpd (bukan nama), konsisten dengan
 * pendekatan AsetScope yang memakai `jenis_skpd` — id_skpd 6 = UPT P2BTP
 * terkonfirmasi dari audit Fase 0. Sidebar memakai method ini juga,
 * sehingga logika gerbang hanya ada di satu tempat.
 */
class TanahScope
{
    /** SKPD UPT P2BTP (terkonfirmasi audit Fase 0). */
    public const SKPD_P2BTP = 6;

    public function __construct(private readonly AsetScope $asetScope)
    {
    }

    public function bolehAkses(): bool
    {
        if (! $this->asetScope->roleValid()) {
            return false;
        }

        if ($this->asetScope->isAdminAset()) {
            return true;
        }

        return $this->asetScope->isAdminUpt()
            && (int) $this->asetScope->skpdId() === self::SKPD_P2BTP;
    }
}
