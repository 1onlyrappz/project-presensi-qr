@extends('layouts.panel')
@php
    $pengguna = auth()->user();
    $bolehProses = $pengguna->isAdmin() || $pengguna->isWaliKelas();
    $lihatFilterKelas = $pengguna->isAdmin() || $pengguna->isGuruPiket();
@endphp
@section('title', 'Surat izin')
@section('subtitle', $pengguna->isSiswa()
    ? 'Ajukan dan pantau surat izin kamu sendiri.'
    : ($pengguna->isOrangTua()
        ? 'Ajukan dan pantau surat izin untuk anak yang terhubung dengan akun ini.'
        : 'Catat surat izin siswa. Setelah disetujui, hari izin otomatis masuk ke rekap bulanan untuk rapor.'))

@section('actions')
    <button type="button" class="btn" data-dialog-open="izin-ajax"><x-icon name="plus" /> Ajukan surat izin</button>
@endsection

@section('content')
<form class="toolbar" method="GET" action="{{ route('surat_izin.index') }}" data-no-busy>
    <div class="field search">
        <label for="cari" class="muted small">Cari</label>
        <div class="search-wrap">
            <x-icon name="search" />
            <input class="input" type="search" id="cari" name="cari" placeholder="Nama siswa, nomor surat, atau alasan" value="{{ $cari }}" maxlength="100">
        </div>
    </div>
    <div class="field fixed">
        <label for="status" class="muted small">Status</label>
        <select class="select" id="status" name="status" data-autosubmit>
            <option value="">Semua status</option>
            <option value="pending" @selected($status === 'pending')>Menunggu</option>
            <option value="disetujui" @selected($status === 'disetujui')>Disetujui</option>
            <option value="ditolak" @selected($status === 'ditolak')>Ditolak</option>
        </select>
    </div>
    @if ($lihatFilterKelas)
        <div class="field fixed">
            <label for="kelas_id" class="muted small">Kelas</label>
            <select class="select" id="kelas_id" name="kelas_id" data-autosubmit>
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected($kelasId == $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        </div>
    @elseif ($pengguna->isWaliKelas() && $kelas->isNotEmpty())
        <div class="field fixed">
            <label class="muted small">Kelas</label>
            <input class="input" type="text" value="{{ $kelas->first()->nama_kelas }}" disabled>
        </div>
    @endif
    <div class="field fixed">
        <label for="bulan" class="muted small">Mulai bulan</label>
        <input class="input" type="month" id="bulan" name="bulan" value="{{ $bulan }}">
    </div>
    <button class="btn btn-ghost" type="submit">Terapkan</button>
    @if ($cari || $status || $kelasId || $bulan)
        <a href="{{ route('surat_izin.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="grid grid-3" style="margin-bottom:18px">
    <div class="card stat warn">
        <div class="s-label"><x-icon name="clock" /> Menunggu persetujuan</div>
        <div class="s-value">{{ (int) ($ringkas->pending ?? 0) }}</div>
    </div>
    <div class="card stat ok">
        <div class="s-label"><x-icon name="check" /> Disetujui</div>
        <div class="s-value">{{ (int) ($ringkas->disetujui ?? 0) }}</div>
    </div>
    <div class="card stat bad">
        <div class="s-label"><x-icon name="xcircle" /> Ditolak</div>
        <div class="s-value">{{ (int) ($ringkas->ditolak ?? 0) }}</div>
    </div>
</div>

<div class="card">
    @if ($suratIzin->isEmpty())
        <div class="empty">
            <x-icon name="letter" />
            <h3>Belum ada surat izin</h3>
            <p>{{ $pengguna->isSiswa() || $pengguna->isOrangTua() ? 'Ajukan surat izin lewat tombol di bawah. Setelah disetujui wali kelas atau Administrator, hari izin otomatis tercatat di rekap bulanan.' : 'Catat surat izin yang masuk dari siswa. Setelah disetujui, hari izin otomatis tercatat di rekap bulanan.' }}</p>
            <button type="button" class="btn" data-dialog-open="izin-ajax"><x-icon name="plus" /> Ajukan surat izin</button>
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr>
                        <th>Siswa</th><th>Jenis</th><th>Periode</th><th>Alasan</th><th>Status</th><th class="right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($suratIzin as $s)
                    <tr>
                        <td class="cell-main">
                            <div class="person">
                                <span class="avatar">{{ $s->siswa->inisial ?? '?' }}</span>
                                <div>
                                    <b>{{ $s->siswa->nama ?? '-' }}</b>
                                    <span class="small muted num">NIS {{ $s->siswa->nis ?? '-' }} &middot; {{ $s->siswa->kelas->nama_kelas ?? '-' }}</span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Jenis">
                            <b>{{ $s->label_jenis }}</b>
                            @if ($s->nomor_surat)
                                <div class="small muted num">No. {{ $s->nomor_surat }}</div>
                            @endif
                        </td>
                        <td data-label="Periode" class="num">
                            {{ \Illuminate\Support\Carbon::parse($s->tanggal_mulai)->translatedFormat('d M Y') }}
                            <div class="small muted">s.d. {{ \Illuminate\Support\Carbon::parse($s->tanggal_selesai)->translatedFormat('d M Y') }}</div>
                        </td>
                        <td data-label="Alasan" class="small">{{ \Illuminate\Support\Str::limit($s->alasan, 70) }}</td>
                        <td data-label="Status">
                            <span class="badge {{ $s->badge_status }}">{{ $s->label_status }}</span>
                            @if ($s->diproses_at)
                                <div class="small muted">{{ $s->diproses_at->translatedFormat('d M Y, H:i') }}</div>
                            @endif
                        </td>
                        <td class="actions-cell">
                            <span class="row-actions">
                                <button type="button" class="btn btn-ghost btn-sm" data-dialog-open="izin-{{ $s->id }}">
                                    {{ $s->status === 'pending' && $bolehProses ? 'Proses' : 'Detail' }}
                                </button>
                                @if ($pengguna->isAdmin() && $s->status !== 'pending')
                                    <form action="{{ route('surat_izin.destroy', $s->id) }}" method="POST" data-confirm="Hapus surat izin {{ $s->siswa->nama }}? Catatan izin di rekap bulanan juga ikut terhapus.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-ghost btn-sm btn-icon" aria-label="Hapus surat izin"><x-icon name="trash" /></button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $suratIzin->links('vendor.pagination.sekolah') }}
    @endif
