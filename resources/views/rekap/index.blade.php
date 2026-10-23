@extends('layouts.panel')
@section('title', 'Rekap bulanan')
@section('subtitle', 'Rekap kehadiran per siswa dalam satu bulan: hadir, telat, izin, sakit, dan alpa. Angka ini yang dipakai untuk absensi rapor.')

@section('actions')
    <details class="menu">
        <summary class="btn btn-soft"><x-icon name="download" /> Unduh CSV <x-icon name="chevron" /></summary>
        <div class="menu-list">
            <a href="{{ route('rekap.export', array_merge(request()->query(), ['pemisah' => 'titik_koma'])) }}">
                Untuk Excel Indonesia
                <small>Pemisah titik koma (;). Paling aman dibuka dengan klik ganda.</small>
            </a>
            <a href="{{ route('rekap.export', array_merge(request()->query(), ['pemisah' => 'koma'])) }}">
                Format standar
                <small>Pemisah koma (,). Untuk Google Sheets atau aplikasi lain.</small>
            </a>
        </div>
    </details>
@endsection

@section('content')
<form class="toolbar" method="GET" action="{{ route('rekap.index') }}" data-no-busy>
    <div class="field fixed">
        <label for="bulan" class="muted small">Bulan</label>
        <input class="input" type="month" id="bulan" name="bulan" value="{{ $bulan }}">
    </div>
    <div class="field fixed wide">
        <label for="kelas_id" class="muted small">Kelas</label>
        @if ($kelasTerkunci ?? false)
            <input type="hidden" name="kelas_id" value="{{ $kelasId ?: '' }}">
            <input class="input" type="text" value="{{ $kelas->first()->nama_kelas ?? 'Belum ditautkan ke kelas' }}" disabled>
        @else
            <select class="select" id="kelas_id" name="kelas_id" data-autosubmit>
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected($kelasId == $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        @endif
    </div>
    <button class="btn" type="submit">Terapkan</button>
    <a href="{{ route('rekap.index') }}" class="btn btn-ghost">Bulan ini</a>
</form>

@if (($kelasTerkunci ?? false) && ! $kelasId)
    <div class="alert alert-warn" style="margin-bottom:18px">
        <x-icon name="school" />
        <div class="grow">Akun wali kelas ini belum ditautkan ke kelas. Mintalah Administrator menautkannya lewat menu <b>Pengguna</b>.</div>
    </div>
@endif

<div class="grid grid-4" style="margin-bottom:18px">
    <div class="card stat">
        <div class="s-label"><x-icon name="calendar" /> Hari efektif</div>
        <div class="s-value">{{ $hariEfektif }}</div>
        <div class="s-note">hari sekolah tercatat</div>
    </div>
    <div class="card stat ok">
        <div class="s-label"><x-icon name="check" /> Rata-rata hadir</div>
        <div class="s-value">{{ $ringkas['persen'] }}<small style="font-size:18px">%</small></div>
        <div class="s-note">{{ $ringkas['siswa'] }} siswa &middot; {{ $ringkas['hadir'] }} hadir + {{ $ringkas['telat'] }} telat</div>
    </div>
    <div class="card stat info">
        <div class="s-label"><x-icon name="letter" /> Izin &amp; sakit</div>
        <div class="s-value">{{ $ringkas['izin'] + $ringkas['sakit'] }}</div>
        <div class="s-note">{{ $ringkas['izin'] }} izin &middot; {{ $ringkas['sakit'] }} sakit</div>
    </div>
    <div class="card stat bad">
        <div class="s-label"><x-icon name="alert" /> Alpa</div>
        <div class="s-value">{{ $ringkas['alpa'] }}</div>
        <div class="s-note">tanpa keterangan</div>
    </div>
</div>

@if (auth()->user()->isAdmin())
    <div class="card card-pad" style="margin-bottom:18px">
        <form method="POST" action="{{ route('rekap.hari-efektif') }}" class="form-grid" style="align-items:end">
            @csrf
            <input type="hidden" name="bulan" value="{{ $bulan }}">
            <div class="field">
                <label for="jumlah_hari">Jumlah hari efektif bulan {{ $namaBulan }}</label>
                <input class="input" type="number" id="jumlah_hari" name="jumlah_hari" min="0" max="31"
                       value="{{ $hariEfektifManual !== null ? $hariEfektifManual : $hariEfektifOtomatis }}">
                <div class="hint">
                    Otomatis: {{ $hariEfektifOtomatis }} hari (tanggal yang ada aktivitas presensi).
                    Isi sendiri kalau ada hari libur nasional atau kegiatan sekolah yang membuat angka ini berbeda.
                    Kosongkan untuk kembali ke hitungan otomatis.
                </div>
            </div>
            <div class="field">
                <button type="submit" class="btn btn-ghost">Simpan hari efektif</button>
            </div>
        </form>
    </div>
@endif

<div class="card">
    <div class="card-head">
        <h2>Rekap {{ $namaBulan }}{{ $kelasId ? ' - ' . (optional($kelas->firstWhere('id', $kelasId))->nama_kelas ?? '') : '' }}</h2>
    </div>
    @if ($baris->isEmpty())
        <div class="empty">
            <x-icon name="calendar" />
            <h3>Belum ada data pada bulan ini</h3>
            <p>Rekap muncul setelah ada scan presensi atau catatan izin pada bulan tersebut.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr>
                        <th style="width:44px">No</th><th>NIS</th><th>Siswa</th>
                        <th class="right">Hadir</th><th class="right">Telat</th><th class="right">Izin</th>
                        <th class="right">Sakit</th><th class="right">Alpa</th><th style="width:170px">Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($baris as $i => $b)
                    <tr>
                        <td class="cell-main num" data-label="No">{{ $i + 1 }}</td>
                        <td data-label="NIS" class="num">{{ $b->nis }}</td>
                        <td data-label="Siswa" class="cell-main">
                            <b>{{ $b->nama }}</b>
                            @if (! $kelasId)
                                <span class="small muted">{{ $b->nama_kelas }}</span>
                            @endif
                        </td>
                        <td data-label="Hadir" class="right num">{{ (int) $b->hadir }}</td>
                        <td data-label="Telat" class="right num">{{ (int) $b->telat }}</td>
                        <td data-label="Izin" class="right num">{{ (int) $b->izin }}</td>
                        <td data-label="Sakit" class="right num">{{ (int) $b->sakit }}</td>
                        <td data-label="Alpa" class="right num">
                            <b class="{{ (int) $b->alpa > 0 ? '' : 'muted' }}">{{ (int) $b->alpa }}</b>
                        </td>
                        <td data-label="Kehadiran">
                            <div class="bar-cell">
                                <div class="bar grow"><i style="width: {{ min(100, $b->persen) }}%"></i></div>
                                <span class="pct">{{ $b->persen }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($rekapKelas->isNotEmpty())
    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2>Ringkasan per kelas</h2></div>
        <div class="card-body" style="padding-top:6px">
            <div class="table-wrap">
                <table class="table stack-sm">
                    <thead>
                        <tr><th>Kelas</th><th class="right">Siswa</th><th class="right">Hadir</th><th class="right">Telat</th>
                            <th class="right">Izin</th><th class="right">Sakit</th><th class="right">Alpa</th><th class="right">Rata-rata</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($rekapKelas as $r)
                        <tr>
                            <td class="cell-main"><b>{{ $r['nama_kelas'] }}</b></td>
                            <td data-label="Siswa" class="right num">{{ $r['siswa'] }}</td>
                            <td data-label="Hadir" class="right num">{{ $r['hadir'] }}</td>
                            <td data-label="Telat" class="right num">{{ $r['telat'] }}</td>
                            <td data-label="Izin" class="right num">{{ $r['izin'] }}</td>
                            <td data-label="Sakit" class="right num">{{ $r['sakit'] }}</td>
                            <td data-label="Alpa" class="right num">{{ $r['alpa'] }}</td>
                            <td data-label="Rata-rata" class="right num">{{ $r['persen'] }}%</td>
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
        <strong>Cara membaca angka ini:</strong>
        <ul>
            <li><b>Hadir</b> dan <b>Telat</b> diambil dari hasil scan kartu QR.</li>
            <li><b>Izin</b> dan <b>Sakit</b> diambil dari surat izin yang disetujui, atau dari penandaan manual di halaman Laporan presensi.</li>
            <li><b>Alpa</b> = hari efektif dikurangi hadir, telat, izin, dan sakit.</li>
            <li>Persentase kehadiran dihitung dari (hadir + telat) dibagi hari efektif.</li>
        </ul>
    </div>
</div>
@endsection
