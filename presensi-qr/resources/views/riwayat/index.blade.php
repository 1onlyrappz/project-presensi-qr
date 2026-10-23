@extends('layouts.panel')
@section('title', 'Riwayat kehadiran')
@section('subtitle', auth()->user()->isSiswa() ? 'Lihat riwayat kehadiran kamu per bulan.' : 'Lihat riwayat kehadiran anak yang terhubung per bulan.')

@section('actions')
    <a href="{{ route('surat_izin.index') }}" class="btn"><x-icon name="letter" /> Ajukan surat izin</a>
@endsection

@section('content')

@if ($daftarSiswa->isEmpty())
    <div class="card">
        <div class="empty">
            <x-icon name="calendar" />
            <h3>Belum ada data siswa yang terhubung</h3>
            <p>Mintalah Administrator untuk menghubungkan akun ini ke data siswa.</p>
        </div>
    </div>
@else

<form class="toolbar" method="GET" action="{{ route('riwayat.index') }}">
    @if ($daftarSiswa->count() > 1)
        <div class="field fixed wide">
            <label for="siswa_id" class="muted small">Pilih anak</label>
            <select class="select" id="siswa_id" name="siswa_id" onchange="this.form.submit()">
                @foreach ($daftarSiswa as $s)
                    <option value="{{ $s->id }}" @selected($siswa && $siswa->id === $s->id)>{{ $s->nama }} ({{ $s->kelas->nama_kelas ?? '-' }})</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="field fixed">
        <label for="bulan" class="muted small">Bulan</label>
        <input class="input" type="month" id="bulan" name="bulan" value="{{ $bulan }}">
    </div>
    <button class="btn" type="submit">Tampilkan</button>
    <a href="{{ route('riwayat.index', $daftarSiswa->count() > 1 && $siswa ? ['siswa_id' => $siswa->id] : []) }}" class="btn btn-ghost">Bulan ini</a>
</form>

@if ($siswa)
    <div class="card card-pad" style="margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <span class="avatar" style="width:48px;height:48px;border-radius:14px">
            {{ $siswa->inisial }}
            @if ($siswa->foto_url)
                <img src="{{ $siswa->foto_url }}" alt="" onerror="this.remove()">
            @endif
        </span>
        <div class="grow">
            <b style="font-size:16px">{{ $siswa->nama }}</b>
            <div class="small muted">NIS {{ $siswa->nis }} &middot; {{ $siswa->kelas->nama_kelas ?? '-' }}</div>
        </div>
        <span class="badge {{ $ringkas['persen'] >= 80 ? 'badge-ok' : ($ringkas['persen'] >= 60 ? 'badge-warn' : 'badge-bad') }}">Kehadiran {{ $ringkas['persen'] }}%</span>
    </div>

    <div class="grid grid-5" style="margin-bottom:18px">
        <div class="card stat ok"><div class="s-label">Hadir</div><div class="s-value">{{ $ringkas['hadir'] }}</div></div>
        <div class="card stat warn"><div class="s-label">Telat</div><div class="s-value">{{ $ringkas['telat'] }}</div></div>
        <div class="card stat info"><div class="s-label">Izin</div><div class="s-value">{{ $ringkas['izin'] }}</div></div>
        <div class="card stat info"><div class="s-label">Sakit</div><div class="s-value">{{ $ringkas['sakit'] }}</div></div>
        <div class="card stat bad"><div class="s-label">Alpa</div><div class="s-value">{{ $ringkas['alpa'] }}</div><div class="s-note">{{ $hariEfektif }} hari efektif</div></div>
    </div>
@endif

<div class="card">
    <div class="card-head">
        <h2>Presensi {{ $namaBulan }}</h2>
    </div>
    @if ($baris->isEmpty())
        <div class="empty">
            <x-icon name="calendar" />
            <h3>Belum ada catatan presensi</h3>
            <p>Belum ada scan masuk atau surat izin yang tercatat untuk bulan ini.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th></tr>
                </thead>
                <tbody>
                @foreach ($baris as $p)
                    <tr>
                        <td class="cell-main num">{{ \Illuminate\Support\Carbon::parse($p->tanggal)->translatedFormat('d M Y') }}</td>
                        <td data-label="Masuk" class="num">{{ $p->jam_masuk_singkat ?? '-' }}</td>
                        <td data-label="Pulang" class="num">{{ $p->jam_pulang_singkat ?? '-' }}</td>
                        <td data-label="Status">
                            <span class="badge {{ \App\Models\Presensi::badgeStatus($p->status) }}">{{ \App\Models\Presensi::labelStatus($p->status) }}</span>
                        </td>
                        <td data-label="Keterangan" class="small muted">{{ $p->catatan ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($surat->isNotEmpty())
    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2>Surat izin bulan ini</h2></div>
        <div class="card-body" style="padding-top:6px">
            <div class="table-wrap">
                <table class="table stack-sm">
                    <thead><tr><th>Jenis</th><th>Periode</th><th>Nomor</th><th>Status</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @foreach ($surat as $s)
                        <tr>
                            <td class="cell-main"><b>{{ $s->label_jenis }}</b></td>
                            <td data-label="Periode" class="num">{{ \Illuminate\Support\Carbon::parse($s->tanggal_mulai)->translatedFormat('d M') }} - {{ \Illuminate\Support\Carbon::parse($s->tanggal_selesai)->translatedFormat('d M Y') }}</td>
                            <td data-label="Nomor" class="num">{{ $s->nomor_surat ?? '-' }}</td>
                            <td data-label="Status"><span class="badge {{ $s->badge_status }}">{{ $s->label_status }}</span></td>
                            <td data-label="Catatan" class="small muted">{{ $s->catatan ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<div class="alert alert-info" style="margin-top:18px">
    <x-icon name="info" />
    <div class="grow">
        <strong>Cara hitung:</strong> Persentase kehadiran = (Hadir + Telat) ÷ hari efektif × 100%. <b>Alpa</b> = hari efektif − (hadir + telat + izin + sakit). Siswa/orang tua bisa mengajukan surat izin lewat menu <b>Surat izin</b>.
    </div>
</div>

@endif
@endsection
