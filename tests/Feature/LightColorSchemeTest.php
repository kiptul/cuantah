<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kontrol bawaan peramban pada input angka, tanggal, waktu, dan checkbox.
 *
 * DaisyUI memasang color-scheme:dark di :root bila sistem pengunjung
 * bermode gelap. Peramban lalu mengecat kontrolnya untuk latar gelap:
 * panah naik-turun pada input angka, ikon jam, dan ikon kalender digambar
 * terang, padahal kotaknya dipaku putih lewat bg-white. Hasilnya formulir
 * setor tampak tidak punya pemilih tanggal sama sekali.
 *
 * Checkbox kena hal yang sama dari sisi sebaliknya: kotaknya digambar gelap
 * di tengah kartu yang putih, sehingga tampak seperti kotak hitam pekat dan
 * centangnya tidak terbaca.
 *
 * Yang dipaksa terang hanya keempat input itu. Temanya sengaja tidak
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

    public function test_sumber_memaksa_keempat_input_memakai_skema_terang(): void
    {
        $this->assertMatchesRegularExpression(
            "/input\[type='number'\],\s*input\[type='date'\],\s*input\[type='time'\],\s*input\[type='checkbox'\]\s*\{[^}]*color-scheme:\s*light/s",
            $this->sumberCss(),
            'Tanpa aturan ini panah dan ikon kembali dicat terang di atas kotak putih.'
        );
    }

    public function test_aturan_itu_ikut_terbangun(): void
    {
        $this->assertStringContainsString(
            'input[type=number],input[type=date],input[type=time],input[type=checkbox]',
            $this->cssTerbangun(),
            'Sumber yang benar tidak menolong bila aset yang di-commit belum di-build ulang.'
        );
    }

    public function test_skema_terang_benar_benar_dideklarasikan_pada_aturan_itu(): void
    {
        preg_match(
            '/input\[type=number\],input\[type=date\],input\[type=time\],input\[type=checkbox\]\{([^}]*)\}/',
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

    /**
     * Checkbox yang terlihat tidak boleh kembali bergantung pada DaisyUI.
     *
     * Kelas .checkbox menyetel appearance:none lalu menggambar centangnya
     * sendiri: isinya dari --input-color, centangnya dari color elemen. Pada
     * tema yang berlaku di sini color-base-content bernilai nyaris putih dan
     * --input-color tidak diatur, sehingga hasilnya centang putih di atas
     * kotak putih. Nilainya tetap terkirim, jadi satu-satunya gejala adalah
     * kotak yang tampak tidak pernah tercentang.
     *
     * Variabel --chkbg dan --chkfg yang dulu dipakai untuk menambalnya milik
     * DaisyUI 4, sedangkan yang terpasang versi 5; keduanya diabaikan diam-diam.
     */
    public function test_checkbox_tidak_memakai_komponen_daisyui(): void
    {
        foreach (['auth/login', 'admin/prices/index'] as $tampilan) {
            /**
             * Komentar Blade dibuang lebih dulu. Yang dilarang adalah markupnya,
             * bukan menyebut nama variabelnya; komentar yang menjelaskan kenapa
             * pola itu ditinggalkan justru perlu tetap boleh menyebutnya.
             */
            $isi = preg_replace(
                '/\{\{--.*?--\}\}/s',
                '',
                file_get_contents(resource_path('views/'.$tampilan.'.blade.php'))
            );

            $this->assertStringNotContainsString('--chkbg', $isi, $tampilan.': --chkbg milik DaisyUI 4 dan diabaikan versi 5.');
            $this->assertStringNotContainsString('--chkfg', $isi, $tampilan.': --chkfg milik DaisyUI 4 dan diabaikan versi 5.');
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*(?<![-\w])checkbox(?![-\w])/s',
                $isi,
                $tampilan.': kelas .checkbox menggambar centangnya sendiri dari warna tema, bukan dari accent-color.'
            );
            $this->assertMatchesRegularExpression(
                '/accent-emerald-\d{3}/',
                $isi,
                $tampilan.': centangnya diwarnai lewat accent-color supaya tidak bergantung pada tema.'
            );
        }
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
