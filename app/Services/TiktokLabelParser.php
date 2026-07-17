<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Parser label pengiriman marketplace.
 *
 * Didukung:
 *  - TikTok Shop + J&T Express (resi JX/JP/JY + kolom Weight/Ship/Order Id)
 *  - TikTok Shop / Tokopedia + J&T Cargo (FastTrack) — sama tabel produk,
 *    tapi resi & courier berbeda (J&T CARGO branding)
 *  - Shopee + SPX (Shopee Express) (resi SPXID + kolom Berat/Batas Kirim/No.Pesanan)
 *
 * Strategi: auto-detect format dari header teks, lalu walkLines()
 * dengan anchor keyword spesifik marketplace.
 *
 * Multi-page handling:
 *  - Tiap PDF page diparse independen.
 *  - Halaman tanpa resi tapi punya Order ID dianggap "continuation page"
 *    (mis. halaman ke-2 J&T Express yang berisi Customer Message / Seller Note)
 *    dan di-merge ke primary page dengan Order ID yang sama.
 *  - Halaman dengan resi sama akan di-dedupe (ambil yang paling lengkap,
 *    merge seller_note dari sisanya).
 */
class TiktokLabelParser
{
    /**
     * @return array<int, array<string, mixed>> Satu entry per pesanan
     */
    public function parseFile(string $pdfPath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);

        $allPages = [];
        $pages = $pdf->getPages();

        foreach ($pages as $index => $page) {
            $text = $this->cleanText($page->getText());
            if (trim($text) === '') {
                continue;
            }

            $allPages[] = $this->parseSinglePage($text, $index + 1);
        }

