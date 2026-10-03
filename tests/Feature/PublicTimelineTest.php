<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Daftar langkah di halaman Dampak.
 *
 * Daftar ini menurun di ponsel dan mendatar mulai lg. Tiga kelasnya hanya
 * masuk akal pada tata letak mendatar, tempat isi langkah berada di bawah
 * titik nomornya: mt-3 memberi jarak ke titik di atasnya, text-center
 * menengahkan isi terhadap titik, dan max-w-xs menahan lebar kolom.
 *
 * Di ponsel daftarnya menurun dan isinya berada di samping titik. Ketiga
 * kelas itu lalu bekerja melawan tata letaknya: isi yang ditengahkan dengan
 * lebar yang berbeda-beda membuat tepi kiri bergerigi, dan mt-3 menggeser
 * teks turun dari titik yang seharusnya sejajar dengannya.
 */
class PublicTimelineTest extends TestCase
{
    private function langkah(): array
    {
        $isi = $this->get(route('public.page', 'dampak'))->assertOk()->getContent();

        preg_match_all('/<li class="step[^"]*">\s*<span class="([^"]*)"/', $isi, $cocok);

        $this->assertCount(4, $cocok[1], 'Keempat langkah harus ditemukan.');

        return $cocok[1];
    }

    public function test_isi_langkah_rata_kiri_di_ponsel(): void
    {
        foreach ($this->langkah() as $kelas) {
            $this->assertStringContainsString('text-left', $kelas);
        }
    }

    public function test_kelas_tata_letak_mendatar_dibatasi_pada_lg(): void
    {
        foreach ($this->langkah() as $kelas) {
            foreach (['lg:mt-3', 'lg:max-w-xs', 'lg:text-center'] as $kelasLg) {
                $this->assertStringContainsString($kelasLg, $kelas);
            }
        }
    }

    public function test_tidak_ada_lagi_kelas_mendatar_yang_berlaku_di_semua_lebar(): void
    {
        foreach ($this->langkah() as $kelas) {
            /**
             * Diperiksa sebagai kelas utuh, bukan potongan. "mt-3" juga
             * terkandung di dalam "lg:mt-3", sehingga pencarian potongan akan
             * selalu gagal dan tidak menguji apa pun.
             */
            $daftar = preg_split('/\s+/', trim($kelas));

            $this->assertNotContains('mt-3', $daftar);
            $this->assertNotContains('text-center', $daftar);
            $this->assertNotContains('max-w-xs', $daftar);
        }
    }
}
