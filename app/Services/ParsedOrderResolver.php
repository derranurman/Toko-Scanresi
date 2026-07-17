<?php

namespace App\Services;

use App\Models\ComboMapping;
use App\Models\Product;
use App\Models\Variant;

/**
 * Ubah hasil parse PDF menjadi daftar item yang siap dibuat OrderItem,
 * dengan resolusi ke Variant lewat:
 *  1. Combo Mapping (berdasarkan teks "Barang :" atau seller_sku)
 *  2. Per-row:
 *     a. Nama produk  — cari master yang name-nya substring dari teks label
 *        (label_name + seller_sku). Master "Skeleton Import R14" akan cocok
 *        dengan label "Stir Racing New Skeleton Import R14 Black".
 *     b. SKU          — exact match di Variant.sku atau Product.sku
 *     c. Seller Note  — cari master yang name-nya substring dari seller note
 *
 * Untuk memilih varian saat produk sudah ketemu, dipakai kamus warna
 * EN↔ID (Black↔Hitam dll) supaya "Black" di label bisa match varian
 * "Hitam" di master.
 *
 * Output item:
 *   [
 *     'product_name' => string,
 *     'variant_name' => ?string,
 *     'sku' => ?string,
 *     'variant_id' => ?int,
 *     'quantity' => int,
 *     'source' => 'combo' | 'sku' | 'name' | 'seller_note' | 'unmatched',
 *     'matched_keyword' => ?string,
 *   ]
 */
class ParsedOrderResolver
{
    /**
     * Keyword yang menandakan pesanan punya item TAMBAHAN selain produk
     * utama (mis. boskit/bosskit dijual bareng stir, atau solder addon).
     *
     * Kalau seller_sku ATAU seller_note mengandung salah satu keyword di
     * bawah, per-row resolution tidak akan auto-match — item dikembalikan
     * sebagai 'unmatched' supaya user EXPLICITLY bikin Combo Mapping yang
     * mendefinisi apa saja yang harus dikirim (stir + boskit + dst.).
     *
     * Tujuannya: cegah auto-match yang nge-skip item-item addon, yang
     * akibatnya barang yang dikirim ke pembeli kurang.
     *
     * @var array<int, string>
     */
    private const COMBO_HINT_KEYWORDS = [
        'boskit',
        'bosskit',
    ];

