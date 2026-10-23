@extends('layouts.panel')
@section('title', 'Pengguna')
@section('subtitle', 'Kelola akun yang boleh masuk ke aplikasi beserta tugasnya: staf sekolah, wali kelas, orang tua, dan siswa.')

@section('actions')
    <button type="button" class="btn" data-dialog-open="pengguna-tambah"><x-icon name="plus" /> Tambah akun</button>
@endsection

@section('content')
@php
    $labelTugas = function ($p) {
        if ($p->isWaliKelas()) {
            return $p->kelas->nama_kelas ?? 'Belum ditautkan ke kelas';
        }

        if ($p->isSiswa()) {
            return $p->siswa
                ? $p->siswa->nama . ' (' . ($p->siswa->kelas->nama_kelas ?? '-') . ')'
                : 'Belum ditautkan ke data siswa';
        }

        if ($p->isOrangTua()) {
            return $p->anak->isEmpty()
                ? 'Belum ada anak terhubung'
                : $p->anak->pluck('nama')->join(', ');
        }

        return '-';
    };

    $badgePeran = function ($p) {
        if ($p->isAdmin()) {
            return 'badge-ok';
        }

        if ($p->isGuruPiket()) {
            return 'badge-info';
        }

        if ($p->isWaliKelas()) {
            return 'badge-warn';
        }

        return 'badge-mute';
    };
@endphp

<div class="card">
    <div class="table-wrap">
        <table class="table stack-sm">
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Peran</th><th>Tugas / Tautan</th><th>Aktivitas terakhir</th><th class="right">Aksi</th></tr>
            </thead>
            <tbody>
            @foreach ($pengguna as $p)
                @php
                    $terakhir = $p->aktivitas->first();
                    $saya = $p->id === auth()->id();
                @endphp
                <tr>
                    <td class="cell-main">
                        <b>{{ $p->name }}</b>
                        @if ($saya)
                            <span class="badge badge-info">Anda</span>
                        @endif
                    </td>
                    <td data-label="Email"><code class="kode">{{ $p->email }}</code></td>
                    <td data-label="Peran">
                        <span class="badge {{ $badgePeran($p) }}">{{ $p->roleLabel() }}</span>
                    </td>
                    <td data-label="Tugas" class="small">
                        {{ $labelTugas($p) }}
                    </td>
                    <td data-label="Aktivitas terakhir">
                        @if ($terakhir)
                            <span class="small muted">{{ $terakhir->created_at->translatedFormat('d M Y H:i') }}</span>
                            <span class="small muted"> &middot; {{ $p->aktivitas_count }} catatan</span>
                        @else
                            <span class="small muted">Belum ada aktivitas</span>
                        @endif
                    </td>
                    <td class="actions-cell">
                        <span class="row-actions">
                            <button type="button" class="btn btn-ghost btn-sm btn-icon" data-dialog-open="pengguna-edit-{{ $p->id }}" aria-label="Edit {{ $p->name }}">
                                <x-icon name="pencil" />
                            </button>
                            @unless ($saya)
                                <form action="{{ route('pengguna.destroy', $p->id) }}" method="POST" data-confirm="Hapus akun {{ $p->name }}? Akun ini tidak bisa dipakai lagi.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-ghost btn-sm btn-icon" aria-label="Hapus {{ $p->name }}"><x-icon name="trash" /></button>
                                </form>
                            @endunless
                        </span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card card-pad" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Tentang peran</h3>
    <ul class="small muted" style="margin:0;padding-left:20px;line-height:1.8">
        <li><b>Administrator</b> &mdash; akses penuh: data siswa, data kelas, pengguna, laporan, rekap, surat izin, dan scan.</li>
        <li><b>Guru Piket</b> &mdash; scan presensi, laporan harian, rekap, dan surat izin. Tidak bisa mengubah data master.</li>
        <li><b>Wali Kelas</b> &mdash; memantau satu kelas: laporan, rekap, daftar siswa, menyetujui surat izin, dan mencetak kartu kelas. Tidak bisa menandai presensi atau mengubah data siswa.</li>
        <li><b>Orang Tua/Wali</b> &mdash; melihat kehadiran anak yang terhubung dan mengajukan surat izin untuk anak tersebut.</li>
        <li><b>Siswa</b> &mdash; melihat riwayat kehadiran sendiri dan mengajukan surat izin.</li>
        <li>Satu kelas hanya punya satu wali kelas, dan satu data siswa hanya boleh punya satu akun siswa. Tidak ada pendaftaran publik.</li>
    </ul>
</div>

