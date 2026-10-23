@extends('layouts.panel')
@section('title', 'Dashboard Wali Kelas')
@if ($kelas)
@section('subtitle', 'Ringkasan kehadiran kelas ' . $kelas->nama_kelas . ' hari ini.')
@else
@section('subtitle', 'Akun wali kelas belum ditautkan ke kelas manapun.')
@endif

@section('actions')
    @if ($kelas)
        <a href="{{ route('presensi.index') }}" class="btn"><x-icon name="report" /> Laporan presensi</a>
        <a href="{{ route('rekap.index', ['kelas_id' => $kelas->id]) }}" class="btn btn-ghost"><x-icon name="calendar" /> Rekap bulanan</a>
        <a href="{{ route('siswa.kartu-kelas', ['kelas_id' => $kelas->id]) }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="idcard" /> Cetak kartu kelas</a>
    @endif
@endsection

@section('content')

@if (! $kelas)
    <div class="card">
        <div class="empty">
            <x-icon name="school" />
            <h3>Belum ada kelas yang diampu</h3>
            <p>Mintalah Administrator untuk menautkan akun ini ke kelas melalui menu Pengguna.</p>
        </div>
    </div>
@else

    @if ($izinPending > 0)
        <div class="alert alert-warn">
            <x-icon name="letter" />
            <div class="grow"><b>{{ $izinPending }} surat izin</b> menunggu persetujuan dari kelas {{ $kelas->nama_kelas }}.</div>
            <a href="{{ route('surat_izin.index') }}" class="btn btn-sm">Lihat surat izin</a>
        </div>
    @endif

    <section class="card hero" aria-label="Kehadiran kelas hari ini">
        <div class="hero-top">
            <div>
                <h2>Kehadiran {{ $kelas->nama_kelas }} hari ini</h2>
                <p class="muted small">{{ $ringkas['hadir'] }} dari {{ $totalSiswa }} siswa sudah scan masuk</p>
            </div>
            <div class="big">{{ $persenHadir }}<small>%</small></div>
        </div>
        <div class="legend">
            <span style="--c:var(--ok)">Tepat waktu <b>{{ max(0, $ringkas['hadir'] - $ringkas['telat']) }}</b></span>
            <span style="--c:#e0a21b">Telat <b>{{ $ringkas['telat'] }}</b></span>
            <span style="--c:#c9d3d1">Belum hadir <b>{{ $ringkas['belum_hadir'] }}</b></span>
            <span style="--c:#7aa9ff">Izin/Sakit <b>{{ $ringkas['izin'] + $ringkas['sakit'] }}</b></span>
        </div>
    </section>

    <div class="grid grid-4" style="margin-top:18px">
        <div class="card stat"><div class="s-label"><x-icon name="users" /> Siswa kelas ini</div><div class="s-value">{{ $totalSiswa }}</div></div>
        <div class="card stat info"><div class="s-label"><x-icon name="logout" /> Sudah pulang</div><div class="s-value">{{ $ringkas['belum_pulang'] }}</div></div>
        <div class="card stat warn"><div class="s-label"><x-icon name="clock" /> Izin/Sakit</div><div class="s-value">{{ $ringkas['izin'] + $ringkas['sakit'] }}</div></div>
        <div class="card stat bad"><div class="s-label"><x-icon name="alert" /> Belum hadir</div><div class="s-value">{{ $ringkas['belum_hadir'] }}</div>
            <div class="s-note"><a href="{{ route('presensi.index', ['status' => 'belum_hadir']) }}">Lihat daftarnya</a></div></div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <section class="card">
            <div class="card-head"><h2>Daftar siswa hari ini</h2></div>
            <div class="card-body" style="padding-top:6px">
                <div class="table-wrap">
                    <table class="table stack-sm">
                        <thead><tr><th>Siswa</th><th>NIS</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($siswaHariIni as $s)
                            @php
                                $p = $s->presensi->first();
                            @endphp
                            <tr>
                                <td class="cell-main"><b>{{ $s->nama }}</b></td>
                                <td data-label="NIS" class="num">{{ $s->nis }}</td>
                                <td data-label="Status">
                                    @if ($p && $p->jam_pulang)
                                        <span class="badge badge-info">Pulang ({{ $p->jam_pulang_singkat }})</span>
                                    @elseif ($p && $p->jam_masuk)
                                        <span class="badge {{ $p->status === 'telat' ? 'badge-warn' : 'badge-ok' }}">{{ $p->status === 'telat' ? 'Telat' : 'Tepat waktu' }} ({{ $p->jam_masuk_singkat }})</span>
                                    @elseif ($p && in_array($p->status, ['izin','sakit'], true))
                                        <span class="badge badge-info">{{ \App\Models\Presensi::labelStatus($p->status) }}</span>
                                    @else
                                        <span class="badge badge-mute">Belum presensi</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">Belum ada siswa di kelas ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Scan terakhir</h2></div>
            <div class="card-body">
                @if ($terbaru->isEmpty())
                    <div class="empty" style="padding:24px 0 8px">
                        <x-icon name="scan" />
                        <h3>Belum ada scan hari ini</h3>
                        <p>Scan pertama akan muncul di sini.</p>
                    </div>
                @else
                    <ul class="feed">
                        @foreach ($terbaru as $p)
                            <li>
                                <span class="avatar">{{ $p->siswa->inisial ?? '?' }}</span>
                                <div class="grow">
                                    <b>{{ $p->siswa->nama ?? '-' }}</b>
                                    <div class="small muted">{{ $p->siswa->nis ?? '-' }}</div>
                                </div>
                                <div class="right">
                                    @if ($p->jam_pulang)
                                        <span class="badge badge-info">Pulang</span>
                                        <div class="f-time">{{ $p->jam_pulang_singkat }}</div>
                                    @else
                                        <span class="badge {{ $p->status === 'telat' ? 'badge-warn' : 'badge-ok' }}">{{ $p->status === 'telat' ? 'Telat' : 'Masuk' }}</span>
                                        <div class="f-time">{{ $p->jam_masuk_singkat }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:18px">
        <div class="card-head"><h2>Kehadiran 7 hari terakhir</h2></div>
        <div class="card-body" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            @foreach ($tren as $t)
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px;min-width:54px">
                    <div title="{{ $t['jumlah'] }} dari {{ $totalSiswa }} siswa ({{ $t['persen'] }}%)" style="width:34px;height:{{ max(12, $t['persen'] * 1.2) }}px;border-radius:8px 8px 4px 4px;background:linear-gradient(180deg,rgba(27,94,63,.9),rgba(27,94,63,.6))"></div>
                    <span class="small muted">{{ $t['label'] }}</span>
                    <span class="small num">{{ $t['persen'] }}%</span>
                </div>
            @endforeach
        </div>
    </section>

@endif
@endsection