    /**
     * Kamus warna EN↔ID. Dipakai dua arah saat matching varian.
     *
     * @var array<string, array<int, string>>
     */
    private const COLOR_SYNONYMS = [
        'black'  => ['hitam'],
        'white'  => ['putih'],
        'red'    => ['merah'],
        'blue'   => ['biru'],
        'green'  => ['hijau'],
        'yellow' => ['kuning'],
        'grey'   => ['abu', 'abu-abu', 'gray'],
        'gray'   => ['abu', 'abu-abu', 'grey'],
        'brown'  => ['coklat', 'cokelat'],
        'orange' => ['oranye', 'jingga'],
        'pink'   => ['merah muda', 'merahmuda'],
        'purple' => ['ungu'],
        'silver' => ['silver', 'perak'],
        'gold'   => ['emas'],
        'cream'  => ['krem'],
        'navy'   => ['dongker', 'navy'],
        'maroon' => ['marun'],
        'hitam'  => ['black'],
        'putih'  => ['white'],
        'merah'  => ['red'],
        'biru'   => ['blue'],
        'hijau'  => ['green'],
        'kuning' => ['yellow'],
        'abu'    => ['grey', 'gray'],
        'coklat' => ['brown'],
        'cokelat'=> ['brown'],
        'ungu'   => ['purple'],
        'perak'  => ['silver'],
        'emas'   => ['gold'],
    ];

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed> { items, warnings, matched_keyword }
     */
    public function resolve(array $parsed): array
    {
        $items = [];
        $warnings = [];
        $matchedKeyword = null;
        $sellerNote = trim((string) ($parsed['seller_note'] ?? ''));
        // Pengali combo: berapa kali combo di-apply untuk label ini.
        // JANGAN dijumlah antar-baris — kalau label punya beberapa PRODUK
        // BERBEDA (mis. "Stir kayu" qty 1 + "Bosskit" qty 1), penjumlahan
        // (1+1=2) akan salah menggandakan tiap item combo jadi 2. Pakai qty
        // TERTINGGI antar-baris: kalau satu produk dipesan qty 2, combo tetap
        // ×2; kalau beberapa produk masing-masing qty 1, combo ×1.
        $orderQty = $this->maxQtyFromRows($parsed['product_rows'] ?? []) ?: 1;

        // =====================================================================
        // STRATEGI 2 (FONDASI) — resolusi per baris produk di label.
        // =====================================================================
        // Selalu jalan duluan. product_rows dianggap "kebenaran utama" (apa
        // yang tertulis di tabel produk label). Combo cuma additive di atasnya.
        foreach ($parsed['product_rows'] ?? [] as $row) {
            $resolved = $this->resolveRow($row, $sellerNote);
            $items[] = $resolved;
            if ($resolved['source'] === 'unmatched') {
                $sellerSkuRow = trim((string) ($row['seller_sku'] ?? ''));
                $hintReason = $this->comboHintReason($sellerSkuRow, $sellerNote);
                if ($hintReason === 'seller_note') {
                    $warnings[] = "Baris produk '{$resolved['product_name']}' ada Seller Note (\"{$sellerNote}\"). Cek isinya — mungkin pembeli minta addon/freebie. Klik 'Atur Mapping' untuk definisi item lengkap.";
                } elseif ($hintReason === 'sku_keyword') {
                    $warnings[] = "Baris produk '{$resolved['product_name']}' kelihatannya pesanan combo (mengandung 'boskit'/'bosskit' di SKU). Klik 'Atur Mapping' untuk definisi item lengkap (stir + boskit + dst.).";
                } else {
                    $warnings[] = "Baris produk '{$resolved['product_name']}' belum cocok dengan master. Tambahkan Combo Mapping atau padankan SKU.";
                }
            }
        }

        // Fallback: kalau tidak ada product_rows sama sekali, simpan
        // barang_keyword sebagai item unmatched.
        if (empty($items) && ! empty($parsed['barang_keyword'])) {
            $items[] = [
                'product_name' => $parsed['barang_keyword'],
                'variant_name' => null,
                'sku' => null,
                'variant_id' => null,
                'quantity' => 1,
                'source' => 'unmatched',
                'matched_keyword' => null,
            ];
            $warnings[] = "Tidak menemukan tabel produk. Disimpan sebagai '{$parsed['barang_keyword']}'. Buat combo mapping untuk ini.";
        }

        // =====================================================================
        // STRATEGI 1 — combo mapping dari teks label (barang_keyword +
        // product_name + seller_sku + sku). ADDITIVE.
        // =====================================================================
        //
        // Kalau combo match, item-nya DITAMBAHKAN. Tidak menggantikan stir
        // dari per-row. Use case:
        //   product_rows: STIR RACING (matched by name)
        //   seller_sku  : "boskit+stir, TOYOTA LAMA"
        //   Combo       : "boskit+stir, TOYOTA LAMA" → 1 boskit
        //   Output      : 1 stir + 1 boskit
        //
        // Edge case: kalau per-row HANYA punya item unmatched (mis. label
        // berisi "PROMO BUNDLE 123" yang nggak ada master-nya) DAN combo
        // match, kita drop item unmatched-nya, karena combo jelas mendefinisi
        // ulang apa yang harus dikirim untuk label ini.
        $combo = $this->findComboForLabel($parsed);
        if ($combo) {
            $matchedKeyword = $combo->keyword;

            // Drop semua item unmatched dari per-row + warning-nya, karena
            // combo nge-cover label ini.
            $hadUnmatched = false;
            $items = array_values(array_filter($items, function ($i) use (&$hadUnmatched) {
                if (($i['source'] ?? null) === 'unmatched') {
                    $hadUnmatched = true;
                    return false;
                }
                return true;
            }));
            if ($hadUnmatched) {
                $warnings = array_values(array_filter(
                    $warnings,
                    fn ($w) => ! str_starts_with($w, 'Baris produk ')
                        && ! str_starts_with($w, 'Tidak menemukan tabel produk')
                ));
            }

            foreach ($combo->items as $ci) {
                $v = $ci->variant;
                if (! $v) {
                    $warnings[] = "Combo '{$combo->keyword}' memiliki item yang referensinya sudah terhapus.";
                    continue;
                }
                $items[] = [
                    'product_name' => $v->product?->name ?? '—',
                    'variant_name' => $v->name,
                    'sku' => $v->sku,
                    'variant_id' => $v->id,
                    'quantity' => $ci->quantity * $orderQty,
                    'source' => 'combo',
                    'matched_keyword' => $combo->keyword,
                    // ID combo mapping yang men-trigger item ini, dipakai
                    // tombol "Edit Mapping" di pratinjau supaya modal bisa
                    // pre-fill keyword/items dari mapping yang persis ini.
                    'combo_mapping_id' => $combo->id,
                ];
            }
        }

        // =====================================================================
        // STRATEGI 1b — combo mapping dari SELLER NOTE. ADDITIVE.
        // =====================================================================
        //
        // Match SEMUA keyword yang cocok (bukan hanya yang pertama), supaya
        // keyword pendek seperti "T2" bisa ketangkap di samping keyword
        // panjang seperti "+Bosskit Ferio".
        //
        // Use case:
        //   product_rows: STIR RACING (matched by name → Strategi 2)
        //   seller_note : "t16 solder"
        //   Combo       : "t16 solder" → 1 bosskit T16
        //   Output      : 1 stir + 1 bosskit T16
        if ($sellerNote !== '') {
            $excludeIds = $combo ? [$combo->id] : [];
            $noteCombos = $this->findAllCombos($sellerNote, $excludeIds);

            if (! empty($noteCombos)) {
                foreach ($noteCombos as $noteCombo) {
                    foreach ($noteCombo->items as $ci) {
                        $v = $ci->variant;
                        if (! $v) {
                            $warnings[] = "Combo '{$noteCombo->keyword}' (dari Seller Note) memiliki item yang referensinya sudah terhapus.";
                            continue;
                        }
                        $items[] = [
                            'product_name' => $v->product?->name ?? '—',
                            'variant_name' => $v->name,
                            'sku' => $v->sku,
                            'variant_id' => $v->id,
                            'quantity' => $ci->quantity * $orderQty,
                            'source' => 'combo',
                            'matched_keyword' => $noteCombo->keyword.' (Seller Note)',
                            'combo_mapping_id' => $noteCombo->id,
                        ];
                    }

                    if (! $matchedKeyword) {
                        $matchedKeyword = $noteCombo->keyword.' (Seller Note)';
                    } else {
                        $matchedKeyword .= ' + '.$noteCombo->keyword.' (Note)';
                    }
                }
            }
        }

        // Gabungkan item dengan variant_id yang sama. Lihat mergeItemsByVariant
        // — sekarang pakai MAX qty (bukan SUM) supaya per-row + combo yang
        // ngepoint ke varian sama tidak double-potong stok.
        $items = $this->mergeItemsByVariant($items);

        return compact('items', 'warnings', 'matchedKeyword');
    }

