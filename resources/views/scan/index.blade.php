@extends('layouts.app')
@section('title', 'Scan Resi')

@section('content')
    @php($header = 'Scan Resi — Packing')
    @php($subheader = 'Scan barcode resi JNT dengan kamera HP atau scanner USB.')
    @php($packSetting = \App\Models\AppSetting::current())

    <div x-data="scanApp()" x-init="init()" class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Panel kiri: scanner + input --}}
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs text-gray-500">
                    Pending: <b class="text-amber-600">{{ $pendingCount }}</b>
                    · Saya hari ini: <b class="text-indigo-600">{{ $mePackedToday }}</b>
                </div>
                <button type="button" @click="toggleCamera()"
                        class="btn-secondary text-xs"
                        x-text="cameraOn ? 'Matikan Kamera' : 'Aktifkan Kamera'"></button>
            </div>

            {{-- Toggle mode scan massal --}}
            <label class="flex items-center justify-between gap-3 mb-3 px-3 py-2 rounded-lg border"
                   :class="bulkMode ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-gray-50'">
                <div class="flex-1">
                    <div class="text-sm font-semibold flex items-center gap-2">
                        <span>⚡ Scan Massal</span>
                        <span x-show="bulkMode" class="badge bg-indigo-600 text-white">AKTIF</span>
                    </div>
                    <div class="text-[11px] text-gray-500 leading-tight mt-0.5">
                        Setiap resi yang discan langsung otomatis di-pack.
                        Cocok untuk USB scanner / scan barcode beruntun.
                    </div>
                </div>
                <input type="checkbox" x-model="bulkMode" @change="onBulkToggle()"
                       class="rounded border-gray-300 h-5 w-5 text-indigo-600">
            </label>

            <div x-show="cameraOn" class="mb-3 rounded-lg overflow-hidden border border-gray-200 aspect-video bg-black">
                <div id="qr-reader" class="w-full h-full"></div>
            </div>

            <form @submit.prevent="doLookup()" class="space-y-3">
                <div>
                    <label class="label">Nomor Resi</label>
                    <input x-ref="resiInput" x-model="resi" inputmode="search" autocomplete="off"
                           placeholder="Scan / ketik resi lalu Enter" required
                           class="input text-lg font-mono tracking-wide">
                </div>
                <div class="flex gap-2">
                    <button class="btn-primary flex-1" type="submit" :disabled="loading">
                        <span x-show="!loading && !bulkMode">Cari Pesanan</span>
                        <span x-show="!loading && bulkMode">Scan &amp; Pack</span>
                        <span x-show="loading">Memuat…</span>
                    </button>
                    <button type="button" class="btn-secondary" @click="reset()">Reset</button>
                </div>
            </form>

            <div x-show="message" class="mt-3 rounded-lg px-3 py-2 text-sm"
                 :class="messageType === 'error' ? 'bg-red-50 text-red-700 border border-red-200'
                       : messageType === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200'
                       : 'bg-green-50 text-green-700 border border-green-200'"
                 x-text="message"></div>
        </div>

        {{-- Panel kanan: detail pesanan ATAU riwayat scan massal --}}
        <div class="card lg:col-span-3">
            {{-- Mode normal: detail pesanan --}}
            <template x-if="!bulkMode">
                <div>
                    <template x-if="!order">
                        <div class="text-center py-16 text-gray-400">
                            <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <p class="text-sm">Scan resi atau masukkan nomor resi untuk melihat pesanan.</p>
                            <p class="text-xs mt-2 text-gray-400">Tip: aktifkan ⚡ Scan Massal untuk scan beruntun tanpa konfirmasi.</p>
                        </div>
                    </template>

                    <template x-if="order">
                        <div>
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <div class="text-xs uppercase text-gray-500">Resi</div>
                                    <div class="text-2xl font-mono font-bold" x-text="order.resi_number"></div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Pembeli: <span x-text="order.buyer_name || '—'"></span>
                                    </div>
                                </div>
                                <span class="badge"
                                      :class="order.status === 'pending' ? 'bg-amber-100 text-amber-700'
                                            : order.status === 'packed' ? 'bg-green-100 text-green-700'
                                            : 'bg-gray-100 text-gray-600'"
                                      x-text="order.status"></span>
                            </div>

                            <h3 class="font-semibold mb-2">Kelengkapan Item</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                                        <tr>
                                            <th class="py-2">Produk</th>
                                            <th class="py-2">Varian</th>
                                            <th class="py-2">SKU</th>
                                            <th class="py-2 text-right">Qty</th>
                                            <th class="py-2 text-right">Stok</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <template x-for="item in order.items" :key="item.sku + item.product_name">
                                            <tr :class="!item.has_variant || (item.stock ?? 0) < item.quantity ? 'bg-red-50' : ''">
                                                <td class="py-2" x-text="item.product_name"></td>
                                                <td class="py-2" x-text="item.variant_name || '—'"></td>
                                                <td class="py-2 font-mono text-xs">
                                                    <span x-text="item.sku || '—'"></span>
                                                    <span x-show="!item.has_variant" class="badge bg-red-100 text-red-700 ml-1">Tidak terdaftar</span>
                                                </td>
                                                <td class="py-2 text-right font-semibold text-lg" x-text="item.quantity"></td>
                                                <td class="py-2 text-right" x-text="item.stock ?? '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <template x-if="warnings && warnings.length">
                                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                                    <div class="font-semibold mb-1">Peringatan:</div>
                                    <ul class="list-disc list-inside space-y-0.5">
                                        <template x-for="w in warnings" :key="w"><li x-text="w"></li></template>
                                    </ul>
                                </div>
                            </template>

                            <div class="mt-5 pt-4 border-t flex gap-2">
                                <button class="btn-primary flex-1 text-base py-3"
                                        @click="doConfirm()"
                                        :disabled="!canConfirm || loading">
                                    <span x-show="!loading">Konfirmasi Packed &amp; Kurangi Stok</span>
                                    <span x-show="loading">Memproses…</span>
                                </button>
                                <button class="btn-secondary" @click="reset()">Scan Lagi</button>
                            </div>

                            <template x-if="order.status === 'packed'">
                                <div class="mt-3 text-xs text-gray-500">
                                    Dipacking oleh <b x-text="order.packed_by"></b> pada <span x-text="order.packed_at"></span>.
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Mode scan massal: riwayat scan + counter --}}
            <template x-if="bulkMode">
                <div>
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-center">
                            <div class="text-2xl font-bold text-green-700" x-text="bulkStats.ok"></div>
                            <div class="text-[11px] uppercase text-green-700 tracking-wide">Sukses Packed</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-center">
                            <div class="text-2xl font-bold text-amber-700" x-text="bulkStats.dup"></div>
                            <div class="text-[11px] uppercase text-amber-700 tracking-wide">Sudah Packed</div>
                        </div>
                        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-center">
                            <div class="text-2xl font-bold text-red-700" x-text="bulkStats.err"></div>
                            <div class="text-[11px] uppercase text-red-700 tracking-wide">Gagal</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-sm">Riwayat Scan Massal</h3>
                        <button type="button" @click="clearHistory()"
                                class="text-xs text-gray-500 hover:text-red-600"
                                x-show="bulkHistory.length > 0">Bersihkan riwayat</button>
                    </div>

                    <template x-if="bulkHistory.length === 0">
                        <div class="text-center py-12 text-gray-400">
                            <div class="text-4xl mb-2">📦</div>
                            <p class="text-sm">Mode Scan Massal aktif.</p>
                            <p class="text-xs mt-1">Scan resi pertama untuk mulai. Setiap resi langsung di-pack otomatis.</p>
                        </div>
                    </template>

                    <div class="max-h-[480px] overflow-y-auto divide-y" x-show="bulkHistory.length > 0">
                        <template x-for="(entry, idx) in bulkHistory" :key="entry.id">
                            <div class="py-2 flex items-center gap-3 text-sm"
                                 :class="entry.status === 'ok' ? '' :
                                         entry.status === 'dup' ? 'opacity-80' : 'bg-red-50/60'">
                                <span class="text-xs text-gray-400 w-6 text-right" x-text="bulkHistory.length - idx"></span>
                                <span class="badge shrink-0"
                                      :class="entry.status === 'ok' ? 'bg-green-100 text-green-700'
                                            : entry.status === 'dup' ? 'bg-amber-100 text-amber-700'
                                            : 'bg-red-100 text-red-700'"
                                      x-text="entry.status === 'ok' ? '✓ Packed'
                                            : entry.status === 'dup' ? '⚠ Sudah'
                                            : '✗ Gagal'"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-mono font-semibold text-sm truncate" x-text="entry.resi"></div>
                                    <div class="text-xs text-gray-500 truncate" x-text="entry.message"></div>
                                </div>
                                <span class="text-[10px] text-gray-400 shrink-0" x-text="entry.time"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function scanApp() {
            return {
                resi: '',
                loading: false,
                order: null,
                warnings: [],
                message: '',
                messageType: 'success',
                cameraOn: false,
                qr: null,

                // Mode scan massal
                bulkMode: false,
                bulkHistory: [],
                bulkStats: { ok: 0, dup: 0, err: 0 },
                bulkLastResi: '',
                bulkLastResiAt: 0,
                bulkSeq: 1,

                init() {
                    // Restore preferensi mode dari localStorage
                    try {
                        this.bulkMode = localStorage.getItem('scan_bulk_mode') === '1';
                    } catch (e) { /* abaikan */ }

                    // Pre-load daftar voice TTS (browser mengisinya async).
                    try {
                        if ('speechSynthesis' in window) {
                            window.speechSynthesis.getVoices();
                            window.speechSynthesis.onvoiceschanged = () => {
                                window.speechSynthesis.getVoices();
                            };
                        }
                    } catch (e) { /* abaikan */ }

                    // Autofocus input supaya USB scanner (keyboard wedge) bisa langsung ketik
                    this.$nextTick(() => this.$refs.resiInput?.focus());
                    // USB scanner biasanya diakhiri Enter → form submit otomatis
                },

                onBulkToggle() {
                    try { localStorage.setItem('scan_bulk_mode', this.bulkMode ? '1' : '0'); } catch (e) {}
                    this.message = '';
                    this.$refs.resiInput?.focus();
                },

                async toggleCamera() {
                    if (this.cameraOn) {
                        try { await this.qr?.stop(); } catch(e) {}
                        this.cameraOn = false;
                        return;
                    }
                    this.cameraOn = true;
                    await this.$nextTick();
                    try {
                        this.qr = new Html5Qrcode("qr-reader");
                        await this.qr.start(
                            { facingMode: "environment" },
                            { fps: 10, qrbox: { width: 280, height: 140 } },
                            (decoded) => {
                                this.resi = decoded.trim();
                                this.doLookup();
                                try { this.qr.pause(true); } catch(e) {}
                                // Pause lebih lama di mode normal supaya tidak re-scan
                                // resi yang sama. Di mode massal cukup 800ms.
                                setTimeout(() => { try { this.qr.resume(); } catch(e){} }, this.bulkMode ? 800 : 1500);
                            },
                            () => {}
                        );
                    } catch (e) {
                        this.cameraOn = false;
                        this.showMsg('Tidak bisa mengakses kamera: ' + e.message, 'error');
                    }
                },

                get canConfirm() {
                    if (!this.order || this.order.status !== 'pending') return false;
                    return this.order.items.every(it => it.has_variant && (it.stock ?? 0) >= it.quantity);
                },

                async doLookup() {
                    const resi = this.resi.trim();
                    if (!resi) return;

                    // Mode massal: lewati lookup, langsung confirm. Endpoint confirm
                    // sudah validate stok & status di backend, jadi kalau gagal tetap
                    // ada error message yang masuk ke history.
                    if (this.bulkMode) {
                        // Debounce: skip duplicate scan dalam 1.5 detik (USB scanner
                        // kadang trigger 2x untuk barcode yang sama).
                        const now = Date.now();
                        if (resi === this.bulkLastResi && (now - this.bulkLastResiAt) < 1500) {
                            this.resi = '';
                            this.$refs.resiInput?.focus();
                            return;
                        }
                        this.bulkLastResi = resi;
                        this.bulkLastResiAt = now;

                        await this.bulkPack(resi);
                        this.resi = '';
                        this.$refs.resiInput?.focus();
                        return;
                    }

                    // Mode normal (lookup → tampilkan detail → user klik Konfirmasi)
                    this.loading = true;
                    this.message = '';
                    this.order = null;
                    this.warnings = [];
                    try {
                        const res = await fetch('{{ route('scan.lookup') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ resi_number: resi })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showMsg(data.message || 'Gagal memuat pesanan.', data.code === 'already_packed' ? 'warning' : 'error');
                            if (data.order) this.order = data.order;
                            return;
                        }
                        this.order = data.order;
                        this.warnings = data.warnings || [];
                        if (this.warnings.length) {
                            this.showMsg('Pesanan ditemukan, tapi ada peringatan.', 'warning');
                        } else {
                            this.showMsg('Pesanan ditemukan. Cek kelengkapan lalu klik "Konfirmasi Packed".', 'success');
                        }
                    } catch (e) {
                        this.showMsg('Kesalahan jaringan: ' + e.message, 'error');
                    } finally {
                        this.loading = false;
                        this.$refs.resiInput?.select();
                    }
                },

                async doConfirm() {
                    if (!this.order) return;
                    this.loading = true;
                    try {
                        const res = await fetch('{{ route('scan.confirm') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ resi_number: this.order.resi_number })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showMsg(data.message || 'Gagal mengkonfirmasi.', 'error');
                            return;
                        }
                        this.order = data.order;
                        this.showMsg(data.message, 'success');
                        this.announce('ok');
                    } catch (e) {
                        this.showMsg('Kesalahan: ' + e.message, 'error');
                        this.announce('err');
                    } finally {
                        this.loading = false;
                    }
                },

                /**
                 * Scan massal: langsung POST ke /scan/confirm. Hasilnya didorong ke
                 * history paling atas + update counter. Tidak ada UI detail order
                 * supaya scan berikutnya bisa langsung jalan.
                 */
                async bulkPack(resi) {
                    const startedAt = new Date();
                    const time = startedAt.toLocaleTimeString('id-ID', { hour12: false });
                    let entry = {
                        id: this.bulkSeq++,
                        resi: resi,
                        status: 'err',
                        message: '',
                        time: time,
                    };

                    try {
                        const res = await fetch('{{ route('scan.confirm') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ resi_number: resi })
                        });
                        const data = await res.json();

                        if (res.ok && data.ok) {
                            entry.status = 'ok';
                            entry.message = data.message || 'Berhasil di-packing.';
                            this.bulkStats.ok++;
                            this.announce('ok');
                        } else if (data.code === 'already_packed') {
                            entry.status = 'dup';
                            entry.message = data.message || 'Resi sudah pernah dipacking.';
                            this.bulkStats.dup++;
                            this.announce('dup');
                        } else {
                            entry.status = 'err';
                            entry.message = data.message || 'Gagal memproses resi.';
                            this.bulkStats.err++;
                            this.announce('err');
                        }
                    } catch (e) {
                        entry.status = 'err';
                        entry.message = 'Kesalahan jaringan: ' + e.message;
                        this.bulkStats.err++;
                        this.announce('err');
                    }

                    // Push ke depan supaya scan terbaru di atas. Cap 100 entry.
                    this.bulkHistory.unshift(entry);
                    if (this.bulkHistory.length > 100) {
                        this.bulkHistory.length = 100;
                    }

                    // Tampilkan banner ringkas di kiri juga
                    this.showMsg(
                        `${entry.resi}: ${entry.message}`,
                        entry.status === 'ok' ? 'success' : entry.status === 'dup' ? 'warning' : 'error'
                    );
                },

                clearHistory() {
                    this.bulkHistory = [];
                    this.bulkStats = { ok: 0, dup: 0, err: 0 };
                    this.bulkLastResi = '';
                    this.message = '';
                    this.$refs.resiInput?.focus();
                },

                reset() {
                    this.resi = '';
                    this.order = null;
                    this.warnings = [];
                    this.message = '';
                    this.$refs.resiInput?.focus();
                },

                showMsg(msg, type = 'success') {
                    this.message = msg;
                    this.messageType = type;
                },

                headers() {
                    return {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    };
                },

                // Cache objek Audio supaya tidak buat ulang tiap scan.
                _sfx: {},

                /**
                 * Bunyikan notifikasi per status. Urutan prioritas:
                 *  1. Suara custom hasil upload admin (Pengaturan), kalau ada.
                 *  2. Suara default /sounds/*.wav.
                 *  3. Fallback TTS gaya anime (pitch tinggi) + beep.
                 * URL #1 & #2 sudah di-resolve di server lewat soundUrl().
                 */
                announce(kind) {
                    const sources = {
                        ok:  ['{{ $packSetting->soundUrl('ok') }}'],
                        dup: ['{{ $packSetting->soundUrl('dup') }}'],
                        err: ['{{ $packSetting->soundUrl('err') }}'],
                    };
                    const list = sources[kind] || sources.err;

                    let audio = this._sfx[kind];
                    if (!audio) {
                        audio = new Audio();
                        audio.volume = 1.0;           // paling keras
                        audio._idx = 0;
                        audio.addEventListener('error', () => {
                            // File saat ini gagal → coba sumber berikutnya, lalu TTS.
                            audio._idx++;
                            if (audio._idx < list.length) {
                                audio.src = list[audio._idx];
                                audio.play().catch(() => {});
                            } else {
                                this.beep(kind);
                                this.speak(kind);
                            }
                        });
                        this._sfx[kind] = audio;
                    }

                    audio._idx = 0;
                    audio.src = list[0];
                    audio.currentTime = 0;
                    const p = audio.play();
                    if (p && p.catch) {
                        p.catch(() => { /* error event di atas yang menangani fallback */ });
                    }
                },

                beep(kind) {
                    // Pakai Web Audio API. Volume dinaikkan (gain 0.9) supaya keras.
                    try {
                        const ctx = this._audioCtx || (this._audioCtx =
                            new (window.AudioContext || window.webkitAudioContext)());
                        if (ctx.state === 'suspended') ctx.resume();

                        // Pola nada per status: [frekuensi(Hz), durasi(detik)] beruntun.
                        const patterns = {
                            ok:  [[784, 0.12], [1175, 0.18]],          // sukses: naik, ceria
                            dup: [[600, 0.12], [600, 0.18]],           // sudah: dua nada datar
                            err: [[300, 0.18], [180, 0.30]],           // gagal: turun, rendah
                        };
                        const seq = patterns[kind] || patterns.err;

                        let t = ctx.currentTime;
                        for (const [freq, dur] of seq) {
                            const o = ctx.createOscillator();
                            const g = ctx.createGain();
                            o.connect(g);
                            g.connect(ctx.destination);
                            o.type = kind === 'err' ? 'sawtooth' : 'square';
                            o.frequency.value = freq;
                            g.gain.setValueAtTime(0.0001, t);
                            g.gain.exponentialRampToValueAtTime(0.9, t + 0.01);
                            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
                            o.start(t);
                            o.stop(t + dur + 0.02);
                            t += dur;
                        }
                    } catch (e) { /* mute kalau browser tidak support */ }
                },

                speak(kind) {
                    // Fallback TTS gaya suara cewe anime (pitch tinggi, agak cepat).
                    try {
                        if (!('speechSynthesis' in window)) return;
                        const phrases = {
                            ok:  'Sukses Packed!',
                            dup: 'Sudah Packed ya',
                            err: 'Gagal!',
                        };
                        const text = phrases[kind] || phrases.err;
                        const u = new SpeechSynthesisUtterance(text);
                        u.lang = 'id-ID';
                        u.volume = 1;        // paling keras
                        u.rate = 1.15;       // agak cepat, lincah
                        u.pitch = 1.8;       // pitch tinggi → kesan suara cewe anime

                        // Prioritas voice: female / Google / Indonesia.
                        const voices = window.speechSynthesis.getVoices() || [];
                        const pick =
                            voices.find(v => /female|wanita|perempuan/i.test(v.name)) ||
                            voices.find(v => /id[-_]ID/i.test(v.lang) && /google/i.test(v.name)) ||
                            voices.find(v => /id[-_]ID/i.test(v.lang) || /indones/i.test(v.name));
                        if (pick) u.voice = pick;

                        // Batalkan ucapan sebelumnya supaya tidak menumpuk saat scan cepat.
                        window.speechSynthesis.cancel();
                        window.speechSynthesis.speak(u);
                    } catch (e) { /* abaikan kalau tidak didukung */ }
                },
            };
        }
    </script>
@endsection
