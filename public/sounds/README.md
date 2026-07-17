# Suara Notifikasi Scan Packing

Halaman **Scan Resi — Packing** memutar file suara di folder ini saat scan.

Urutan prioritas yang dipakai sistem:
1. `*.mp3` (taruh suara cewe anime kamu sendiri) — **paling diutamakan**
2. `*.wav` (suara default yang sudah otomatis dibuat) — cadangan
3. Suara TTS browser gaya anime (pitch tinggi) — kalau dua-duanya tidak ada

| Status         | File MP3 (opsional) | File WAV (sudah ada) | Ucapan default        |
| -------------- | ------------------- | -------------------- | --------------------- |
| Sukses Packed  | `sukses.mp3`        | `sukses.wav`         | "Sukses Packed!"      |
| Sudah Packed   | `sudah.mp3`         | `sudah.wav`          | "Sudah Packed ya"     |
| Gagal          | `gagal.mp3`         | `gagal.wav`          | "Gagal! Coba lagi"    |

> File `.wav` sudah dibuat otomatis pakai suara perempuan Windows (Microsoft Zira)
> dengan pitch dinaikkan supaya terdengar lebih imut. Kalau mau suara cewe anime
> asli, cukup taruh file `.mp3` dengan nama di atas — otomatis menimpa yang `.wav`.

## Regenerate suara default
Jalankan script di root project:
```
powershell -ExecutionPolicy Bypass -File gen_sounds.ps1
```

---

## Cara membuat suara karakter cewe anime (gratis)

### Opsi 1 — VOICEVOX (rekomendasi, suara anime Jepang)
1. Download & install VOICEVOX: https://voicevox.hiroshiba.jp/ (gratis, Windows).
2. Buka VOICEVOX, pilih karakter cewe (mis. "Zundamon", "Shikoku Metan", "Hau").
3. Ketik teksnya. Untuk Bahasa Indonesia kamu bisa ketik fonetik, contoh:
   - `sukusesu pakuto` → "Sukses Packed"
   - `sudah pakuto` → "Sudah Packed"
   - `gagaru` → "Gagal"
   (atau pakai teks Jepang sesukamu, mis. "成功！" / "もう完了" / "失敗")
4. Klik **Export audio** → simpan sebagai WAV.
5. Convert WAV → MP3 (pakai https://cloudconvert.com atau Audacity), lalu
   rename jadi `sukses.mp3`, `sudah.mp3`, `gagal.mp3` dan taruh di folder ini.

### Opsi 2 — TTS online gaya anime
- https://lazypy.ro/tts/ (pilih voice anime / Japanese female)
- https://voicemaker.in/ (banyak voice female)
- ElevenLabs (https://elevenlabs.io) untuk suara lebih natural.

Download hasilnya sebagai MP3, rename sesuai tabel di atas.

### Opsi 3 — Rekam sendiri / minta teman
Rekam suara "Sukses Packed", "Sudah Packed", "Gagal", export MP3, rename.

---

Setelah file ditaruh, refresh halaman scan. Tidak perlu ubah kode lagi.
