<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Penyandi QR Code murni PHP, tanpa dependensi dan tanpa ekstensi gambar.
 *
 * Ditulis sendiri, bukan memakai paket, sebab .cpanel.yml tidak menjalankan
 * composer install dan vendor/ tidak ikut di repo: paket baru tidak akan pernah
 * sampai ke server, dan halamannya akan mati dengan "Class not found". Code 128
 * yang digantikan berkas ini pun ditulis dengan alasan yang sama.
 *
 * Lingkupnya sengaja dipersempit pada yang benar-benar dipakai:
 *
 * - Mode byte saja. Mode alfanumerik memang lebih padat, tetapi himpunan
 *   karakternya tidak memuat huruf kecil, sedangkan factory transaksi
 *   menghasilkan kode berhuruf kecil.
 * - Tingkat koreksi galat M, pulih dari kerusakan sekitar 15 persen.
 * - Versi 1 sampai 6, cukup untuk 108 bita. Kode transaksi panjangnya 17
 *   karakter, jadi nyatanya selalu versi 2. Di atas versi 6 penyandian menuntut
 *   bita informasi versi tersendiri, dan menuliskannya tanpa ada yang
 *   memakainya hanya menambah hal yang bisa salah.
 */
class QrEncoder
{
    /** Penanda mode byte, 4 bita. */
    private const MODE_BYTE = 0b0100;

    /** Penanda tingkat koreksi galat M pada informasi format. */
    private const EC_LEVEL_BITS = 0b00;

    /** Pembagi BCH untuk informasi format. */
    private const FORMAT_POLY = 0x537;

    /** Topeng informasi format, dipakai agar format nol tidak jadi polos. */
    private const FORMAT_MASK = 0x5412;

    /**
     * Struktur blok tiap versi pada tingkat koreksi M.
     *
     * @var array<int, array{0:int, 1:int, 2:array<int, array{0:int, 1:int}>}>
     *                                                                         [kodeword data, kodeword EC per blok, [[jumlah blok, data per blok], ...]]
     */
    private const VERSI = [
        1 => [16, 10, [[1, 16]]],
        2 => [28, 16, [[1, 28]]],
        3 => [44, 26, [[1, 44]]],
        4 => [64, 18, [[2, 32]]],
        5 => [86, 24, [[2, 43]]],
        6 => [108, 16, [[4, 27]]],
    ];

    /**
     * Titik tengah pola penyelaras. Versi 1 tidak memilikinya, dan versi 2
     * sampai 6 hanya punya satu, sebab tiga kombinasi lainnya bertabrakan
     * dengan pola pencari di tiga sudut.
     *
     * @var array<int, int|null>
     */
    private const PENYELARAS = [1 => null, 2 => 18, 3 => 22, 4 => 26, 5 => 30, 6 => 34];

    /**
     * Menyandikan teks menjadi matriks modul.
     *
     * @return array{size:int, modules:array<int, array<int, bool>>}
     */
    public function encode(string $data): array
    {
        if ($data === '') {
            throw new InvalidArgumentException('Teks QR tidak boleh kosong.');
        }

        $versi = $this->versiTerkecil(strlen($data));
        [$kodewordData, $ecPerBlok, $strukturBlok] = self::VERSI[$versi];

        $kodeword = $this->kodewordAkhir(
            $this->bitaData($data, $kodewordData),
            $ecPerBlok,
            $strukturBlok,
        );

        $ukuran = 17 + 4 * $versi;
        $kerangka = $this->kerangka($ukuran, $versi);
        $terisi = $this->tuangData($kerangka, $kodeword, $ukuran);

        $topeng = $this->topengTerbaik($terisi, $kerangka, $ukuran);

        return [
            'size' => $ukuran,
            'modules' => $this->rampungkan($terisi, $kerangka, $ukuran, $topeng),
        ];
    }

