@extends('layouts.panel')
@section('title', 'Laporan presensi')
@section('subtitle', 'Lihat siapa yang sudah dan belum presensi pada tanggal tertentu, tandai izin/sakit/alpa, lalu unduh sebagai CSV.')

@section('actions')
    <details class="menu">
        <summary class="btn btn-soft"><x-icon name="download" /> Unduh CSV <x-icon name="chevron" /></summary>
        <div class="menu-list">
            <a href="{{ route('presensi.export', array_merge(request()->query(), ['pemisah' => 'titik_koma'])) }}">
                Untuk Excel Indonesia
                <small>Pemisah titik koma (;). Paling aman dibuka dengan klik ganda.</small>
            </a>
            <a href="{{ route('presensi.export', array_merge(request()->query(), ['pemisah' => 'koma'])) }}">
                Format standar
                <small>Pemisah koma (,). Untuk Google Sheets atau aplikasi lain.</small>
            </a>
        </div>
    </details>
@endsection

@section('content')
@if (auth()->user()->isWaliKelas() && auth()->user()->kelas)
    <div class="alert alert-info" style="margin-bottom:16px">
        <x-icon name="school" />
        <div class="grow">Menampilkan laporan presensi untuk kelas <b>{{ auth()->user()->kelas->nama_kelas }}</b>.</div>
    </div>
