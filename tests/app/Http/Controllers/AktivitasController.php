<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use Illuminate\Http\Request;

/**
 * Jejak aktivitas: siapa yang melakukan apa dan kapan.
 */
class AktivitasController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate([
            'jenis' => ['nullable', 'string', 'max:30'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        $jenis = $filter['jenis'] ?? null;
        $cari = $filter['cari'] ?? null;

        $aktivitas = Aktivitas::with('user')
            ->when($jenis, fn ($q, $j) => $q->where('jenis', $j))
            ->when($cari, function ($q, $c) {
                $q->where(function ($q) use ($c) {
                    $q->where('keterangan', 'like', "%{$c}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$c}%"));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('aktivitas.index', compact('aktivitas', 'jenis'));
    }
}
