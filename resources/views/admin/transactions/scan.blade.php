<x-layouts.admin title="Pindai QR Antar Sendiri">
    <x-slot:head>
        @vite('resources/js/scanner.js')
    </x-slot:head>

    {{-- Pemindaian admin tidak mengubah apa pun, hanya membuka transaksinya.
         Admin bukan karyawan: menugaskan transaksi kepadanya akan mengotori
         peringkat karyawan dan laporan, dan menulis scanned_at akan
         memunculkan drop-off ini di antrean Pickup yang sedetik lagi ia
         tinggalkan karena langsung diselesaikan. --}}
    <x-qr-scanner
        :action="route('admin.transactions.scan.store')"
        :close="route('admin.transactions.index')"
        submit-label="Buka Transaksi"
        subtitle="Terima setoran antar sendiri"
        confirm-hint="Cocokkan dengan kode di layar penyetor, lalu tekan tombol di bawah untuk membukanya."
    />
</x-layouts.admin>
