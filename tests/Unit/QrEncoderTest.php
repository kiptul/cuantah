<?php

namespace Tests\Unit;

use App\Services\QrEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Matriks acuan di berkas ini bukan hasil perhitungan ulang dari spesifikasi,
 * melainkan keluaran penyandi ini yang sudah dipindai dan terbaca benar oleh
 * zxing, pustaka yang sama yang dipakai karyawan di halaman pemindai.
 *
 * Artinya test ini menjaga hal yang berbeda dari test biasa: ia tidak
 * membuktikan penyandinya benar, sebab pembuktian itu dilakukan di luar. Yang
 * dijaganya adalah agar penyandinya tetap menghasilkan matriks yang sama,
 * sehingga perubahan pada Reed-Solomon, penjalinan blok, pemilihan topeng, atau
 * penempatan informasi format tidak lolos tanpa disadari.
 *
 * Bila test ini gagal, jangan perbarui angkanya sampai keluaran yang baru
 * dipindai ulang dan benar-benar terbaca.
 */
class QrEncoderTest extends TestCase
{
    public function test_it_encodes_v1_into_a_matrix_a_real_scanner_reads(): void
    {
        $this->assertSame(
            self::V1_TERVERIFIKASI,
            $this->ringkas((new QrEncoder)->encode('A')),
            'Matriks berubah. Pindai ulang keluarannya sebelum memperbarui acuan ini.',
        );
    }

    public function test_it_encodes_v2_into_a_matrix_a_real_scanner_reads(): void
    {
        $this->assertSame(
            self::V2_TERVERIFIKASI,
            $this->ringkas((new QrEncoder)->encode('CNT-261003-ABC123')),
            'Matriks berubah. Pindai ulang keluarannya sebelum memperbarui acuan ini.',
        );
    }

    public function test_it_encodes_v6_into_a_matrix_a_real_scanner_reads(): void
    {
        $this->assertSame(
            self::V6_TERVERIFIKASI,
            $this->ringkas((new QrEncoder)->encode(str_repeat('Q', 106))),
            'Matriks berubah. Pindai ulang keluarannya sebelum memperbarui acuan ini.',
        );
    }

    public function test_the_symbol_grows_only_as_far_as_the_payload_needs(): void
    {
        $encoder = new QrEncoder;

        // 21x21 adalah versi 1, lalu tiap versi menambah empat modul per sisi.
        $this->assertSame(21, $encoder->encode('A')['size']);
        $this->assertSame(25, $encoder->encode(str_repeat('A', 17))['size']);
        $this->assertSame(41, $encoder->encode(str_repeat('A', 106))['size']);
    }

    public function test_it_refuses_a_payload_it_cannot_hold_instead_of_truncating(): void
    {
        // Memotong muatan menghasilkan QR yang terbaca tetapi menunjuk transaksi
        // yang salah, dan itu lebih buruk daripada gagal terang-terangan.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maksimum 106 karakter');

        (new QrEncoder)->encode(str_repeat('A', 107));
    }

    public function test_it_refuses_an_empty_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QrEncoder)->encode('');
    }

    public function test_the_fixed_patterns_sit_where_a_scanner_looks_for_them(): void
    {
        $qr = (new QrEncoder)->encode('CNT-261003-ABC123');
        $m = $qr['modules'];
        $n = $qr['size'];

        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$baris, $kolom]) {
            $this->assertTrue($m[$baris][$kolom], 'Sudut pola pencari harus gelap.');
            $this->assertTrue($m[$baris][$kolom + 6], 'Sudut pola pencari harus gelap.');
            $this->assertTrue($m[$baris + 6][$kolom], 'Sudut pola pencari harus gelap.');
            $this->assertFalse($m[$baris + 1][$kolom + 1], 'Cincin dalam pola pencari harus terang.');
            $this->assertTrue($m[$baris + 3][$kolom + 3], 'Inti pola pencari harus gelap.');
        }

        for ($i = 8; $i < $n - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], "Penanda waktu mendatar salah di kolom {$i}.");
            $this->assertSame($i % 2 === 0, $m[$i][6], "Penanda waktu tegak salah di baris {$i}.");
        }

        $this->assertTrue($m[$n - 8][8], 'Modul gelap wajib tidak terpasang.');
    }

    /**
     * Matriks diringkas menjadi hex supaya acuannya bisa dibandingkan tanpa
     * membentangkan ratusan boolean.
     *
     * @param  array{size:int, modules:array<int, array<int, bool>>}  $qr
     */
    private function ringkas(array $qr): string
    {
        $bit = '';

        for ($y = 0; $y < $qr['size']; $y++) {
            for ($x = 0; $x < $qr['size']; $x++) {
                $bit .= $qr['modules'][$y][$x] ? '1' : '0';
            }
        }

        $bit = str_pad($bit, (int) (ceil(strlen($bit) / 4) * 4), '0');
        $hex = '';

        foreach (str_split($bit, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        return $hex;
    }

    /** Versi 1, 21x21 modul: satu karakter. */
    private const V1_TERVERIFIKASI =
        'fe93fc17d06e8abb75b5dba72ec12d07faafe01b00b73a5acaf233b41b6827c9e492005927fa'.
        '65105836ba6f8dd79faeaac1044a5fe8480';

    /** Versi 2, 25x25 modul: kode transaksi sungguhan. */
    private const V2_TERVERIFIKASI =
        'feb23fc128506e992bb75b65dba872ec17b107faaafe0125008b96fcd88caeedb7d1882db4f7'.
        '2fca346938f45f04880a150dee16fe8064447fa7eb104a91cbaacfedd0372ee987150457aefe'.
        'd77f8';

    /** Versi 6, 41x41 modul: muatan terpanjang yang didukung. */
    private const V6_TERVERIFIKASI =
        'fef7d5553fc1058444506ea277776bb744655555dba31d5552ec1548444507faaaaaaafe0028'.
        '444400a3325555128410d555688bc69ddddaa21b644448a9299d5552b8014155568a9c0bdddd'.
        'aab1b444448a9e0955552bb49815556891c15ddddabb8c244448ac32d55552b8ea9155568959'.
        '05ddddabbb8e444489c2afd5552b5ac0d555688ad37ddddab53be44448a2f9755552bb8d1d55'.
        '568b8d51ddddaa539e44448ae316d555fb80591555c4bfbfbdddeab047744471aba07f555fbd'.
        'd182555a86eaeb5ddeab0433044628fe85d554ba8';
}
