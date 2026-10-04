<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kontrol bawaan peramban pada input angka, tanggal, dan waktu.
 *
 * DaisyUI memasang color-scheme:dark di :root bila sistem pengunjung
 * bermode gelap. Peramban lalu mengecat isi kontrolnya untuk latar gelap:
 * panah naik-turun pada input angka, ikon jam, dan ikon kalender digambar
 * terang, padahal kotaknya dipaku putih lewat bg-white. Hasilnya formulir
 * setor tampak tidak punya pemilih tanggal sama sekali.
 *
 * Yang dipaksa terang hanya ketiga input itu. Temanya sengaja tidak
 * disentuh, sebab gejalanya memang hanya pada kontrol bawaan peramban.
 *
 * Kerusakan ini tidak terlihat oleh siapa pun yang sistemnya bermode
 * terang, jadi ia mudah lolos dari pemeriksaan manual.
 */
class LightColorSchemeTest extends TestCase
{
    private function sumberCss(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    /**
     * CSS yang benar-benar dikirim ke pengunjung, ditelusuri lewat manifest
     * Vite supaya test membaca berkas yang sama dengan yang dimuat halaman,
     * bukan menebak nama berkasnya.
     */
    private function cssTerbangun(): string
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        $berkas = $manifest['resources/css/app.css']['file'] ?? null;

        $this->assertNotNull($berkas, 'Manifest Vite harus memuat entri untuk resources/css/app.css.');

        $jalur = public_path('build/'.$berkas);

        $this->assertFileExists($jalur, 'Aset yang ditunjuk manifest harus ikut di-commit.');

        return file_get_contents($jalur);
    }

    public function test_sumber_memaksa_ketiga_input_memakai_skema_terang(): void
    {
        $this->assertMatchesRegularExpression(
            "/input\[type='number'\],\s*input\[type='date'\],\s*input\[type='time'\]\s*\{[^}]*color-scheme:\s*light/s",
            $this->sumberCss(),
            'Tanpa aturan ini panah dan ikon kembali dicat terang di atas kotak putih.'
        );
    }

    public function test_aturan_itu_ikut_terbangun(): void
    {
        $this->assertStringContainsString(
            'input[type=number],input[type=date],input[type=time]',
            $this->cssTerbangun(),
            'Sumber yang benar tidak menolong bila aset yang di-commit belum di-build ulang.'
        );
    }

    public function test_skema_terang_benar_benar_dideklarasikan_pada_aturan_itu(): void
    {
        preg_match(
            '/input\[type=number\],input\[type=date\],input\[type=time\]\{([^}]*)\}/',
            $this->cssTerbangun(),
            $cocok
        );

        $this->assertNotEmpty($cocok, 'Aturannya harus ada untuk bisa diperiksa isinya.');
        $this->assertStringContainsString(
            'color-scheme:light',
            $cocok[1],
            'Selektornya ada tetapi deklarasinya hilang, jadi aturannya tidak melakukan apa pun.'
        );
    }

    public function test_perbaikan_tidak_melebar_ke_tema(): void
    {
        /**
         * Gejalanya hanya pada kontrol bawaan peramban, jadi tema DaisyUI
         * dibiarkan apa adanya. Test ini menjaga agar perbaikan berikutnya
         * tidak diam-diam mematikan tema gelap, yang mengubah tampilan
         * seluruh aplikasi demi tiga buah ikon.
         */
        $this->assertStringContainsString(
            'prefers-color-scheme:dark){:root:not([data-theme])',
            $this->cssTerbangun(),
            'Tema gelap DaisyUI tidak termasuk dalam cakupan perbaikan ini.'
        );
    }
}