    private function versiTerkecil(int $panjang): int
    {
        // 4 bita mode + 8 bita penghitung karakter + 8 bita per bita data.
        $bitDiperlukan = 4 + 8 + 8 * $panjang;

        foreach (self::VERSI as $versi => [$kodewordData]) {
            if ($kodewordData * 8 >= $bitDiperlukan) {
                return $versi;
            }
        }

        $versiTerbesar = array_key_last(self::VERSI);
        $maksimum = intdiv(self::VERSI[$versiTerbesar][0] * 8 - 12, 8);

        throw new InvalidArgumentException(sprintf(
            'Teks %d karakter melampaui versi %d yang didukung penyandi ini, maksimum %d karakter.',
            $panjang,
            $versiTerbesar,
            $maksimum,
        ));
    }

    /**
     * Menyusun bita data: mode, penghitung, muatan, penutup, lalu bita isian.
     *
     * @return array<int, int>
     */
    private function bitaData(string $data, int $kodewordData): array
    {
        $bit = [];
        $this->tambahBit($bit, self::MODE_BYTE, 4);
        $this->tambahBit($bit, strlen($data), 8);

        foreach (str_split($data) as $karakter) {
            $this->tambahBit($bit, ord($karakter), 8);
        }

        $kapasitas = $kodewordData * 8;

        // Penutup empat bita nol, dipendekkan bila ruangnya tidak cukup.
        $penutup = min(4, $kapasitas - count($bit));
        $this->tambahBit($bit, 0, max($penutup, 0));

        // Dirapikan ke batas bita.
        if (count($bit) % 8 !== 0) {
            $this->tambahBit($bit, 0, 8 - count($bit) % 8);
        }

        $kodeword = [];
        foreach (array_chunk($bit, 8) as $oktet) {
            $kodeword[] = (int) bindec(implode('', $oktet));
        }

        // Isian bergantian sampai kapasitas data terpenuhi.
        $isian = [0xEC, 0x11];
        $putaran = 0;
        while (count($kodeword) < $kodewordData) {
            $kodeword[] = $isian[$putaran % 2];
            $putaran++;
        }

        return $kodeword;
    }

