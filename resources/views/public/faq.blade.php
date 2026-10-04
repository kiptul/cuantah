<x-layouts.public title="FAQ CUANTAH">
    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1500px] px-4 py-12 lg:px-8 lg:py-16">
            <div class="relative overflow-hidden rounded-[2rem] border border-emerald-100 bg-white p-5 shadow-sm sm:p-7 lg:p-9">
                <div class="absolute -right-10 -top-16 h-48 w-48 rounded-full bg-emerald-100"></div>
                <div class="absolute bottom-0 right-24 h-24 w-24 rounded-full bg-lime-100"></div>

                <div class="relative max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">?</span>
                        Pertanyaan Umum
                    </div>

                    <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight text-emerald-950 sm:text-5xl">
                        Ada yang ingin dipastikan dulu?
                    </h1>

                    <p class="mt-4 max-w-2xl text-lg leading-8 text-slate-600">
                        Cek jawaban singkat seputar pembayaran, verifikasi volume, dan harga transaksi sebelum mulai setor.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Pembayaran</span>
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Verifikasi</span>
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Harga</span>
                    </div>
                </div>
            </div>

            <div class="mt-10 grid gap-8 lg:grid-cols-[.62fr_1.38fr] lg:items-start">
                <div class="rounded-[1.75rem] border border-emerald-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-black uppercase tracking-[0.16em] text-emerald-700">Yang perlu diketahui</p>

                    <div class="mt-5 grid gap-3">
                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">Rp</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Pembayaran langsung</p>
                                <p class="text-sm leading-6 text-slate-600">Cash atau transfer oleh mitra.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">L</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Volume diverifikasi</p>
                                <p class="text-sm leading-6 text-slate-600">Nilai akhir mengikuti hasil timbang aktual.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">✓</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Harga tersimpan</p>
                                <p class="text-sm leading-6 text-slate-600">Transaksi memakai snapshot harga saat dibuat.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Memakai <details> bawaan browser, bukan komponen collapse
                     DaisyUI. Versi sebelumnya bersandar pada input tersembunyi
                     beserta transisi content-visibility: butir yang terbuka saat
                     halaman dimuat memang tampil, tetapi butir lain tidak pernah
                     bisa dibuka sama sekali, sehingga sebelas dari dua belas
                     jawaban tidak terjangkau.

                     Jawabannya sendiri disusun dari perilaku sistem yang
                     sebenarnya, bukan janji umum. --}}
                <div class="grid w-full gap-3">
                    @foreach([
                        ['Apa yang dimaksud jelantah, dan kenapa tidak boleh dibuang ke saluran air?',
                         'Jelantah adalah minyak goreng bekas pakai. Dibuang ke saluran air, ia mengeras dan menyumbat; dibuang ke tanah, ia menutup pori tanah dan mencemari air tanah. Dikumpulkan, minyak yang sama bisa diolah menjadi bahan bakar nabati dan bernilai uang.'],
                        ['Bagaimana cara menyetor?',
                         'Buat akun, lalu pilih Setor Jelantah. Tentukan mitra tujuan, pilih dijemput atau diantar sendiri, isi perkiraan volume, dan kirim. Nilai perkiraan yang kamu terima sudah terlihat sebelum tombol kirim ditekan.'],
                        ['Apa bedanya dijemput dan antar sendiri?',
                         'Dijemput berarti karyawan datang ke alamatmu pada tanggal dan jam yang kamu pilih, dan ada potongan ongkir sesuai jarak ke mitra. Antar sendiri berarti kamu membawanya ke lokasi mitra, tanpa potongan ongkir sama sekali; kamu cukup menunjukkan QR transaksi untuk dipindai petugas.'],
                        ['Kenapa setoran jemput ada potongan ongkirnya?',
                         'Karena ada orang dan kendaraan yang berangkat ke alamatmu. Besarnya mengikuti jarak, dan aturannya ditetapkan tiap mitra. Bila volume setoranmu terlalu kecil sehingga nilainya habis termakan ongkir, sistem menolak pengajuan itu dan menyarankan volume minimal atau opsi antar sendiri, supaya tidak ada yang berangkat untuk hasil nol.'],
                        ['Harga per liternya berapa, dan apa bisa berubah setelah saya menyetor?',
                         'Harga yang berlaku selalu tercantum di halaman Harga dan di formulir setoran. Setiap transaksi menyimpan harga saat pengajuan dibuat, jadi perubahan harga sesudahnya tidak mengubah transaksi yang sudah jalan.'],
                        ['Kapan dan bagaimana saya dibayar?',
                         'Pembayaran dilakukan langsung oleh mitra, tunai atau transfer, setelah jelantah ditimbang dan volumenya diverifikasi. Sesudah mitra menandainya terbayar, kamu akan diminta membenarkan bahwa uangnya memang sudah diterima — penandaan oleh mitra saja belum dianggap selesai.'],
                        ['Bagaimana kalau hasil timbangannya tidak sesuai perkiraan saya?',
                         'Kamu bisa mengajukan keberatan dari halaman transaksi dalam tiga hari setelah transaksi selesai. Keberatan itu masuk ke mitra terkait, dan tanggapannya tampil di halaman transaksi yang sama. Batas tiga hari dipakai supaya jelantahnya masih bisa ditelusuri.'],
                        ['Jelantah seperti apa yang diterima?',
                         'Minyak goreng bekas yang masih berupa minyak: disaring dari remah dan tidak tercampur air, deterjen, atau oli. Campuran air adalah alasan penolakan yang paling sering terjadi, dan alasannya selalu disampaikan supaya bisa diperbaiki pada setoran berikutnya.'],
                        ['Bagaimana menyimpannya sebelum disetor?',
                         'Tunggu dingin, saring, lalu simpan dalam wadah tertutup rapat yang tidak bocor — jeriken bekas air minum sudah cukup. Jauhkan dari panas dan sinar matahari langsung. Tidak ada batas waktu simpan selama wadahnya tertutup.'],
                        ['Berapa jumlah paling sedikit yang bisa disetor?',
                         'Setengah liter. Untuk setoran jemput, jumlahnya perlu cukup agar nilainya melebihi ongkir; formulirnya akan memberi tahu angka minimal bila belum mencukupi.'],
                        ['Apakah saya bisa membatalkan pengajuan?',
                         'Bisa, selama statusnya masih menunggu dan belum ada karyawan yang mengerjakannya. Sesudah ditugaskan, pembatalan sepihak merugikan pihak yang sudah berangkat, jadi hubungi mitranya langsung.'],
                        ['Apa yang terjadi dengan data lokasi saya?',
                         'Titik lokasi dipakai untuk menghitung ongkir dan menuntun karyawan ke alamatmu. Yang bisa melihatnya hanya kamu dan staf mitra yang menangani transaksi itu; mitra lain tidak.'],
                    ] as $index => [$q, $a])
                        <details class="group rounded-[1.5rem] border border-emerald-100 bg-white shadow-sm" @if($index === 0) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-5 text-lg font-black text-emerald-950">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-sm font-black text-white">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1">{{ $q }}</span>
                                <svg class="h-5 w-5 shrink-0 text-emerald-700 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>
                            <p class="px-5 pb-5 leading-7 text-slate-600 sm:pl-[4.25rem]">{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
