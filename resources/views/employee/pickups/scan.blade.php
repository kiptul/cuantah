<x-layouts.app title="Pindai QR Drop-off">
    <x-slot:head>
        @vite('resources/js/scanner.js')
    </x-slot:head>

    {{-- Pemindaian karyawan sekalian menugaskan transaksinya kepada dirinya,
         jadi kalimat penegasannya menyebut hal itu. --}}
    <x-qr-scanner
        :action="route('employee.scan.store')"
        :close="route('employee.dashboard')"
        submit-label="Tampilkan & Assign ke Saya"
        subtitle="Terima setoran antar sendiri"
        confirm-hint="Cocokkan dengan kode di layar penyetor, lalu tekan tombol di bawah untuk mengambil tugasnya."
    />
</x-layouts.app>