{{-- Dialog tambah akun --}}
<dialog id="pengguna-tambah" @if (old('_modal') === 'pengguna-tambah') data-reopen @endif aria-labelledby="pengguna-tambah-t">
    <form action="{{ route('pengguna.store') }}" method="POST" data-role-form>
        @csrf
        <input type="hidden" name="_modal" value="pengguna-tambah">
        <div class="dlg-head"><h2 id="pengguna-tambah-t">Tambah akun</h2></div>
        <div class="dlg-body">
            @if (old('_modal') === 'pengguna-tambah' && $errors->any())
                <div class="alert alert-bad" role="alert"><x-icon name="alert" /><div class="grow">{{ $errors->first() }}</div></div>
            @endif
            <div class="field">
                <label for="tambah-nama">Nama lengkap</label>
                <input class="input" id="tambah-nama" type="text" name="name" maxlength="255" required value="{{ old('_modal') === 'pengguna-tambah' ? old('name') : '' }}">
            </div>
            <div class="field">
                <label for="tambah-email">Email</label>
                <input class="input" id="tambah-email" type="email" name="email" maxlength="255" required value="{{ old('_modal') === 'pengguna-tambah' ? old('email') : '' }}">
            </div>
            <div class="field">
                <label for="tambah-role">Peran</label>
                <select class="select" id="tambah-role" name="role" required data-role-select>
                    @foreach (\App\Models\User::daftarRole() as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('_modal') === 'pengguna-tambah' && old('role') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" data-role-field="wali_kelas" hidden>
                <label for="tambah-kelas">Kelas yang diampu</label>
                <select class="select" id="tambah-kelas" name="kelas_id" data-required-when-active>
                    <option value="">Pilih kelas</option>
                    @foreach ($kelas as $k)
                        <option value="{{ $k->id }}" @selected(old('_modal') === 'pengguna-tambah' && old('kelas_id') == $k->id)>{{ $k->nama_kelas }}</option>
                    @endforeach
                </select>
                <div class="hint">Satu kelas hanya boleh punya satu wali kelas.</div>
            </div>

            <div class="field" data-role-field="siswa" hidden>
                <label for="tambah-siswa">Data siswa</label>
                <select class="select" id="tambah-siswa" name="siswa_id" data-required-when-active>
                    <option value="">Pilih siswa</option>
                    @foreach ($daftarSiswa as $namaKelas => $anggota)
                        <optgroup label="{{ $namaKelas }}">
                            @foreach ($anggota as $s)
                                <option value="{{ $s->id }}" @selected(old('_modal') === 'pengguna-tambah' && old('siswa_id') == $s->id)>{{ $s->nama }} ({{ $s->nis }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="hint">Satu data siswa hanya boleh punya satu akun siswa.</div>
            </div>

            <div class="field" data-role-field="orang_tua" hidden>
                <span class="label">Anak yang terhubung</span>
                <div class="check-list">
                    @forelse ($daftarSiswa as $namaKelas => $anggota)
                        <div class="small muted" style="margin-top:8px"><b>{{ $namaKelas }}</b></div>
                        @foreach ($anggota as $s)
                            <label class="check">
                                <input type="checkbox" name="anak_ids[]" value="{{ $s->id }}"
                                    @checked(old('_modal') === 'pengguna-tambah' && in_array($s->id, array_map('intval', (array) old('anak_ids', [])), true))>
                                <span>{{ $s->nama }} ({{ $s->nis }})</span>
                            </label>
                        @endforeach
                    @empty
                        <p class="small muted">Belum ada data siswa.</p>
                    @endforelse
                </div>
                <div class="hint">Boleh lebih dari satu anak. Minimal pilih satu.</div>
            </div>

            <div class="field">
                <label for="tambah-sandi">Kata sandi</label>
                <input class="input" id="tambah-sandi" type="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="tambah-sandi2">Ulangi kata sandi</label>
                <input class="input" id="tambah-sandi2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <div class="dlg-foot">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            <button type="submit" class="btn">Simpan akun</button>
        </div>
    </form>
</dialog>

{{-- Dialog edit (satu per akun) --}}
@foreach ($pengguna as $p)
    @php
        $modalEdit = 'pengguna-edit-' . $p->id;
        $bukaEdit = old('_modal') === $modalEdit;
        $peranEdit = $bukaEdit ? old('role', $p->role) : $p->role;
    @endphp
    <dialog id="{{ $modalEdit }}" @if ($bukaEdit) data-reopen @endif aria-labelledby="{{ $modalEdit }}-t">
        <form action="{{ route('pengguna.update', $p->id) }}" method="POST" data-role-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="{{ $modalEdit }}">
            <div class="dlg-head"><h2 id="{{ $modalEdit }}-t">Edit akun</h2></div>
            <div class="dlg-body">
                @if ($bukaEdit && $errors->any())
                    <div class="alert alert-bad" role="alert"><x-icon name="alert" /><div class="grow">{{ $errors->first() }}</div></div>
                @endif
                <div class="field">
                    <label for="edit-nama-{{ $p->id }}">Nama lengkap</label>
                    <input class="input" id="edit-nama-{{ $p->id }}" type="text" name="name" maxlength="255" required value="{{ $bukaEdit ? old('name') : $p->name }}">
                </div>
                <div class="field">
                    <label for="edit-email-{{ $p->id }}">Email</label>
                    <input class="input" id="edit-email-{{ $p->id }}" type="email" name="email" maxlength="255" required value="{{ $bukaEdit ? old('email') : $p->email }}">
                </div>
                <div class="field">
                    <label for="edit-role-{{ $p->id }}">Peran</label>
                    @if ($p->id === auth()->id())
                        <input class="input" type="text" value="{{ $p->roleLabel() }}" disabled>
                        <input type="hidden" name="role" value="{{ $p->role }}">
                        <span class="small muted">Peran akun Anda sendiri tidak bisa diubah.</span>
                    @else
                        <select class="select" id="edit-role-{{ $p->id }}" name="role" required data-role-select>
                            @foreach (\App\Models\User::daftarRole() as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($peranEdit === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                @unless ($p->id === auth()->id())
                    <div class="field" data-role-field="wali_kelas" hidden>
                        <label for="edit-kelas-{{ $p->id }}">Kelas yang diampu</label>
                        <select class="select" id="edit-kelas-{{ $p->id }}" name="kelas_id" data-required-when-active>
                            <option value="">Pilih kelas</option>
                            @foreach ($kelas as $k)
                                <option value="{{ $k->id }}" @selected(($bukaEdit ? old('kelas_id') : $p->kelas_id) == $k->id)>{{ $k->nama_kelas }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field" data-role-field="siswa" hidden>
                        <label for="edit-siswa-{{ $p->id }}">Data siswa</label>
                        <select class="select" id="edit-siswa-{{ $p->id }}" name="siswa_id" data-required-when-active>
                            <option value="">Pilih siswa</option>
                            @foreach ($daftarSiswa as $namaKelas => $anggota)
                                <optgroup label="{{ $namaKelas }}">
                                    @foreach ($anggota as $s)
                                        <option value="{{ $s->id }}" @selected(($bukaEdit ? old('siswa_id') : $p->siswa_id) == $s->id)>{{ $s->nama }} ({{ $s->nis }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="field" data-role-field="orang_tua" hidden>
                        <span class="label">Anak yang terhubung</span>
                        @php
                            $anakTerpilih = $bukaEdit
                                ? array_map('intval', (array) old('anak_ids', []))
                                : $p->anak->pluck('id')->all();
                        @endphp
                        <div class="check-list">
                            @forelse ($daftarSiswa as $namaKelas => $anggota)
                                <div class="small muted" style="margin-top:8px"><b>{{ $namaKelas }}</b></div>
                                @foreach ($anggota as $s)
                                    <label class="check">
                                        <input type="checkbox" name="anak_ids[]" value="{{ $s->id }}"
                                            @checked(in_array($s->id, $anakTerpilih, true))>
                                        <span>{{ $s->nama }} ({{ $s->nis }})</span>
                                    </label>
                                @endforeach
                            @empty
                                <p class="small muted">Belum ada data siswa.</p>
                            @endforelse
                        </div>
                    </div>
                @endunless

                <div class="field">
                    <label for="edit-sandi-{{ $p->id }}">Kata sandi baru <span class="muted">(kosongkan jika tidak diubah)</span></label>
                    <input class="input" id="edit-sandi-{{ $p->id }}" type="password" name="password" minlength="8" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="edit-sandi2-{{ $p->id }}">Ulangi kata sandi baru</label>
                    <input class="input" id="edit-sandi2-{{ $p->id }}" type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
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

@section('scripts')
<script>
    // Tampilkan hanya isian yang relevan dengan peran yang dipilih.
    document.querySelectorAll('[data-role-form]').forEach(function (form) {
        var pilihPeran = form.querySelector('[data-role-select]');
        var grup = form.querySelectorAll('[data-role-field]');

        function sinkron() {
            var peran = pilihPeran ? pilihPeran.value : '';

            grup.forEach(function (el) {
                var aktif = el.getAttribute('data-role-field') === peran;
                el.hidden = !aktif;

                el.querySelectorAll('[data-required-when-active]').forEach(function (input) {
                    input.required = aktif;
                    if (!aktif) {
                        input.value = '';
                    }
                });
            });
        }

        if (pilihPeran) {
            pilihPeran.addEventListener('change', sinkron);
        }

        sinkron();
    });
</script>
@endsection