    private function maxQtyFromRows(array $rows): int
    {
        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, (int) ($r['quantity'] ?? 0));
        }

        return $max;
    }

    /**
     * Deteksi apakah baris ini punya "combo hint" — sinyal bahwa pesanan
     * kemungkinan punya item TAMBAHAN selain produk utama. Kalau iya,
     * per-row resolution tidak akan auto-match — user harus eksplisit
     * bikin Combo Mapping.
     *
     * Trigger:
     *   1. Seller note non-kosong. Seller note umumnya berisi instruksi
     *      tambahan dari pembeli (addon, freebie, "t16 solder", dst.).
     *      Lebih safe dianggap perlu mapping manual daripada auto-match
     *      stir-nya doang sementara addon-nya ke-skip.
     *   2. Seller SKU mengandung keyword combo (mis. "boskit+stir,
     *      TOYOTA LAMA" → ada "boskit").
     *
     * Cegah skenario di mana stir auto-resolved tapi bosskit-nya ke-skip
     * karena gak ada di product_rows.
     *
     * @return string|null Alasan trigger ('seller_note' / 'sku_keyword' /
     *                     null kalau tidak match)
     */
    private function comboHintReason(string $sellerSku, string $sellerNote): ?string
    {
        // 1. Seller note ada → trigger.
        if (trim($sellerNote) !== '') {
            return 'seller_note';
        }

        // 2. Seller SKU mengandung keyword combo.
        $haystack = mb_strtolower(trim($sellerSku));
        if ($haystack !== '') {
            foreach (self::COMBO_HINT_KEYWORDS as $hint) {
                if (mb_stripos($haystack, $hint) !== false) {
                    return 'sku_keyword';
                }
            }
        }

        return null;
    }

    private function hasComboHint(string $sellerSku, string $sellerNote): bool
    {
        return $this->comboHintReason($sellerSku, $sellerNote) !== null;
    }

    /**
     * Resolve satu baris produk ke Variant master.
     *
     * Urutan strategi (stop di yang pertama match):
     *   1. NAMA  — cari Product yang namanya substring dari label text
     *              (label_name + seller_sku). Lalu pilih varian yang name-nya
     *              cocok di label text juga (dengan kamus warna EN↔ID).
     *   2. SKU   — exact match di Variant.sku, lalu Product.sku
     *   3. NOTE  — cari Product yang namanya substring dari seller note
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function resolveRow(array $row, string $sellerNote = ''): array
    {
        $qty = max(1, (int) ($row['quantity'] ?? 1));
        $name = trim((string) ($row['product_name'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $sellerSku = trim((string) ($row['seller_sku'] ?? ''));

        // Teks pencarian untuk strategi nama: nama + variasi (seller_sku)
        // supaya "Black" dari kolom variasi ikut jadi sinyal pemilihan varian.
        $labelText = trim($name.' '.$sellerSku);

        // ---- Combo hint guard ----
        // Kalau seller_sku atau seller_note mengandung keyword combo
        // (boskit/bosskit/dst.), skip auto-match. Pesanan ini kemungkinan
        // punya item tambahan, jadi user harus bikin Combo Mapping yang
        // mendefinisi semua item secara eksplisit.
        if ($this->hasComboHint($sellerSku, $sellerNote)) {
            return [
                'product_name' => $name ?: '—',
                'variant_name' => $sellerSku ?: ($sku ?: null),
                'sku' => $sku ?: null,
                'variant_id' => null,
                'quantity' => $qty,
                'source' => 'unmatched',
                'matched_keyword' => null,
            ];
        }

        // ---- 1. Match via NAMA produk ----
        $byName = $this->matchByProductName($labelText);
        if ($byName !== null) {
            return $this->makeItem($byName['variant'], $name, $qty, 'name', $byName['reason']);
        }

        // ---- 2. Match via SKU (exact) ----
        foreach ([$sku, $sellerSku] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            // 2a. Variant.sku
            $v = Variant::where('sku', $candidate)->first();
            if ($v) {
                return $this->makeItem($v, $name, $qty, 'sku', "SKU: {$candidate}");
            }
            // 2b. Product.sku → ambil varian pertama / yang cocok warnanya
            $product = Product::where('sku', $candidate)->first();
            if ($product) {
                $pick = $this->pickVariantForLabel($product, $labelText);
                if ($pick !== null) {
                    return $this->makeItem(
                        $pick['variant'],
                        $name,
                        $qty,
                        'sku',
                        "SKU: {$candidate}".($pick['reason'] ? ' · '.$pick['reason'] : '')
                    );
                }
            }
        }

        // ---- 3. Match via SELLER NOTE ----
        if ($sellerNote !== '') {
            $byNote = $this->matchByProductName($sellerNote);
            if ($byNote !== null) {
                return $this->makeItem(
                    $byNote['variant'],
                    $name,
                    $qty,
                    'seller_note',
                    'Note: '.$byNote['reason']
                );
            }
        }

        // Fallback: unmatched
        return [
            'product_name' => $name ?: '—',
            'variant_name' => $sellerSku ?: ($sku ?: null),
            'sku' => $sku ?: null,
            'variant_id' => null,
            'quantity' => $qty,
            'source' => 'unmatched',
            'matched_keyword' => null,
        ];
    }

    /**
     * Cari Variant berdasarkan nama produk master yang menjadi substring dari
     * $labelText. Priority terpanjang menang (supaya "Skeleton Import R14"
     * menang atas "Skeleton Import").
     *
     * Untuk produk yang ketemu, pilih varian yang paling cocok dengan
     * $labelText (match warna EN↔ID + nama varian biasa).
     *
     * @return array{variant: Variant, reason: string}|null
     */
    private function matchByProductName(string $labelText): ?array
    {
        $labelText = trim($labelText);
        if ($labelText === '' || mb_strlen($labelText) < 3) {
            return null;
        }

        $labelLower = mb_strtolower($labelText);

        // Hanya produk aktif. Urutkan nama terpanjang dulu (more specific wins).
        $products = Product::with('variants')
            ->where('is_active', true)
            ->get()
            ->sortByDesc(fn ($p) => mb_strlen((string) $p->name));

        foreach ($products as $product) {
            $pname = trim((string) $product->name);
            if ($pname === '' || mb_strlen($pname) < 3) {
                continue;
            }

            if (! str_contains($labelLower, mb_strtolower($pname))) {
                continue;
            }

            // Produk cocok. Pilih varian yang paling match dengan label.
            $pick = $this->pickVariantForLabel($product, $labelText);
            if ($pick !== null) {
                return [
                    'variant' => $pick['variant'],
                    'reason' => "Master: {$pname}".($pick['reason'] ? ' · '.$pick['reason'] : ''),
                ];
            }
        }

        return null;
    }

    /**
     * Pilih varian dari $product yang namanya paling cocok dengan $labelText.
     *
     * Scoring pakai WORD-BOUNDARY match supaya "merah" tidak asal substring
     * dengan "racing" atau "semi merah-merahan" dst:
     *   - Nama varian exact (word-boundary) di label: 10 poin
     *   - Token varian (split by space/slash/dash) ketemu word-boundary: 5 poin
     *     (token 1-karakter ikut dihitung supaya varian "Biru 2" bisa beat
     *     "Biru" untuk label "Biru 2".)
     *   - Synonym warna (Black↔Hitam) ketemu word-boundary: 10 poin
     *
     * Tie-break: skor sama → varian dengan NAMA LEBIH PANJANG menang
     * (lebih spesifik). Cegah label "Biru 2" salah pilih varian "Biru".
     *
     * SKU varian TIDAK dihitung di sini — ada strategi SKU match terpisah di
     * resolver. Ini supaya SKU yang kebetulan punya token umum ("R14") tidak
     * bikin varian "salah" menang atas varian yang warnanya benar-benar cocok.
     *
     * Kalau produk hanya punya 1 varian, varian itu dipakai tanpa scoring
     * (banyak produk master yang tidak punya pilihan varian).
     *
     * @return array{variant: Variant, reason: string}|null
     */
    private function pickVariantForLabel(Product $product, string $labelText): ?array
    {
        $variants = $product->variants;
        if ($variants->isEmpty()) {
            return null;
        }

        // Produk dengan 1 varian saja: langsung pilih (apapun nama varian-nya).
        if ($variants->count() === 1) {
            return [
                'variant' => $variants->first(),
                'reason' => '',
            ];
        }

        $labelLower = mb_strtolower($labelText);

        $best = null;
        $bestScore = 0;
        $bestNameLen = 0;
        $bestReason = '';

        foreach ($variants as $v) {
            $vname = trim((string) $v->name);
            if ($vname === '') {
                continue;
            }

            $score = 0;
            $reasonParts = [];

            // 1. Nama varian penuh (word-boundary).
            if ($this->hasWord($labelLower, $vname)) {
                $score += 10;
                $reasonParts[] = $vname;
            }

            // 2. Tiap token nama varian (word-boundary).
            //    Token 1-karakter (mis. "2" pada varian "Biru 2") tetap
            //    dihitung supaya varian "Biru 2" punya skor lebih tinggi
            //    dari "Biru" saat label-nya berisi "Biru 2".
            $tokens = preg_split('/[\s,\/\-_]+/u', mb_strtolower($vname)) ?: [];
            foreach ($tokens as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }
                if ($this->hasWord($labelLower, $token)) {
                    $score += 5;
                    continue;
                }
                // 3. Synonym warna (EN↔ID)
                foreach ($this->synonymsFor($token) as $syn) {
                    if ($this->hasWord($labelLower, $syn)) {
                        $score += 10;
                        $reasonParts[] = "{$syn}→{$v->name}";
                        break;
                    }
                }
            }

            // Tie-break: kalau skor sama, varian dengan nama lebih PANJANG
            // menang (lebih spesifik). Tanpa ini, urutan iterasi koleksi
            // (yang biasanya by id) menentukan pemenang — bug nyata: SKU
            // label "Biru 2" memilih varian "Biru" karena "Biru" lebih dulu
            // di koleksi dan kedua varian ber-skor sama 15 poin.
            $vlen = mb_strlen($vname);
            $isBetter = $score > $bestScore
                || ($score === $bestScore && $score > 0 && $vlen > $bestNameLen);

            if ($isBetter) {
                $best = $v;
                $bestScore = $score;
                $bestNameLen = $vlen;
                $bestReason = $reasonParts ? implode(', ', array_unique($reasonParts)) : '';
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'variant' => $best,
            'reason' => $bestReason,
        ];
    }

    /**
     * Cek apakah $needle muncul di $haystack sebagai "kata" — dengan
     * toleransi separator di antara token huruf/digit. Case-insensitive,
     * $haystack diasumsikan sudah lowercase.
     *
     * Toleransi yang dimaksud:
     *   - Letter↔digit boundary dianggap pemisah opsional. Varian master
     *     "Biru2" (tanpa spasi) cocok dengan label "Biru 2" (dengan spasi),
     *     dan sebaliknya.
     *   - Separator non-alfanumerik di antara token disamakan. "abu-abu"
     *     juga match "abu abu" / "abuabu".
     *
     * Word-boundary luar tetap dijaga: needle "biru" tidak nyangkut di
     * "biru2", needle "biru2" tidak nyangkut di "biru20".
     *
     * Contoh:
     *   hasWord("stir ... biru 2, ...",   "biru2") → TRUE
     *   hasWord("stir ... biru 2, ...",   "biru")  → TRUE
     *   hasWord("stir ... biru 2, ...",   "biru3") → FALSE
     *   hasWord("stir ... biru, ...",     "biru2") → FALSE
     *   hasWord("blackjack ...",          "black") → FALSE
     */
    private function hasWord(string $haystack, string $needle): bool
    {
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return false;
        }

        // Pecah needle jadi run-run alfanumerik (drop semua separator),
        // lalu pecah lagi di setiap letter↔digit boundary supaya pattern
        // bisa nyari label yang punya pemisah di posisi tsb.
        //   "biru2"   → ["biru", "2"]
        //   "biru 2"  → ["biru", "2"]
        //   "abu-abu" → ["abu", "abu"]
        //   "merah"   → ["merah"]
        $runs = preg_split('/[^A-Za-z0-9]+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $parts = [];
        foreach ($runs as $r) {
            $sub = preg_split('/(?<=[A-Za-z])(?=\d)|(?<=\d)(?=[A-Za-z])/u', $r) ?: [];
            foreach ($sub as $s) {
                if ($s !== '') {
                    $parts[] = $s;
                }
            }
        }

        if (empty($parts)) {
            return false;
        }

        // Rangkai jadi pattern: setiap bagian di-quote, dirangkai dengan
        // separator non-alfanumerik opsional. Outer word-boundary pakai
        // negative lookbehind/lookahead alfanumerik.
        $core = implode(
            '[^A-Za-z0-9]*',
            array_map(fn ($s) => preg_quote($s, '/'), $parts)
        );
        $pattern = '/(?<![A-Za-z0-9])'.$core.'(?![A-Za-z0-9])/iu';

        return preg_match($pattern, $haystack) === 1;
    }

    /**
     * Kembalikan daftar sinonim untuk sebuah token warna (case-insensitive).
     *
     * @return array<int, string>
     */
    private function synonymsFor(string $token): array
    {
        $key = mb_strtolower($token);

        return self::COLOR_SYNONYMS[$key] ?? [];
    }

    /**
     * Susun item output dari Variant yang ketemu.
     *
     * @return array<string, mixed>
     */
    private function makeItem(Variant $v, string $fallbackName, int $qty, string $source, ?string $reason = null): array
    {
        return [
            'product_name' => $v->product?->name ?? ($fallbackName ?: '—'),
            'variant_name' => $v->name,
            'sku' => $v->sku,
            'variant_id' => $v->id,
            'quantity' => $qty,
            'source' => $source,
            'matched_keyword' => $reason ?: null,
        ];
    }

    /**
     * Cari ComboMapping yang cocok untuk SELURUH label PDF.
     *
     * Algoritma:
     *   1. Bangun "combined label text" dari semua sumber yang tersedia di
     *      label: barang_keyword + nama produk per row + seller_sku + sku.
     *   2. Pecah keyword mapping berdasarkan separator umum ("—" em-dash,
     *      ",", "-" hyphen).
     *   3. Tiap bagian non-kosong dari keyword harus muncul (case-insensitive,
     *      sebagai substring) di combined label text. Kalau semua bagian
     *      cocok → mapping match.
     *
     * Contoh:
     *   keyword = "SPOILER MOBIL SEDAN MODEL GAWANG PENDEK UNIVERSAL — Default"
     *   parts   = ["SPOILER MOBIL SEDAN MODEL GAWANG PENDEK UNIVERSAL", "Default"]
     *
     *   Label A: barang="Default", product_name="SPOILER MOBIL SEDAN MODEL GAWANG PENDEK UNIVERSAL"
     *     combined = "Default SPOILER MOBIL SEDAN MODEL GAWANG PENDEK UNIVERSAL Default"
     *     - "SPOILER MOBIL...UNIVERSAL" ada → ✓
     *     - "Default" ada → ✓
     *     → MATCH (benar)
     *
     *   Label B: barang="Default", product_name="SPOILER MOBIL MODEL CITY UNIVERSAL SEDAN"
     *     combined = "Default SPOILER MOBIL MODEL CITY UNIVERSAL SEDAN Default"
     *     - "SPOILER MOBIL SEDAN MODEL GAWANG PENDEK UNIVERSAL" ada? TIDAK (urutan kata beda)
     *     → TIDAK MATCH (benar, beda produk)
     */
    private function findComboForLabel(array $parsed): ?ComboMapping
    {
        $combined = $this->buildCombinedLabelText($parsed);
        if ($combined === '') {
            return null;
        }

        // Urutkan keyword terpanjang dulu — keyword lebih spesifik menang
        // atas keyword umum.
        $mappings = ComboMapping::with('items.variant.product')->get();
        $mappings = $mappings->sortByDesc(fn ($m) => mb_strlen((string) $m->keyword));

        foreach ($mappings as $mapping) {
            $keyword = trim((string) $mapping->keyword);
            if (mb_strlen($keyword) < 4) {
                // Keyword super pendek (mis. "T2") di-handle di Strategi 1b
                // (seller_note) dengan word-boundary, bukan di sini.
                continue;
            }

            if ($this->keywordPartsAllMatch($keyword, $combined)) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Gabungkan semua field text dari label PDF jadi satu string panjang
     * untuk dipakai sebagai bahan substring-match.
     */
    private function buildCombinedLabelText(array $parsed): string
    {
        $parts = [];
        $parts[] = (string) ($parsed['barang_keyword'] ?? '');

        foreach ($parsed['product_rows'] ?? [] as $row) {
            $parts[] = (string) ($row['product_name'] ?? '');
            $parts[] = (string) ($row['seller_sku'] ?? '');
            $parts[] = (string) ($row['sku'] ?? '');
        }

        $parts = array_filter(array_map('trim', $parts), fn ($s) => $s !== '');

        return implode(' | ', $parts);
    }

    /**
     * Pecah keyword berdasarkan separator umum (em-dash, comma, hyphen) lalu
     * cek apakah SEMUA bagian non-kosong muncul di $combined text.
     */
    private function keywordPartsAllMatch(string $keyword, string $combined): bool
    {
        // "\x{2014}" adalah em-dash (—). Hyphen biasa "-" juga di-split supaya
        // keyword seperti "Stir-Bosskit" tidak harus exact match.
        $parts = preg_split('/\s*[\x{2014},]\s*/u', $keyword) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));

        if (empty($parts)) {
            return false;
        }

        foreach ($parts as $part) {
            if (mb_stripos($combined, $part) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cari SEMUA combo mapping yang keyword-nya cocok dengan $candidate.
     * Berguna untuk seller note yang bisa mengandung banyak keyword sekaligus.
     *
     * @param array<int, int> $excludeIds ID mapping yang mau diskip
     * @return array<int, ComboMapping>
     */
    private function findAllCombos(string $candidate, array $excludeIds = []): array
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return [];
        }

        $mappings = ComboMapping::with('items.variant.product')->get();
        $matches = [];

        foreach ($mappings as $mapping) {
            if (in_array($mapping->id, $excludeIds, true)) {
                continue;
            }

            $keyword = trim($mapping->keyword);
            if ($keyword === '') {
                continue;
            }

            if ($this->keywordMatches($candidate, $keyword)) {
                $matches[] = $mapping;
            }
        }

        return $matches;
    }

    /**
     * Cek keyword cocok di seller_note.
     *
     * Untuk keyword pendek (<4 char) pakai word-boundary supaya keyword "T2"
     * tidak false-match "T23" / "T20".
     *
     * Untuk keyword panjang, dipecah dulu berdasarkan separator (—, comma,
     * hyphen) lalu SEMUA bagian harus muncul di seller_note. Konsisten
     * dengan logika di Strategi 1.
     */
    private function keywordMatches(string $candidate, string $keyword): bool
    {
        if (mb_strlen($keyword) >= 4) {
            return $this->keywordPartsAllMatch($keyword, $candidate);
        }

        // Keyword super pendek: word-boundary match.
        //   "T2" cocok di "Ferio (T2)" tapi TIDAK cocok di "T23"
        $pattern = '/(?<![A-Za-z0-9])'.preg_quote($keyword, '/').'(?![A-Za-z0-9])/iu';

        return preg_match($pattern, $candidate) === 1;
    }

    /**
     * Gabung item dengan variant_id yang sama supaya stok tidak double-potong.
     *
     * Pakai MAX qty (bukan SUM): kalau per-row resolution dan combo Strategi 1
     * sama-sama nge-resolve ke varian yang sama (combo "Stir Racing" → 1 stir
     * sementara product_row juga punya 1 stir), output jadi 1 stir, bukan 2.
     *
     * Konsekuensinya: kalau user explicitly bikin combo "+freebie 1 stir
     * tambahan" untuk produk yang sudah di-resolve per-row, qty tidak akan
     * tambah. Untuk freebie, mapping varian-nya harus beda dari yang utama.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function mergeItemsByVariant(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            // Item tanpa variant_id (unmatched) tidak di-dedup — biarkan apa
            // adanya supaya user tahu masih ada baris yang belum ke-mapping.
            $key = $item['variant_id'] ?? spl_object_hash((object) $item);

            if (! isset($merged[$key])) {
                $merged[$key] = $item;
                continue;
            }

            // MAX qty supaya tidak double-count stok.
            $merged[$key]['quantity'] = max(
                (int) $merged[$key]['quantity'],
                (int) $item['quantity']
            );

            $existingKeyword = $merged[$key]['matched_keyword'] ?? '';
            $newKeyword = $item['matched_keyword'] ?? '';
            if ($newKeyword && ! str_contains((string) $existingKeyword, $newKeyword)) {
                $merged[$key]['matched_keyword'] = trim($existingKeyword.' + '.$newKeyword, ' +');
            }
        }

        return array_values($merged);
    }
}
