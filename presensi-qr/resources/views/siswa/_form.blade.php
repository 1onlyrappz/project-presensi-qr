{{-- Form bersama untuk tambah & edit siswa. Variabel: $siswa (null saat tambah), $kelas, $action, $method --}}
<form action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="nis">NIS</label>
            <input class="input" type="text" id="nis" name="nis" maxlength="30" required value="{{ old('nis', $siswa->nis ?? '') }}" @error('nis') aria-invalid="true" @enderror>
            @error('nis') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="nama">Nama lengkap</label>
            <input class="input" type="text" id="nama" name="nama" maxlength="100" required value="{{ old('nama', $siswa->nama ?? '') }}" @error('nama') aria-invalid="true" @enderror>
            @error('nama') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="kelas_id">Kelas</label>
            <select class="select" id="kelas_id" name="kelas_id" required @error('kelas_id') aria-invalid="true" @enderror>
                <option value="">Pilih kelas</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected(old('kelas_id', $siswa->kelas_id ?? '') == $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
            @error('kelas_id') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="jenis_kelamin">Jenis kelamin</label>
            <select class="select" id="jenis_kelamin" name="jenis_kelamin" required>
                <option value="L" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? 'L') === 'L')>Laki-laki</option>
                <option value="P" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? 'L') === 'P')>Perempuan</option>
            </select>
            @error('jenis_kelamin') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="field full">
            <label for="foto">Foto <span class="muted">(opsional, tampil di kartu pelajar)</span></label>
            <div class="photo-row">
                <span class="avatar avatar-lg" data-photo-preview>
                    @if (isset($siswa) && $siswa->foto_url)
                        <img src="{{ $siswa->foto_url }}" alt="Foto saat ini">
                    @else
                        <x-icon name="user" />
                    @endif
                </span>
                <div class="grow">
                    <input class="file input" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" data-photo-input>
                    <div class="hint">JPG, PNG, atau WEBP. Maksimal 2 MB. Foto potret (3:4) paling pas di kartu.</div>
                    <div class="field-error" data-photo-error>@error('foto') {{ $message }} @enderror</div>
                </div>
            </div>
        </div>
    </div>

    @if (! isset($siswa))
        <div class="alert alert-info"><x-icon name="info" /><div class="grow">Kode QR dibuat otomatis setelah data disimpan.</div></div>
    @endif

    <div class="form-actions">
        <button type="submit" class="btn">{{ isset($siswa) ? 'Simpan perubahan' : 'Simpan siswa' }}</button>
        <a href="{{ route('siswa.index') }}" class="btn btn-ghost">Batal</a>
    </div>
</form>
