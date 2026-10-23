@extends('layouts.panel')
@section('title', 'Data kelas')
@section('subtitle', 'Kelompokkan siswa per kelas. Kelas baru harus ada sebelum siswa bisa ditambahkan.')

@section('actions')
    <button type="button" class="btn" data-dialog-open="kelas-tambah"><x-icon name="plus" /> Tambah kelas</button>
@endsection

@section('content')
<div class="card">
    @if ($kelas->isEmpty())
        <div class="empty">
            <x-icon name="school" />
            <h3>Belum ada kelas</h3>
            <p>Buat kelas pertama, misalnya "X TJKT 2", lalu tambahkan siswanya.</p>
            <button type="button" class="btn" data-dialog-open="kelas-tambah"><x-icon name="plus" /> Tambah kelas</button>
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr><th>Nama kelas</th><th>Wali kelas</th><th>Jumlah siswa</th><th class="right">Aksi</th></tr>
                </thead>
                <tbody>
                @foreach ($kelas as $k)
                    <tr>
                        <td class="cell-main"><b>{{ $k->nama_kelas }}</b></td>
                        <td data-label="Wali kelas">{{ $k->wali_kelas ?: '-' }}</td>
                        <td data-label="Jumlah siswa" class="num">{{ $k->siswa_count }}</td>
                        <td class="actions-cell">
                            <span class="row-actions">
                                @if ($k->siswa_count > 0)
                                    <a href="{{ route('siswa.kartu-kelas', ['kelas_id' => $k->id]) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Cetak semua kartu kelas ini">
                                        <x-icon name="printer" /> Cetak kartu
                                    </a>
                                @endif
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-dialog-open="kelas-edit-{{ $k->id }}" aria-label="Edit {{ $k->nama_kelas }}">
                                    <x-icon name="pencil" />
                                </button>
                                <form action="{{ route('kelas.destroy', $k->id) }}" method="POST" data-confirm="Hapus kelas {{ $k->nama_kelas }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-ghost btn-sm btn-icon" aria-label="Hapus {{ $k->nama_kelas }}"><x-icon name="trash" /></button>
                                </form>
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Dialog tambah --}}
<dialog id="kelas-tambah" @if (old('_modal') === 'kelas-tambah') data-reopen @endif aria-labelledby="kelas-tambah-t">
    <form action="{{ route('kelas.store') }}" method="POST">
        @csrf
        <input type="hidden" name="_modal" value="kelas-tambah">
        <div class="dlg-head"><h2 id="kelas-tambah-t">Tambah kelas</h2></div>
        <div class="dlg-body">
            @if (old('_modal') === 'kelas-tambah' && $errors->any())
                <div class="alert alert-bad" role="alert"><x-icon name="alert" /><div class="grow">{{ $errors->first() }}</div></div>
            @endif
            <div class="field">
                <label for="tambah-nama">Nama kelas</label>
                <input class="input" id="tambah-nama" type="text" name="nama_kelas" maxlength="50" required placeholder="Contoh: X TJKT 2" value="{{ old('_modal') === 'kelas-tambah' ? old('nama_kelas') : '' }}">
            </div>
            <div class="field">
                <label for="tambah-wali">Wali kelas <span class="muted">(opsional)</span></label>
                <input class="input" id="tambah-wali" type="text" name="wali_kelas" maxlength="100" value="{{ old('_modal') === 'kelas-tambah' ? old('wali_kelas') : '' }}">
            </div>
        </div>
        <div class="dlg-foot">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            <button type="submit" class="btn">Simpan kelas</button>
        </div>
    </form>
</dialog>

{{-- Dialog edit (satu per kelas) --}}
@foreach ($kelas as $k)
    <dialog id="kelas-edit-{{ $k->id }}" @if (old('_modal') === 'kelas-edit-'.$k->id) data-reopen @endif aria-labelledby="kelas-edit-t-{{ $k->id }}">
        <form action="{{ route('kelas.update', $k->id) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="kelas-edit-{{ $k->id }}">
            <div class="dlg-head"><h2 id="kelas-edit-t-{{ $k->id }}">Edit kelas</h2></div>
            <div class="dlg-body">
                @if (old('_modal') === 'kelas-edit-'.$k->id && $errors->any())
                    <div class="alert alert-bad" role="alert"><x-icon name="alert" /><div class="grow">{{ $errors->first() }}</div></div>
                @endif
                <div class="field">
                    <label for="edit-nama-{{ $k->id }}">Nama kelas</label>
                    <input class="input" id="edit-nama-{{ $k->id }}" type="text" name="nama_kelas" maxlength="50" required value="{{ old('_modal') === 'kelas-edit-'.$k->id ? old('nama_kelas') : $k->nama_kelas }}">
                </div>
                <div class="field">
                    <label for="edit-wali-{{ $k->id }}">Wali kelas <span class="muted">(opsional)</span></label>
                    <input class="input" id="edit-wali-{{ $k->id }}" type="text" name="wali_kelas" maxlength="100" value="{{ old('_modal') === 'kelas-edit-'.$k->id ? old('wali_kelas') : $k->wali_kelas }}">
                </div>
            </div>
            <div class="dlg-foot">
                <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                <button type="submit" class="btn">Simpan perubahan</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection
