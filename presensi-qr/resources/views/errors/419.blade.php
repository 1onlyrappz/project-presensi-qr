@include('errors._halaman', [
    'kode' => 419,
    'judul' => 'Sesi sudah berakhir',
    'pesan' => 'Demi keamanan, halaman ini kedaluwarsa setelah lama tidak dipakai. Silakan masuk lagi.',
    'aksi' => 'Masuk lagi',
    'tujuan' => url('/login'),
])
