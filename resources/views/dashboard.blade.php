@extends('layouts.panel')
@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan kehadiran siswa hari ini.')

@section('actions')
    <a href="{{ route('scan.index') }}" class="btn"><x-icon name="scan" /> Buka scanner</a>
    <a href="{{ route('presensi.index') }}" class="btn btn-ghost"><x-icon name="report" /> Lihat laporan</a>
@endsection

@section('content')

@if ($totalSiswa === 0)
    <div class="card">
        <div class="empty">
            <x-icon name="student" />
            <h3>Belum ada data siswa</h3>
            <p>Tambahkan kelas dan siswa dulu. Setelah itu kartu QR bisa dicetak dan presensi mulai dicatat.</p>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('kelas.index') }}" class="btn btn-soft">Atur kelas</a>
            @endif
        </div>
    </div>
@else
    <section class="card hero" aria-label="Kehadiran hari ini">
        <div class="hero-top">
            <div>
                <h2>Kehadiran hari ini</h2>
                <p class="muted small">{{ $hadir }} dari {{ $totalSiswa }} siswa sudah scan masuk</p>
            </div>
            <div class="big">{{ $persenHadir }}<small>%</small></div>
        </div>
        <div class="seg" role="img" aria-label="Tepat waktu {{ $tepat }}, telat {{ $telat }}, belum hadir {{ $belumHadir }}">
            <i class="s-ok" style="width: {{ $lebarTepat }}%"></i>
            <i class="s-warn" style="width: {{ $lebarTelat }}%"></i>
        </div>
        <div class="legend">
            <span style="--c:var(--ok)">Tepat waktu <b>{{ $tepat }}</b></span>
            <span style="--c:#e0a21b">Telat <b>{{ $telat }}</b></span>
            <span style="--c:#c9d3d1">Belum hadir <b>{{ $belumHadir }}</b></span>
        </div>
    </section>

    <div class="grid grid-4" style="margin-top:18px">
        <div class="card stat"><div class="s-label"><x-icon name="users" /> Total siswa</div><div class="s-value">{{ $totalSiswa }}</div></div>
        <div class="card stat"><div class="s-label"><x-icon name="school" /> Total kelas</div><div class="s-value">{{ $totalKelas }}</div></div>
        <div class="card stat info"><div class="s-label"><x-icon name="logout" /> Sudah pulang</div><div class="s-value">{{ $sudahPulang }}</div></div>
        <div class="card stat bad"><div class="s-label"><x-icon name="clock" /> Belum hadir</div><div class="s-value">{{ $belumHadir }}</div>
            <div class="s-note"><a href="{{ route('presensi.index', ['status' => 'belum_hadir']) }}">Lihat daftarnya</a></div></div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <section class="card">
            <div class="card-head"><h2>Per kelas</h2></div>
            <div class="card-body" style="padding-top:6px">
                <div class="table-wrap">
                    <table class="table stack-sm">
                        <thead><tr><th>Kelas</th><th>Siswa</th><th>Hadir</th><th style="width:42%">Persentase</th></tr></thead>
                        <tbody>
                        @forelse ($rekapKelas as $r)
                            <tr>
                                <td class="cell-main"><b>{{ $r['nama_kelas'] }}</b></td>
                                <td data-label="Siswa" class="num">{{ $r['total_siswa'] }}</td>
                                <td data-label="Hadir" class="num">{{ $r['hadir'] }}</td>
                                <td data-label="Persentase">
                                    <div class="bar-cell">
                                        <div class="bar grow"><i style="width: {{ $r['persen'] }}%"></i></div>
                                        <span class="pct">{{ $r['persen'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Belum ada kelas.</td></tr>
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
                                    <div class="small muted">{{ $p->siswa->kelas->nama_kelas ?? '-' }}</div>
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
@endif
@endsection
