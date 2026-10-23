<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScanController extends Controller
{
    // Halaman kamera untuk scan kartu pelajar
    public function index()
    {
        return view('scan.index');
    }

    // Dipanggil via AJAX setiap kali kamera (atau scanner USB) membaca kode.
    public function proses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_qr' => ['required', 'string', 'max:100'],
        ]);

        $siswa = Siswa::with('kelas')->where('kode_qr', trim($data['kode_qr']))->first();

        if (! $siswa) {
            $request->user()?->catatAktivitas(
                'scan_gagal',
                'Kartu tidak dikenali: '.mb_substr(trim($data['kode_qr']), 0, 60),
                $request->ip()
            );

            return response()->json([
                'status' => 'gagal',
                'pesan' => 'Kartu tidak dikenali. Pastikan kartu pelajar masih berlaku.',
            ], 404);
        }

        $tz = config('presensi.timezone');
        $sekarang = Carbon::now($tz);
        $tanggal = $sekarang->toDateString();
        $jam = $sekarang->format('H:i:s');
        $batasTelat = Carbon::parse($tanggal . ' ' . config('presensi.batas_telat'), $tz);

        $info = [
            'nama' => $siswa->nama,
            'nis' => $siswa->nis,
            'kelas' => $siswa->kelas->nama_kelas ?? '-',
        ];

        // 1) Coba catat MASUK. insertOrIgnore bersifat atomik: kalau baris hari ini
        //    sudah ada (unique siswa_id + tanggal), hasilnya 0 dan tidak ada error,
        //    sehingga dua scan bersamaan tidak menyebabkan error 500.
        $status = $sekarang->gt($batasTelat) ? 'telat' : 'hadir';

        $masukBaru = DB::table('presensi')->insertOrIgnore([
            'siswa_id' => $siswa->id,
            'tanggal' => $tanggal,
            'jam_masuk' => $jam,
            'status' => $status,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ]);

        if ($masukBaru === 1) {
            $request->user()?->catatAktivitas(
                'scan_masuk',
                "Presensi masuk: {$siswa->nama} ({$siswa->nis}) - {$info['kelas']}",
                $request->ip()
            );

            return response()->json($info + [
                'status' => 'masuk',
                'pesan' => 'Presensi masuk tercatat.',
                'jam' => substr($jam, 0, 5),
                'keterangan' => $status,
            ]);
        }

        // 2) Baris hari ini sudah ada -> cek pulang / duplikat.
        $presensi = Presensi::where('siswa_id', $siswa->id)->where('tanggal', $tanggal)->first();

        if (! $presensi) {
            return response()->json([
                'status' => 'gagal',
                'pesan' => 'Data presensi tidak ditemukan. Silakan scan ulang.',
            ] + $info, 409);
        }

        if ($presensi->jam_pulang) {
            return response()->json($info + [
                'status' => 'duplikat',
                'pesan' => 'Sudah presensi masuk dan pulang hari ini.',
                'jam_masuk' => $presensi->jam_masuk_singkat,
                'jam_pulang' => $presensi->jam_pulang_singkat,
                'keterangan' => $presensi->status,
            ]);
        }

        // Baris manual (izin/sakit/alpa): ternyata siswa hadir, jadi scan-nya
        // yang dipakai dan status manualnya diganti.
        if ($presensi->jam_masuk === null && in_array($presensi->status, Presensi::statusManual(), true)) {
            $diubah = Presensi::where('id', $presensi->id)
                ->whereNull('jam_masuk')
                ->update(['jam_masuk' => $jam, 'status' => $status]);

            if ($diubah === 1) {
                $request->user()?->catatAktivitas(
                    'scan_masuk',
                    "Presensi masuk: {$siswa->nama} ({$siswa->nis}) - {$info['kelas']}",
                    $request->ip()
                );

                return response()->json($info + [
                    'status' => 'masuk',
                    'pesan' => 'Presensi masuk tercatat. Status izin/sakit diganti karena siswa hadir.',
                    'jam' => substr($jam, 0, 5),
                    'keterangan' => $status,
                ]);
            }

            // Baris berubah barusan (scan lain) -> muat ulang dan lanjut normal.
            $presensi->refresh();
        }

        // Jeda minimal antara masuk dan pulang.
        $masuk = Carbon::parse($tanggal . ' ' . $presensi->jam_masuk, $tz);
        $jedaDetik = max(0, (int) config('presensi.jeda_pulang_menit')) * 60;

        if (($sekarang->timestamp - $masuk->timestamp) < $jedaDetik) {
            return response()->json($info + [
                'status' => 'tunggu',
                'pesan' => 'Sudah presensi masuk pukul ' . $presensi->jam_masuk_singkat
                    . '. Scan pulang baru bisa dilakukan setelah '
                    . config('presensi.jeda_pulang_menit') . ' menit.',
                'jam_masuk' => $presensi->jam_masuk_singkat,
                'keterangan' => $presensi->status,
            ]);
        }

        // Update atomik: hanya berhasil kalau jam_pulang masih kosong.
        $diubah = Presensi::where('id', $presensi->id)
            ->whereNull('jam_pulang')
            ->update(['jam_pulang' => $jam]);

        if ($diubah === 0) {
            return response()->json($info + [
                'status' => 'duplikat',
                'pesan' => 'Sudah presensi masuk dan pulang hari ini.',
                'keterangan' => $presensi->status,
            ]);
        }

        $request->user()?->catatAktivitas(
            'scan_pulang',
            "Presensi pulang: {$siswa->nama} ({$siswa->nis}) - {$info['kelas']}",
            $request->ip()
        );

        return response()->json($info + [
            'status' => 'pulang',
            'pesan' => 'Presensi pulang tercatat.',
            'jam' => substr($jam, 0, 5),
            'jam_masuk' => $presensi->jam_masuk_singkat,
            'keterangan' => $presensi->status,
        ]);
    }
}