        return $this->consolidatePages($allPages);
    }

    /**
     * Gabungkan halaman-halaman PDF menjadi daftar pesanan unik.
     *
     * Logika:
     *  1. Pisahkan halaman primer (punya resi) dan halaman continuation
     *     (tidak punya resi, hanya berisi info tambahan seperti Customer
     *     Message / Seller Note pada layout 2-page J&T Express).
     *  2. Dedupe halaman primer berdasarkan resi: kalau ada >1 halaman
     *     dengan resi yang sama, ambil yang paling lengkap (punya
     *     product_rows / buyer_name) sebagai primary, merge seller_note
     *     dari sisanya.
     *  3. Untuk tiap halaman continuation, cari primary dengan Order ID
     *     yang sama dan merge seller_note + customer_message + raw_text.
     *     Continuation tanpa primary yang cocok di-drop diam-diam.
     *
     * @param  array<int, array<string, mixed>>  $allPages
     * @return array<int, array<string, mixed>>
     */
    private function consolidatePages(array $allPages): array
    {
        $primaryByResi = [];
        $continuations = [];

        foreach ($allPages as $page) {
            if (! empty($page['resi_number'])) {
                $resi = strtoupper((string) $page['resi_number']);
                if (! isset($primaryByResi[$resi])) {
                    $primaryByResi[$resi] = $page;
                } else {
                    // Resi duplikat: pilih halaman yang paling lengkap sebagai
                    // primary, merge field tambahan dari yang lain.
                    $primaryByResi[$resi] = $this->mergePrimaryPages($primaryByResi[$resi], $page);
                }
                continue;
            }

            // Halaman tanpa resi — kandidat continuation.
            // Cuma simpan kalau ada Order ID atau seller_note / customer_message,
            // selain itu drop (halaman noise).
            $hasUseful = ! empty($page['tiktok_order_id'])
                || ! empty($page['seller_note'])
                || ! empty($page['customer_message']);
            if ($hasUseful) {
                $continuations[] = $page;
            }
        }

        // Merge continuation pages ke primary by Order ID.
        $primaryByOrderId = [];
        foreach ($primaryByResi as $resi => $primary) {
            $oid = (string) ($primary['tiktok_order_id'] ?? '');
            if ($oid !== '') {
                $primaryByOrderId[$oid] = &$primaryByResi[$resi];
            }
        }

        foreach ($continuations as $cont) {
            $oid = (string) ($cont['tiktok_order_id'] ?? '');
            if ($oid === '' || ! isset($primaryByOrderId[$oid])) {
                continue;
            }
            $this->mergeContinuationInto($primaryByOrderId[$oid], $cont);
        }
        unset($primaryByOrderId);

        // Output urutkan berdasarkan halaman primer-nya.
        $orders = array_values($primaryByResi);
        usort($orders, fn ($a, $b) => (int) ($a['page'] ?? 0) <=> (int) ($b['page'] ?? 0));

        return $orders;
    }

    /**
     * Gabung 2 halaman primer dengan resi yang sama. Pilih yang paling lengkap
     * sebagai dasar, isi field kosong dari yang lain.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function mergePrimaryPages(array $a, array $b): array
    {
        $scoreA = $this->pageCompletenessScore($a);
        $scoreB = $this->pageCompletenessScore($b);

        [$primary, $other] = $scoreB > $scoreA ? [$b, $a] : [$a, $b];

        foreach (['tiktok_order_id', 'buyer_name', 'buyer_phone', 'sender_name',
                  'shipping_address', 'weight', 'order_date', 'barang_keyword',
                  'courier'] as $key) {
            if (empty($primary[$key]) && ! empty($other[$key])) {
                $primary[$key] = $other[$key];
            }
        }

        if (empty($primary['product_rows']) && ! empty($other['product_rows'])) {
            $primary['product_rows'] = $other['product_rows'];
        }

        // Seller note: gabung kalau berbeda.
        $primary['seller_note'] = $this->joinDistinct($primary['seller_note'] ?? null, $other['seller_note'] ?? null);
        if (! empty($other['customer_message'] ?? null)) {
            $primary['customer_message'] = $this->joinDistinct(
                $primary['customer_message'] ?? null,
                $other['customer_message'] ?? null
            );
        }

        // Raw text di-append (untuk debug)
        if (! empty($other['raw_text'])) {
            $primary['raw_text'] = trim(($primary['raw_text'] ?? '')."\n\n--- halaman ".($other['page'] ?? '?')." ---\n".$other['raw_text']);
        }

        return $primary;
    }

    /**
     * Merge continuation page (tanpa resi) ke primary page by-reference.
     *
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $cont
     */
    private function mergeContinuationInto(array &$primary, array $cont): void
    {
        $primary['seller_note'] = $this->joinDistinct(
            $primary['seller_note'] ?? null,
            $cont['seller_note'] ?? null
        );
        if (! empty($cont['customer_message'] ?? null)) {
            $primary['customer_message'] = $this->joinDistinct(
                $primary['customer_message'] ?? null,
                $cont['customer_message'] ?? null
            );
        }

        // Append raw_text untuk debugging
        if (! empty($cont['raw_text'])) {
            $primary['raw_text'] = trim(($primary['raw_text'] ?? '')."\n\n--- halaman ".($cont['page'] ?? '?')." (lanjutan) ---\n".$cont['raw_text']);
        }

        // Kalau primary tidak ada seller_note tapi continuation punya
        // customer_message, taruh sebagai seller_note juga supaya combo
        // mapping yang baca seller_note bisa ketangkap.
        if (empty($primary['seller_note']) && ! empty($cont['customer_message'] ?? null)) {
            $primary['seller_note'] = $cont['customer_message'];
        }
    }

    private function pageCompletenessScore(array $p): int
    {
        $score = 0;
        if (! empty($p['product_rows'])) {
            $score += 5;
        }
        if (! empty($p['buyer_name'])) {
            $score += 2;
        }
        if (! empty($p['shipping_address'])) {
            $score += 2;
        }
        if (! empty($p['barang_keyword'])) {
            $score += 1;
        }
        if (! empty($p['seller_note'])) {
            $score += 1;
        }

        return $score;
    }

    private function joinDistinct(?string $a, ?string $b): ?string
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a === '' && $b === '') {
            return null;
        }
        if ($a === '') {
            return $b;
        }
        if ($b === '' || mb_stripos($a, $b) !== false) {
            return $a;
        }
        if (mb_stripos($b, $a) !== false) {
            return $b;
        }

        return $a.' | '.$b;
    }

    /**
     * Bersihkan separator yang nyangkut di nama hasil ekstraksi
     * "Penerima:" / "Pengirim:". Contoh kasus typical pada label SPX baru:
     *
     *   "Ranco Autoshop · 6282240057978"
     *     -> regex extract phone -> sisa "Ranco Autoshop ·"
     *     -> stripNameSeparators() -> "Ranco Autoshop"
     *
     * Dibuat tolerant terhadap bullet (·, •), pipe (|), dot/koma di ujung,
     * dash, colon — semua bisa muncul sebagai pemisah nama-vs-nomor pada
     * berbagai variasi layout label.
     */
    private function stripNameSeparators(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $cleaned = preg_replace('/^[\s\-:|·•,\.]+|[\s\-:|·•,\.]+$/u', '', $name);
        $cleaned = is_string($cleaned) ? trim($cleaned) : '';

        return $cleaned !== '' ? $cleaned : null;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Pertahankan gap 2+ spasi sebagai penanda KOLOM tabel produk Shopee
        // (Nama Produk | SKU | Variasi | Qty). smalot/pdfparser merender baris
        // tabel sebagai SATU baris fisik dengan kolom dipisah gap lebar (banyak
        // spasi); kolom yang kosong (mis. SKU) menyisakan gap. Meng-collapse
        // semua spasi ke 1 spasi menghapus batas kolom sehingga variasi (mis.
        // "Hitam polos") ikut tergabung ke nama produk. Maka: tab -> 2 spasi,
        // dan run 3+ spasi diringkas ke tepat 2 spasi (tetap delimiter kolom),
        // sedangkan 1-2 spasi dibiarkan apa adanya.
        $text = str_replace("\t", '  ', $text);
        $text = preg_replace('/ {3,}/', '  ', $text);
        $text = preg_replace('/ +\n/', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    /**
     * Deteksi marketplace:
     *  - 'shopee'    → ada 'SPXID' / 'Shopee' / 'SPX' / 'Shop Express',
     *                  ATAU keyword khas label Shopee: "No. Pesanan" /
     *                  "Batas Kirim" (Shopee non-SPX, mis. Anteraja/JNE/SiCepat)
     *  - 'tokopedia' → ada watermark 'tokopedia' (tanpa indikator Shopee)
     *  - 'tiktok'    → default (juga dipakai untuk label TikTok Shop yang
     *                  dikirim via J&T Cargo karena layout tabel produk sama)
     */
    private function detectMarketplace(string $text): string
    {
        // Shopee + SPX (paling kuat)
        if (preg_match('/\bSPXID\d+|\bShopee\b|\bSPX\b|Shop\s*Express/i', $text)) {
            return 'shopee';
        }

        // Shopee non-SPX (Anteraja / JNE / SiCepat / dll). Ciri khas:
        //   - "No. Pesanan: <kode>" — Shopee order ID label (TikTok pakai "Order Id",
        //     Tokopedia pakai "No. Resi"/"Invoice")
        //   - "Batas Kirim:" — Shopee delivery deadline (TikTok pakai "Ship")
        //   - Header tabel "Nama Produk ... Variasi" — khas Shopee Indonesia
        //     (TikTok pakai "Product Name ... Seller SKU")
        $shopeeMarkers = 0;
        if (preg_match('/No\.?\s*Pesanan\s*[:\-]/i', $text)) $shopeeMarkers++;
        if (preg_match('/Batas\s*Kirim\s*[:\-]/i', $text)) $shopeeMarkers++;
        if (preg_match('/Nama\s*Produk[\s\S]{0,30}Variasi/i', $text)) $shopeeMarkers++;
        if (preg_match('/^Pesan\s*[:\(]/im', $text)) $shopeeMarkers++;
        if ($shopeeMarkers >= 2) {
            return 'shopee';
        }

        // Tokopedia: hanya kalau ada watermark "tokopedia" tapi BUKAN TikTok Shop
        // (banyak label TikTok Shop juga mencantumkan tokopedia footer).
        $hasTokped = preg_match('/\btokopedia\b/i', $text) === 1;
        $hasTiktok = preg_match('/\btiktok\b|TT\s*Order\s*ID/i', $text) === 1;
        if ($hasTokped && ! $hasTiktok) {
            return 'tokopedia';
        }

        // Tokopedia/paxel (SAMEDAY): logo "tokopedia"/"paxel" pada label ini
        // umumnya berupa GAMBAR sehingga tidak ter-extract sebagai teks. Maka
        // deteksi lewat penanda struktural yang khas:
        //   - Resi prefix "TSPX-" (Tokopedia SPX)
        //   - Header alamat English "FROM(SENDER)" + "TO(ADDRESS)"
        //   - Service "SAMEDAY" / kurir "paxel" + "In transit by"
        $tokpedMarkers = 0;
        if (preg_match('/\bTSPX[-\s]?\d/i', $text)) $tokpedMarkers++;
        if (preg_match('/FROM\s*\(\s*SENDER\s*\)/i', $text)
            && preg_match('/TO\s*\(\s*ADDRESS\s*\)/i', $text)) $tokpedMarkers++;
        if (preg_match('/\bpaxel\b/i', $text)) $tokpedMarkers++;
        if (preg_match('/\bIn\s*transit\s*by\b/i', $text)) $tokpedMarkers++;
        if ($tokpedMarkers >= 2 && ! $hasTiktok) {
            return 'tokopedia';
        }

        return 'tiktok';
    }

    /**
     * @return array<string, mixed>
     */
    public function parseSinglePage(string $text, int $pageNumber = 1): array
    {
        $marketplace = $this->detectMarketplace($text);

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn ($l) => $l !== ''
        ));

        $fields = $this->walkLines($lines, $marketplace);

        return [
            'page' => $pageNumber,
            'marketplace' => $marketplace,
            'raw_text' => $text,
            'resi_number' => $this->extractResi($text, $marketplace),
            'tiktok_order_id' => $this->extractOrderId($text, $marketplace),
            'courier' => $this->extractCourier($text, $marketplace),
            'buyer_name' => $fields['buyer_name'],
            'buyer_phone' => $fields['buyer_phone'],
            'shipping_address' => $fields['shipping_address'],
            'weight' => $fields['weight'],
            'order_date' => $fields['order_date'],
            'barang_keyword' => $fields['barang_keyword'],
            'sender_name' => $fields['sender_name'],
            'product_rows' => $this->extractProductRows($text, $marketplace),
            'seller_note' => $this->extractSellerNote($text, $marketplace),
            'customer_message' => $this->extractCustomerMessage($text),
        ];
    }

    /**
     * Walk baris-per-baris, mengisi field ketika anchor ditemukan.
     *
     * @param array<int, string> $lines
     * @return array<string, ?string>
     */
    private function walkLines(array $lines, string $marketplace): array
    {
        // ----------------------------------------------------------------
        // J&T Cargo / Tokopedia layout pakai header "Pengirim" / "Penerima"
        // tanpa colon. Smalot/pdfparser sering meng-extract layout ini dalam
        // urutan KOLOM-MAJOR (kedua header dulu, baru semua content sender +
        // buyer berurutan), bukan baris-per-baris visual. Akibatnya
        // line-walker biasa ke-confuse dan content sender masuk ke field
        // buyer (atau sebaliknya).
        //
        // Solusinya: deteksi layout ini lewat pre-scan, lalu pakai parser
        // dedicated yang buffer SEMUA content antar header dan klasifikasi
        // tiap line by content (PHONE / NAMA / ALAMAT) + masking signal
        // (`*` umumnya menandakan field pembeli karena marketplace mask
        // data privacy buyer).
        // ----------------------------------------------------------------
        if ($marketplace !== 'shopee' && $this->isTokopediaPaxelLayout($lines)) {
            return $this->walkLinesTokopediaPaxel($lines, $marketplace);
        }

        if ($marketplace !== 'shopee' && $this->isJntCargoLayout($lines)) {
            return $this->walkLinesJntCargo($lines, $marketplace);
        }

        // Shopee non-SPX (ECO / Anteraja "PakEkoAja" / JNE / SiCepat) sering
        // ter-extract dalam urutan KACAU sehingga line-walker salah assign
        // buyer/sender. Coba resolver order-independent dulu; kalau yakin
        // (ketemu pembeli + alamat) pakai hasilnya, kalau tidak fallback ke
        // line-walker legacy di bawah.
        if ($marketplace === 'shopee') {
            $shopeeCols = $this->resolveShopeeColumns($lines);
            if ($shopeeCols !== null) {
                return $shopeeCols;
            }
        }

        $buyerName = null;
        $buyerPhone = null;
        $senderName = null;
        $addressParts = [];
        $weight = null;
        $orderDate = null;
        $barangKeyword = null;

        $mode = null; // 'address' aktif setelah "Penerima" sampai ketemu Weight/Berat/Jumlah/dll

        // Flag untuk layout J&T Cargo / Tokopedia di mana "Pengirim" / "Penerima"
        // muncul sebagai HEADER tanpa colon. Sebagian besar kasus ditangani
        // oleh walkLinesJntCargo() di atas, tapi flag ini tetap dipertahankan
        // sebagai fallback jika hanya satu header (Pengirim atau Penerima)
        // yang muncul tanpa colon — misalnya layout label parsial / non-standar.
        $awaitingSenderName = false;
        $awaitingBuyerName = false;

        // Anchor yang dianggap "akhir blok pengirim/penerima" — kalau muncul
        // saat sedang awaiting name, batalkan capture supaya tidak salah ambil.
        $headerEndAnchors = '/^(Weight|Berat|Ship|Batas\s*Kirim|COD|Print\s*Time|TT\s*Order\s*ID|Order\s*Id|In\s*transit|Product\s*Name|Qty\s*Total|Seller\s*Note|Jumlah|Pengirim|Penerima)\b/i';

        $breakAnchors = $marketplace === 'shopee'
            ? '/^(Berat|Batas\s*Kirim|No\.?\s*Pesanan|COD|Nama\s*Produk|Pesan|Order\s*ID|#\s*Nama)\b/i'
            : '/^(Pengirim|Weight|Ship|Jumlah|JL\s*\.|Order\s*Id|In transit|Product\s*Name|Qty\s*Total|Seller\s*Note|Syarat\s+dan\s+ketentuan)\b/i';

        foreach ($lines as $line) {
            // ----------------------------------------------------------------
            // (J&T Cargo / Tokopedia layout) Capture nama setelah header
            // "Pengirim" / "Penerima" tanpa colon. Jalan duluan supaya tidak
            // ke-overshadow oleh break-anchor regex di bawah.
            // ----------------------------------------------------------------
            if ($awaitingSenderName || $awaitingBuyerName) {
                // Phone-only line: ambil sebagai phone (kalau buyer & belum ada),
                // tapi tetap menunggu name di line berikutnya.
                if (preg_match('/^(\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15})$/', $line)) {
                    if ($awaitingBuyerName && ! $buyerPhone) {
                        $buyerPhone = trim($line);
                    }
                    continue;
                }

                // Hit header berikutnya / akhir blok → batalkan await tanpa capture.
                if (preg_match($headerEndAnchors, $line)) {
                    $awaitingSenderName = false;
                    $awaitingBuyerName = false;
                    // Jangan continue — lanjutkan proses normal untuk line ini
                    // (mungkin ini "Penerima ..." atau "Weight :..." dll).
                } else {
                    // Line ini = nama. Strip phone yang mungkin nyangkut.
                    $rest = trim($line);
                    if (preg_match('/(\(\+?62\)[\d\*\-\s]{5,30})/', $rest, $mm)) {
                        if ($awaitingBuyerName && ! $buyerPhone) {
                            $buyerPhone = trim($mm[1]);
                        }
                        $rest = trim(str_replace($mm[0], '', $rest));
                    } elseif (preg_match('/(\b\d{10,15}\b)/', $rest, $mm)) {
                        if ($awaitingBuyerName && ! $buyerPhone) {
                            $buyerPhone = trim($mm[1]);
                        }
                        $rest = trim(str_replace($mm[0], '', $rest));
                    }
                    $cleanedName = $this->stripNameSeparators($rest);

                    if ($awaitingSenderName) {
                        $senderName = $cleanedName;
                        $awaitingSenderName = false;
                        // Sender's address tidak disimpan; mode tetap null.
                    } else { // $awaitingBuyerName
                        $buyerName = $cleanedName;
                        $awaitingBuyerName = false;
                        $mode = 'address';
                    }
                    continue;
                }
            }

            // ----------------------------------------------------------------
            // (J&T Cargo / Tokopedia) "Pengirim <phone>" atau "Pengirim" alone
            // — header tanpa colon, nama ada di line berikutnya. Khusus
            // marketplace non-shopee karena Shopee punya layout dedicated di
            // bawah. Cek SEBELUM colon-regex supaya tidak konflik (regex ini
            // hanya match kalau TIDAK ada colon/dash).
            // ----------------------------------------------------------------
            if ($marketplace !== 'shopee'
                && preg_match('/^Pengirim(?:\s+(\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))?\s*$/i', $line)) {
                // Phone pengirim tidak disimpan (kita tidak track sender_phone).
                $awaitingSenderName = true;
                $mode = null;
                continue;
            }
            if ($marketplace !== 'shopee'
                && preg_match('/^Penerima(?:\s+(\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))?\s*$/i', $line, $m)) {
                if (! empty($m[1] ?? null)) {
                    $buyerPhone = trim($m[1]);
                }
                $awaitingBuyerName = true;
                $mode = null;
                continue;
            }

            // --- Shopee sering punya "Penerima: X Pengirim: Y" di satu baris
            //     (atau terbalik: "Pengirim: Y Penerima: X" tergantung cara
            //     PDF extractor membaca layout 2-kolom).
            //     Tangani keduanya sekaligus kalau ketemu pola ini.
            if ($marketplace === 'shopee'
                && preg_match('/^Penerima\s*[:\-]\s*(.*?)\s+Pengirim\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $buyerName = trim($m[1]) ?: null;
                // Strip nomor HP dari sender_name jika muncul setelah nama pengirim
                $senderRaw = trim($m[2]);
                if (preg_match('/^(.+?)\s+(\d{10,15})$/', $senderRaw, $sp)) {
                    $senderName = trim($sp[1]) ?: null;
                } else {
                    $senderName = $senderRaw ?: null;
                }
                $buyerName = $this->stripNameSeparators($buyerName);
                $senderName = $this->stripNameSeparators($senderName);
                $mode = 'address';
                continue;
            }

            // --- Shopee: urutan terbalik "Pengirim: Y Penerima: X" (PDF extractor
            //     membaca kolom kanan duluan pada layout 2-kolom label SPX).
            if ($marketplace === 'shopee'
                && preg_match('/^Pengirim\s*[:\-]\s*(.*?)\s+Penerima\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $senderRaw = trim($m[1]);
                if (preg_match('/^(.+?)\s+(\d{10,15})$/', $senderRaw, $sp)) {
                    $senderName = trim($sp[1]) ?: null;
                } else {
                    $senderName = $senderRaw ?: null;
                }
                $buyerName = trim($m[2]) ?: null;
                $buyerName = $this->stripNameSeparators($buyerName);
                $senderName = $this->stripNameSeparators($senderName);
                $mode = 'address';
                continue;
            }

            // --- Pengirim: nama + HP
            if (preg_match('/^Pengirim\s*[:\-]\s*(.*)$/i', $line, $m)) {
                $rest = trim($m[1]);
                if (preg_match('/(\(\+?62\)[\d\*\-\s]{5,30})/', $rest, $mm)) {
                    $senderName = trim(str_replace($mm[0], '', $rest)) ?: null;
                } elseif (preg_match('/(\b\d{10,15}\b)/', $rest, $mm)) {
                    $senderName = trim(str_replace($mm[0], '', $rest)) ?: null;
                } else {
                    $senderName = $rest ?: null;
                }
                $senderName = $this->stripNameSeparators($senderName);
                continue;
            }

            // --- Penerima: nama + HP
            if (preg_match('/^Penerima\s*[:\-]\s*(.*)$/i', $line, $m)) {
                $rest = trim($m[1]);
                if (preg_match('/(\(\+?62\)[\d\*\-\s]{5,30})/', $rest, $mm)) {
                    $buyerPhone = trim($mm[1]);
                    $buyerName = trim(str_replace($mm[0], '', $rest)) ?: null;
                } elseif (preg_match('/(\b\d{10,15}\b)/', $rest, $mm)) {
                    $buyerPhone = trim($mm[1]);
                    $buyerName = trim(str_replace($mm[0], '', $rest)) ?: null;
                } else {
                    $buyerName = $rest ?: null;
                }
                $buyerName = $this->stripNameSeparators($buyerName);
                $mode = 'address';
                continue;
            }

            // --- Weight / Berat (+ optional Ship/Batas Kirim di baris yang sama)
            if (preg_match('/(?:Weight|Berat)\s*[:\-]\s*([\d\.,]+\s*(?:KG|kg|gr|g))/i', $line, $m)) {
                $weight = trim($m[1]);
                if (preg_match('/(?:Ship|Batas\s*Kirim)\s*[:\-]\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i', $line, $m2)) {
                    $orderDate = $this->normalizeDate($m2[1]);
                }
                $mode = null;
                continue;
            }

            // --- Ship / Batas Kirim standalone
            if (preg_match('/^(?:Ship|Batas\s*Kirim)\s*[:\-]\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i', $line, $m)) {
                $orderDate = $this->normalizeDate($m[1]);
                $mode = null;
                continue;
            }

            // --- Jumlah : Npcs, Barang : <KEYWORD>  (TikTok only)
            if ($marketplace === 'tiktok' && preg_match('/Barang\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $barangKeyword = trim($m[1]);
                $mode = null;
                continue;
            }

            // --- Anchor yang meng-cancel mode "address"
            if (preg_match($breakAnchors, $line)) {
                $mode = null;
                continue;
            }

            // --- Baris alamat
            if ($mode === 'address') {
                // Skip HP yang muncul solo di bawah "Penerima: Nama"
                //   Kalau belum ada buyer_phone & baris ini pure digit 10-15, anggap HP.
                if (! $buyerPhone && preg_match('/^\d{10,15}$/', $line)) {
                    $buyerPhone = $line;
                    continue;
                }

                if (preg_match('/^(J[XPY]\d{8,}|SPXID\d{8,})$/i', $line)) {
                    continue; // nomor resi
                }
                if (preg_match('/^\d{3}-[A-Z0-9]{2,}-\d+[A-Z]?$/i', $line)) {
                    continue; // kode sortir TikTok
                }
                if (preg_match('/^LOP[- ]?[A-Z]?[- ]?\d+$/i', $line)) {
                    continue; // kode sortir Shopee (LOP-C-06)
                }
                if (preg_match('/^\d{1,4}$/', $line)) {
                    continue; // angka kode
                }
                if (preg_match('/^V\s*[-]\s*\d+/', $line)) {
                    continue; // "V - 2" (nomor handle Shopee)
                }
                if (preg_match('/^COD$/i', $line)) {
                    continue;
                }
                // Skip baris noise dari template label Shopee
                if (preg_match('/^(CASHLESS|Penjual\s+tidak\s+perlu|tidak\s+perlu\s+bayar|bayar\s+ongkir|Kurir\s*$)/i', $line)) {
                    continue;
                }

                $addressParts[] = $line;
                if (count($addressParts) >= 4) {
                    $mode = null;
                }
                continue;
            }
        }

        // --- Shopee fallback: PDF parser sering salah assign kolom kiri-kanan
        //     pada label SPX. Beberapa skenario yang ditangani:
        //       a) sender_name null tapi "Pengirim:" ada di teks → extract sender
        //       b) buyer_name = sender_name (exact match) → null buyer
        //       c) buyer_name mengandung "Pengirim:" prefix → strip prefix
        //       d) buyer_name terlihat seperti nama toko (Shop/Store/Autoshop/dll)
        //          + tidak ada Pengirim valid → swap: ini sebenarnya sender
        //       e) sender_name berisi noise dari template label (CASHLESS,
        //          "Penjual tidak perlu bayar ongkir", dll) → null sender
        //       f) buyer_name null, tapi address_parts pertama tampak seperti
        //          nama orang → ambil sebagai buyer (kasus PDF parser interleave
        //          kolom: nama Penerima sebenarnya muncul di awal blok alamat)
        if ($marketplace === 'shopee') {
            $fullText = implode("\n", $lines);

            // (e) Reject sender_name yang berisi noise dari template label.
            //     Pengirim regex bisa nyangkut text bukan nama (mis. "Penjual
            //     tidak perlu bayar ongkir ke Kurir", "CASHLESS", etc.) ketika
            //     PDF parser merge kolom secara salah.
            //
            //     Untuk SPX layout BARU, "Pengirim" adalah column header dan
            //     value-nya sering tergabung dengan field berikutnya
            //     ("No.Pesanan: XXX", "Resi", "Order ID", "Product Name",
            //     "SKU", "Seller Note") karena PDF text extractor merge
            //     vertical-adjacent strings. Filter ini menolak hasil tsb
            //     sehingga heuristik (d) bisa fire untuk swap buyer→sender
            //     (sama seperti penanganan kasus Anteraja Mama Fahri).
            $senderNoiseRegex = '/Penjual|bayar\s*ongkir|tidak\s*perlu|^CASHLESS$|^Kurir$|Kurir\s*CASHLESS|tidak\s*ada\s*biaya|^No\.?\s*Pesanan\b|^Resi\b|^Order\s*ID\b|^Product\s*Name\b|^SKU\b|^Seller\s*Note\b|^Berat\b|^Batas\s*Kirim\b/i';
            if ($senderName !== null && preg_match($senderNoiseRegex, $senderName)) {
                $senderName = null;
            }

            // (c) Strip "Pengirim:" prefix dari buyer_name jika nyangkut
            if ($buyerName !== null && preg_match('/^Pengirim\s*[:\-]\s*(.+)$/i', $buyerName, $cm)) {
                $buyerName = trim($cm[1]) ?: null;
            }

            // (a) Coba extract sender dari "Pengirim:" di teks jika belum ada.
            //     Iterasi semua match — pilih yang BUKAN noise (Penjual/CASHLESS/dll).
            if ($senderName === null && preg_match_all('/Pengirim\s*[:\-]\s*([^\n]+)/i', $fullText, $allFm)) {
                foreach ($allFm[1] as $rawSender) {
                    $senderRaw = trim($rawSender);
                    $senderRaw = preg_replace('/\s*Penerima\s*[:\-].*$/i', '', $senderRaw);
                    $senderRaw = trim($senderRaw);
                    if ($senderRaw === '' || preg_match($senderNoiseRegex, $senderRaw)) {
                        continue;
                    }
                    if (preg_match('/^(.+?)\s+(\d{10,15})$/', $senderRaw, $sp)) {
                        $candidateSender = trim($sp[1]) ?: null;
                    } elseif (preg_match('/(\b\d{10,15}\b)/', $senderRaw, $sp)) {
                        $candidateSender = trim(str_replace($sp[0], '', $senderRaw)) ?: null;
                    } else {
                        $candidateSender = $senderRaw ?: null;
                    }
                    // Validate again after stripping phone
                    if ($candidateSender !== null && ! preg_match($senderNoiseRegex, $candidateSender)) {
                        $senderName = $candidateSender;
                        break;
                    }
                }
            }

            // (b) Jika buyer_name == sender_name (corruption), null buyer
            if ($buyerName !== null && $senderName !== null
                && mb_strtolower($buyerName) === mb_strtolower($senderName)) {
                // Cari semua occurrence "Penerima:" di teks — mungkin ada yang
                // punya nama berbeda dari sender (kasus jarang, multi-resi).
                $foundBuyer = null;
                $foundBuyerPhone = null;
                if (preg_match_all('/Penerima\s*[:\-]\s*([^\n]+)/i', $fullText, $allPm)) {
                    foreach ($allPm[1] as $penerimaLine) {
                        $penerimaLine = trim($penerimaLine);
                        $penerimaLine = preg_replace('/\s*Pengirim\s*[:\-].*$/i', '', $penerimaLine);
                        $penerimaLine = trim($penerimaLine);
                        if (preg_match('/^(.+?)\s+(\d{10,15})$/', $penerimaLine, $pp)) {
                            $candidate = trim($pp[1]);
                            if ($candidate !== '' && mb_strtolower($candidate) !== mb_strtolower($senderName)) {
                                $foundBuyer = $candidate;
                                $foundBuyerPhone = trim($pp[2]);
                                break;
                            }
                        } elseif ($penerimaLine !== '' && mb_strtolower($penerimaLine) !== mb_strtolower($senderName)) {
                            $foundBuyer = $penerimaLine;
                            break;
                        }
                    }
                }
                $buyerName = $foundBuyer; // null jika tidak ketemu — lebih baik kosong daripada salah
                // Phone yang tertangkap sebelumnya adalah phone sender (yang
                // tercampur ke baris Penerima). Reset kalau buyer baru tidak
                // ada nomor barunya.
                $buyerPhone = $foundBuyerPhone;
            }

            // (d) Heuristik: buyer_name terlihat seperti nama toko/bisnis tapi
            //     sender_name kosong → kemungkinan PDF parser salah assign kolom.
            //     Pada label SPX Shopee (baik layout LAMA 2-kolom maupun layout
            //     BARU dengan English headers), kolom Pengirim berisi nama toko
            //     dan nomor HP format internasional (62...). Kalau buyer_phone
            //     adalah format internasional 13 digit & buyer_name "shop-like",
            //     swap ke sender.
            //
            //     Catatan: untuk SPX baru, sender_name yang "ter-extract" dari
            //     baris "Pengirim: No.Pesanan: ..." sudah di-null-kan di (e)
            //     karena cocok dengan template-label noise regex. Jadi
            //     guard `$senderName === null` di sini tetap aman.
            if ($senderName === null && $buyerName !== null) {
                $shopKeywords = '/\b(Shop|Store|Autoshop|Auto[- ]?shop|Toko|Official|Online|Mart|Shopee|Tokped|Tiktok|Olshop)\b/i';
                $isShopLike = preg_match($shopKeywords, $buyerName) === 1;
                $isIntlPhone = $buyerPhone !== null
                    && preg_match('/^62\d{9,13}$/', $buyerPhone) === 1;

                if ($isShopLike || $isIntlPhone) {
                    // Swap: data ini sebenarnya milik pengirim
                    $senderName = $buyerName;
                    $buyerName = null;
                    // Phone internasional juga milik sender, bukan buyer
                    if ($isIntlPhone) {
                        $buyerPhone = null;
                    }
                }
            }

            // (f) Kalau buyer_name masih null tapi address_parts pertama tampak
            //     seperti nama orang (1-4 kata, tanpa keyword alamat) → ambil
            //     sebagai buyer. Kasus typical: PDF parser interleave kolom
            //     2-kolom sehingga "Penerima: <name>" yang seharusnya di kolom
            //     kiri ter-extract sebagai bagian dari address block.
            if ($buyerName === null && ! empty($addressParts)) {
                $addressKeywords = '/\b(Jl\.?|Jalan|RT|RW|Gang|Gg\.?|Komplek|Perumahan|Blok|No\.?|Kel\.?|Kec\.?|Kota|Kabupaten|KAB|RT\.|RW\.|Dusun|Desa|Kelurahan|Kecamatan|BANTEN|JAWA|SUMATERA|SULAWESI|KALIMANTAN|PAPUA|BALI|NTT|NTB)\b|[,]/i';
                $foundIdx = null;
                foreach ($addressParts as $idx => $part) {
                    $part = trim($part);
                    if ($part === '') continue;
                    // Bukan all-caps (city/region biasanya all caps)
                    if ($part === mb_strtoupper($part)) continue;
                    // Bukan address keywords
                    if (preg_match($addressKeywords, $part)) continue;
                    // Bukan noise
                    if (preg_match($senderNoiseRegex, $part)) continue;
                    // Length & word count check
                    if (mb_strlen($part) > 35) continue;
                    if (substr_count($part, ' ') > 3) continue;
                    $foundIdx = $idx;
                    break;
                }
                if ($foundIdx !== null) {
                    $buyerName = trim($addressParts[$foundIdx]);
                    array_splice($addressParts, $foundIdx, 1);
                }
            }

            // (f2) Fallback: kalau (f) tidak ketemu standalone person-name part,
            //      coba split TOKEN PERTAMA dari addressParts[0]. Kasus typical
            //      SPX: address ditulis sebagai satu line "Ruslan, Jalan Trans
            //      Sulawesi, Dusun ..., KAB. LUWU UTARA, ..." — nama orang
            //      ke-prefix di address tapi tidak di line tersendiri.
            //
            //      Aman karena hanya fire kalau: (a) buyer masih null, (b)
            //      first part diawali oleh kata berhuruf kapital pendek (1-3
            //      kata) yang BUKAN address keyword, (c) ada koma sebagai
            //      pemisah dari address sungguhan.
            if ($buyerName === null && ! empty($addressParts)) {
                $first = trim((string) $addressParts[0]);
                if ($first !== '' && preg_match('/^([A-Z][\p{L}\.\-\']{1,20}(?:\s+[A-Z][\p{L}\.\-\']{1,20}){0,2})\s*,\s*(.+)$/u', $first, $sm)) {
                    $candName = trim($sm[1]);
                    $rest = trim($sm[2]);
                    $addressKeywordsHead = '/\b(Jl\.?|Jalan|RT|RW|Gang|Gg\.?|Komplek|Perumahan|Blok|No\.?|Kel\.?|Kec\.?|Kota|Kabupaten|Dusun|Desa|Kelurahan|Kecamatan)\b/i';
                    $isCapsOnly = $candName === mb_strtoupper($candName);
                    $isNoise = preg_match($senderNoiseRegex, $candName) === 1;
                    $hasAddrKeyword = preg_match($addressKeywordsHead, $candName) === 1;
                    if (! $isCapsOnly && ! $isNoise && ! $hasAddrKeyword && mb_strlen($candName) >= 3) {
                        $buyerName = $candName;
                        $addressParts[0] = $rest;
                    }
                }
            }

            // (g) Bersihkan address_parts dari noise yang bocor dari kolom
            //     pengirim (city all-caps yang BUKAN bagian alamat penerima).
            //     Khusus untuk address part PERTAMA yang berupa "KOTA XXX" saja
            //     (single-word city tanpa kontext alamat) — itu kemungkinan
            //     city pengirim, bukan kota penerima. Skip kalau berikutnya
            //     ada address part yang lebih natural.
            if (! empty($addressParts) && count($addressParts) >= 2) {
                $first = trim($addressParts[0]);
                // First part = "KOTA <NAME>" all-caps + 1-3 kata, dan
                // address selanjutnya tidak mengandung KOTA <NAME> yang sama
                // (artinya kota itu bukan kota penerima).
                if (preg_match('/^KOTA\s+[A-Z\s]+$/u', $first) === 1
                    && ! preg_match('/'.preg_quote($first, '/').'/i', implode(' ', array_slice($addressParts, 1)))) {
                    array_shift($addressParts);
                }
            }
        }

        // Koreksi marketplace-anon untuk jalur legacy line-walker (sama seperti
        // di resolveShopeeColumns): nama marketplace = penerima anonim, bukan
        // pengirim. Tukar kalau sender-nya nama marketplace tapi buyer bukan.
        if ($marketplace === 'shopee' && $senderName !== null && $senderName !== ''
            && preg_match('/\b(Shopee|Tokopedia|Tik\s*Tok|Tiktok|Lazada|Blibli|Bukalapak)\b/i', $senderName)
            && ($buyerName === null || $buyerName === ''
                || ! preg_match('/\b(Shopee|Tokopedia|Tik\s*Tok|Tiktok|Lazada|Blibli|Bukalapak)\b/i', $buyerName))) {
            $tmp = $buyerName;
            $buyerName = $senderName;
            $senderName = $tmp;
        }

        return [
            'buyer_name' => $buyerName,
            'buyer_phone' => $buyerPhone,
            'sender_name' => $senderName,
            'shipping_address' => $addressParts ? implode(', ', $addressParts) : null,
            'weight' => $weight,
            'order_date' => $orderDate,
            'barang_keyword' => $barangKeyword,
        ];
    }

    /**
     * Resolver kolom Shopee yang ORDER-INDEPENDENT.
     *
     * Label Shopee non-SPX (ECO / Anteraja "PakEkoAja" dll) sering ter-extract
     * oleh PDF parser (smalot/pdfparser) dalam urutan yang KACAU: label
     * "Penerima:" bisa ter-pasang ke nama PENGIRIM, nomor HP seller bocor ke
     * field pembeli, dan blok alamat terpotong/menyatu. Akibatnya line-walker
     * biasa salah assign buyer/sender tergantung urutan extraction.
     *
     * Strategi anti-kacau (tidak bergantung urutan baris):
     *   - PEMBELI = nama orang yang menempel tepat di atas BLOK ALAMAT tujuan
     *     (RT/RW, Jl, Desa, dst.) — penerima selalu berdampingan dgn alamatnya.
     *   - PENGIRIM = nama lain (toko/seller), biasanya berdampingan dgn nomor
     *     HP format internasional 62... dan/atau kota asal.
     *   - Nomor HP 62... (internasional) milik PENGIRIM, bukan pembeli.
     *
     * Return null kalau tidak yakin (tidak ketemu blok alamat + nama) supaya
     * fallback ke line-walker legacy.
     *
     * @param array<int, string> $lines
     * @return array<string, ?string>|null
     */
    private function resolveShopeeColumns(array $lines): ?array
    {
        $lines = array_values(array_filter(
            array_map('trim', $lines),
            fn ($l) => $l !== ''
        ));
        if (empty($lines)) {
            return null;
        }

        $addrBody = 'Jl\.?|Jalan|Gg\.?|Gang|RT|RW|Blok|Komplek|Perumahan|No\.?|Kel\.?|Kelurahan|Kec\.?|Kecamatan|Kota|Kabupaten|Kab\.?|Desa|Dusun|Kp\.?|Kampung|BANTEN|JAWA|SUMATERA|SUMATRA|SULAWESI|KALIMANTAN|PAPUA|BALI|ACEH|RIAU|LAMPUNG|JAMBI|BENGKULU|GORONTALO|MALUKU|NTT|NTB|DKI|JAKARTA|BOGOR|SERANG';
        $addrAny = '/\b(?:'.$addrBody.')\b/iu';
        $addrHead = '/^(?:'.$addrBody.')\b/iu';
        $noise = '/^(?:CASHLESS|COD|HOME|ECO|Penjual|tidak\s*perlu|bayar\s*ongkir|Kurir|No\.?\s*Resi|No\.?\s*Pesanan|Batas\s*Kirim|Berat|Nama\s*Produk|#|Pesan|Order\s*ID|SKU|Variasi|Qty|Seller|Print|Penerima|Pengirim)\b/iu';
        $street = '/\b(?:Jl\.?|Jalan|RT|RW|Desa|Dusun|Kp\.?|Blok|No\.?|Gg\.?)\b/iu';

        $isPhone = function (string $s): bool {
            $s = trim($s);
            return preg_match('/^\(?\+?62\)?[\d\*\-\s]{7,15}$/', $s) === 1
                || preg_match('/^0\d{9,13}$/', $s) === 1
                || preg_match('/^\d{10,15}$/', $s) === 1;
        };
        $isIntl = function (string $p): bool {
            $d = (string) preg_replace('/\D/', '', $p);
            return strncmp($d, '62', 2) === 0 && strlen($d) >= 11;
        };
        $hasLower = fn (string $s): bool => preg_match('/\p{Ll}/u', $s) === 1;
        $isWeighty = fn (string $s): bool => preg_match('/\d\s*(?:KG|kg|gr|g|gram)\b/iu', $s) === 1;

        $isAddr = function (string $l) use ($addrAny): bool {
            $s = trim($l);
            if (preg_match($addrAny, $s)) {
                return true;
            }
            if (mb_strpos($s, ',') !== false && mb_strlen($s) > 6) {
                return true;
            }
            if ($s === mb_strtoupper($s) && mb_strlen($s) >= 4
                && preg_match('/[A-Z]/', $s) === 1 && preg_match('/\d/', $s) === 0) {
                return true;
            }
            return false;
        };
        $isName = function (string $s) use ($noise, $addrHead, $isPhone, $hasLower, $isWeighty): bool {
            $s = trim($s);
            if (! $hasLower($s)) {
                return false;
            }
            // Nama orang/toko tidak mengandung koma (baris ber-koma umumnya
            // potongan alamat, mis. "Kalideres, KALIDERES, KOTA JAKARTA").
            if (mb_strpos($s, ',') !== false) {
                return false;
            }
            // Baris resi/barcode (mis. "Resi:SPXID061415271137") jangan dianggap
            // nama — kalau lolos, ia bisa salah dipilih sebagai pengirim.
            if (preg_match('/SPXID\d+/i', $s) === 1 || preg_match('/^Resi\b/i', $s) === 1) {
                return false;
            }
            if (preg_match($noise, $s)) {
                return false;
            }
            if ($isPhone($s) || $isWeighty($s)) {
                return false;
            }
            if (preg_match('/^\d/', $s)) {
                return false;
            }
            $w = preg_split('/\s+/', $s);
            if (count($w) < 1 || count($w) > 4) {
                return false;
            }
            // Tolak FRAGMEN TABEL PRODUK (variasi + qty), mis. "R14 Silver 1"
            // atau "R15 datar silver". Ini BUKAN nama orang: umumnya diawali
            // kode varian (R14/R15/M8) dan/atau diakhiri angka qty (" 1").
            // Tanpa guard ini, teks variasi produk yang ke-interleave ke blok
            // alamat bisa salah dipilih sebagai nama Penerima (bug: penerima
            // jadi "R14 Silver 1").
            if (preg_match('/\s\d{1,3}$/', $s)
                || preg_match('/^[A-Za-z]{1,3}\d{1,2}$/', (string) ($w[0] ?? ''))) {
                return false;
            }
            if (mb_strlen($s) > 35) {
                return false;
            }
            if (preg_match($addrHead, $s)) {
                return false;
            }
            if (preg_match('/[A-Za-z]{2,}/', $s) === 0) {
                return false;
            }
            return true;
        };
        $nameFromHeader = function (string $v) use ($isName): ?string {
            $v = (string) preg_replace('/\(?\+?62\)?[\d\*\-\s]{7,}/', '', $v);
            $v = (string) preg_replace('/\b\d{10,15}\b/', '', $v);
            $v = trim($v, " \t\n\r\0\x0B,");
            return ($v !== '' && $isName($v)) ? $v : null;
        };

        $buyer = null;
        $sender = null;
        $phone = null;
        $weight = null;
        $orderDate = null;
        $cands = [];

        foreach ($lines as $line) {
            if (preg_match('/Penerima\s*[:\-]\s*(.*?)\s*Pengirim\s*[:\-]\s*(.+)$/iu', $line, $m)
                && trim($m[1]) !== '' && trim($m[2]) !== '') {
                $buyer = $buyer ?? trim($m[1]);
                $sender = $sender ?? trim((string) preg_replace('/\s*\d{10,15}$/', '', trim($m[2])));
                continue;
            }
            if (preg_match('/Pengirim\s*[:\-]\s*(.*?)\s*Penerima\s*[:\-]\s*(.+)$/iu', $line, $m)
                && trim($m[1]) !== '' && trim($m[2]) !== '') {
                $sender = $sender ?? trim((string) preg_replace('/\s*\d{10,15}$/', '', trim($m[1])));
                $buyer = $buyer ?? trim($m[2]);
                continue;
            }
            if (preg_match('/^(?:Penerima|Pengirim)\s*[:\-]\s*(.+)$/iu', $line, $m)) {
                $nm = $nameFromHeader($m[1]);
                if ($nm !== null) {
                    $cands[] = $nm;
                }
                continue;
            }
            if ($isName($line)) {
                $cands[] = trim($line);
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/(\d[\d\.,]*\s*(?:KG|kg|gr|g|gram))\b/iu', $line, $m)
                && (preg_match('/Berat/iu', $line)
                    || preg_match('/^\d[\d\.,]*\s*(?:KG|kg|gr|g)$/iu', trim($line)))) {
                $weight = trim($m[1]);
                break;
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/(?:Batas\s*Kirim|Ship)\s*[:\-]?\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/iu', $line, $m)) {
                $orderDate = $this->normalizeDate($m[1]);
                break;
            }
        }

        $addrIdx = [];
        foreach ($lines as $i => $line) {
            if ($isAddr($line) && preg_match($noise, $line) === 0 && ! $isName($line)) {
                $addrIdx[] = $i;
            }
        }
        $blocks = [];
        $cur = [];
        foreach ($addrIdx as $i) {
            if (! empty($cur) && $i === end($cur) + 1) {
                $cur[] = $i;
            } else {
                if (! empty($cur)) {
                    $blocks[] = $cur;
                }
                $cur = [$i];
            }
        }
        if (! empty($cur)) {
            $blocks[] = $cur;
        }
        $streetBlocks = array_values(array_filter($blocks, function ($b) use ($lines, $street) {
            foreach ($b as $i) {
                if (preg_match($street, $lines[$i])) {
                    return true;
                }
            }
            return false;
        }));
        if (! empty($streetBlocks)) {
            $blocks = $streetBlocks;
        }
        $addrBlock = [];
        $bestLen = -1;
        foreach ($blocks as $b) {
            $len = 0;
            foreach ($b as $i) {
                $len += mb_strlen($lines[$i]);
            }
            if ($len > $bestLen) {
                $bestLen = $len;
                $addrBlock = $b;
            }
        }

        $address = null;
        if (! empty($addrBlock)) {
            $parts = [];
            foreach ($addrBlock as $i) {
                $seg = trim((string) preg_replace('/\s+KOTA\s+[A-Z]+$/u', '', $lines[$i]));
                $parts[] = $seg;
            }
            while (count($parts) > 1) {
                $last = $parts[count($parts) - 1];
                $prior = mb_strtoupper(implode(' ', array_slice($parts, 0, -1)));
                preg_match_all('/[A-Za-z]+/u', $last, $wmatch);
                $words = array_values(array_filter($wmatch[0], fn ($w) => mb_strlen($w) > 2));
                $allSeen = ! empty($words);
                foreach ($words as $w) {
                    if (mb_strpos($prior, mb_strtoupper($w)) === false) {
                        $allSeen = false;
                        break;
                    }
                }
                if ($last === mb_strtoupper($last) && $allSeen) {
                    array_pop($parts);
                } else {
                    break;
                }
            }
            $address = implode(', ', $parts);
            $address = (string) preg_replace('/,\s*,/', ', ', $address);
            $address = (string) preg_replace('/\s+,/', ',', $address);
            $address = trim((string) preg_replace('/\s{2,}/', ' ', $address));
        }

        // Nama pembeli selalu menempel pada blok alamat tujuan. Pada teks yang
        // ter-scramble (2 kolom), barcode/resi (SPXID...) menandai batas kolom,
        // jadi berhenti di situ — jangan lompati untuk mengambil nama pengirim
        // (mis. "Ranco Autoshop") yang berada di kolom lain di atas barcode.
        // Cari ke atas dari awal blok dulu; kalau terhalang barcode, cari ke
        // bawah dari akhir blok.
        $isBarcodeBoundary = function (string $s): bool {
            return preg_match('/SPXID\d+/i', $s) === 1
                || preg_match('/No\.?\s*Resi/i', $s) === 1
                || preg_match('/^Resi\s*[:\-]/i', $s) === 1;
        };
        if (($buyer === null || $buyer === '') && ! empty($addrBlock)) {
            for ($j = $addrBlock[0] - 1; $j >= 0; $j--) {
                if ($isBarcodeBoundary($lines[$j])) {
                    break;
                }
                if ($isName($lines[$j])) {
                    $buyer = trim($lines[$j]);
                    break;
                }
                if ($isPhone($lines[$j]) || $isAddr($lines[$j])) {
                    continue;
                }
            }
        }
        if (($buyer === null || $buyer === '') && ! empty($addrBlock)) {
            $end = (int) end($addrBlock);
            $nLines = count($lines);
            for ($j = $end + 1; $j < $nLines; $j++) {
                if ($isBarcodeBoundary($lines[$j])) {
                    break;
                }
                if ($isName($lines[$j])) {
                    $buyer = trim($lines[$j]);
                    break;
                }
                if ($isPhone($lines[$j]) || $isAddr($lines[$j])) {
                    continue;
                }
                if (preg_match('/Berat|Batas\s*Kirim|COD|Nama\s*Produk|^#|^Pesan/iu', $lines[$j])) {
                    continue;
                }
            }
        }

        if ($sender === null || $sender === '') {
            $n = count($lines);
            foreach ($lines as $i => $line) {
                if ($isName($line) && trim($line) !== $buyer) {
                    $near = false;
                    foreach ([$i - 1, $i + 1] as $k) {
                        if ($k >= 0 && $k < $n && $isPhone($lines[$k]) && $isIntl($lines[$k])) {
                            $near = true;
                        }
                    }
                    if ($near) {
                        $sender = trim($line);
                        break;
                    }
                }
            }
            if ($sender === null || $sender === '') {
                foreach ($cands as $c) {
                    if ($c !== $buyer) {
                        $sender = $c;
                        break;
                    }
                }
            }
        }

        // Hanya ambil nomor HP pembeli (format lokal 08.../021...). Nomor
        // format internasional (62...) diabaikan karena umumnya milik
        // pengirim/penjual, bukan pembeli.
        foreach ($lines as $line) {
            if ($isPhone($line) && ! $isIntl($line)) {
                $phone = trim($line);
                break;
            }
        }

        // Koreksi marketplace-anon: nama yang mengandung nama marketplace
        // (Shopee/Tokopedia/Tiktok/Lazada/dll) adalah nama PENERIMA yang
        // dianonimkan oleh marketplace (mis. "Shopee International Pla..."),
        // BUKAN penjual/pengirim. PDF extractor kerap menaruh nama ini
        // bersebelahan dengan nomor HP pengirim, sehingga heuristik "nama
        // dekat HP internasional = pengirim" salah menandainya sebagai sender
        // (sementara nama toko asli di atas blok alamat salah jadi buyer).
        // Kalau sender berupa nama marketplace tapi buyer tidak, tukar.
        $mpName = '/\b(Shopee|Tokopedia|Tik\s*Tok|Tiktok|Lazada|Blibli|Bukalapak)\b/i';
        if ($sender !== null && $sender !== '' && preg_match($mpName, $sender)
            && ($buyer === null || $buyer === '' || ! preg_match($mpName, $buyer))) {
            $tmp = $buyer;
            $buyer = $sender;
            $sender = $tmp;
        }

        $buyer = $this->stripNameSeparators(($buyer !== null && $buyer !== '') ? $buyer : null);
        $sender = $this->stripNameSeparators(($sender !== null && $sender !== '') ? $sender : null);

        if (($buyer === null || $buyer === '') || ($address === null || $address === '')) {
            return null;
        }

        return [
            'buyer_name' => ($buyer !== '') ? $buyer : null,
            'buyer_phone' => ($phone !== null && $phone !== '') ? $phone : null,
            'sender_name' => ($sender !== null && $sender !== '') ? $sender : null,
            'shipping_address' => ($address !== '') ? $address : null,
            'weight' => ($weight !== null && $weight !== '') ? $weight : null,
            'order_date' => $orderDate,
            'barang_keyword' => null,
        ];
    }

    /**
     * Deteksi layout J&T Cargo / Tokopedia: header "Pengirim" / "Penerima"
     * muncul TANPA colon (cuma label kolom box 2-kolom). Layout ini berbeda
     * dengan J&T Express/TikTok klasik yang pakai "Pengirim:" / "Penerima:".
     *
     * Trigger kalau:
     *   - SETIDAKNYA satu header bare (no-colon) ditemukan, DAN
     *   - TIDAK ada header bercolon (supaya kasus campur tidak salah dispatch)
     */
    private function isJntCargoLayout(array $lines): bool
    {
        $hasPengirimHeader = false;
        $hasPenerimaHeader = false;
        $hasPengirimColon = false;
        $hasPenerimaColon = false;

        $headerNoColon = '/^(?:Pengirim|Penerima)(?:\s+(?:\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))?\s*$/i';

        foreach ($lines as $line) {
            if (preg_match('/^Pengirim\s*[:\-]/i', $line)) {
                $hasPengirimColon = true;
            } elseif (preg_match('/^Pengirim(?:\s+(?:\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))?\s*$/i', $line)) {
                $hasPengirimHeader = true;
            }
            if (preg_match('/^Penerima\s*[:\-]/i', $line)) {
                $hasPenerimaColon = true;
            } elseif (preg_match('/^Penerima(?:\s+(?:\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))?\s*$/i', $line)) {
                $hasPenerimaHeader = true;
            }
        }

        return ($hasPengirimHeader || $hasPenerimaHeader)
            && ! $hasPengirimColon
            && ! $hasPenerimaColon;
    }

    /**
     * Parser dedicated untuk layout J&T Cargo / Tokopedia.
     *
     * Smalot/pdfparser sering meng-extract layout 2-kolom Pengirim/Penerima
     * dalam urutan yang TIDAK predictable: kadang per-baris visual, kadang
     * column-major (kedua header dulu, baru semua content). Untuk handle
     * semua varian, parser ini:
     *
     *   1. Cari index header "Pengirim" dan "Penerima" + posisi end-block
     *      (Weight / COD / TT Order ID / dll).
     *   2. Buffer SEMUA non-header line di antara header pertama dan
     *      end-block ke `$contentLines`. Phone yang inline dengan header
     *      ("Pengirim (+62)...") di-extract dan dimasukkan ke buffer.
     *   3. Klasifikasi tiap content line sebagai PHONE / ADDRESS / NAME
     *      berdasarkan pattern dan keyword (kota, jalan, koma, dll).
     *   4. Assign ke field sender/buyer pakai signal:
     *        - Mask `*` di name/phone → buyer (marketplace mask buyer
     *          data untuk privacy)
     *        - Tanpa mask → sender (umumnya nama toko / merchant)
     *        - Fallback: pakai urutan kemunculan (sender first karena
     *          spasial top-of-box).
     *   5. Field non-Pengirim/Penerima (Weight, OrderDate, Barang) tetap
     *      di-extract pakai regex line-walk seperti biasa.
     */
    private function walkLinesJntCargo(array $lines, string $marketplace): array
    {
        $buyerName = null;
        $buyerPhone = null;
        $senderName = null;
        $addressParts = [];
        $weight = null;
        $orderDate = null;
        $barangKeyword = null;

        $headerWithPhone = '/^(?:Pengirim|Penerima)\s+((?:\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15}))\s*$/i';
        $headerOnly = '/^(?:Pengirim|Penerima)\s*$/i';
        $endBlockRegex = '/^(Weight|Berat|Ship|Batas\s*Kirim|COD|Print\s*Time|TT\s*Order\s*ID|Order\s*Id|In\s*transit|Product\s*Name|Qty\s*Total|Seller\s*Note|Jumlah)\b/i';

        // Cari index header pertama & end-block
        $firstHeaderIdx = -1;
        $endIdx = count($lines);
        foreach ($lines as $i => $line) {
            if ($firstHeaderIdx < 0
                && (preg_match($headerWithPhone, $line) || preg_match($headerOnly, $line))) {
                $firstHeaderIdx = $i;
                continue;
            }
            if ($firstHeaderIdx >= 0 && preg_match($endBlockRegex, $line)) {
                $endIdx = $i;
                break;
            }
        }

        // Buffer content lines (header lines di-strip; phone inline dengan header
        // tetap di-keep sebagai pure-phone line di buffer).
        $contentLines = [];
        if ($firstHeaderIdx >= 0) {
            for ($i = $firstHeaderIdx; $i < $endIdx; $i++) {
                $line = $lines[$i];
                if (preg_match($headerWithPhone, $line, $hm)) {
                    $contentLines[] = trim($hm[1]);
                    continue;
                }
                if (preg_match($headerOnly, $line)) {
                    continue;
                }
                $contentLines[] = $line;
            }
        }

        $this->classifyJntCargoContent($contentLines, $senderName, $buyerName, $buyerPhone, $addressParts);

        // Field independen (Weight, OrderDate, Barang) — scan seluruh lines
        // termasuk yang sebelum header (kalau ada) dan setelah end-block.
        foreach ($lines as $line) {
            if ($weight === null
                && preg_match('/(?:Weight|Berat)\s*[:\-]\s*([\d\.,]+\s*(?:KG|kg|gr|g))/i', $line, $m)) {
                $weight = trim($m[1]);
                if (preg_match('/(?:Ship|Batas\s*Kirim)\s*[:\-]\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i', $line, $m2)) {
                    $orderDate = $this->normalizeDate($m2[1]);
                }
                continue;
            }
            if ($orderDate === null
                && preg_match('/^(?:Ship|Batas\s*Kirim)\s*[:\-]\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i', $line, $m)) {
                $orderDate = $this->normalizeDate($m[1]);
                continue;
            }
            if ($barangKeyword === null
                && $marketplace === 'tiktok'
                && preg_match('/Barang\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $barangKeyword = trim($m[1]);
                continue;
            }
        }

        return [
            'buyer_name' => $buyerName,
            'buyer_phone' => $buyerPhone,
            'sender_name' => $senderName,
            'shipping_address' => $addressParts ? implode(', ', $addressParts) : null,
            'weight' => $weight,
            'order_date' => $orderDate,
            'barang_keyword' => $barangKeyword,
        ];
    }

    /**
     * Klasifikasi & assign content lines J&T Cargo ke field sender/buyer.
     *
     * Strategi:
     *  - Phone-only line → list phones
     *  - Phone inline + nama → split: phone ke list, sisa ke list nama
     *  - Address-like (ada koma + keyword alamat / panjang) → list addresses
     *  - Sisanya → list names
     *
     * Setelah klasifikasi:
     *  - Names: yang ada `*` → buyer; yang tidak → sender. Fallback by order.
     *  - Phones: yang ada `*` → buyer. Kalau tidak ada masked phone tapi ada
     *    >=2 unmasked → urutan ke-2 sebagai buyer. Sender phone tidak
     *    di-track (field tidak ada).
     *  - Addresses: spasial sender duluan (top box), buyer kedua (bottom box).
     *    Kalau ada >=2 address, ambil yang KEDUA sebagai shipping_address
     *    (alamat buyer). Kalau cuma 1, anggap milik buyer (best-effort).
     *
     * @param array<int, string> $contentLines
     * @param array<int, string> $addressParts
     */
    private function classifyJntCargoContent(
        array $contentLines,
        ?string &$senderName,
        ?string &$buyerName,
        ?string &$buyerPhone,
        array &$addressParts
    ): void {
        $phones = [];
        $names = [];
        $addresses = [];

        $addrKeywordsRegex = '/\b(Jl\.?|Jalan|Kota|Kabupaten|Kab\.?|Kel\.?|Kec\.?|Perumahan|Komplek|Kompleks|Blok|Dusun|Desa|Kelurahan|Kecamatan|Provinsi|Aceh|Sumatera|Riau|Jambi|Bengkulu|Lampung|Banten|Jawa|Jakarta|DKI|Yogyakarta|Bali|NTT|NTB|Kalimantan|Sulawesi|Maluku|Papua|Gorontalo)\b/iu';
        $phoneOnlyRegex = '/^(\(\+?62\)[\d\*\-\s]{5,30}|\+?\d{10,15})$/';
        $phoneInlineRegex = '/(\(\+?62\)[\d\*\-\s]{5,30}|\+62\d{9,13})/';

        foreach ($contentLines as $raw) {
            $line = trim($raw);
            if ($line === '') continue;

            // Skip noise yang kadang masuk ke blok header (misal kode resi /
            // kode sortir yang ke-grouping ke area Pengirim/Penerima).
            if (preg_match('/^(J[XPY]\d{8,}|SPXID\d{8,})$/i', $line)) continue;
            if (preg_match('/^\d{12,16}$/', $line)) continue; // resi numerik panjang
            if (preg_match('/^\d{3}-[A-Z0-9]{2,}-\d+[A-Z]?$/i', $line)) continue;
            if (preg_match('/^[A-Z]{2,}-[A-Z0-9]+-[A-Z0-9]+$/i', $line)) continue;
            if (preg_match('/^\d{1,4}$/', $line)) continue;
            if (preg_match('/^COD$/i', $line)) continue;

            // Strip "Pengirim"/"Penerima" prefix kalau nyangkut di line content.
            $line = preg_replace('/^(?:Pengirim|Penerima)\s+/i', '', $line);
            $line = trim($line);
            if ($line === '') continue;

            // Pure phone line
            if (preg_match($phoneOnlyRegex, $line)) {
                $phones[] = $line;
                continue;
            }

            // Phone inline + sisa text → ekstrak phone, sisa diklasifikasi lagi
            if (preg_match($phoneInlineRegex, $line, $mm)) {
                $phones[] = trim($mm[1]);
                $line = trim(str_replace($mm[0], '', $line));
                if ($line === '') continue;
            }

            // Address-like vs name-like
            $hasComma = str_contains($line, ',');
            $hasAddrKeyword = preg_match($addrKeywordsRegex, $line) === 1;
            $isLong = mb_strlen($line) > 30;

            if ($hasComma || ($hasAddrKeyword && $isLong)) {
                $addresses[] = $line;
            } else {
                $names[] = $line;
            }
        }

        // Assign names: prefer masking signal (`*` → buyer)
        $assignedNames = [];
        foreach ($names as $n) {
            $clean = $this->stripNameSeparators(trim($n));
            if (! is_string($clean) || $clean === '') continue;
            if (str_contains($clean, '*')) {
                if ($buyerName === null) {
                    $buyerName = $clean;
                    $assignedNames[] = $clean;
                }
            }
        }
        // Sisa name (tanpa mask) → sender, lalu buyer kalau slot kosong
        foreach ($names as $n) {
            $clean = $this->stripNameSeparators(trim($n));
            if (! is_string($clean) || $clean === '') continue;
            if (in_array($clean, $assignedNames, true)) continue;
            if (str_contains($clean, '*')) continue;
            if ($senderName === null) {
                $senderName = $clean;
                $assignedNames[] = $clean;
            } elseif ($buyerName === null) {
                $buyerName = $clean;
                $assignedNames[] = $clean;
            }
        }

        // Assign phones: masked → buyer; >=2 unmasked → urutan ke-2 = buyer
        $unmaskedPhones = [];
        foreach ($phones as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (str_contains($p, '*')) {
                if ($buyerPhone === null) $buyerPhone = $p;
            } else {
                $unmaskedPhones[] = $p;
            }
        }
        if ($buyerPhone === null && count($unmaskedPhones) >= 2) {
            $buyerPhone = $unmaskedPhones[1];
        }

        // Address: kalau >=2, ambil ke-2 (alamat buyer; spasial bottom box).
        // Kalau cuma 1, anggap alamat buyer (best-effort, sender address tidak
        // di-track di field).
        if (count($addresses) >= 2) {
            $addressParts[] = $addresses[1];
        } elseif (count($addresses) === 1) {
            $addressParts[] = $addresses[0];
        }
    }

    /**
     * Deteksi layout label Tokopedia/paxel (SAMEDAY) yang memakai header
     * alamat berbahasa Inggris "FROM(SENDER)" / "TO(ADDRESS)" (bukan
     * "Pengirim" / "Penerima").
     *
     * @param array<int, string> $lines
     */
    private function isTokopediaPaxelLayout(array $lines): bool
    {
        $hasFrom = false;
        $hasTo = false;
        foreach ($lines as $line) {
            if (preg_match('/^FROM\s*\(\s*SENDER\s*\)/i', $line)) {
                $hasFrom = true;
            }
            if (preg_match('/^TO\s*\(\s*ADDRESS\s*\)/i', $line)) {
                $hasTo = true;
            }
        }

        return $hasFrom && $hasTo;
    }

    /**
     * Parser ORDER-INDEPENDENT + INLINE-AWARE untuk layout Tokopedia/paxel.
     *
     * smalot/pdfparser sering menaruh NAMA + NOMOR HP pada SATU baris, bahkan
     * menempelkan nama pengirim di belakang baris alamatnya, mis:
     *   "Jawa Barat, Kota Tasikmalaya, ArrozaqAuto96 (+62)82*******48"
     * dan urutan baris bisa acak/terbalik. Karena itu parser ini:
     *   1. mengekstrak HP inline dari mana saja pada sebuah baris,
     *   2. mengambil nama = teks tepat sebelum HP (potongan setelah koma
     *      terakhir); sisanya menjadi alamat,
     *   3. nama ber-mask "*" => pembeli (penerima); tanpa mask => pengirim,
     *   4. blok alamat berurutan TERPANJANG => alamat kirim (pembeli),
     *   5. HP pembeli = HP pada baris nama pembeli / terdekat ke baris pembeli.
     *
     * @param array<int, string> $lines
     * @return array<string, ?string>
     */
    private function walkLinesTokopediaPaxel(array $lines, string $marketplace): array
    {
        $endRegex = '/^(Order\s*ID|In\s*transit|Product\s*Name|Qty\s*Total|Seller\s*Note)\b/iu';
        $fromRegex = '/^FROM\s*\(\s*SENDER\s*\)/i';
        $toRegex = '/^TO\s*\(\s*ADDRESS\s*\)/i';
        $phoneRegex = '/(\(\+?62\)[\d\*\-\s]{5,}|\+?62[\d\*\-\s]{8,})/';
        $addrKeywordsRegex = '/\b(Jl\.?|Jalan|Gg\.?|Kota|Kabupaten|Kab\.?|Kel\.?|Kec\.?|Desa|Dusun|Kelurahan|Kecamatan|Provinsi|Blok|Komplek|Kompleks|Perumahan|RT|RW|No\.?|Aceh|Sumatera|Riau|Jambi|Bengkulu|Lampung|Banten|Jawa|Jakarta|DKI|Yogyakarta|Bali|NTT|NTB|Kalimantan|Sulawesi|Maluku|Papua|Gorontalo)\b/iu';

        // Bersihkan baris kosong, ambil region sampai anchor akhir.
        $clean = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t !== '') {
                $clean[] = $t;
            }
        }
        $region = [];
        foreach ($clean as $line) {
            if (preg_match($endRegex, $line)) {
                break;
            }
            $region[] = $line;
        }
        $n = count($region);
        if ($n === 0) {
            return [
                'buyer_name' => null, 'buyer_phone' => null, 'sender_name' => null,
                'shipping_address' => null, 'weight' => null, 'order_date' => null,
                'barang_keyword' => null,
            ];
        }

        $kind = array_fill(0, $n, null);
        $nameOf = array_fill(0, $n, null);
        $phoneOf = array_fill(0, $n, null);
        $addrText = array_fill(0, $n, null);

        foreach ($region as $i => $line) {
            if (preg_match($fromRegex, $line) || preg_match($toRegex, $line)) {
                $kind[$i] = 'HDR';
                continue;
            }
            if (preg_match($phoneRegex, $line, $pm, PREG_OFFSET_CAPTURE)) {
                $phoneOf[$i] = trim($pm[1][0]);
                $before = trim(substr($line, 0, $pm[0][1]));
                $after = trim(substr($line, $pm[0][1] + strlen($pm[0][0])));
                if ($before !== '') {
                    if (str_contains($before, ',')) {
                        $pos = strrpos($before, ',');
                        $cand = trim(substr($before, $pos + 1));
                        $rem = trim(substr($before, 0, $pos + 1));
                    } else {
                        $cand = $before;
                        $rem = '';
                    }
                    if ($this->isPaxelNameLike($cand, $addrKeywordsRegex)) {
                        $nameOf[$i] = $cand;
                        $addrText[$i] = $rem !== '' ? $this->stripPaxelNoiseFragment($rem) : null;
                    } else {
                        $addrText[$i] = $this->stripPaxelNoiseFragment($before);
                    }
                }
                // smalot kadang menaruh HP DULU lalu nama menempel di belakang,
                // mis. "(+62)82*******71R**i" => nama ada SESUDAH nomor HP.
                if ($nameOf[$i] === null && $after !== '' && $this->isPaxelNameLike($after, $addrKeywordsRegex)) {
                    $nameOf[$i] = $after;
                }
                $kind[$i] = 'PHONE';
                continue;
            }
            $s = $this->stripPaxelNoiseFragment($line);
            if ($s === '' || $this->isPaxelNoiseLine($s)) {
                $kind[$i] = 'NOISE';
            } elseif (str_contains($s, ',') || preg_match($addrKeywordsRegex, $s)) {
                $kind[$i] = 'ADDR';
                $addrText[$i] = $s;
            } else {
                $kind[$i] = 'NAME';
                $nameOf[$i] = $s;
            }
        }

        // HP tanpa nama inline => ambil nama dari baris NAME tetangga.
        for ($i = 0; $i < $n; $i++) {
            if ($kind[$i] === 'PHONE' && $nameOf[$i] === null) {
                foreach ([$i + 1, $i - 1] as $j) {
                    if ($j >= 0 && $j < $n && $kind[$j] === 'NAME' && $nameOf[$j] !== null) {
                        $nameOf[$i] = $nameOf[$j];
                        $nameOf[$j] = null;
                        $kind[$j] = 'CONSUMED';
                        break;
                    }
                }
            }
        }

        // Baris NAME huruf kecil tepat setelah ADDR = lanjutan alamat
        // (mis. kata "Ruko" terpotong jadi "...Depan Ru" + "ko BRI Link ...").
        for ($i = 1; $i < $n; $i++) {
            if ($kind[$i] === 'NAME'
                && $addrText[$i] === null
                && preg_match('/^\p{Ll}/u', $region[$i])
                && $addrText[$i - 1] !== null) {
                $addrText[$i] = $this->stripPaxelNoiseFragment($region[$i]);
                $nameOf[$i] = null;
                $kind[$i] = 'ADDR';
            }
        }

        // Pembeli/pengirim berdasarkan mask "*".
        $buyerName = null;
        $senderName = null;
        $buyerLine = -1;
        for ($i = 0; $i < $n; $i++) {
            $nm = $nameOf[$i];
            if ($nm === null || $kind[$i] === 'CONSUMED') {
                continue;
            }
            if (str_contains($nm, '*')) {
                if ($buyerName === null) {
                    $c = $this->stripNameSeparators($nm);
                    $buyerName = is_string($c) && $c !== '' ? $c : $nm;
                    $buyerLine = $i;
                }
            } elseif ($senderName === null) {
                $c = $this->stripNameSeparators($nm);
                $senderName = is_string($c) && $c !== '' ? $c : $nm;
            }
        }
        // Fallback: tanpa nama ber-mask, pakai nama pertama sebagai pembeli.
        if ($buyerName === null) {
            for ($i = 0; $i < $n; $i++) {
                if ($nameOf[$i] !== null && $kind[$i] !== 'CONSUMED') {
                    $c = $this->stripNameSeparators($nameOf[$i]);
                    $buyerName = is_string($c) && $c !== '' ? $c : $nameOf[$i];
                    $buyerLine = $i;
                    break;
                }
            }
        }

        // Blok alamat berurutan; terpanjang = alamat pembeli.
        $blocks = [];
        $cur = [];
        $prevIdx = -2;
        for ($i = 0; $i < $n; $i++) {
            if ($addrText[$i] === null || $addrText[$i] === '') {
                continue;
            }
            if ($i === $prevIdx + 1 && $cur) {
                $cur[] = [$i, $addrText[$i]];
            } else {
                if ($cur) {
                    $blocks[] = $cur;
                }
                $cur = [[$i, $addrText[$i]]];
            }
            $prevIdx = $i;
        }
        if ($cur) {
            $blocks[] = $cur;
        }

        $buyerBlock = null;
        $bestLen = -1;
        foreach ($blocks as $b) {
            $len = 0;
            foreach ($b as [, $t]) {
                $len += mb_strlen($t);
            }
            if ($len > $bestLen) {
                $bestLen = $len;
                $buyerBlock = $b;
            }
        }

        $shippingAddress = null;
        $buyerIdxs = [];
        if ($buyerLine >= 0) {
            $buyerIdxs[] = $buyerLine;
        }
        if ($buyerBlock) {
            $parts = '';
            foreach ($buyerBlock as $j => [$idx, $t]) {
                $buyerIdxs[] = $idx;
                if ($j === 0) {
                    $parts = $t;
                } elseif (preg_match('/^\p{Ll}/u', $t)) {
                    $parts .= $t; // lanjutan kata terpotong (Ru + ko)
                } else {
                    $parts .= ', '.$t;
                }
            }
            $parts = preg_replace('/\s*,\s*,/', ',', (string) $parts);
            $shippingAddress = trim(trim((string) $parts), ',');
            $shippingAddress = trim($shippingAddress) !== '' ? trim($shippingAddress) : null;
        }

        // HP pembeli: pada baris nama pembeli, atau terdekat ke baris pembeli.
        $buyerPhone = null;
        if ($buyerLine >= 0 && $phoneOf[$buyerLine] !== null) {
            $buyerPhone = $phoneOf[$buyerLine];
        } else {
            $phones = [];
            for ($i = 0; $i < $n; $i++) {
                if ($phoneOf[$i] !== null) {
                    $phones[] = [$i, $phoneOf[$i]];
                }
            }
            if ($phones) {
                if ($buyerIdxs) {
                    $best = null;
                    $bestDist = PHP_INT_MAX;
                    foreach ($phones as [$pi, $pl]) {
                        $dist = PHP_INT_MAX;
                        foreach ($buyerIdxs as $bi) {
                            $dist = min($dist, abs($pi - $bi));
                        }
                        if ($dist < $bestDist) {
                            $bestDist = $dist;
                            $best = $pl;
                        }
                    }
                    $buyerPhone = $best;
                } else {
                    $buyerPhone = $phones[0][1];
                }
            }
        }

        // Weight & tanggal "In transit by" (scan seluruh baris).
        $weight = null;
        $orderDate = null;
        foreach ($clean as $line) {
            if ($weight === null
                && preg_match('/(?:Weight|Berat)\s*[:\-]\s*([\d\.,]+\s*(?:KG|kg|gr|g))/i', $line, $m)) {
                $weight = trim($m[1]);
                continue;
            }
            if ($orderDate === null
                && preg_match('/In\s*transit\s*by\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $line, $m)) {
                $orderDate = $this->normalizeDate($m[1]);
                continue;
            }
        }

        return [
            'buyer_name' => $buyerName,
            'buyer_phone' => $buyerPhone,
            'sender_name' => $senderName,
            'shipping_address' => $shippingAddress,
            'weight' => $weight,
            'order_date' => $orderDate,
            'barang_keyword' => null,
        ];
    }

    /**
     * Apakah string tampak seperti nama orang/toko (bukan potongan alamat)?
     */
    private function isPaxelNameLike(string $cand, string $addrKeywordsRegex): bool
    {
        $cand = trim($cand);
        $len = mb_strlen($cand);
        if ($len < 1 || $len > 40) {
            return false;
        }
        if (! preg_match('/[A-Za-z]/', $cand)) {
            return false;
        }
        if (str_contains($cand, ',')) {
            return false;
        }
        // Tolak jika murni keyword alamat tanpa angka/mask (mis. "Kota Bandung").
        if (preg_match($addrKeywordsRegex, $cand) && ! preg_match('/[\d\*]/', $cand)) {
            return false;
        }

        return true;
    }

    /**
     * Buang fragmen noise yang menempel di baris alamat (COD：, angka 0, dll).
     */
    private function stripPaxelNoiseFragment(string $line): string
    {
        $line = preg_replace('/\bCOD\s*[:\x{FF1A}]?\s*\d*/iu', '', $line);
        $line = preg_replace('/\s+0$/', '', (string) $line);
        $line = preg_replace('/[:\x{FF1A}]\s*$/u', '', (string) $line);

        return trim(trim((string) $line), ',');
    }

    /**
     * Baris noise pada layout paxel: resi, kode sortir, label kurir, dll.
     */
    private function isPaxelNoiseLine(string $line): bool
    {
        $patterns = [
            '/^TSPX/i',
            '/^SPXID/i',
            '/^J[XPY]\d{8,}$/i',
            '/^COD\b/i',
            '/^[:\-\x{FF1A}]+$/u',
            '/^\d{1,6}$/',
            '/^\d{12,}$/',
            '/^SAMEDAY$/i',
            '/^NEXTDAY$/i',
            '/^REGULAR$/i',
            '/^Reguler$/i',
            '/^paxel$/i',
            '/^tokopedia$/i',
            '/^Shop$/i',
            '/Paketmu/i',
            '/^SKU$/i',
            '/^Seller\s*SKU$/i',
            '/^Qty/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $line)) {
                return true;
            }
        }

        return false;
    }

    private function extractResi(string $text, string $marketplace = 'tiktok'): ?string
    {
        // Shopee: SPXID + 10+ digit
        if (preg_match_all('/\b(SPXID\d{10,20})\b/i', $text, $matches)) {
            $counts = array_count_values(array_map('strtoupper', $matches[1]));
            arsort($counts);

            return array_key_first($counts);
        }

        // Shopee non-SPX (Anteraja/JNE/SiCepat/dll): cari "No. Resi: <digits>"
        // sebagai anchor utama, atau angka panjang (10-20 digit) yang paling
        // sering muncul (biasanya tertulis di barcode header & sample dibawah).
        if ($marketplace === 'shopee') {
            // Format 1: explicit "No. Resi: 11003835228537"
            if (preg_match('/No\.?\s*Resi\s*[:\-]\s*([A-Z0-9]{8,24})/i', $text, $rm)) {
                return strtoupper(trim($rm[1]));
            }

            // Format 2: angka panjang yang paling sering muncul, dengan filter
            // exclude Order ID Shopee (alfanumerik 13-15 char campur huruf).
            if (preg_match_all('/\b(\d{10,20})\b/', $text, $matches)) {
                $orderIds = [];
                if (preg_match_all('/No\.?\s*Pesanan\s*[:\-]?\s*(\w{8,24})/i', $text, $oidMatches)) {
                    $orderIds = array_map('strtoupper', $oidMatches[1]);
                }
                $candidates = array_diff($matches[1], $orderIds);

                if (! empty($candidates)) {
                    $counts = array_count_values($candidates);
                    arsort($counts);

                    return (string) array_key_first($counts);
                }
            }
        }

        // TikTok/Tokopedia + J&T Express: JX/JP/JY + 10-16 digit.
        // Beberapa label Tokopedia x TikTok Shop memakai prefix JY (contoh:
        // JY1053535860). Versi lama hanya menerima JX/JP sehingga label J&T
        // Express valid dianggap "tidak ada resi".
        if (preg_match_all('/\b(J[XPY]\d{10,16})\b/i', $text, $matches)) {
            $counts = array_count_values(array_map('strtoupper', $matches[1]));
            arsort($counts);

            return array_key_first($counts);
        }

        // Fallback OCR/PDF extractor: kadang barcode samping terbaca per karakter
        // atau terpecah baris/spasi seperti "J Y10 5 3 ...". Padatkan teks dulu,
        // lalu cari JX/JP/JY. Ini tidak mengubah raw_text, hanya untuk deteksi resi.
        $compactText = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $text));
        if (preg_match_all('/(J[XPY]\d{10,16})/i', $compactText, $compactMatches)) {
            $counts = array_count_values(array_map('strtoupper', $compactMatches[1]));
            arsort($counts);

            return array_key_first($counts);
        }

        // J&T Cargo / Tokopedia / FastTrack: nomor resi numerik 10-16 digit,
        // biasanya muncul sebagai barcode utama tepat di bawah header
        // "J&T CARGO" / "FastTrack" / "TBN-..." routing code.
        // Coba ambil angka panjang yang paling sering muncul.
        $isCargo = preg_match('/J&T\s*CARGO|FastTrack/i', $text) === 1;
        if ($isCargo || $marketplace === 'tokopedia') {
            // Tokopedia/paxel: resi berformat "TSPX-00542130401" (Tokopedia SPX).
            // Tangani lebih dulu sebelum heuristik angka-terbanyak supaya tidak
            // salah ambil potongan numerik di dalamnya.
            if (preg_match('/\b(TSPX-?\d{6,})\b/i', $text, $tm)) {
                return strtoupper(trim($tm[1]));
            }

            // Format 1: token TBN-XXXX-XX (sortation code, BUKAN resi tapi
            // bisa jadi fallback unik).
            $sortToken = null;
            if (preg_match('/\b(TBN-[A-Z0-9]+-[A-Z0-9]+)\b/i', $text, $sm)) {
                $sortToken = strtoupper(trim($sm[1]));
            }

            // Format 2: resi numerik 10-16 digit. Pilih yang paling sering muncul
            // dan abaikan angka yang jelas-jelas Order ID (biasanya 16+ digit
            // diawali "5840" pada TikTok atau didahului label "Order Id").
            if (preg_match_all('/\b(\d{10,16})\b/', $text, $matches)) {
                $orderIds = [];
                if (preg_match_all('/(?:TT\s*Order\s*ID|Order\s*Id)\s*[:\-]?\s*(\d{10,24})/i', $text, $oidMatches)) {
                    $orderIds = array_map('trim', $oidMatches[1]);
                }
                $candidates = array_diff($matches[1], $orderIds);
                $candidates = array_filter($candidates, fn ($n) => strlen($n) >= 10 && strlen($n) <= 16);

                if (! empty($candidates)) {
                    $counts = array_count_values($candidates);
                    arsort($counts);

                    return (string) array_key_first($counts);
                }
            }

            if ($sortToken) {
                return $sortToken;
            }
        }

        // Fallback generik
        if (preg_match('/(?:Resi|No\.?\s*Resi|AWB)\s*[:\-]?\s*([A-Z0-9\-]{10,24})/i', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    private function extractOrderId(string $text, string $marketplace): ?string
    {
        if ($marketplace === 'shopee') {
            if (preg_match('/No\.?\s*Pesanan\s*[:\-]?\s*([A-Z0-9]{8,24})/i', $text, $m)) {
                return strtoupper(trim($m[1]));
            }
        }

        // TikTok di J&T Cargo: "TT Order ID : 58405...46303" — prioritaskan ini
        // karena lebih jelas TikTok-nya.
        if (preg_match('/TT\s*Order\s*ID\s*[:\-]?\s*(\d{10,24})/i', $text, $m)) {
            return trim($m[1]);
        }

        // TikTok: "Order Id : 58394..." (digit saja). Toleransi titik dua
        // fullwidth "：" yang dipakai label Tokopedia/paxel ("Order ID： 5846...").
        if (preg_match('/Order\s*Id\s*[:\-\x{FF1A}]?\s*(\d{10,24})/iu', $text, $m)) {
            return trim($m[1]);
        }

        // Fallback Order ID umum
        if (preg_match('/Order\s*ID\s*[:\-\x{FF1A}]?\s*([A-Z0-9]{8,24})/iu', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    private function extractCourier(string $text, string $marketplace): string
    {
        if ($marketplace === 'shopee') {
            // Shopee bisa pakai berbagai kurir. Deteksi dari teks:
            if (preg_match('/\bAnteraja\b/i', $text)) {
                return 'Anteraja';
            }
            if (preg_match('/\bSiCepat\b/i', $text)) {
                return 'SiCepat';
            }
            if (preg_match('/\bJNE\b/i', $text)) {
                return 'JNE';
            }
            if (preg_match('/J&T\s*Express|J\s*&\s*T/i', $text)) {
                return 'JNT';
            }
            // Fallback: kalau ada SPXID di teks → SPX (Shopee Express).
            //   Selain itu = "Other" (kemungkinan Anteraja/JNE/SiCepat tapi
            //   logo image-only, tidak di-extract sebagai text oleh PDF parser).
            if (preg_match('/\bSPXID\d+|\bSPX\b|Shop\s*Express/i', $text)) {
                return 'SPX';
            }
            return 'Other';
        }
        // Tokopedia/paxel SAMEDAY
        if (preg_match('/\bpaxel\b|\bSAMEDAY\b/i', $text)) {
            return 'Paxel';
        }
        // J&T Cargo (FastTrack) — cek dulu sebelum J&T Express
        if (preg_match('/J&T\s*CARGO|J\s*&\s*T\s*CARGO|FastTrack/i', $text)) {
            return 'JNT_CARGO';
        }
        if (preg_match('/J&T\s*Express|J\s*&\s*T/i', $text)) {
            return 'JNT';
        }
        if (preg_match('/JNE\b/i', $text)) {
            return 'JNE';
        }
        if (preg_match('/SiCepat/i', $text)) {
            return 'SiCepat';
        }
        if (preg_match('/Anteraja/i', $text)) {
            return 'Anteraja';
        }

        return 'JNT';
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = str_replace('/', '-', trim($raw));
        $parts = explode('-', $raw);
        if (count($parts) !== 3) {
            return null;
        }
        [$d, $m, $y] = $parts;
        if (strlen($y) === 2) {
            $y = '20'.$y;
        }

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    /**
     * Extract rows dari tabel produk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractProductRows(string $text, string $marketplace): array
    {
        if ($marketplace === 'shopee') {
            return $this->extractShopeeProductRows($text);
        }

        return $this->extractTiktokProductRows($text);
    }

    /**
     * TikTok: tabel "Product Name SKU Seller SKU Qty".
     *
     * Dua layout didukung:
     *  - Inline: "Stir Racing  R14  Coklat,Stir+Bosskit  1"
     *    (kolom dipisah 2+ spasi, qty di ujung baris yang sama)
     *  - Multi-line: tiap kolom bisa wrap ke beberapa baris, qty
     *    berdiri sendiri di satu baris. Contoh:
     *       Stir Racing GAZOO RACING Ring    <- name part 1
     *       14 & +Bosskit                     <- name part 2
     *       Coklat,                            <- seller_sku part 1
     *       Stir+Bossk                         <- seller_sku part 2
     *       it                                 <- mid-word wrap
     *       1                                  <- qty (baris sendiri)
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractTiktokProductRows(string $text): array
    {
        if (! preg_match(
            '/Product\s*Name\s+SKU\s+Seller\s*SKU\s+Qty\s*(.+?)(?:Qty\s*Total|Order\s*ID\s*[:\-]|Seller\s*Note|$)/is',
            $text,
            $m
        )) {
            return [];
        }

        $block = trim($m[1]);

        // Coba parser multiline dulu (tahan wrap + qty di baris sendiri)
        $rows = $this->parseTiktokMultilineRows($block);

        if (empty($rows)) {
            $rows = $this->parseTiktokInlineRows($block);
        }

        return $rows;
    }

    /**
     * Parser TikTok legacy: qty di ujung baris yang sama, kolom dipisah 2+ spasi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTiktokInlineRows(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));

        $rows = [];
        $buffer = [];
        foreach ($lines as $line) {
            $buffer[] = $line;
            if (preg_match('/\s(\d{1,4})$/', $line, $matchQty)) {
                $joined = implode(' ', $buffer);
                $qty = (int) $matchQty[1];

                $rest = trim(preg_replace('/\s\d{1,4}$/', '', $joined));
                $cols = preg_split('/\s{2,}/', $rest) ?: [];

                $rows[] = [
                    'product_name' => $cols[0] ?? $rest,
                    'sku' => $cols[1] ?? null,
                    'seller_sku' => $cols[2] ?? null,
                    'quantity' => $qty,
                    'raw_line' => $rest,
                ];
                $buffer = [];
            }
        }

        return $rows;
    }

    /**
     * Parser TikTok multi-line: qty terletak di baris sendiri, nama dan
     * seller_sku dapat tersebar di beberapa baris. Beda dengan Shopee,
     * TikTok tidak punya nomor urut baris.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTiktokMultilineRows(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
        if (empty($lines)) {
            return [];
        }

        $rows = [];
        $buffer = [];

        $count = count($lines);
        foreach ($lines as $idx => $line) {
            // Baris angka murni BIASANYA = qty (kolom terakhir) di baris sendiri.
            if (preg_match('/^(\d{1,4})$/', $line)) {
                if (! empty($buffer)) {
                    // ------------------------------------------------------------
                    // Disambiguasi UKURAN vs QTY.
                    //
                    // Pada label TikTok, kolom Qty adalah kolom TERAKHIR, jadi
                    // qty selalu muncul SETELAH kolom seller_sku/variasi (kolom
                    // yang khas mengandung ',' atau '+', mis. "Silver, Stir +
                    // Bosskit"). Kalau angka ini muncul SEBELUM buffer punya
                    // baris seller_sku, DAN masih ada baris seller_sku menyusul,
                    // maka angka ini adalah bagian NAMA produk yang wrap ke baris
                    // sendiri (mis. ukuran ring "15" pada "...celong Ring" + "15")
                    // — BUKAN qty. Tanpa cek ini, ukuran "15" salah dibaca jadi
                    // qty=15 sehingga stok terpotong 15x per produk
                    // (bug: "ngurang 15 per produk").
                    // ------------------------------------------------------------
                    $bufferHasSellerSku = false;
                    foreach ($buffer as $b) {
                        if (str_contains($b, ',')) {
                            $bufferHasSellerSku = true;
                            break;
                        }
                    }
                    $sellerSkuAhead = false;
                    for ($j = $idx + 1; $j < $count; $j++) {
                        if (preg_match('/^\d{1,4}$/', $lines[$j])) {
                            break; // batas row berikutnya
                        }
                        if (str_contains($lines[$j], ',') || str_contains($lines[$j], '+')) {
                            $sellerSkuAhead = true;
                            break;
                        }
                    }

                    if (! $bufferHasSellerSku && $sellerSkuAhead) {
                        // Angka = lanjutan nama produk (ukuran), bukan qty.
                        $buffer[] = $line;
                        continue;
                    }

                    $qty = (int) $line;
                    $row = $this->buildTiktokRowFromBuffer($buffer, $qty, false);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                    $buffer = [];
                }
                continue;
            }

            $buffer[] = $line;
        }

        // Flush sisa buffer: baris terakhir berakhir " <digit>" (qty inline).
        //   Kasus: seller_sku + qty ada di baris terakhir yang sama.
        //   Contoh layout label ini:
        //     Stir Racing New Skeleton Import   <- nama part 1
        //     R14" Black                         <- nama part 2
        //     Stir Aja 1                         <- seller_sku + qty
        if (! empty($buffer)) {
            $lastLine = (string) end($buffer);
            $inlineQty = null;
            if (preg_match('/^(.+?)\s+(\d{1,4})$/', $lastLine, $inlineMatch)) {
                $inlineQty = (int) $inlineMatch[2];
                $buffer[array_key_last($buffer)] = trim($inlineMatch[1]);
            } elseif (preg_match('/^(.+[A-Za-z\)\]])(\d{1,2})$/u', $lastLine, $gluedMatch)) {
                // Qty NEMPEL tanpa spasi ke ujung kolom seller_sku, mis.
                // "Silver, Stir + Bosskit1". smalot/pdfparser kerap menempelkan
                // angka Qty langsung ke ujung kolom terakhir sehingga regex
                // qty-berspasi gagal & row tidak pernah terbentuk (item hilang).
                $inlineQty = (int) $gluedMatch[2];
                $buffer[array_key_last($buffer)] = trim($gluedMatch[1]);
            }
            if ($inlineQty !== null) {
                $row = $this->buildTiktokRowFromBuffer($buffer, $inlineQty, true);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * Susun row TikTok dari buffer baris-konten + qty.
     *
     * $qtyWasInline menandakan qty ter-split dari ujung baris terakhir buffer.
     * Ketika TRUE, baris terakhir buffer dianggap sebagai seller_sku (kolom
     * terakhir) dan baris-baris di atasnya adalah product_name. Ini meng-
     * cover layout di mana SKU kosong & seller_sku cukup pendek sehingga
     * tampil di baris yang sama dengan qty (mis. "Stir Aja 1").
     *
     * Ketika FALSE (qty berdiri di baris sendiri), pakai heuristik koma:
     * baris pertama yang mengandung ',' = awal seller_sku.
     *
     * @param array<int, string> $buffer
     * @return ?array<string, mixed>
     */
    private function buildTiktokRowFromBuffer(array $buffer, int $qty, bool $qtyWasInline = false): ?array
    {
        if (empty($buffer)) {
            return null;
        }

        // Kasus 1-line: buffer cuma punya 1 baris → layout legacy inline,
        //   split by 2+ spaces (kolom Product Name / SKU / Seller SKU).
        if (count($buffer) === 1) {
            $rest = trim($buffer[0]);
            $cols = preg_split('/\s{2,}/', $rest) ?: [];
            if (count($cols) >= 3) {
                return [
                    'product_name' => trim($cols[0]),
                    'sku' => trim($cols[1]) ?: null,
                    'seller_sku' => trim($cols[2]) ?: null,
                    'quantity' => $qty,
                    'raw_line' => $rest,
                ];
            }
            // Kalau cuma 1 kolom, simpan apa adanya
            return [
                'product_name' => $rest,
                'sku' => null,
                'seller_sku' => null,
                'quantity' => $qty,
                'raw_line' => $rest,
            ];
        }

        // Kasus qty inline di baris terakhir:
        //   Baris terakhir (setelah qty di-strip) = seller_sku kolom.
        //   Baris-baris sebelumnya = product_name.
        // Ini cocok untuk layout label tanpa SKU dan tanpa koma di seller_sku,
        // mis. "Stir Aja 1" (seller_sku="Stir Aja", qty=1).
        if ($qtyWasInline) {
            $lastContent = trim((string) end($buffer));
            if ($lastContent !== '') {
                $nameParts = array_slice($buffer, 0, -1);
                $sellerSku = trim($lastContent);
                $name = $this->smartJoinShopeeLines($nameParts);

                if ($name !== '' && mb_strlen($name) >= 3) {
                    return [
                        'product_name' => $name,
                        'sku' => null,
                        'seller_sku' => $sellerSku ?: null,
                        'quantity' => $qty,
                        'raw_line' => trim(implode(' | ', $buffer).' | qty='.$qty),
                    ];
                }
            }
            // Fall through ke heuristik koma kalau gagal (mis. nama terlalu pendek)
        }

        // Kasus qty di baris sendiri: baris pertama yang mengandung ',' = awal
        // seller_sku (khas TikTok: "Coklat,Stir+Bosskit").
        $sellerSkuStart = null;
        foreach ($buffer as $i => $line) {
            if ($i === 0) {
                continue; // baris pertama selalu nama
            }
            if (str_contains($line, ',')) {
                $sellerSkuStart = $i;
                break;
            }
        }

        if ($sellerSkuStart !== null) {
            $nameParts = array_slice($buffer, 0, $sellerSkuStart);
            $sellerSkuParts = array_slice($buffer, $sellerSkuStart);
        } else {
            $nameParts = $buffer;
            $sellerSkuParts = [];
        }

        $name = $this->smartJoinShopeeLines($nameParts);
        $sellerSku = $this->smartJoinShopeeLines($sellerSkuParts);

        if ($name === '' || mb_strlen($name) < 3) {
            return null;
        }

        return [
            'product_name' => $name,
            'sku' => null,
            'seller_sku' => $sellerSku ?: null,
            'quantity' => $qty,
            'raw_line' => trim(implode(' | ', $buffer).' | qty='.$qty),
        ];
    }

    /**
     * Shopee: tabel "# Nama Produk SKU Variasi Qty".
     *
     * Strategi multi-fallback:
     *   (A) Blok antara header "Qty\n" dan "Pesan:" / "No.Pesanan" / \z
     *       — paling stabil, mengatasi kasus No.Pesanan diletakkan di bawah Pesan.
     *   (B) Blok antara "No.Pesanan: XXX" dan "Pesan:" — layout Shopee lama.
     *   (C) Seluruh teks halaman.
     *
     * Setiap blok diparse dengan:
     *   1. parseShopeeMultilineRows — qty berdiri sendiri di satu baris,
     *      nama/SKU/variasi bisa wrap ke beberapa baris (termasuk mid-word).
     *   2. parseShopeeRowsFromBlock — qty di ujung baris yang sama (layout lama).
     *   3. parseShopeeSingleLine — seluruh row jadi satu baris panjang.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractShopeeProductRows(string $text): array
    {
        // (0) SPX layout BARU dengan English headers (single-product per label):
        //
        //   Product Name
        //   <nama produk, bisa multi-line>
        //   SKU
        //   <sku atau "—" untuk kosong>
        //   Seller Note
        //   ...
        //
        //  Layout ini TIDAK punya header "Qty" dan TIDAK punya kolom variasi
        //  (single product, qty=1 implisit). Ditangani duluan supaya tidak
        //  jatuh ke fallback parser yang akan generate noise dari blok header.
        //
        //  Hanya fire kalau "Product Name" muncul sebagai header standalone
        //  (di baris sendiri, diikuti newline) dan TIDAK ada "Qty" header
        //  (yang menandakan layout lama dengan tabel multi-kolom).
        if (! preg_match('/\bQty\s*\n/i', $text)
            && preg_match(
                '/^Product\s*Name\s*\n+(.+?)\n+(?:SKU\s*\n+(.+?)\n+)?(?:Seller\s*Note|Penerima\s*[:\-]|Order\s*ID\s*\n|\z)/ism',
                $text,
                $m
            )
        ) {
            $name = trim((string) preg_replace('/\s+/', ' ', $m[1]));
            $sku = isset($m[2]) ? trim($m[2]) : '';
            // SKU "—" / "-" / "–" / "N/A" artinya kosong.
            if (in_array($sku, ['—', '-', '–', 'N/A', 'NA', 'n/a', '', '–'], true)) {
                $sku = '';
            }
            if ($name !== '' && mb_strlen($name) >= 3) {
                return [[
                    'product_name' => $name,
                    'sku' => $sku !== '' ? $sku : null,
                    'seller_sku' => null,
                    'quantity' => 1,
                    'raw_line' => $name,
                ]];
            }
        }

        // (A) Anchor kuat: header tabel "Qty\n" → seller-note "Pesan:" / dll.
        //     Dipakai duluan karena No.Pesanan kadang diletakkan DI BAWAH Pesan.
        $block = null;
        if (preg_match(
            '/\bQty\b\s*\n(.+?)(?:Pesan\s*[:\(]|Order\s*ID\s*[:\-]|No\.?\s*Pesanan\s*[:\-]|\z)/is',
            $text,
            $m
        )) {
            $block = $m[1];
        }

        // (B) Fallback: setelah "No.Pesanan: XXX" sampai "Pesan:" (layout lama).
        if ($block === null && preg_match(
            '/No\.?\s*Pesanan\s*[:\-]\s*[A-Z0-9]+\s*\n?(.+?)(?:Pesan\s*[:\(]|Order\s*ID\s*[:\-]|\z)/is',
            $text,
            $m
        )) {
            $block = $m[1];
        }

        // (C) Seluruh teks — akan difilter per baris.
        if ($block === null) {
            $block = $text;
        }

        // Parse: multi-line (qty di baris sendiri) dulu, baru fallback ke legacy.
        $rows = $this->parseShopeeMultilineRows((string) $block);

        if (empty($rows)) {
            $rows = $this->parseShopeeRowsFromBlock((string) $block);
        }

        // Fallback terakhir: teks PDF satu baris panjang tanpa newline.
        if (empty($rows)) {
            $rows = $this->parseShopeeSingleLine($text);
        }

        // Fallback inline (label ECO/Anteraja non-SPX): satu baris
        // "N <nama> <variasi><qty>" di mana kolom SKU kosong & Qty NYATU ke
        // ujung variasi tanpa spasi. Pakai teks "Pesan:" sbg penanda variasi.
        if (empty($rows)) {
            $rows = $this->parseShopeeInlineRowFromText($text);
        }

        return $rows;
    }

    /**
     * Parse block Shopee di mana tiap kolom (nama / SKU / variasi / qty) bisa
     * wrap ke baris sendiri. Layout typical:
     *
     *   1Stir kayu Palang ...   <- index + nama (kadang tanpa spasi)
     *   Ring 15 &14 inc         <- sambungan nama
     *   R14                     <- SKU
     *   Silver,Dus+bubl         <- variasi
     *   e                       <- sambungan variasi (wrap mid-word)
     *   1                       <- qty (baris sendiri)
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseShopeeMultilineRows(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
        if (empty($lines)) {
            return [];
        }

        // 1. Buang baris noise yang bisa nyasar ke dalam blok produk.
        //    SHPE... = barcode/resi label Shopee non-SPX (mis. Shopee + Pos
        //    Indonesia REG). Sama seperti SPXID pada label SPX, teks barcode ini
        //    berulang di seluruh label dan sering bocor ke dalam blok produk;
        //    kalau tidak dibuang, baris SHPE menutup baris Qty asli sehingga
        //    qty gagal terbaca dan item "tidak terdeteksi".
        $lines = array_values(array_filter($lines, function ($line) {
            return ! preg_match(
                '/^(SPXID\d+|SHPE[0-9A-Z]{6,}|No\.?\s*Resi|J[XPY]\d{8,}|LOP[- ]?[A-Z]?[- ]?\d+|V\s*[-]\s*\d+|ECO$|COD$|Shop$|tokopedia|Pesan\s*[:\(]|Order\s*ID\s*[:\-]|No\.?\s*Pesanan|Pengirim\s*[:\-]?|#\s*Nama\s*Produk|Nama\s*Produk\s+SKU|Variasi\s+Qty|Qty\s*Total|Batas\s*Kirim|Berat\s*[:\-])/i',
                $line
            );
        }));

        // 2. Split index yang nempel ke nama: "1Stir kayu..." atau "1 Stir kayu..."
        //    -> ["1", "Stir kayu..."]
        $normalized = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\d{1,2})\s*([A-Za-z].+)$/u', $line, $m)) {
                $normalized[] = $m[1];
                $normalized[] = trim($m[2]);
            } else {
                $normalized[] = $line;
            }
        }

        // 3. Baca token: pure digit = batas row (index baru ATAU qty row sekarang).
        //    Tapi kalau baris konten berakhir " <digit>" (qty inline), flush
        //    langsung tanpa menunggu baris digit terpisah.
        $rows = [];
        $buffer = [];
        $rowStarted = false;

        $count = count($normalized);
        for ($idx = 0; $idx < $count; $idx++) {
            $line = $normalized[$idx];

            if (preg_match('/^\d{1,3}$/', $line)) {
                $num = (int) $line;

                if (! $rowStarted && empty($buffer)) {
                    // Awal row: angka ini adalah nomor urut.
                    $rowStarted = true;
                    continue;
                }

                if (! empty($buffer)) {
                    // ------------------------------------------------------------
                    // Disambiguasi INDEX vs QTY.
                    //
                    // Kalau baris konten TERAKHIR di buffer sudah berakhir dengan
                    // qty inline (mis. "... Wood corak 1") DAN angka ini diikuti
                    // oleh baris KONTEN (bukan angka lagi), maka angka ini adalah
                    // NOMOR URUT produk BERIKUTNYA — BUKAN qty produk sekarang.
                    //
                    // Tanpa cek ini, nomor urut "2" milik produk kedua salah
                    // dibaca sebagai qty produk pertama. Akibatnya
                    // maxQtyFromRows() jadi 2 dan tiap item combo terpotong 2×
                    // per produk (bug: "ngurang 2 per produk").
                    // ------------------------------------------------------------
                    $lastLine = (string) end($buffer);
                    $nextLine = $idx + 1 < $count ? $normalized[$idx + 1] : null;
                    $nextIsContent = $nextLine !== null && ! preg_match('/^\d{1,3}$/', $nextLine);

                    if ($nextIsContent && preg_match('/^(.+?)\s+(\d{1,3})$/', $lastLine, $lastInline)) {
                        // Flush row SEKARANG pakai qty inline dari baris terakhir,
                        // lalu perlakukan angka ini sebagai nomor urut row berikutnya.
                        [$inlineQty, $leftoverDigits] = $this->splitTrailingQty($lastInline[2]);
                        $contentHead = trim($lastInline[1]);
                        if ($leftoverDigits !== '') {
                            $contentHead = trim($contentHead.' '.$leftoverDigits);
                        }
                        $buffer[array_key_last($buffer)] = $contentHead;
                        $row = $this->buildShopeeRowFromBuffer($buffer, $inlineQty);
                        if ($row !== null) {
                            $rows[] = $row;
                        }
                        $buffer = [];
                        $rowStarted = true; // angka ini = nomor urut row berikutnya
                        continue;
                    }

                    // ------------------------------------------------------------
                    // Angka nyasar dari KOLOM yang wrap (nama/variasi panjang
                    // ke-pecah ke baris sendiri, mis. "...R15" lalu "2").
                    // QTY yang benar adalah angka TERAKHIR sebelum batas row
                    // (nomor urut row berikutnya / akhir blok). Kalau angka ini
                    // BUKAN angka terakhir sebelum batas, dia bagian dari konten
                    // (variasi) yang wrap — masukkan ke buffer, JANGAN dijadikan
                    // qty. Tanpa ini, "2" nyasar salah jadi qty=2 sehingga stok
                    // combo terpotong 2× per produk (bug: "ngurang 2 per produk").
                    // ------------------------------------------------------------
                    $boundaryAfter = ($idx + 1 >= $count)
                        || $this->isShopeeRowIndex($normalized, $idx + 1, $count);
                    if (! $boundaryAfter) {
                        $buffer[] = $line;
                        continue;
                    }

                    // Angka ini adalah qty — akhiri row sekarang.
                    $row = $this->buildShopeeRowFromBuffer($buffer, $num);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                    $buffer = [];
                    $rowStarted = false;
                    continue;
                }

                // Buffer kosong tapi rowStarted=true → dua angka berturut,
                // anggap angka kedua sebagai index ulang. Skip.
                continue;
            }

            // Baris konten: masukkan ke buffer apa adanya. Deteksi qty inline
            // (mis. "Tombol Klakson 1" / "... Wood corak 1") ditangani saat
            // flush — lihat cabang angka di atas (disambiguasi INDEX vs QTY)
            // dan step 4 (flush sisa buffer di akhir loop).
            $buffer[] = $line;
        }

        // 4. Flush buffer yang tersisa: kalau buffer berisi konten dan baris terakhirnya
        //    berakhir dengan angka (qty inline), strip qty dari baris tersebut dan flush.
        if (! empty($buffer) && $rowStarted) {
            $lastLine = (string) end($buffer);
            $inlineQty = null;
            if (preg_match('/^(.+?)\s+(\d{1,3})$/', $lastLine, $inlineMatch)) {
                // Qty dipisah spasi: "... Hitam polos 1"
                [$inlineQty, $leftoverDigits] = $this->splitTrailingQty($inlineMatch[2]);
                $contentHead = trim($inlineMatch[1]);
                if ($leftoverDigits !== '') {
                    $contentHead = trim($contentHead.' '.$leftoverDigits);
                }
                $buffer[array_key_last($buffer)] = $contentHead;
            } elseif (preg_match('/^(.+[A-Za-z\)\]])(\d{1,2})$/u', $lastLine, $gluedMatch)) {
                // Qty NEMPEL ke ujung kolom tanpa spasi: "... Hitam polos1".
                // smalot/pdfparser sering menempelkan angka Qty langsung ke
                // ujung kolom variasi (dan nomor urut ke awal nama), sehingga
                // regex qty-berspasi di atas gagal dan row tidak pernah
                // terbentuk -> item "tidak terdeteksi". Tangani di sini.
                $inlineQty = (int) $gluedMatch[2];
                $buffer[array_key_last($buffer)] = trim($gluedMatch[1]);
            }
            if ($inlineQty !== null) {
                $row = $this->buildShopeeRowFromBuffer($buffer, $inlineQty);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * Apakah $normalized[$j] adalah NOMOR URUT row (bukan qty)?
     *
     * Nomor urut row = baris pure-digit yang LANGSUNG diikuti baris KONTEN
     * (bukan digit). Dipakai untuk menentukan batas antar-row saat kolom
     * nama/variasi wrap ke beberapa baris dan menyisakan angka nyasar.
     *
     * @param array<int, string> $normalized
     */
    private function isShopeeRowIndex(array $normalized, int $j, int $count): bool
    {
        if ($j >= $count || ! preg_match('/^\d{1,3}$/', $normalized[$j])) {
            return false;
        }
        $k = $j + 1;

        return $k < $count && ! preg_match('/^\d{1,3}$/', $normalized[$k]);
    }

    /**
     * Susun row Shopee dari buffer baris-konten + qty yang sudah diketahui.
     *
     * Heuristik:
     *   - Cari baris yang "terlihat seperti SKU": token pendek alfanumerik
     *     tanpa spasi/koma/plus, minimal 1 huruf. Baris pertama dilewati
     *     (itu nama produk), baris terakhir juga tidak diprioritaskan.
     *   - nama = semua baris SEBELUM SKU, digabung dengan smart-join.
     *   - variasi = semua baris SESUDAH SKU, digabung dengan smart-join.
     *   - Kalau SKU tidak ketemu, baris terakhir yang mengandung ',' atau '+'
     *     dianggap variasi; sisanya nama.
     *
     * @param array<int, string> $buffer
     * @return ?array<string, mixed>
     */
    /**
     * Pisahkan qty dari digit yang MENEMPEL ke ujung kolom variasi.
     *
     * smalot/pdfparser sering menempelkan angka Qty langsung ke ujung kolom
     * variasi tanpa spasi. Kalau variasi ITU SENDIRI berakhir angka
     * (mis. "Semi Silver 02"), qty "1" nempel jadi "Semi Silver 021" dan regex
     * qty menangkap "021" lalu (int) "021" === 21 -> stok terpotong 21x padahal
     * cuma 1 produk (bug: "ngurang 21 padahal 1 produk").
     *
     * Qty asli TIDAK PERNAH punya leading zero, jadi digit-run yang diawali "0"
     * (mis. "021") menandakan penggabungan: digit paling belakang = qty asli,
     * sisanya (termasuk "0") dikembalikan ke konten variasi.
     *
     * Return: [int qty, string sisaDigitUntukVariasi].
     *
     * @return array<int, mixed>
     */
    private function splitTrailingQty(string $digits): array
    {
        if (strlen($digits) >= 2 && $digits[0] === '0') {
            return [max(1, (int) substr($digits, -1)), substr($digits, 0, -1)];
        }

        return [max(1, (int) $digits), ''];
    }

    private function buildShopeeRowFromBuffer(array $buffer, int $qty): ?array
    {
        if (empty($buffer)) {
            return null;
        }

        // Kasus baris TUNGGAL dengan kolom dipisah 2+ spasi (row muat dalam 1
        // baris fisik; kolom SKU/variasi tidak wrap ke baris sendiri):
        //   "STIR KAYU R12 NARDI UNIVERSAL  Hitam polos"   -> nama | variasi
        //   "STIR KAYU ...  R14  Hitam,Dus"                -> nama | SKU | variasi
        // cleanText() mempertahankan 2 spasi sebagai penanda kolom, sehingga
        // variasi biasa (mis. "Hitam polos", tanpa koma/plus) tidak lagi salah
        // tergabung ke nama produk (yang sebelumnya bikin item "tidak terbaca"
        // karena nama produk jadi tidak cocok dengan master).
        if (count($buffer) === 1 && preg_match('/\S\s{2,}\S/', $buffer[0])) {
            $cols = array_values(array_filter(
                array_map('trim', preg_split('/\s{2,}/', $buffer[0]) ?: []),
                fn ($c) => $c !== ''
            ));
            if (count($cols) >= 2) {
                $name = $cols[0];
                $sku = null;
                $variation = null;

                if (count($cols) >= 3) {
                    // nama | SKU | variasi (variasi bisa >1 kolom, gabungkan).
                    $sku = $cols[1] !== '' ? $cols[1] : null;
                    $variation = trim(implode(' ', array_slice($cols, 2)));
                } elseif (preg_match('/^[A-Z0-9][A-Z0-9\-_\.]{1,14}$/i', $cols[1])
                    && ! preg_match('/[a-z]{3,}/', $cols[1])) {
                    // Kolom ke-2 terlihat seperti kode SKU (pendek, tanpa spasi,
                    // bukan kata biasa) -> SKU; variasi kosong.
                    $sku = $cols[1];
                } else {
                    // Kolom ke-2 = variasi (kolom SKU kosong).
                    $variation = $cols[1];
                }

                if (mb_strlen($name) >= 3) {
                    return [
                        'product_name' => $name,
                        'sku' => $sku ?: null,
                        'seller_sku' => $variation ?: null,
                        'quantity' => $qty,
                        'raw_line' => trim($buffer[0].' | qty='.$qty),
                    ];
                }
            }
        }

        // Nama produk yang WRAP karena baris diakhiri kata sambung
        // ("dan", "atau", "&", "+"). Baris berikutnya (mis. "R15" pada
        // "...R14 dan" + "R15") adalah LANJUTAN NAMA, bukan SKU. Pada layout
        // Shopee/SPX ini, kalau nama sampai wrap begini, kolom SKU KOSONG dan
        // seluruh baris setelah nama adalah variasi.
        //   Sebelum fix: nama="...R14 dan", SKU="R15" (salah).
        //   Sesudah fix: nama="...R14 dan R15", SKU=null, variasi=sisa baris.
        $conjEnd = 0;
        $bufCount = count($buffer);
        while ($conjEnd + 1 < $bufCount
            && preg_match('/(?:\bdan\b|\batau\b|&|\+)\s*$/iu', trim((string) $buffer[$conjEnd]))) {
            $conjEnd++;
        }
        if ($conjEnd > 0) {
            $name = $this->smartJoinShopeeLines(array_slice($buffer, 0, $conjEnd + 1));
            $variation = $this->smartJoinShopeeLines(array_slice($buffer, $conjEnd + 1));
            if ($name !== '' && mb_strlen($name) >= 3) {
                return [
                    'product_name' => $name,
                    'sku' => null,
                    'seller_sku' => $variation ?: null,
                    'quantity' => $qty,
                    'raw_line' => trim(implode(' | ', $buffer).' | qty='.$qty),
                ];
            }
        }

        $skuIdx = null;
        foreach ($buffer as $i => $line) {
            if ($i === 0) {
                // Baris pertama = nama produk, jangan diklaim SKU.
                continue;
            }
            if (mb_strlen($line) > 20) {
                continue;
            }
            if (str_contains($line, ' ') || str_contains($line, ',') || str_contains($line, '+')) {
                continue;
            }
            if (! preg_match('/^[A-Z0-9][A-Z0-9\-_\.]{0,14}$/i', $line)) {
                continue;
            }
            if (! preg_match('/[A-Za-z]/', $line)) {
                // Pure digits: kemungkinan sisa nomor, bukan SKU.
                continue;
            }
            if (preg_match('/[a-z]{3,}/', $line)) {
                // Kata biasa (mis. "Hitam", "Silver") = VARIASI/warna, BUKAN
                // kode SKU. Kode SKU khas huruf-besar + angka (R14, ND01).
                // Kalau warna variasi salah diklaim jadi SKU, dia tidak ikut
                // labelText (nama + variasi) sehingga pemilihan varian warna
                // di resolver gagal. Konsisten dengan heuristik SKU di cabang
                // baris-tunggal.
                continue;
            }
            $skuIdx = $i;
            break;
        }

        $sku = null;
        if ($skuIdx !== null) {
            $nameParts = array_slice($buffer, 0, $skuIdx);
            $sku = $buffer[$skuIdx];
            $variationParts = array_slice($buffer, $skuIdx + 1);
        } else {
            // Tidak ketemu SKU: coba pisah baris terakhir sebagai variasi
            // kalau mengandung koma/plus (ciri khas variasi Shopee), ATAU
            // kalau baris terakhir jelas satu kata VARIASI/warna (mis. "Hitam")
            // yang berdiri sendiri setelah nama produk yang wrap ke beberapa
            // baris. Tanpa ini, warna "Hitam" ikut tergabung ke nama produk.
            $lastIdx = count($buffer) - 1;
            if ($lastIdx > 0 && preg_match('/[,+]/', $buffer[$lastIdx])) {
                $nameParts = array_slice($buffer, 0, $lastIdx);
                $variationParts = [$buffer[$lastIdx]];
            } elseif ($lastIdx > 0 && $this->looksLikeShopeeVariation($buffer[$lastIdx])) {
                $nameParts = array_slice($buffer, 0, $lastIdx);
                $variationParts = [$buffer[$lastIdx]];
            } else {
                $nameParts = $buffer;
                $variationParts = [];
            }
        }

        $name = $this->smartJoinShopeeLines($nameParts);
        $variation = $this->smartJoinShopeeLines($variationParts);

        if ($name === '' || mb_strlen($name) < 3) {
            return null;
        }

        return [
            'product_name' => $name,
            'sku' => $sku ?: null,
            'seller_sku' => $variation ?: null,
            'quantity' => $qty,
            'raw_line' => trim(implode(' | ', $buffer).' | qty='.$qty),
        ];
    }

    /**
     * Apakah $line jelas merupakan satu kata VARIASI/warna yang berdiri
     * sendiri (mis. "Hitam", "Silver", "Merah")?
     *
     * Dipakai saat kolom SKU kosong dan nama produk wrap ke beberapa baris,
     * sehingga warna variasi jatuh sebagai baris terakhir tanpa koma/plus.
     * Sengaja konservatif: HANYA token tunggal (tanpa spasi) yang cocok
     * kamus warna, supaya lanjutan NAMA produk tidak salah dianggap variasi.
     */
    private function looksLikeShopeeVariation(string $line): bool
    {
        $t = trim($line);
        if ($t === '' || str_contains($t, ' ')) {
            return false;
        }

        $norm = mb_strtolower((string) preg_replace('/[^A-Za-z]/', '', $t));
        if ($norm === '') {
            return false;
        }

        static $colors = [
            'hitam', 'putih', 'merah', 'biru', 'hijau', 'kuning', 'abu',
            'abuabu', 'coklat', 'cokelat', 'oranye', 'jingga', 'ungu', 'silver',
            'perak', 'emas', 'krem', 'dongker', 'navy', 'marun', 'pink',
            'maroon', 'cream', 'gold', 'grey', 'gray', 'black', 'white', 'red',
            'blue', 'green', 'yellow', 'brown', 'orange', 'purple', 'beige',
            'tosca', 'toska',
        ];

        return in_array($norm, $colors, true);
    }

    /**
     * Smart-join: kalau baris BERIKUTNYA sangat pendek (<=3 char) dan
     * ekstensi mid-word yang wajar (huruf kecil, tanpa spasi/tanda baca)
     * sementara baris sebelumnya juga berakhir dengan huruf/angka — gabung
     * tanpa spasi (line-wrap mid-word). Selain itu, gabung dengan spasi.
     *
     * Contoh mid-word wrap (join tanpa spasi):
     *   ["Silver,Dus+bubl", "e"] -> "Silver,Dus+buble"
     *
     * Contoh baris lanjutan biasa (join dengan spasi):
     *   ["Kemeja batik pria modern", "lengan panjang"]
     *     -> "Kemeja batik pria modern lengan panjang"
     *
     * @param array<int, string> $parts
     */
    private function smartJoinShopeeLines(array $parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
        if (empty($parts)) {
            return '';
        }

        $result = $parts[0];
        $count = count($parts);
        for ($i = 1; $i < $count; $i++) {
            $curr = $parts[$i];

            $isMidWordWrap = preg_match('/[A-Za-z0-9]$/u', $result)
                && mb_strlen($curr) <= 3
                && preg_match('/^[a-z]+$/u', $curr);

            if ($isMidWordWrap) {
                $result .= $curr;
            } else {
                $result .= ' '.$curr;
            }
        }

        return trim($result);
    }

    /**
     * Parse block multi-line: cari baris yang diakhiri angka qty (1-3 digit)
     * dan diawali nomor urut (atau nomor urut di tengah kalau wrap).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseShopeeRowsFromBlock(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));

        $rows = [];
        $buffer = [];

        foreach ($lines as $line) {
            // Skip baris yang jelas noise (resi, footer)
            if (preg_match('/^(SPXID\d+|SHPE[0-9A-Z]{6,}|No\.?\s*Resi|LOP[- ][A-Z]?[- ]?\d+|V\s*[-]\s*\d+|Shop\s*$|tokopedia|Pesan\s*[:\(]|Order\s*ID\s*[:\-]|#\s*Nama\s*Produk|Nama\s*Produk\s+SKU|Variasi\s+Qty|^Qty\s*Total)/i', $line)) {
                $buffer = [];
                continue;
            }

            $buffer[] = $line;

            // Apakah baris ini punya qty di ujung? (spasi + angka 1-3 digit di akhir)
            if (! preg_match('/\s(\d{1,3})\s*$/', $line, $matchQty)) {
                continue;
            }

            $joined = implode(' ', $buffer);

            // Cari nomor urut di awal ATAU di tengah (kalau buffer kemasukan noise)
            //   Pola: "^1 " atau " 1 Stir..."
            if (preg_match('/(?:^|\s)(\d{1,2})\s+(\S.+)$/', $joined, $startMatch, PREG_OFFSET_CAPTURE)) {
                $startOffset = (int) $startMatch[0][1];
                $fromStart = ltrim(substr($joined, $startOffset));

                // Harus benar-benar diawali digit
                if (preg_match('/^\d{1,2}\s+/', $fromStart)) {
                    $row = $this->parseShopeeRowLine($fromStart);
                    if ($row !== null) {
                        $rows[] = $row;
                        $buffer = [];
                        continue;
                    }
                }
            }

            // Reset buffer kalau baris ini ketemu qty tapi tidak fit sebagai row
            $buffer = [];
        }

        return $rows;
    }

    /**
     * Fallback: teks PDF kadang jadi satu baris panjang tanpa newline.
     * Cari pola "1 <name> <sku> <variasi> <qty>" dengan regex global.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseShopeeSingleLine(string $text): array
    {
        $rows = [];

        // Ambil substring setelah "No.Pesanan:" kalau ada, supaya cuma kena
        // blok produk, bukan resi berulang.
        if (preg_match('/No\.?\s*Pesanan\s*[:\-]\s*[A-Z0-9]+(.+?)(?:Pesan\s*[:\(]|Order\s*ID|\z)/is', $text, $m)) {
            $text = $m[1];
        }

        // Pattern: nomor-urut  nama-produk  variasi-mengandung-koma-atau-plus  qty
        // Contoh: "1 Stir kayu Palang ... R14 Silver,Dus+buble 1"
        //   - ^\d+\s - nomor urut
        //   - (.+?) - nama produk (lazy)
        //   - (\S+[\+,][\S,+]*) - variasi: token mengandung + atau ,
        //   - \s(\d{1,3})\b - qty
        if (preg_match_all(
            '/\b(\d{1,2})\s+(.+?)(?:\s+([A-Z0-9][A-Z0-9\-_]{1,14}))?\s+(\S*[,+][\S,+]*)\s+(\d{1,3})\b/u',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $name = trim($m[2]);
                $sku = trim($m[3] ?? '');
                $variation = trim($m[4]);
                $qty = (int) $m[5];

                // Filter noise: name minimal 8 karakter supaya bukan angka random
                if (mb_strlen($name) < 5) {
                    continue;
                }

                $rows[] = [
                    'product_name' => $name,
                    'sku' => $sku ?: null,
                    'seller_sku' => $variation ?: null,
                    'quantity' => $qty,
                    'raw_line' => trim($m[0]),
                ];
            }
        }

        return $rows;
    }

    /**
     * Fallback untuk label Shopee non-SPX (ECO / Anteraja) dengan tabel produk
     * inline satu baris, mis.:
     *   "1 Bosskit Stir semua merk mobil isuzu giga NMR711"
     * di mana kolom SKU kosong dan kolom Qty MENYATU ke ujung variasi tanpa
     * spasi (variasi "NMR71" + qty "1" -> "NMR711"). Parser baris biasa gagal
     * karena tidak ada spasi sebelum qty.
     *
     * Strategi: pakai teks "Pesan:" (seller note) sebagai PENANDA variasi.
     * Kalau note core muncul di dalam konten baris, potong di situ:
     *   - sebelum match = nama produk
     *   - span yang cocok = variasi
     *   - sisa digit setelahnya = qty
     * Tanpa note, fallback: ambil digit qty di ujung (spasi atau 1 digit yang
     * nempel ke huruf).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseShopeeInlineRowFromText(string $text): array
    {
        // Region: setelah header "Nama Produk ... Qty" sampai terminator.
        $region = $text;
        if (preg_match(
            '/(?:#\s*)?Nama\s*Produk[^\n]*Qty\s*\n(.+?)(?:\nPesan\s*[:\(]|\nQty\s*Total|\nKAB\b|\nPengirim|\nNo\.?\s*Resi|\z)/is',
            $text,
            $rm
        )) {
            $region = $rm[1];
        }

        // Seller note core sebagai penanda variasi (buang "(orderid)").
        $note = null;
        if (preg_match('/Pesan\s*[:\(]\s*(.+)/i', $text, $pm)) {
            $note = trim((string) preg_replace('/\s{2,}/', ' ', (string) preg_replace('/\(.*?\)/', '', $pm[1])));
            if ($note === '') {
                $note = null;
            }
        }

        $rows = [];
        foreach (preg_split('/\n/', $region) as $line) {
            $line = trim((string) $line);
            if (! preg_match('/^(\d{1,2})\s+([A-Za-z].+)$/u', $line, $m)) {
                continue;
            }
            $content = trim($m[2]);
            $name = $content;
            $variation = null;
            $qty = 1;

            if ($note !== null && mb_stripos($content, $note) !== false) {
                $idx = (int) mb_stripos($content, $note);
                $matched = mb_substr($content, $idx, mb_strlen($note));
                $after = trim((string) mb_substr($content, $idx + mb_strlen($note)));
                $before = trim((string) mb_substr($content, 0, $idx));
                $variation = $matched;
                $name = $before !== '' ? $before : $matched;
                if (preg_match('/^\d{1,3}$/', $after)) {
                    $qty = (int) $after;
                }
            } else {
                if (preg_match('/\s+(\d{1,3})$/', $content, $mq)) {
                    [$qty, $leftoverDigits] = $this->splitTrailingQty($mq[1]);
                    $name = trim((string) mb_substr($content, 0, mb_strlen($content) - mb_strlen($mq[0])));
                    if ($leftoverDigits !== '') {
                        $name = trim($name.' '.$leftoverDigits);
                    }
                } elseif (preg_match('/[A-Za-z](\d)$/u', $content, $mg)) {
                    $qty = (int) $mg[1];
                    $name = trim((string) mb_substr($content, 0, -1));
                }
            }

            if (mb_strlen($name) >= 3) {
                $rows[] = [
                    'product_name' => $name,
                    'sku' => null,
                    'seller_sku' => $variation ?: null,
                    'quantity' => $qty,
                    'raw_line' => $content,
                ];
            }
        }

        return $rows;
    }

    /**
     * Parse satu baris row Shopee yang sudah yakin diawali nomor urut.
     *
     * @return ?array<string, mixed>
     */
    private function parseShopeeRowLine(string $joined): ?array
    {
        // Buang nomor urut
        if (! preg_match('/^\d{1,2}\s+(.+)$/s', $joined, $m)) {
            return null;
        }
        $rest = trim($m[1]);

        // Qty di akhir
        if (! preg_match('/\s(\d{1,3})\s*$/', $rest, $mq)) {
            return null;
        }
        [$qty, $leftoverDigits] = $this->splitTrailingQty($mq[1]);
        $rest = trim(preg_replace('/\s\d{1,3}\s*$/', '', $rest));
        if ($leftoverDigits !== '') {
            $rest = trim($rest.' '.$leftoverDigits);
        }

        // Strategi 1: Split by 2+ whitespace
        $cols = preg_split('/\s{2,}/', $rest) ?: [];
        if (count($cols) >= 3) {
            return [
                'product_name' => trim($cols[0]),
                'sku' => trim($cols[1]) ?: null,
                'seller_sku' => trim($cols[2]) ?: null,
                'quantity' => $qty,
                'raw_line' => $rest,
            ];
        }

        // Strategi 2: scan dari kanan (heuristik)
        [$productName, $sku, $variation] = $this->splitShopeeRowFromRight($rest);

        // Validasi: produk minimal 5 char, supaya tidak tertukar dengan kode
        if (! $productName || mb_strlen($productName) < 5) {
            return null;
        }

        return [
            'product_name' => trim($productName),
            'sku' => $sku ? trim($sku) : null,
            'seller_sku' => $variation ? trim($variation) : null,
            'quantity' => $qty,
            'raw_line' => $rest,
        ];
    }

    /**
     * Pisah "Nama Produk  SKU  Variasi" dari kanan berdasarkan pola.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function splitShopeeRowFromRight(string $rest): array
    {
        $tokens = preg_split('/\s+/', $rest) ?: [];
        if (count($tokens) < 2) {
            return [$rest, null, null];
        }

        // Variasi = token terakhir kalau mengandung koma/plus/slash, atau
        //           1-2 token terakhir yang bukan kata umum.
        //   Kita ambil maks 2 token terakhir sebagai kandidat variasi.
        $variation = array_pop($tokens);

        // Cek kalau token variasi sangat pendek (<4 char) & token sebelumnya
        // juga "variasi-like", gabungkan.
        //   (Kasus wrap seperti "Silver,\nDus+buble")
        if (! empty($tokens)) {
            $prev = end($tokens);
            if (str_contains($variation, ',') === false
                && (str_contains($prev, ',') || str_ends_with($prev, ','))) {
                $variation = array_pop($tokens).' '.$variation;
            }
        }

        // SKU = token sebelum variasi (biasanya 2-10 karakter alfanumerik, tanpa spasi)
        $sku = null;
        if (! empty($tokens)) {
            $maybeSku = end($tokens);
            if (preg_match('/^[A-Z0-9][A-Z0-9\-_]{1,14}$/i', $maybeSku)) {
                array_pop($tokens);
                $sku = $maybeSku;
            }
        }

        $productName = implode(' ', $tokens);

        return [$productName ?: null, $sku, $variation];
    }

    /**
     * Seller Note / Pesan.
     *
     * Tiga variasi yang ditangani:
     *   - Shopee lama: "Pesan: <text>" (inline, separator colon/dash)
     *   - SPX baru:    "Seller Note\n<text>" (newline-separated, English header)
     *   - TikTok:      "Seller Note: <text>" (inline)
     *
     * Note yang isinya cuma kombinasi "(order_id) (resi)" / "(order_id)"
     * dianggap noise dan di-null-kan.
     */
    private function extractSellerNote(string $text, string $marketplace): ?string
    {
        if ($marketplace === 'shopee') {
            // Format Shopee lama: "Pesan: <text>"
            if (preg_match('/Pesan\s*[:\-]\s*([^\n]+)/i', $text, $m)) {
                $note = trim($m[1]);
                if (! $this->isJustIdNoise($note)) {
                    return $note;
                }
            }

            // Format SPX baru (English headers, newline-separated):
            //   "Seller Note\n(260522425RTFB8) (SPXID069712933975)"
            // Note yang cuma berisi (order_id)(resi) di-null-kan.
            if (preg_match('/Seller\s*Note\s*\n+([^\n]+)/i', $text, $m)) {
                $note = trim($m[1]);
                if (! $this->isJustIdNoise($note)) {
                    return $note;
                }
            }

            return null;
        }

        // TikTok / Tokopedia: "Seller Note: <text>"
        if (preg_match('/Seller\s*Note\s*[:\-]\s*([^\n]+)/i', $text, $m)) {
            $note = trim($m[1]);
            if (! $this->isJustIdNoise($note)) {
                return $note;
            }
        }

        return null;
    }

    /**
     * True kalau note hanya berisi kombinasi "(orderid)" / "(orderid) (resi)" —
     * bukan pesan asli dari pembeli, cuma duplikasi metadata yang sudah
     * tersimpan di field lain.
     */
    private function isJustIdNoise(string $note): bool
    {
        $note = trim($note);
        if ($note === '') {
            return true;
        }
        // "(XYZ123)" tunggal
        if (preg_match('/^\(\s*[A-Z0-9]+\s*\)\s*$/i', $note)) {
            return true;
        }
        // "(XYZ123) (ABC456)" — beberapa token kurung berisi ID alfanumerik
        if (preg_match('/^(\s*\(\s*[A-Z0-9]+\s*\)\s*)+$/i', $note)) {
            return true;
        }

        return false;
    }

    /**
     * Customer Message muncul di halaman ke-2 label J&T Express bulk-print
     * (TikTok Shop / Tokopedia). Format: "Customer Message: <pesan dari pembeli>".
     * Field ini SEPARATE dari Seller Note dan dipakai untuk merging ke primary
     * page lewat consolidatePages().
     */
    private function extractCustomerMessage(string $text): ?string
    {
        if (preg_match('/Customer\s*Message\s*[:\-]\s*([^\n]+)/i', $text, $m)) {
            $note = trim($m[1]);
            if ($note !== '') {
                return $note;
            }
        }

        return null;
    }
}