@endif
<form class="toolbar" method="GET" action="{{ route('presensi.index') }}" data-no-busy>
    <div class="field fixed">
        <label for="tanggal" class="muted small">Tanggal</label>
        <input class="input" type="date" id="tanggal" name="tanggal" value="{{ $tanggal }}" required>
    </div>
    <div class="field fixed">
        <label for="kelas_id" class="muted small">Kelas</label>
        @if ($kelasTerkunci ?? false)
            <input type="hidden" name="kelas_id" value="{{ $kelas->first()->id ?? auth()->user()->kelas_id }}">
            <input class="input" type="text" value="{{ $kelas->first()->nama_kelas ?? (auth()->user()->kelas->nama_kelas ?? '-') }}" disabled>
        @else
            <select class="select" id="kelas_id" name="kelas_id">
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected(request('kelas_id') == $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        @endif
    </div>
    <div class="field fixed wide">
        <label for="status" class="muted small">Tampilkan</label>
        <select class="select" id="status" name="status">
            <option value="">Semua yang sudah presensi</option>
            <option value="hadir" @selected($statusAktif === 'hadir')>Tepat waktu</option>
            <option value="telat" @selected($statusAktif === 'telat')>Telat</option>
            <option value="izin" @selected($statusAktif === 'izin')>Izin</option>
            <option value="sakit" @selected($statusAktif === 'sakit')>Sakit</option>
            <option value="alpa" @selected($statusAktif === 'alpa')>Alpa</option>
            <option value="belum_pulang" @selected($statusAktif === 'belum_pulang')>Belum pulang</option>
            <option value="belum_hadir" @selected($statusAktif === 'belum_hadir')>Belum presensi</option>
        </select>
    </div>
    <button class="btn" type="submit">Terapkan</button>
    <a href="{{ route('presensi.index') }}" class="btn btn-ghost">Hari ini</a>
</form>

<div class="grid grid-3" style="margin-bottom:18px">
    <div class="card stat ok"><div class="s-label">Sudah hadir</div><div class="s-value">{{ $ringkas['hadir'] }}</div><div class="s-note">dari {{ $ringkas['total_siswa'] }} siswa</div></div>
    <div class="card stat warn"><div class="s-label">Telat</div><div class="s-value">{{ $ringkas['telat'] }}</div><div class="s-note">terlambat scan masuk</div></div>
    <div class="card stat info"><div class="s-label">Izin</div><div class="s-value">{{ $ringkas['izin'] }}</div><div class="s-note">dari surat izin / manual</div></div>
    <div class="card stat info"><div class="s-label">Sakit</div><div class="s-value">{{ $ringkas['sakit'] }}</div><div class="s-note">dari surat izin / manual</div></div>
    <div class="card stat warn"><div class="s-label">Belum pulang</div><div class="s-value">{{ $ringkas['belum_pulang'] }}</div></div>
    <div class="card stat bad"><div class="s-label">Belum presensi</div><div class="s-value">{{ $ringkas['belum_hadir'] }}</div></div>
</div>

<div class="card">
    @if ($data->isEmpty())
        <div class="empty">
            <x-icon name="report" />
            <h3>{{ $modeBelumHadir ? 'Semua siswa sudah presensi' : 'Belum ada data presensi' }}</h3>
            <p>{{ $modeBelumHadir ? 'Tidak ada siswa yang tertinggal pada tanggal dan kelas ini.' : 'Tidak ada presensi yang cocok dengan filter ini. Coba ganti tanggal atau kelas.' }}</p>
            @if ($bolehScan ?? auth()->user()->isAdmin() || auth()->user()->isGuruPiket())
                <a href="{{ route('scan.index') }}" class="btn btn-soft"><x-icon name="scan" /> Buka scanner</a>
            @endif
        </div>
    @elseif ($modeBelumHadir)
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead><tr><th>Siswa</th><th>NIS</th><th>Kelas</th><th>Status</th><th class="right">Tandai</th></tr></thead>
                <tbody>
                @foreach ($data as $s)
                    <tr>
                        <td class="cell-main"><b>{{ $s->nama }}</b></td>
                        <td data-label="NIS" class="num">{{ $s->nis }}</td>
                        <td data-label="Kelas">{{ $s->kelas->nama_kelas ?? '-' }}</td>
                        <td data-label="Status"><span class="badge badge-mute">Belum presensi</span></td>
                        <td class="actions-cell">
                            {{-- Tandai izin / sakit / alpa untuk siswa yang tidak scan. --}}
                            @if ($bolehUbah ?? true)
                                <form method="POST" action="{{ route('presensi.status') }}" class="row-actions">
                                    @csrf
                                    <input type="hidden" name="siswa_id" value="{{ $s->id }}">
                                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                                    <select class="select" name="status" required aria-label="Status untuk {{ $s->nama }}">
                                        <option value="">Pilih status...</option>
                                        <option value="izin">Izin</option>
                                        <option value="sakit">Sakit</option>
                                        <option value="alpa">Alpa</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm">Simpan</button>
                                </form>
                            @else
                                <span class="small muted">Hanya guru piket/administrator</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $data->links('vendor.pagination.sekolah') }}
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead><tr><th>Siswa</th><th>NIS</th><th>Kelas</th><th>Masuk</th><th>Pulang</th><th>Status</th><th class="right">Tandai</th></tr></thead>
                <tbody>
                @foreach ($data as $p)
                    <tr>
                        <td class="cell-main"><b>{{ $p->siswa->nama ?? '-' }}</b></td>
                        <td data-label="NIS" class="num">{{ $p->siswa->nis ?? '-' }}</td>
                        <td data-label="Kelas">{{ $p->siswa->kelas->nama_kelas ?? '-' }}</td>
                        <td data-label="Masuk" class="num">{{ $p->jam_masuk_singkat ?? '-' }}</td>
                        <td data-label="Pulang" class="num">{{ $p->jam_pulang_singkat ?? '-' }}</td>
                        <td data-label="Status">
                            <span class="badge {{ \App\Models\Presensi::badgeStatus($p->status) }}">{{ \App\Models\Presensi::labelStatus($p->status) }}</span>
                            @if ($p->catatan)
                                <div class="small muted">{{ $p->catatan }}</div>
                            @endif
                        </td>
                        <td class="actions-cell">
                            @if ($p->manual)
                                {{-- Status manual (izin/sakit/alpa) masih bisa diubah. --}}
                                <form method="POST" action="{{ route('presensi.status') }}" class="row-actions">
                                    @csrf
                                    <input type="hidden" name="siswa_id" value="{{ $p->siswa_id }}">
                                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                                    <select class="select" name="status" required aria-label="Ubah status untuk {{ $p->siswa->nama ?? 'siswa' }}">
                                        @foreach (\App\Models\Presensi::statusManual() as $statusManual)
                                            <option value="{{ $statusManual }}" @selected($p->status === $statusManual)>{{ \App\Models\Presensi::labelStatus($statusManual) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm">Simpan</button>
                                </form>
                            @elseif ($bolehUbah ?? true)
                                <span class="small muted">-</span>
                            @else
                                <span class="small muted">Lihat saja</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $data->links('vendor.pagination.sekolah') }}
    @endif
</div>

<div class="alert alert-info" style="margin-top:18px">
    <x-icon name="info" />
    <div class="grow">
        <strong>Catatan:</strong>
        <ul>
            <li>Kolom <b>Tandai</b> dipakai untuk mencatat <b>izin</b>, <b>sakit</b>, atau <b>alpa</b> bagi siswa yang tidak scan QR. Status ini langsung masuk ke rekap bulanan untuk rapor.</li>
            <li>Siswa yang sudah scan QR (ada jam masuk) tidak bisa diubah statusnya manual, karena yang tercatat adalah kehadiran sebenarnya.</li>
            <li>Surat izin yang disetujui otomatis menandai hari izin, jadi tidak perlu menandai dua kali.</li>
        </ul>
    </div>
</div>
@endsection
