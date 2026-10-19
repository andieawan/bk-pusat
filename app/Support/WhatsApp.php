<?php

namespace App\Support;

use App\Models\BkCatatan;
use App\Models\Siswa;

/**
 * Pesan WhatsApp gratis lewat tautan wa.me (tanpa gateway/API berbayar):
 * aplikasi menyiapkan teks, guru menekan tombol, WhatsApp di HP/laptop terbuka
 * dengan pesan siap kirim ke nomor orang tua.
 */
class WhatsApp
{
    /** "0812-3456-789" / "+62 812..." / "812..." → "62812..."; null bila kosong/tidak valid. */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0')) {
            $d = '62'.substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62'.$d;
        }

        return preg_match('/^[1-9]\d{8,14}$/', $d) ? $d : null;
    }

    public static function link(?string $phone, string $text): ?string
    {
        $p = self::normalize($phone);

        return $p ? 'https://wa.me/'.$p.'?text='.rawurlencode($text) : null;
    }

    /** Tautan "bagikan" tanpa nomor (pengguna memilih kontak sendiri di WhatsApp). */
    public static function share(string $text): string
    {
        return 'https://wa.me/?text='.rawurlencode($text);
    }

    public static function display(?string $phone): string
    {
        $p = self::normalize($phone);

        return $p ? '0'.substr($p, 2) : '-';
    }

    private static function sapaan(Siswa $s): string
    {
        return 'Assalamu\'alaikum Bapak/Ibu'.($s->nama_ortu ? ' '.$s->nama_ortu : '').', orang tua/wali dari *'.$s->nama.'*'
            .($s->kelas ? ' (kelas '.$s->kelas->nama.')' : '').'.';
    }

    private static function penutup(?string $sender): string
    {
        return "\n\nTerima kasih.\n".($sender ? $sender."\n" : '').config('bk.school_name');
    }

    /** Pesan panggilan/tindak lanjut BK. */
    public static function bkMessage(Siswa $s, string $perihal, ?string $sender): string
    {
        return self::sapaan($s)."\n\nKami dari Bimbingan Konseling ingin menyampaikan hal berikut terkait ananda:\n*".$perihal."*\n\nMohon Bapak/Ibu dapat menghubungi kami atau hadir ke sekolah untuk berkoordinasi."
            .self::penutup($sender);
    }

    /** Pesan dari satu catatan BK (detail kasus rahasia tidak ikut dikirim). */
    public static function recordMessage(BkCatatan $r, ?string $sender): string
    {
        $s = $r->siswa;
        if ($r->jenis === 'prestasi') {
            return self::sapaan($s)."\n\nAlhamdulillah, kami turut bangga menyampaikan bahwa ananda meraih prestasi:\n*".$r->judul.'*'
                .($r->x('peringkat') ? ' — '.$r->x('peringkat') : '').($r->kategori ? ' (tingkat '.$r->kategori.')' : '')
                ."\n\nSemoga menjadi motivasi untuk terus berprestasi.".self::penutup($sender);
        }

        return self::bkMessage($s, $r->rahasia ? 'Tindak lanjut layanan bimbingan konseling' : $r->judul, $sender);
    }
}
