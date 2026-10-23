@extends('layouts.panel')
@section('title', 'Dashboard')
@section('subtitle', auth()->user()->isSiswa() ? 'Ringkasan kehadiran kamu.' : 'Ringkasan kehadiran anak yang terhubung dengan akun ini.')

@section('actions')
    <a href="{{ route('riwayat.index') }}" class="btn"><x-icon name="calendar" /> Riwayat kehadiran</a>
    <a href="{{ route('surat_izin.index') }}" class="btn btn-ghost"><x-icon name="letter" /> Ajukan surat izin</a>
@endsection

@section('content')

@if ($daftar->isEmpty())
    <div class="card">
        <div class="empty">
            <x-icon name="student" />
            <h3>Belum ada data siswa yang terhubung</h3>
            <p>Mintalah Administrator untuk menghubungkan akun ini ke data siswa (atau ke salah satu anak).</p>
        </div>
    </div>
@else

    @if ($izinPending > 0)
        <div class="alert alert-warn">
            <x-icon name="letter" />
            <div class="grow"><b>{{ $izinPending }} pengajuan surat izin</b> masih menunggu persetujuan.</div>
            <a href="{{ route('surat_izin.index') }}" class="btn btn-sm">Lihat</a>
        </div>
    @endif

    @foreach ($daftar as $item)
        @php
            $s = $item['siswa'];
            $p = $item['hari_ini'];
        @endphp
        <section class="card" style="margin-bottom:18px">
            <div class="card-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:12px">
                    <span class="avatar" style="width:42px;height:42px;border-radius:12px">
                        {{ $s->inisial }}
                        @if ($s->foto_url)
                            <img src="{{ $s->foto_url }}" alt="" onerror="this.remove()">
                        @endif
                    </span>
                    <div>
                        <h2 style="margin:0">{{ $s->nama }}</h2>
                        <div class="small muted">NIS {{ $s->nis }} &middot; {{ $s->kelas->nama_kelas ?? '-' }}</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <span class="muted small">Kehadiran {{ $namaBulan }}</span>
                    <span class="badge {{ $item['persen'] >= 80 ? 'badge-ok' : ($item['persen'] >= 60 ? 'badge-warn' : 'badge-bad') }}">{{ $item['persen'] }}%</span>
                </div>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
                    <div>
                        <span class="muted small">Hari ini</span>
                        <div style="margin-top:4px">
                            @if ($p && $p->jam_pulang)
                                <span class="badge badge-info">Sudah pulang &middot; {{ $p->jam_pulang_singkat }}</span>
                            @elseif ($p && $p->jam_masuk)
                                <span class="badge {{ $p->status === 'telat' ? 'badge-warn' : 'badge-ok' }}">{{ $p->status === 'telat' ? 'Telat masuk' : 'Tepat waktu' }} &middot; {{ $p->jam_masuk_singkat }}</span>
                            @elseif ($p && $p->status === 'izin')
                                <span class="badge badge-info">Izin</span>
                            @elseif ($p && $p->status === 'sakit')
                                <span class="badge badge-info">Sakit</span>
                            @else
                                <span class="badge badge-mute">Belum ada presensi hari ini</span>
                            @endif
                        </div>
                    </div>
                    <div class="grow"></div>
                    <a class="btn btn-sm btn-ghost" href="{{ route('riwayat.index', ['siswa_id' => $s->id]) }}">Lihat riwayat lengkap</a>
                    <a class="btn btn-sm" href="{{ route('surat_izin.index') }}">Ajukan izin</a>
                </div>

                <div class="grid grid-5">
                    <div class="card stat ok"><div class="s-label">Hadir</div><div class="s-value">{{ $item['hadir'] }}</div></div>
                    <div class="card stat warn"><div class="s-label">Telat</div><div class="s-value">{{ $item['telat'] }}</div></div>
                    <div class="card stat info"><div class="s-label">Izin</div><div class="s-value">{{ $item['izin'] }}</div></div>
                    <div class="card stat info"><div class="s-label">Sakit</div><div class="s-value">{{ $item['sakit'] }}</div></div>
                    <div class="card stat bad"><div class="s-label">Alpa</div><div class="s-value">{{ $item['alpa'] }}</div><div class="s-note">dari {{ $hariEfektif }} hari efektif</div></div>
                </div>
            </div>
        </section>
    @endforeach

    <section class="card">
        <div class="card-head"><h2>Aktivitas terakhir</h2></div>
        <div class="card-body">
            @if ($terbaru->isEmpty())
                <div class="empty" style="padding:24px 0 8px">
                    <x-icon name="calendar" />
                    <h3>Belum ada riwayat presensi</h3>
                    <p>Data akan muncul setelah scan presensi atau surat izin disetujui.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table stack-sm">
                        <thead><tr><th>Tanggal</th><th>Siswa</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($terbaru as $p)
                            <tr>
                                <td data-label="Tanggal" class="num">{{ \Illuminate\Support\Carbon::parse($p->tanggal)->translatedFormat('d M Y') }}</td>
                                <td class="cell-main"><b>{{ $p->siswa->nama ?? '-' }}</b></td>
                                <td data-label="Masuk" class="num">{{ $p->jam_masuk_singkat ?? '-' }}</td>
                                <td data-label="Pulang" class="num">{{ $p->jam_pulang_singkat ?? '-' }}</td>
                                <td data-label="Status"><span class="badge {{ \App\Models\Presensi::badgeStatus($p->status) }}">{{ \App\Models\Presensi::labelStatus($p->status) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

@endif
@endsection
