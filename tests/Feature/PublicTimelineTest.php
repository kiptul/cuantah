<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Daftar langkah alur di halaman Dampak.
 *
 * Komponen steps DaisyUI menempatkan titik nomornya di tengah seluruh blok
 * isi. Pada tata letak mendatar itu benar, sebab isinya berada di bawah
 * titik. Pada tata letak menurun isinya berada di samping, sehingga pada
 * langkah berisi dua baris nomornya sejajar dengan keterangan, bukan dengan
 * judul yang diterangkannya; terukur 26px meleset.
 *
 * Karena itu versi menurun disusun sendiri dan versi mendatar tetap memakai
 * steps. Keduanya dibangun dari satu larik, sebab dua susunan yang isinya
 * diketik terpisah akan berbeda diam-diam begitu salah satunya disunting.
 */
class PublicTimelineTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $judul = ['Kumpulkan', 'Setorkan', 'Diverifikasi', 'Diolah ulang'];

    private function isi(): string
    {
        return $this->get(route('public.page', 'dampak'))->assertOk()->getContent();
    }

    private function daftarMenurun(string $isi): string
    {
        preg_match('/<ol class="lg:hidden">.*?<\/ol>/s', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Daftar menurun untuk ponsel harus ditemukan.');

        return $cocok[0];
    }

    public function test_daftar_menurun_punya_keempat_langkah(): void
    {
        $daftar = $this->daftarMenurun($this->isi());

        $this->assertSame(4, substr_count($daftar, '<li '), 'Keempat langkah harus ada.');

        foreach ($this->judul as $judul) {
            $this->assertStringContainsString($judul, $daftar);
        }
    }

    public function test_nomor_mendahului_judul_dalam_satu_baris(): void
    {
        $daftar = $this->daftarMenurun($this->isi());

        /**
         * Inilah yang membuat nomor sejajar dengan judulnya: keduanya berada
         * dalam satu baris flex, nomor lebih dulu. Memindahkan nomor ke luar
         * baris itu mengembalikan kemelesetan yang justru sedang diperbaiki.
         */
        foreach ($this->judul as $nomor => $judul) {
            $this->assertMatchesRegularExpression(
                '/<span[^>]*rounded-full[^>]*>'.($nomor + 1).'<\/span>\s*(?:\{\{--.*?--\}\}\s*)?<span[^>]*>\s*<span[^>]*>'.preg_quote($judul, '/').'/s',
                $daftar,
                'Nomor '.($nomor + 1).' harus mendahului judul "'.$judul.'" dalam baris yang sama.'
            );
        }
    }

    public function test_daftar_menurun_tidak_memakai_komponen_steps(): void
    {
        $daftar = $this->daftarMenurun($this->isi());

        /**
         * Diperiksa sebagai kelas utuh. Kata "step" juga terkandung di dalam
         * kata lain, sehingga pencarian potongan akan menuduh tanpa dasar.
         */
        preg_match_all('/class="([^"]*)"/', $daftar, $kelas);

        foreach ($kelas[1] as $satu) {
            $this->assertNotContains('step', preg_split('/\s+/', trim($satu)));
        }
    }

    public function test_garis_penghubung_berhenti_di_langkah_terakhir(): void
    {
        $daftar = $this->daftarMenurun($this->isi());

        /**
         * Tiga garis untuk empat langkah. Garis keempat akan menjulur ke
         * bawah tanpa titik tujuan.
         */
        $this->assertSame(
            3,
            substr_count($daftar, 'bg-emerald-700" aria-hidden="true"'),
            'Garis penghubung hanya digambar di antara langkah, bukan sesudah yang terakhir.'
        );
    }

    public function test_versi_mendatar_tetap_memakai_steps(): void
    {
        $isi = $this->isi();

        preg_match('/<ul class="[^"]*steps[^"]*">.*?<\/ul>/s', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Versi mendatar harus tetap ada untuk lg ke atas.');
        $this->assertSame(4, substr_count($cocok[0], 'class="step step-primary"'));
        $this->assertStringContainsString('lg:inline-grid', $cocok[0]);
    }

    public function test_kedua_susunan_dibangun_dari_satu_sumber(): void
    {
        $isi = $this->isi();

        preg_match('/<section[^>]*>(?:(?!<\/section>).)*Kumpulkan.*?<\/section>/s', $isi, $bagian);

        $this->assertNotEmpty($bagian);

        foreach ($this->judul as $judul) {
            /**
             * Tepat dua kali: sekali di daftar menurun, sekali di daftar
             * mendatar. Lebih dari itu berarti ada salinan ketiga yang akan
             * tertinggal saat isinya disunting.
             */
            $this->assertSame(
                2,
                substr_count($bagian[0], '>'.$judul.'</span>'),
                'Judul "'.$judul.'" harus berasal dari satu larik, bukan diketik di tiap susunan.'
            );
        }
    }
}
