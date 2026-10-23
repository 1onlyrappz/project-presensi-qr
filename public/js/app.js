/* Presensi QR - perilaku UI bersama (tanpa library) */
(function () {
    'use strict';

    var body = document.body;

    /* ---------- Menu samping (mobile) ---------- */
    var toggle = document.querySelector('[data-nav-toggle]');

    function setNav(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () { setNav(!body.classList.contains('nav-open')); });
    }
    document.querySelectorAll('[data-nav-close]').forEach(function (el) {
        el.addEventListener('click', function () { setNav(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setNav(false);
    });

    /* ---------- Pesan (alert) ---------- */
    document.querySelectorAll('[data-alert-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.alert');
            if (box) box.remove();
        });
    });
    document.querySelectorAll('[data-autohide]').forEach(function (box) {
        setTimeout(function () { if (box.isConnected) box.remove(); }, 7000);
    });

    /* ---------- Tampilkan / sembunyikan kata sandi ---------- */
    var ikonMata = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var ikonMataTutup = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m3 3 18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.4 17.4 0 0 1-3.1 4.1"/><path d="M6.6 6.6C3.6 8.6 2 12 2 12s3.5 7 10 7a10.7 10.7 0 0 0 4.5-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.getAttribute('data-password-toggle') === 'off') return;

        var bungkus = document.createElement('div');
        bungkus.className = 'password-wrap';
        input.parentNode.insertBefore(bungkus, input);
        bungkus.appendChild(input);

        var tombol = document.createElement('button');
        tombol.type = 'button';
        tombol.className = 'password-toggle';
        tombol.innerHTML = ikonMata;
        tombol.setAttribute('aria-label', 'Tampilkan kata sandi');
        tombol.setAttribute('aria-pressed', 'false');
        tombol.title = 'Tampilkan kata sandi';

        tombol.addEventListener('click', function () {
            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            tombol.innerHTML = tampil ? ikonMataTutup : ikonMata;
            tombol.setAttribute('aria-pressed', tampil ? 'true' : 'false');
            tombol.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            tombol.title = tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';

            // Kembalikan fokus ke isian dan caret ke akhir teks.
            input.focus({ preventScroll: true });
            try { input.setSelectionRange(input.value.length, input.value.length); } catch (err) {}
        });

        bungkus.appendChild(tombol);
    });

    /* ---------- Fokus ke isian pertama yang bermasalah (mis. halaman login) ---------- */
    var isianSalah = document.querySelector('.login-card [aria-invalid="true"]');
    if (isianSalah) isianSalah.focus({ preventScroll: true });

    /* ---------- Peringatan Caps Lock ---------- */
    document.querySelectorAll('[data-capslock-for]').forEach(function (hint) {
        var input = document.getElementById(hint.getAttribute('data-capslock-for'));
        if (!input) return;

        function periksa(e) {
            hint.hidden = !(e.getModifierState && e.getModifierState('CapsLock'));
        }

        input.addEventListener('keydown', periksa);
        input.addEventListener('keyup', periksa);
        input.addEventListener('blur', function () { hint.hidden = true; });
    });

    /* ---------- Dialog (tambah/edit) ---------- */
    document.querySelectorAll('[data-dialog-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = document.getElementById(btn.getAttribute('data-dialog-open'));
            if (d && typeof d.showModal === 'function') {
                d.showModal();
                var first = d.querySelector('input:not([type=hidden]), select, textarea');
                if (first) first.focus();
            }
        });
    });
    document.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = btn.closest('dialog');
            if (d) d.close();
        });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
        // Klik di area gelap (backdrop) menutup dialog.
        d.addEventListener('click', function (e) {
            if (e.target === d) d.close();
        });
        // Buka kembali dialog yang formnya gagal validasi.
        if (d.hasAttribute('data-reopen') && typeof d.showModal === 'function') d.showModal();
    });

    /* ---------- Konfirmasi hapus: <form data-confirm="Pesan..."> ---------- */
    var dlg = document.getElementById('dlg-confirm');
    var pending = null;

    if (dlg && typeof dlg.showModal === 'function') {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            var msg = form.getAttribute && form.getAttribute('data-confirm');
            if (!msg || form.__confirmed) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            pending = form;
            document.getElementById('dlg-confirm-msg').textContent = msg;
            dlg.showModal();
        }, true);

        dlg.querySelector('[data-confirm-no]').addEventListener('click', function () {
            pending = null;
            dlg.close();
        });
        dlg.querySelector('[data-confirm-yes]').addEventListener('click', function () {
            var form = pending;
            pending = null;
            dlg.close();
            if (form) {
                form.__confirmed = true;
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }
        });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { pending = null; dlg.close(); } });
    } else {
        // Browser lama tanpa <dialog>: pakai confirm() bawaan.
        document.addEventListener('submit', function (e) {
            var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) e.preventDefault();
        }, true);
    }

    /* ---------- Tombol kirim: tampilkan loading & cegah klik ganda ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form.querySelectorAll || form.hasAttribute('data-no-busy')) return;
        var buttons = form.querySelectorAll('button[type=submit], button:not([type])');
        // Tunda sedikit agar nilai form tetap terkirim sebelum tombol dinonaktifkan.
        setTimeout(function () {
            buttons.forEach(function (b) {
                b.setAttribute('aria-busy', 'true');
                b.disabled = true;
            });
        }, 0);
    });
    // Tombol Kembali di browser (bfcache) tidak boleh meninggalkan tombol yang terkunci.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[aria-busy]').forEach(function (b) {
            b.removeAttribute('aria-busy');
            b.disabled = false;
        });
    });

    /* ---------- Filter yang langsung dikirim saat berubah ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form && el.form.submit(); });
    });

    /* ---------- Jam & tanggal ---------- */
    var clocks = document.querySelectorAll('[data-clock]');

    function tick() {
        var now = new Date();
        clocks.forEach(function (c) {
            var tz = c.getAttribute('data-tz') || undefined;
            var t, d;
            try {
                t = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz });
                d = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', timeZone: tz });
            } catch (err) {
                t = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
                d = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
            }
            var tEl = c.querySelector('[data-clock-time]');
            var dEl = c.querySelector('[data-clock-date]');
            if (tEl) tEl.textContent = t.replace('.', ':');
            if (dEl) dEl.textContent = d;
        });
    }
    if (clocks.length) { tick(); setInterval(tick, 15000); }

    /* ---------- Pratinjau & validasi foto ---------- */
    var fotoInput = document.querySelector('[data-photo-input]');
    if (fotoInput) {
        var preview = document.querySelector('[data-photo-preview]');
        var errBox = document.querySelector('[data-photo-error]');
        var original = preview ? preview.innerHTML : '';

        fotoInput.addEventListener('change', function () {
            var file = fotoInput.files && fotoInput.files[0];
            if (errBox) errBox.textContent = '';
            if (!file) { if (preview) preview.innerHTML = original; return; }

            if (file.size > 2 * 1024 * 1024) {
                if (errBox) errBox.textContent = 'Ukuran foto maksimal 2 MB. Pilih foto yang lebih kecil.';
                fotoInput.value = '';
                if (preview) preview.innerHTML = original;
                return;
            }
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                if (errBox) errBox.textContent = 'Format foto harus JPG, PNG, atau WEBP.';
                fotoInput.value = '';
                if (preview) preview.innerHTML = original;
                return;
            }
            if (preview) {
                var img = document.createElement('img');
                img.alt = 'Pratinjau foto';
                img.src = URL.createObjectURL(file);
                preview.innerHTML = '';
                preview.appendChild(img);
            }
        });
    }
})();