    /**
     * @param  array<int, int>  $bit
     */
    private function tambahBit(array &$bit, int $nilai, int $jumlah): void
    {
        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $bit[] = ($nilai >> $i) & 1;
        }
    }

    /**
     * Memecah data ke blok, menghitung koreksi galat, lalu menjalinnya.
     *
     * @param  array<int, int>  $kodewordData
     * @param  array<int, array{0:int, 1:int}>  $strukturBlok
     * @return array<int, int>
     */
    private function kodewordAkhir(array $kodewordData, int $ecPerBlok, array $strukturBlok): array
    {
        $pembagi = $this->pembagiReedSolomon($ecPerBlok);

        $blokData = [];
        $blokEc = [];
        $posisi = 0;

        foreach ($strukturBlok as [$jumlahBlok, $dataPerBlok]) {
            for ($i = 0; $i < $jumlahBlok; $i++) {
                $potongan = array_slice($kodewordData, $posisi, $dataPerBlok);
                $posisi += $dataPerBlok;

                $blokData[] = $potongan;
                $blokEc[] = $this->sisaReedSolomon($potongan, $pembagi);
            }
        }

        // Dijalin per kolom: kodeword ke-n dari setiap blok lebih dulu, baru
        // ke-(n+1). Kerusakan setempat pada simbol jadi tersebar ke banyak blok,
        // dan itulah yang membuat koreksi galatnya berguna.
        $hasil = [];

        $dataTerpanjang = max(array_map('count', $blokData));
        for ($i = 0; $i < $dataTerpanjang; $i++) {
            foreach ($blokData as $blok) {
                if (isset($blok[$i])) {
                    $hasil[] = $blok[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlok; $i++) {
            foreach ($blokEc as $blok) {
                $hasil[] = $blok[$i];
            }
        }

        return $hasil;
    }

    /**
     * Perkalian pada GF(256) dengan polinom primitif x^8+x^4+x^3+x^2+1.
     */
    private function kaliGalois(int $x, int $y): int
    {
        $z = 0;

        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /**
     * @return array<int, int>
     */
    private function pembagiReedSolomon(int $derajat): array
    {
        $hasil = array_fill(0, $derajat, 0);
        $hasil[$derajat - 1] = 1;
        $akar = 1;

        for ($i = 0; $i < $derajat; $i++) {
            for ($j = 0; $j < $derajat; $j++) {
                $hasil[$j] = $this->kaliGalois($hasil[$j], $akar);

                if ($j + 1 < $derajat) {
                    $hasil[$j] ^= $hasil[$j + 1];
                }
            }

            $akar = $this->kaliGalois($akar, 0x02);
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $data
     * @param  array<int, int>  $pembagi
     * @return array<int, int>
     */
    private function sisaReedSolomon(array $data, array $pembagi): array
    {
        $derajat = count($pembagi);
        $sisa = array_fill(0, $derajat, 0);

        foreach ($data as $bita) {
            $faktor = $bita ^ array_shift($sisa);
            $sisa[] = 0;

            for ($i = 0; $i < $derajat; $i++) {
                $sisa[$i] ^= $this->kaliGalois($pembagi[$i], $faktor);
            }
        }

        return $sisa;
    }

    /**
     * Matriks pola tetap: pencari, pemisah, penanda waktu, penyelaras, modul
     * gelap, dan ruang yang disisihkan untuk informasi format.
     *
     * Modul bernilai null berarti bebas diisi data.
     *
     * @return array<int, array<int, bool|null>>
     */
    private function kerangka(int $ukuran, int $versi): array
    {
        $m = array_fill(0, $ukuran, array_fill(0, $ukuran, null));

        // Tiga pola pencari beserta pemisahnya.
        foreach ([[0, 0], [$ukuran - 7, 0], [0, $ukuran - 7]] as [$baris, $kolom]) {
            for ($b = -1; $b <= 7; $b++) {
                for ($k = -1; $k <= 7; $k++) {
                    $y = $baris + $b;
                    $x = $kolom + $k;

                    if ($y < 0 || $y >= $ukuran || $x < 0 || $x >= $ukuran) {
                        continue;
                    }

                    $diTepi = $b === -1 || $b === 7 || $k === -1 || $k === 7;
                    $diCincin = $b === 0 || $b === 6 || $k === 0 || $k === 6;
                    $diInti = $b >= 2 && $b <= 4 && $k >= 2 && $k <= 4;

                    $m[$y][$x] = ! $diTepi && ($diCincin || $diInti);
                }
            }
        }

        // Penanda waktu pada baris dan kolom ke-6.
        for ($i = 8; $i < $ukuran - 8; $i++) {
            $nilai = $i % 2 === 0;
            $m[6][$i] ??= $nilai;
            $m[$i][6] ??= $nilai;
        }

        // Pola penyelaras. Versi 2 sampai 6 hanya punya satu, di pojok kanan
        // bawah; tiga kombinasi lain bertabrakan dengan pola pencari.
        $titik = self::PENYELARAS[$versi];

        if ($titik !== null) {
            for ($b = -2; $b <= 2; $b++) {
                for ($k = -2; $k <= 2; $k++) {
                    $m[$titik + $b][$titik + $k] = max(abs($b), abs($k)) !== 1;
                }
            }
        }

        // Modul gelap, selalu hitam.
        $m[$ukuran - 8][8] = true;

        // Ruang informasi format disisihkan dengan nilai sementara; isinya
        // ditulis sesudah topeng terpilih.
        foreach ($this->titikFormat($ukuran) as [$y, $x]) {
            $m[$y][$x] ??= false;
        }

        return $m;
    }

    /**
     * Kedua salinan posisi informasi format, berurutan dari bita paling berarti.
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private function titikFormat(int $ukuran): array
    {
        $titik = [];

        // Salinan pertama, mengitari pola pencari kiri atas. Baris dan kolom
        // ke-6 dilewati sebab di sana ada penanda waktu.
        for ($i = 0; $i <= 5; $i++) {
            $titik[] = [$i, 8];
        }
        $titik[] = [7, 8];
        $titik[] = [8, 8];
        $titik[] = [8, 7];
        for ($i = 5; $i >= 0; $i--) {
            $titik[] = [8, $i];
        }

        // Salinan kedua: delapan bita pertama ke kanan atas, sisanya ke kiri
        // bawah. Modul gelap di (ukuran-8, 8) tidak termasuk.
        $kedua = [];
        for ($i = 0; $i < 8; $i++) {
            $kedua[] = [8, $ukuran - 1 - $i];
        }
        for ($i = 8; $i < 15; $i++) {
            $kedua[] = [$ukuran - 7 + ($i - 8), 8];
        }

        return array_merge($titik, $kedua);
    }

    /**
     * Menuangkan kodeword mengikuti alur zig-zag dari pojok kanan bawah.
     *
     * @param  array<int, array<int, bool|null>>  $kerangka
     * @param  array<int, int>  $kodeword
     * @return array<int, array<int, bool>>
     */
    private function tuangData(array $kerangka, array $kodeword, int $ukuran): array
    {
        $bit = [];
        foreach ($kodeword as $bita) {
            for ($i = 7; $i >= 0; $i--) {
                $bit[] = ($bita >> $i) & 1;
            }
        }

        $data = array_fill(0, $ukuran, array_fill(0, $ukuran, false));
        $indeks = 0;

        for ($kanan = $ukuran - 1; $kanan >= 1; $kanan -= 2) {
            // Kolom ke-6 berisi penanda waktu dan tidak pernah jadi kolom data.
            if ($kanan === 6) {
                $kanan = 5;
            }

            for ($langkah = 0; $langkah < $ukuran; $langkah++) {
                for ($sisi = 0; $sisi < 2; $sisi++) {
                    $x = $kanan - $sisi;
                    $naik = (($kanan + 1) & 2) === 0;
                    $y = $naik ? $ukuran - 1 - $langkah : $langkah;

                    if ($kerangka[$y][$x] !== null) {
                        continue;
                    }

                    $data[$y][$x] = ($bit[$indeks] ?? 0) === 1;
                    $indeks++;
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<int, array<int, bool>>  $data
     * @param  array<int, array<int, bool|null>>  $kerangka
     */
    private function topengTerbaik(array $data, array $kerangka, int $ukuran): int
    {
        $terbaik = 0;
        $denda = PHP_INT_MAX;

        for ($topeng = 0; $topeng < 8; $topeng++) {
            $nilai = $this->denda($this->rampungkan($data, $kerangka, $ukuran, $topeng), $ukuran);

            if ($nilai < $denda) {
                $denda = $nilai;
                $terbaik = $topeng;
            }
        }

        return $terbaik;
    }

    /**
     * Menggabungkan pola tetap, data bertopeng, dan informasi format.
     *
     * @param  array<int, array<int, bool>>  $data
     * @param  array<int, array<int, bool|null>>  $kerangka
     * @return array<int, array<int, bool>>
     */
    private function rampungkan(array $data, array $kerangka, int $ukuran, int $topeng): array
    {
        $m = [];

        for ($y = 0; $y < $ukuran; $y++) {
            for ($x = 0; $x < $ukuran; $x++) {
                $m[$y][$x] = $kerangka[$y][$x] !== null
                    ? $kerangka[$y][$x]
                    : $data[$y][$x] !== $this->topeng($topeng, $y, $x);
            }
        }

        $format = $this->bitaFormat($topeng);

        foreach ($this->titikFormat($ukuran) as $urutan => [$y, $x]) {
            // Koordinat pertama memegang bita paling tidak berarti, dan kedua
            // salinan memakai urutan yang sama. Dibuktikan dengan memindai
            // hasilnya memakai zxing, pustaka yang dipakai karyawan.
            $bita = $urutan % 15;
            $m[$y][$x] = (($format >> $bita) & 1) === 1;
        }

        $m[$ukuran - 8][8] = true;

        return $m;
    }

    private function bitaFormat(int $topeng): int
    {
        $data = (self::EC_LEVEL_BITS << 3) | $topeng;
        $sisa = $data << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ((($sisa >> $i) & 1) === 1) {
                $sisa ^= self::FORMAT_POLY << ($i - 10);
            }
        }

        return (($data << 10) | $sisa) ^ self::FORMAT_MASK;
    }

    private function topeng(int $pola, int $y, int $x): bool
    {
        return match ($pola) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
            6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
            7 => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            default => throw new InvalidArgumentException("Pola topeng {$pola} tidak dikenal."),
        };
    }

    /**
     * Denda keterbacaan. Topeng dengan denda terkecil yang dipakai, sebab pola
     * yang terlalu rata atau terlalu mirip pola pencari membuat pemindai gagal.
     *
     * @param  array<int, array<int, bool>>  $m
     */
    private function denda(array $m, int $ukuran): int
    {
        $total = 0;

        // Aturan 1: deretan lima modul sewarna atau lebih.
        for ($i = 0; $i < $ukuran; $i++) {
            foreach ([true, false] as $mendatar) {
                $panjang = 1;

                for ($j = 1; $j < $ukuran; $j++) {
                    $kini = $mendatar ? $m[$i][$j] : $m[$j][$i];
                    $lalu = $mendatar ? $m[$i][$j - 1] : $m[$j - 1][$i];

                    if ($kini === $lalu) {
                        $panjang++;

                        continue;
                    }

                    if ($panjang >= 5) {
                        $total += 3 + ($panjang - 5);
                    }

                    $panjang = 1;
                }

                if ($panjang >= 5) {
                    $total += 3 + ($panjang - 5);
                }
            }
        }

        // Aturan 2: blok dua kali dua sewarna.
        for ($y = 0; $y < $ukuran - 1; $y++) {
            for ($x = 0; $x < $ukuran - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1]
                    && $m[$y][$x] === $m[$y + 1][$x]
                    && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }

        // Aturan 3: pola 1:1:3:1:1 yang menyerupai pola pencari.
        $pola = [true, false, true, true, true, false, true];

        for ($i = 0; $i < $ukuran; $i++) {
            for ($j = 0; $j <= $ukuran - 7; $j++) {
                foreach ([true, false] as $mendatar) {
                    $cocok = true;

                    for ($k = 0; $k < 7; $k++) {
                        $nilai = $mendatar ? $m[$i][$j + $k] : $m[$j + $k][$i];

                        if ($nilai !== $pola[$k]) {
                            $cocok = false;
                            break;
                        }
                    }

                    if (! $cocok) {
                        continue;
                    }

                    // Dihitung bila salah satu sisinya diapit empat modul terang.
                    $sebelum = true;
                    $sesudah = true;

                    for ($k = 1; $k <= 4; $k++) {
                        $kiri = $j - $k;
                        $kanan = $j + 6 + $k;

                        if ($kiri < 0 || ($mendatar ? $m[$i][$kiri] : $m[$kiri][$i])) {
                            $sebelum = false;
                        }

                        if ($kanan >= $ukuran || ($mendatar ? $m[$i][$kanan] : $m[$kanan][$i])) {
                            $sesudah = false;
                        }
                    }

                    if ($sebelum || $sesudah) {
                        $total += 40;
                    }
                }
            }
        }

        // Aturan 4: ketimpangan jumlah modul gelap terhadap separuh.
        $gelap = 0;
        foreach ($m as $baris) {
            $gelap += count(array_filter($baris));
        }

        $persen = $gelap * 100 / ($ukuran * $ukuran);
        $total += 10 * intdiv((int) floor(abs($persen - 50)), 5);

        return $total;
    }
}
