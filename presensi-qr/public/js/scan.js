/* Presensi QR - halaman scan.
 * Semua teks dari server dimasukkan lewat textContent (bukan innerHTML) agar aman dari XSS. */
(function () {
    'use strict';

    var app = document.getElementById('scan-app');
    if (!app) return;

    var URL_PROSES = app.getAttribute('data-url');
    var URL_LOGIN = app.getAttribute('data-login-url');
    var LOCAL_LIB = app.getAttribute('data-local-lib');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';

    var COOLDOWN_KODE_SAMA_MS = 12000; // kartu yang sama tidak dikirim ulang selama jeda ini
    var REQUEST_TIMEOUT_MS = 12000;

    var el = {
        viewfinder: document.getElementById('viewfinder'),
        idleText: document.getElementById('vf-idle-text'),
        btnStart: document.getElementById('btn-start'),
        btnStop: document.getElementById('btn-stop'),
        select: document.getElementById('camera-select'),
        sound: document.getElementById('btn-sound'),
        warn: document.getElementById('cam-warning'),
        warnText: document.getElementById('cam-warning-text'),
        result: document.getElementById('result'),
        rIcon: document.getElementById('r-icon'),
        rTitle: document.getElementById('r-title'),
        rMsg: document.getElementById('r-msg'),
        rName: document.getElementById('r-name'),
        rMeta: document.getElementById('r-meta'),
        form: document.getElementById('manual-form'),
        manual: document.getElementById('manual-code'),
        history: document.getElementById('history'),
        historyEmpty: document.getElementById('history-empty')
    };

    /* ---------- Ikon hasil (statis, aman) ---------- */
    function svg(inner) {
        return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }
    var ICONS = {
        idle: svg('<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>'),
        loading: svg('<path d="M21 12a9 9 0 1 1-3-6.7"/>'),
        ok: svg('<path d="m5 12.5 4.5 4.5L19 7.5"/>'),
        warn: svg('<path d="M12 8v5M12 17h.01"/>'),
        bad: svg('<path d="M18 6 6 18M6 6l12 12"/>')
    };

    var TAMPIL = {
        idle:     { title: 'Siap memindai', icon: 'idle' },
        loading:  { title: 'Memeriksa kartu...', icon: 'loading' },
        masuk:    { title: 'Masuk tercatat', icon: 'ok' },
        pulang:   { title: 'Pulang tercatat', icon: 'ok' },
        duplikat: { title: 'Sudah presensi', icon: 'warn' },
        tunggu:   { title: 'Baru saja masuk', icon: 'warn' },
        gagal:    { title: 'Gagal', icon: 'bad' }
    };

    /* ---------- Suara & getar ---------- */
    var soundOn = true;
    try { soundOn = localStorage.getItem('presensi.sound') !== 'off'; } catch (e) { /* abaikan */ }
    var audioCtx = null;

    function syncSoundButton() {
        el.sound.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
        el.sound.setAttribute('aria-label', soundOn ? 'Suara notifikasi aktif' : 'Suara notifikasi mati');
        el.sound.style.opacity = soundOn ? '1' : '.55';
    }
    syncSoundButton();

    el.sound.addEventListener('click', function () {
        soundOn = !soundOn;
        try { localStorage.setItem('presensi.sound', soundOn ? 'on' : 'off'); } catch (e) { /* abaikan */ }
        syncSoundButton();
        if (soundOn) beep('ok');
    });

    function beep(kind) {
        if (!soundOn) return;
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!audioCtx) audioCtx = new AC();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            var notes = kind === 'ok' ? [[880, 0, 0.12]] : kind === 'warn' ? [[520, 0, 0.12], [520, 0.16, 0.12]] : [[220, 0, 0.18], [180, 0.22, 0.22]];
            notes.forEach(function (n) {
                var o = audioCtx.createOscillator();
                var g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = n[0];
                g.gain.value = 0.08;
                o.connect(g);
                g.connect(audioCtx.destination);
                o.start(audioCtx.currentTime + n[1]);
                o.stop(audioCtx.currentTime + n[1] + n[2]);
            });
        } catch (e) { /* suara hanya tambahan */ }
    }

    function vibrate(pattern) {
        try { if (navigator.vibrate) navigator.vibrate(pattern); } catch (e) { /* abaikan */ }
    }

    /* ---------- Tampilan hasil ---------- */
    function metaItem(text) {
        var s = document.createElement('span');
        s.textContent = text;
        el.rMeta.appendChild(s);
    }

    function ketLabel(k) { return k === 'telat' ? 'Telat' : 'Tepat waktu'; }

    function render(status, d) {
        d = d || {};
        var t = TAMPIL[status] || TAMPIL.gagal;
        el.result.setAttribute('data-s', status);
        el.rIcon.innerHTML = ICONS[t.icon];
        el.rTitle.textContent = t.title;
        el.rMsg.textContent = d.pesan || '';
        el.rName.textContent = d.nama || '';
        el.rMeta.textContent = '';

        if (d.nama) {
            if (d.kelas) metaItem('Kelas ' + d.kelas);
            if (d.nis) metaItem('NIS ' + d.nis);
            if (status === 'masuk') {
                metaItem('Jam ' + d.jam);
                metaItem(ketLabel(d.keterangan));
            } else if (status === 'pulang') {
                metaItem('Pulang ' + d.jam);
                if (d.jam_masuk) metaItem('Masuk ' + d.jam_masuk);
            } else if (d.jam_masuk) {
                metaItem('Masuk ' + d.jam_masuk + (d.jam_pulang ? ', pulang ' + d.jam_pulang : ''));
            }
        }
    }

    function addHistory(status, d) {
        if (!d || (!d.nama && status !== 'gagal')) return;
        var now = new Date();
        var hh = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
        var li = document.createElement('li');

        var time = document.createElement('span'); time.className = 'h-time'; time.textContent = hh;
        var name = document.createElement('span'); name.className = 'h-name'; name.textContent = d.nama || d.pesan || 'Tidak dikenali';
        var badge = document.createElement('span');
        var cls = { masuk: 'badge-ok', pulang: 'badge-info', duplikat: 'badge-warn', tunggu: 'badge-warn', gagal: 'badge-bad' }[status] || 'badge-mute';
        badge.className = 'badge ' + cls;
        badge.textContent = { masuk: d.keterangan === 'telat' ? 'Masuk (telat)' : 'Masuk', pulang: 'Pulang', duplikat: 'Sudah', tunggu: 'Menunggu', gagal: 'Gagal' }[status] || status;

        li.appendChild(time); li.appendChild(name); li.appendChild(badge);
        el.history.insertBefore(li, el.history.firstChild);
        while (el.history.children.length > 8) el.history.removeChild(el.history.lastChild);
        el.history.hidden = false;
        el.historyEmpty.hidden = true;
    }

    function feedback(status) {
        if (status === 'masuk' || status === 'pulang') { beep('ok'); vibrate(80); }
        else if (status === 'duplikat' || status === 'tunggu') { beep('warn'); vibrate([60, 60, 60]); }
        else { beep('bad'); vibrate([200, 100, 200]); }
    }

    /* ---------- Kirim ke server ---------- */
    var busy = false;
    var lastCode = '';
    var lastTime = 0;

    function kirim(code, manual) {
        code = String(code || '').trim();
        if (!code || busy) return;

        var now = Date.now();
        if (!manual && code === lastCode && (now - lastTime) < COOLDOWN_KODE_SAMA_MS) return;
        lastCode = code;
        lastTime = now;

        busy = true;
        render('loading', { pesan: 'Mohon tunggu sebentar.' });

        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, REQUEST_TIMEOUT_MS) : null;

        fetch(URL_PROSES, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({ kode_qr: code }),
            signal: ctrl ? ctrl.signal : undefined
        })
        .then(function (res) {
            return res.json().then(function (d) { return { code: res.status, data: d }; }, function () { return { code: res.status, data: null }; });
        })
        .then(function (r) {
            var d = r.data;

            if (r.code === 419 || r.code === 401) {
                render('gagal', { pesan: 'Sesi login berakhir. Halaman dimuat ulang...' });
                feedback('gagal');
                lastCode = '';
                setTimeout(function () { window.location.href = URL_LOGIN; }, 1800);
                return;
            }
            if (r.code === 422) {
                var msg = d && d.errors && d.errors.kode_qr ? d.errors.kode_qr[0] : 'Kode tidak valid.';
                render('gagal', { pesan: msg });
                feedback('gagal'); lastCode = '';
                return;
            }
            if (r.code === 429) {
                render('gagal', { pesan: 'Terlalu banyak scan dalam waktu singkat. Tunggu beberapa detik.' });
                feedback('gagal'); lastCode = '';
                return;
            }
            if (!d || !d.status) {
                render('gagal', { pesan: 'Server membalas dengan kesalahan (kode ' + r.code + '). Coba scan lagi.' });
                feedback('gagal'); lastCode = '';
                return;
            }

            render(d.status, d);
            addHistory(d.status, d);
            feedback(d.status);
            if (d.status === 'gagal') lastCode = '';
        })
        .catch(function (err) {
            var timeout = err && err.name === 'AbortError';
            render('gagal', { pesan: timeout ? 'Server terlalu lama membalas. Coba scan lagi.' : 'Tidak bisa terhubung ke server. Periksa koneksi lalu scan lagi.' });
            feedback('gagal'); lastCode = '';
        })
        .then(function () {
            if (timer) clearTimeout(timer);
            busy = false;
        });
    }

    /* ---------- Input manual / scanner USB ---------- */
    el.form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = el.manual.value.trim();
        if (!v) { el.manual.focus(); return; }
        kirim(v, true);
        el.manual.value = '';
        el.manual.focus();
    });

    /* ---------- Kamera ---------- */
    var scanner = null;
    var running = false;
    var starting = false;
    var resumeAfterHide = false;

    function showWarn(text) { el.warnText.textContent = text; el.warn.hidden = false; }
    function hideWarn() { el.warn.hidden = true; el.warnText.textContent = ''; }

    function setIdle(text, canRetry) {
        el.viewfinder.classList.remove('live');
        el.idleText.textContent = text;
        el.btnStart.hidden = canRetry === false;
        el.btnStop.hidden = true;
    }

    function libReady() { return typeof Html5Qrcode !== 'undefined'; }

    function explain(err) {
        var s = String((err && (err.name || err.message)) || err || '');
        if (/NotAllowed|Permission/i.test(s)) return 'Izin kamera ditolak. Klik ikon gembok di address bar, izinkan Kamera, lalu klik "Nyalakan kamera".';
        if (/NotFound|Requested device not found|no camera/i.test(s)) return 'Kamera tidak ditemukan di perangkat ini. Gunakan input manual atau scanner USB.';
        if (/NotReadable|TrackStart|in use/i.test(s)) return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        if (/Overconstrained/i.test(s)) return 'Kamera yang dipilih tidak mendukung pengaturan ini. Pilih kamera lain.';
        return 'Kamera tidak bisa dinyalakan (' + s + ').';
    }

    function onDecode(text) { kirim(text, false); }

    function fillCameraList() {
        if (!libReady() || typeof Html5Qrcode.getCameras !== 'function') return;
        Html5Qrcode.getCameras().then(function (cams) {
            if (!cams || cams.length < 2) { el.select.hidden = true; return; }
            var saved = '';
            try { saved = localStorage.getItem('presensi.camera') || ''; } catch (e) { /* abaikan */ }
            el.select.textContent = '';
            cams.forEach(function (c, i) {
                var o = document.createElement('option');
                o.value = c.id;
                o.textContent = c.label || ('Kamera ' + (i + 1));
                el.select.appendChild(o);
            });
            if (saved) el.select.value = saved;
            el.select.hidden = false;
        }).catch(function () { el.select.hidden = true; });
    }

    function stopCamera() {
        if (!scanner || !running) { running = false; return Promise.resolve(); }
        return scanner.stop().catch(function () { /* sudah berhenti */ }).then(function () { running = false; });
    }

    function startCamera(deviceId) {
        if (starting) return;
        hideWarn();

        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showWarn('Browser memblokir kamera karena halaman ini dibuka lewat alamat http:// biasa. Buka lewat http://localhost di komputer server, atau pasang HTTPS: jalankan mulai-https.bat untuk jaringan lokal, atau aktifkan AutoSSL/Let\'s Encrypt di hosting (lihat ssl/README-SSL.md). Sementara itu, gunakan input manual atau scanner USB.');
            setIdle('Kamera tidak tersedia di alamat ini.', false);
            el.manual.focus();
            return;
        }
        if (!libReady()) {
            showWarn('Pembaca QR belum termuat. Komputer ini perlu internet sekali untuk mengambilnya, atau simpan filenya di public/vendor (lihat README). Input manual tetap bisa dipakai.');
            setIdle('Pembaca QR belum termuat.');
            return;
        }

        starting = true;
        el.btnStart.disabled = true;
        setIdle('Menyalakan kamera...');

        stopCamera().then(function () {
            if (!scanner) {
                scanner = new Html5Qrcode('reader', {
                    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                    verbose: false
                });
            }
            var cfg = {
                fps: 10,
                qrbox: function (w, h) { var s = Math.floor(Math.min(w, h) * 0.72); return { width: s, height: s }; }
            };
            var cam = deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' };
            return scanner.start(cam, cfg, onDecode, function () { /* dipanggil terus selama belum ada QR */ });
        }).then(function () {
            running = true;
            el.viewfinder.classList.add('live');
            el.btnStop.hidden = false;
            fillCameraList();
        }).catch(function (err) {
            running = false;
            showWarn(explain(err));
            setIdle('Kamera belum aktif.');
        }).then(function () {
            starting = false;
            el.btnStart.disabled = false;
        });
    }

    el.btnStart.addEventListener('click', function () { startCamera(el.select.hidden ? null : el.select.value); });
    el.btnStop.addEventListener('click', function () {
        resumeAfterHide = false;
        stopCamera().then(function () { setIdle('Kamera dimatikan.'); });
    });
    el.select.addEventListener('change', function () {
        try { localStorage.setItem('presensi.camera', el.select.value); } catch (e) { /* abaikan */ }
        startCamera(el.select.value);
    });

    // Matikan kamera saat tab disembunyikan (hemat baterai), nyalakan lagi saat kembali.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            resumeAfterHide = running;
            if (running) stopCamera().then(function () { setIdle('Kamera dijeda.'); });
        } else if (resumeAfterHide) {
            resumeAfterHide = false;
            startCamera(el.select.hidden ? null : el.select.value);
        }
    });
    window.addEventListener('pagehide', function () { if (scanner && running) scanner.stop().catch(function () {}); });

    /* ---------- Mulai ---------- */
    function boot() {
        if (!libReady() && LOCAL_LIB) {
            // CDN gagal dimuat: coba salinan lokal di public/vendor.
            var s = document.createElement('script');
            s.src = LOCAL_LIB;
            s.onload = function () { startCamera(null); };
            s.onerror = function () { startCamera(null); };
            document.head.appendChild(s);
            return;
        }
        startCamera(null);
    }

    if (document.readyState === 'complete') boot();
    else window.addEventListener('load', boot);
})();
