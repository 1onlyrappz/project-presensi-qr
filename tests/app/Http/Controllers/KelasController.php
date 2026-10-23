<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasController extends Controller
{
    public function index()
    {
        $kelas = Kelas::withCount('siswa')->orderBy('nama_kelas')->get();

        return view('kelas.index', compact('kelas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kelas' => ['required', 'string', 'max:50', 'unique:kelas,nama_kelas'],
            'wali_kelas' => ['nullable', 'string', 'max:100'],
        ]);

        $kelas = Kelas::create($request->only('nama_kelas', 'wali_kelas'));

        $request->user()?->catatAktivitas('kelas_buat', "Kelas ditambahkan: {$kelas->nama_kelas}", $request->ip());

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, Kelas $kelas)
    {
        $request->validate([
            'nama_kelas' => ['required', 'string', 'max:50', Rule::unique('kelas', 'nama_kelas')->ignore($kelas->id)],
            'wali_kelas' => ['nullable', 'string', 'max:100'],
        ]);

        $kelas->update($request->only('nama_kelas', 'wali_kelas'));

        $request->user()?->catatAktivitas('kelas_ubah', "Kelas diubah: {$kelas->nama_kelas}", $request->ip());

        return back()->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Kelas $kelas)
    {
        // Migration memakai ON DELETE CASCADE: menghapus kelas yang masih berisi siswa
        // akan ikut menghapus semua siswa dan riwayat presensinya. Cegah itu.
        $jumlah = $kelas->siswa()->count();

        if ($jumlah > 0) {
            return back()->with('error', "Kelas {$kelas->nama_kelas} masih berisi {$jumlah} siswa. Pindahkan atau hapus siswanya dulu.");
        }

        $nama = $kelas->nama_kelas;
        $kelas->delete();

        $request->user()?->catatAktivitas('kelas_hapus', "Kelas dihapus: {$nama}", $request->ip());

        return back()->with('success', 'Kelas berhasil dihapus.');
    }
}
