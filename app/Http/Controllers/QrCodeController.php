<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\QrEncoder;
use Illuminate\Contracts\View\View;

class QrCodeController extends Controller
{
    /** Lebar zona sepi di tiap sisi, dalam satuan modul. Spesifikasi QR menuntut empat. */
    private const ZONA_SEPI = 4;

    public function show(Transaction $transaction, QrEncoder $encoder): View
    {
        $this->authorize('view', $transaction);
        abort_unless($transaction->method === Transaction::METHOD_DROP_OFF, 404);

        $qr = $encoder->encode($transaction->code);

        return view('user.transactions.qr', [
            'transaction' => $transaction,
            'qr' => [
                'viewBox' => $qr['size'] + 2 * self::ZONA_SEPI,
                'runs' => $this->deretMendatar($qr['modules'], $qr['size']),
            ],
        ]);
    }

    /**
     * Menggabungkan modul gelap yang bersebelahan menjadi satu persegi.
     *
     * Satu persegi per modul menghasilkan ratusan simpul SVG, dan tepi antar
     * persegi yang bersentuhan sering tampil sebagai garis rambut terang pada
     * pencetakan. Menyatukan deretnya menghilangkan keduanya.
     *
     * @param  array<int, array<int, bool>>  $modules
     * @return array<int, array{x:int, y:int, w:int}>
     */
    private function deretMendatar(array $modules, int $size): array
    {
        $deret = [];

        for ($y = 0; $y < $size; $y++) {
            $x = 0;

            while ($x < $size) {
                if (! $modules[$y][$x]) {
                    $x++;

                    continue;
                }

                $awal = $x;
                while ($x < $size && $modules[$y][$x]) {
                    $x++;
                }

                $deret[] = [
                    'x' => $awal + self::ZONA_SEPI,
                    'y' => $y + self::ZONA_SEPI,
                    'w' => $x - $awal,
                ];
            }
        }

        return $deret;
    }
}
