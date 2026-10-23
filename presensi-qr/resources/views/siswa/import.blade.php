@extends('layouts.panel')
@section('title', 'Import siswa')
@section('subtitle', 'Masukkan ratusan siswa sekaligus dari berkas Excel, tanpa mengisi satu per satu.')

@section('actions')
    <a href="{{ route('siswa.import.template') }}" class="btn btn-soft"><x-icon name="download" /> Unduh template CSV</a>
    <a href="{{ route('siswa.index') }}" class="btn btn-ghost">Kembali ke data siswa</a>
@endsection

@section('content')
<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2>Langkah demi langkah</h2></div>
        <div class="card-body">
            <div class="stack">
                <div>
                    <b>1. Unduh template</b>
                    <p class="muted small">Dapat dari tombol di atas. Isi kolom NIS, Nama, Kelas, dan Jenis Kelamin.</p>
                </div>
                <div>
                    <b>2. Simpan sebagai CSV</b>
                    <p class="muted small">Di Excel: <i>File &rsaquo; Save As &rsaquo; CSV UTF-8</i>. Pemisah koma (,) maupun titik koma (;) keduanya dikenali.</p>
                </div>
                <div>
                    <b>3. Unggah berkasnya</b>
                    <p class="muted small">Maksimal 4 MB. Kode QR setiap siswa dibuat otomatis setelah impor, kartu QR bisa langsung dicetak.</p>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:16px">
                <x-icon name="info" />
                <div class="grow">
                    <strong>Yang perlu diperhatikan:</strong>
                    <ul>
                        <li>NIS harus diisi dan belum terdaftar. Baris dengan NIS yang sudah ada dilewati (bisa di diperbarui lewat centang di bawah).</li>
                        <li>Kolom "Jenis Kelamin" diisi <b>L</b> atau <b>P</b>. Kosong berarti dianggap <b>L</b>.</li>
                        <li>Simpan kolom NIS di Excel sebagai <b>Tekst</b> supaya angka panjang tidak berubah jadi notasi ilmiah.</li>
                        <li>Judul kolom bebas: "NIS", "No Induk", "Nama Lengkap", "Kelas", "JK", dan sejenisnya.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>Unggah berkas</h2></div>
        <div class="card-body">
            <form action="{{ route('siswa.import.proses') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label for="berkas">Berkas CSV</label>
                    <input class="file input" type="file" id="berkas" name="berkas" accept=".csv,.txt,text/csv,text/plain" required>
                    <div class="hint">Berkas hasil "Save As CSV" dari Excel. Maksimal 4 MB.</div>
                    @error('berkas') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="buat_kelas" value="1" @checked(old('buat_kelas', true))>
                        <span>Buat kelas baru otomatis kalau nama kelas belum ada</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="perbarui" value="1" @checked(old('perbarui'))>
                        <span>Perbarui data siswa yang NIS-nya sudah terdaftar</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn"><x-icon name="upload" /> Mulai impor</button>
                </div>
            </form>
        </div>
    </section>
</div>

@if (session('import_hasil'))
    @php($hasil = session('import_hasil'))
    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2>Hasil impor</h2>
            <a href="{{ route('siswa.index') }}" class="btn btn-soft btn-sm">Lihat data siswa</a>
        </div>
        <div class="card-body">
            <div class="grid grid-4" style="margin-bottom:16px">
                <div class="card stat ok"><div class="s-label">Berhasil menambah</div><div class="s-value">{{ $hasil['berhasil'] }}</div></div>
                <div class="card stat info"><div class="s-label">Data diperbarui</div><div class="s-value">{{ $hasil['diperbarui'] }}</div></div>
                <div class="card stat warn"><div class="s-label">Dilewati</div><div class="s-value">{{ $hasil['dilewati'] }}</div></div>
                <div class="card stat bad"><div class="s-label">Gagal</div><div class="s-value">{{ $hasil['gagal'] }}</div></div>
            </div>

            <p class="muted small">
                Baris data dibaca: <b>{{ $hasil['total'] }}</b>.
                Pemisah kolom: <b>{{ $hasil['pemisah'] === ';' ? 'titik koma (;)' : ($hasil['pemisah'] === ',' ? 'koma (,)' : 'tab') }}</b>.
                Judul kolom: {{ $hasil['ada_header'] ? 'dikenali' : 'tidak ada (asumsi urutan NIS, Nama, Kelas, Jenis Kelamin)' }}.
            </p>

            @if (! empty($hasil['kelas_baru']))
                <div class="alert alert-info">
                    <x-icon name="school" />
                    <div class="grow">Kelas baru yang dibuat: <b>{{ implode(', ', $hasil['kelas_baru']) }}</b></div>
                </div>
            @endif

            @if (! empty($hasil['errors']))
                <div class="table-wrap">
                    <table class="table stack-sm">
                        <thead><tr><th>Baris</th><th>NIS</th><th>Nama</th><th>Keterangan</th></tr></thead>
                        <tbody>
                        @foreach ($hasil['errors'] as $e)
                            <tr>
                                <td class="cell-main num">{{ $e['baris'] }}</td>
                                <td data-label="NIS" class="num">{{ $e['nis'] }}</td>
                                <td data-label="Nama">{{ $e['nama'] }}</td>
                                <td data-label="Keterangan" class="small">{{ $e['pesan'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