</div>

{{-- Dialog mengajukan surat izin --}}
<dialog id="izin-ajax" @if (old('_modal') === 'izin-ajax') data-reopen @endif aria-labelledby="izin-ajax-t">
    <form action="{{ route('surat_izin.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_modal" value="izin-ajax">
        <div class="dlg-head"><h2 id="izin-ajax-t">Ajukan surat izin</h2></div>
        <div class="dlg-body">
            @if (old('_modal') === 'izin-ajax' && $errors->any())
                <div class="alert alert-bad" role="alert"><x-icon name="alert" /><div class="grow">{{ $errors->first() }}</div></div>
            @endif

            <div class="field">
                <label for="izin-siswa">Siswa</label>
                <select class="select" id="izin-siswa" name="siswa_id" required @error('siswa_id') aria-invalid="true" @enderror>
                    <option value="">Pilih siswa</option>
                    @foreach ($daftarSiswa as $namaKelas => $anggota)
                        <optgroup label="{{ $namaKelas }}">
                            @foreach ($anggota as $s)
                                <option value="{{ $s->id }}" @selected(old('siswa_id', $pengguna->isSiswa() ? $pengguna->siswa_id : null) == $s->id)>{{ $s->nama }} ({{ $s->nis }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($pengguna->isSiswa() || $pengguna->isOrangTua())
                    <div class="hint">Hanya siswa yang terhubung dengan akun Anda yang bisa dipilih.</div>
                @elseif ($pengguna->isWaliKelas())
                    <div class="hint">Hanya siswa di kelas {{ $pengguna->kelas->nama_kelas ?? '-' }} yang bisa dipilih.</div>
                @endif
                @error('siswa_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="izin-jenis">Jenis izin</label>
                <select class="select" id="izin-jenis" name="jenis_izin" required>
                    @foreach (\App\Models\SuratIzin::daftarJenis() as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('jenis_izin', 'sakit') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('jenis_izin') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="izin-mulai">Tanggal mulai</label>
                    <input class="input" type="date" id="izin-mulai" name="tanggal_mulai" required value="{{ old('tanggal_mulai') }}">
                    @error('tanggal_mulai') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="izin-selesai">Tanggal selesai</label>
                    <input class="input" type="date" id="izin-selesai" name="tanggal_selesai" required value="{{ old('tanggal_selesai') }}">
                    @error('tanggal_selesai') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field full">
                    <label for="izin-nomor">Nomor surat <span class="muted">(opsional)</span></label>
                    <input class="input" type="text" id="izin-nomor" name="nomor_surat" maxlength="100" value="{{ old('nomor_surat') }}">
                    @error('nomor_surat') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field full">
                    <label for="izin-alasan">Alasan</label>
                    <textarea class="input" id="izin-alasan" name="alasan" rows="3" maxlength="1000" required placeholder="Contoh: Demam, perlu beristirahat di rumah.">{{ old('alasan') }}</textarea>
                    @error('alasan') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field full">
                    <label for="izin-lampiran">Foto surat <span class="muted">(opsional)</span></label>
                    <input class="file input" type="file" id="izin-lampiran" name="lampiran" accept="image/jpeg,image/png,image/webp">
                    <div class="hint">JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
                    @error('lampiran') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="alert alert-info"><x-icon name="info" /><div class="grow">Surat masuk daftar sebagai <b>menunggu persetujuan</b>. Wali kelas atau Administrator yang menyetujui, dan hari izinnya langsung tercatat di rekap bulanan.</div></div>
        </div>
        <div class="dlg-foot">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            <button type="submit" class="btn">Simpan pengajuan</button>
        </div>
    </form>
</dialog>

{{-- Dialog detail & proses (satu per surat) --}}
@foreach ($suratIzin as $s)
    <dialog id="izin-{{ $s->id }}" aria-labelledby="izin-t-{{ $s->id }}">
        <div class="dlg-head"><h2 id="izin-t-{{ $s->id }}">Detail surat izin</h2></div>
        <div class="dlg-body">
            <div class="stack">
                <div>
                    <div class="muted small">Siswa</div>
                    <b>{{ $s->siswa->nama ?? '-' }}</b>
                    <div class="small muted num">NIS {{ $s->siswa->nis ?? '-' }} &middot; {{ $s->siswa->kelas->nama_kelas ?? '-' }}</div>
                </div>
                <div>
                    <div class="muted small">Jenis izin</div>
                    <b>{{ $s->label_jenis }}</b>
                    @if ($s->nomor_surat)
                        <div class="small muted num">Nomor surat {{ $s->nomor_surat }}</div>
                    @endif
                </div>
                <div>
                    <div class="muted small">Periode</div>
                    <b>
                        {{ \Illuminate\Support\Carbon::parse($s->tanggal_mulai)->translatedFormat('d M Y') }}
                        s.d. {{ \Illuminate\Support\Carbon::parse($s->tanggal_selesai)->translatedFormat('d M Y') }}
                    </b>
                    <div class="small muted">{{ $s->jumlah_hari }} hari sekolah (tidak termasuk akhir pekan)</div>
                </div>
                <div>
                    <div class="muted small">Alasan</div>
                    <div>{{ $s->alasan }}</div>
                </div>
                @if ($s->lampiran)
                    <div>
                        <div class="muted small">Lampiran</div>
                        <a href="{{ $s->lampiran_url }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                            <x-icon name="idcard" /> Lihat foto surat
                        </a>
                    </div>
                @endif
                @if ($s->catatan)
                    <div>
                        <div class="muted small">Catatan pemroses</div>
                        <div>{{ $s->catatan }}</div>
                    </div>
                @endif
                <div>
                    <div class="muted small">Status</div>
                    <span class="badge {{ $s->badge_status }}">{{ $s->label_status }}</span>
                    @if ($s->diproses_at)
                        <span class="small muted">oleh {{ $s->pemroses->name ?? '-' }} pada {{ $s->diproses_at->translatedFormat('d M Y, H:i') }}</span>
                    @endif
                </div>
            </div>

            @if ($s->status === 'pending' && $bolehProses)
                <form action="{{ route('surat_izin.proses', $s->id) }}" method="POST" class="stack" style="margin-top:18px">
                    @csrf
                    <div class="field">
                        <span class="label">Keputusan</span>
                        <label class="check">
                            <input type="radio" name="status" value="disetujui" checked>
                            <span>Disetujui &mdash; hari izin dicatat di rekap bulanan</span>
                        </label>
                        <label class="check">
                            <input type="radio" name="status" value="ditolak">
                            <span>Ditolak &mdash; hari izin tidak dicatat</span>
                        </label>
                    </div>
                    <div class="field">
                        <label for="catatan-{{ $s->id }}">Catatan <span class="muted">(opsional)</span></label>
                        <textarea class="input" id="catatan-{{ $s->id }}" name="catatan" rows="2" maxlength="500" placeholder="Contoh: Sesuai surat orang tua."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn">Simpan keputusan</button>
                    </div>
                </form>
            @elseif ($s->status === 'pending')
                <div class="alert alert-warn" style="margin-top:18px">
                    <x-icon name="clock" />
                    <div class="grow">Surat ini menunggu persetujuan wali kelas atau Administrator.</div>
                </div>
            @endif
        </div>
        <div class="dlg-foot">
            <button type="button" class="btn btn-ghost" data-dialog-close>Tutup</button>
        </div>
    </dialog>
@endforeach
@endsection
