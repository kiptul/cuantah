<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lambang mata uang dan ukuran sasaran sentuh di halaman publik.
 *
 * Kalimat tentang bayaran ditemani ikon dolar, padahal seluruh nilai di
 * aplikasi ini dalam rupiah. Ikon mata uang asing di sebelah kalimat soal
 * uang membuat pembaca ragu pada mata uang yang dimaksud.
 *
 * Sasaran sentuh di bawah 44px sulit dikenai di ponsel. Tiga kelompok
 * terukur di bawah batas itu: tombol estimasi di beranda 33px, tautan
 * navigasi di footer 36px, dan label penutup laci 40px. Yang terakhir
 * dibuat pada pekerjaan laci sebelumnya, jadi ikut dibereskan di sini.
 */
class PublicMobileTouchTest extends TestCase
{
    // Halaman publik membaca harga jelantah yang berlaku, jadi tabelnya harus
    // ada. Tanpa ini seluruh test di berkas ini menerima 500, bukan 200.
    use RefreshDatabase;

    public function test_ikon_mata_uang_memakai_rupiah(): void
    {
        $isi = $this->get(route('public.page', 'edukasi'))->assertOk()->getContent();

        /**
         * Jalur SVG lambang dolar yang dipakai sebelumnya. Diperiksa dengan
         * potongan yang khas supaya tidak ikut menuduh ikon lain.
         */
        $this->assertStringNotContainsString('M12 3v18M8 7h6a3 3 0 0 1 0 6H9', $isi);
        $this->assertMatchesRegularExpression('/>Rp<\/span>/', $isi, 'Lambangnya ditulis sebagai teks Rp.');
    }

    public function test_tombol_estimasi_di_beranda_cukup_besar(): void
    {
        $isi = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/<button[^>]*data-liters[^>]*class="([^"]*)"/', $isi, $cocok);

        $this->assertNotEmpty($cocok[1], 'Tombol estimasi harus ditemukan.');

        foreach ($cocok[1] as $kelas) {
            $this->assertStringContainsString('min-h-11', $kelas);
        }
    }

    public function test_tautan_navigasi_footer_cukup_besar(): void
    {
        $isi = $this->get(route('home'))->assertOk()->getContent();

        preg_match('/<footer.*?<\/footer>/s', $isi, $footer);

        $this->assertNotEmpty($footer, 'Footer harus ditemukan.');

        preg_match_all('/<a[^>]*class="([^"]*rounded-lg[^"]*)"/', $footer[0], $cocok);

        $this->assertNotEmpty($cocok[1], 'Tautan navigasi footer harus ditemukan.');

        foreach ($cocok[1] as $kelas) {
            $this->assertStringContainsString('min-h-11', $kelas);
        }
    }

    public function test_label_penutup_laci_cukup_besar(): void
    {
        $isi = $this->get(route('home'))->assertOk()->getContent();

        preg_match('/<label[^>]*for="publicDrawer"[^>]*>\s*Tutup menu/s', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Label penutup laci harus ditemukan.');
        $this->assertStringContainsString('min-h-11', $cocok[0]);
    }
}
