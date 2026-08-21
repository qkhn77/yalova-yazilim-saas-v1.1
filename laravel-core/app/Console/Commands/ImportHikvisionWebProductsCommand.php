<?php

namespace App\Console\Commands;

use App\Models\Muhasebe\DovizKuru;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKartiGorseli;
use App\Models\Muhasebe\StokKategorisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportHikvisionWebProductsCommand extends Command
{
    private const PRICE_MARKUP_MULTIPLIER = 1.30;

    protected $signature = 'hikvision:import-web-products
        {--json= : JSON export path}
        {--firma-id=3 : Target firma ID}
        {--usd-try= : USD/TRY exchange rate override}
        {--limit= : Limit product count}
        {--only-categories : Import only categories}
        {--only-prices : Sync only product prices from live product pages}
        {--only-images : Sync only product images}';

    protected $description = 'Imports Hikvision categories and products into stok kategorileri / stok kartlari for web products.';

    /** @var array<string, int> */
    private array $categoryIdByPath = [];

    public function handle(): int
    {
        $jsonPath = (string) ($this->option('json') ?: base_path('hikvisionturkiye.net/urunler-tum/products.json'));
        $firmaId = (int) ($this->option('firma-id') ?: 0);
        $limit = (int) ($this->option('limit') ?: 0);
        $onlyCategories = (bool) $this->option('only-categories');
        $onlyPrices = (bool) $this->option('only-prices');
        $onlyImages = (bool) $this->option('only-images');

        if ($firmaId < 1) {
            $this->error('Gecerli bir --firma-id vermelisiniz.');

            return self::FAILURE;
        }

        $usdTry = $this->resolveUsdTryRate($firmaId);
        if ($this->option('usd-try') !== null && $this->option('usd-try') !== '') {
            $usdTry = (float) $this->option('usd-try');
        }

        if ($usdTry <= 0) {
            $this->error('Gecerli USD/TRY kuru bulunamadi.');

            return self::FAILURE;
        }

        $this->info('Firma ID: '.$firmaId);
        $this->info('USD/TRY kuru: '.number_format($usdTry, 8, '.', ''));

        if ($onlyPrices) {
            return $this->syncOnlyPrices($firmaId, $usdTry, $limit);
        }

        if (! is_file($jsonPath)) {
            $this->error('JSON dosyasi bulunamadi: '.$jsonPath);

            return self::FAILURE;
        }

        $raw = file_get_contents($jsonPath);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        $products = is_array($payload['products'] ?? null) ? $payload['products'] : null;

        if (! is_array($products)) {
            $this->error('JSON icinde products listesi bulunamadi.');

            return self::FAILURE;
        }

        if ($limit > 0) {
            $products = array_slice($products, 0, $limit);
        }

        $this->info('Kayit sayisi: '.count($products));

        $categoryPaths = [];
        foreach ($products as $product) {
            $segments = $this->inferCategorySegments(is_array($product) ? $product : []);
            if ($segments === []) {
                $segments = ['Diger Urunler'];
            }

            $path = implode(' > ', $segments);
            $categoryPaths[$path] = $segments;
        }

        uasort($categoryPaths, static function (array $a, array $b): int {
            $depthCompare = count($a) <=> count($b);
            if ($depthCompare !== 0) {
                return $depthCompare;
            }

            return strcmp(implode(' > ', $a), implode(' > ', $b));
        });

        $categoryCreated = 0;
        $categoryUpdated = 0;

        foreach ($categoryPaths as $segments) {
            $result = $this->upsertCategoryPath($firmaId, $segments);
            if ($result === 'created') {
                $categoryCreated++;
            } elseif ($result === 'updated') {
                $categoryUpdated++;
            }
        }

        $this->info("Kategoriler hazir. Yeni: {$categoryCreated}, guncel: {$categoryUpdated}");

        if ($onlyCategories) {
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $cleared = 0;
        $skipped = 0;

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                $skipped++;
                continue;
            }

            if ($onlyImages) {
                $result = $this->syncExistingProductImages($firmaId, $product);
                if ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            $priceTry = $this->extractPriceTry($product);
            if ($priceTry <= 0) {
                $skipped++;
                $this->warn('Fiyat bulunamadi, atlandi: '.($product['title'] ?? ('#'.($index + 1))));
                continue;
            }

            $result = $this->upsertProduct($firmaId, $product, $usdTry, $priceTry);
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Urun import tamamlandi. Yeni: {$created}, guncel: {$updated}, atlanan: {$skipped}");

        return self::SUCCESS;
    }

    private function syncOnlyPrices(int $firmaId, float $usdTry, int $limit = 0): int
    {
        $query = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->where('kod', 'like', 'HKV-%')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get(['id', 'kod', 'slug', 'ad']);
        $this->info('Kayit sayisi: '.$records->count());

        $updated = 0;
        $cleared = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $pricePayload = $this->fetchLivePricePayload((string) $record->slug);

            if ($pricePayload === null) {
                $skipped++;
                $this->warn('Fiyat alinamadi: '.($record->slug ?: $record->kod));
                continue;
            }

            $priceTry = $this->extractPriceTry([
                'price_numeric' => null,
                'price_display' => $pricePayload['price_new'] !== '' ? $pricePayload['price_new'] : $pricePayload['price_old'],
            ]);

            if ($priceTry <= 0) {
                $record->forceFill([
                    'alis_fiyati' => 0,
                    'satis_fiyati' => 0,
                    'indirimli_fiyat' => null,
                    'para_birimi' => 'USD',
                ])->save();

                $cleared++;
                $this->warn('Fiyat temizlendi (kaynak 0 veya belirsiz): '.($record->slug ?: $record->kod));
                continue;
            }

            $baseUsd = round($priceTry / $usdTry, 2);
            $currentUsd = round(($priceTry * self::PRICE_MARKUP_MULTIPLIER) / $usdTry, 2);
            $oldTry = $this->extractPriceTry([
                'price_numeric' => null,
                'price_display' => $pricePayload['price_old'],
            ]);
            $oldUsd = $oldTry > 0 ? round(($oldTry * self::PRICE_MARKUP_MULTIPLIER) / $usdTry, 2) : null;
            $saleUsd = $oldUsd !== null && $oldUsd > $currentUsd ? $oldUsd : $currentUsd;
            $discountUsd = $oldUsd !== null && $oldUsd > $currentUsd ? $currentUsd : null;

            $record->forceFill([
                'alis_fiyati' => $baseUsd,
                'satis_fiyati' => $saleUsd,
                'indirimli_fiyat' => $discountUsd,
                'para_birimi' => 'USD',
            ])->save();

            $updated++;
        }

        $this->info("Fiyat guncelleme tamamlandi. Guncel: {$updated}, temizlenen: {$cleared}, atlanan: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function syncExistingProductImages(int $firmaId, array $product): string
    {
        $slug = $this->makeProductSlug($product);
        if ($slug === '') {
            return 'skipped';
        }

        $record = StokKarti::withTrashed()
            ->where('firma_id', $firmaId)
            ->where('slug', $slug)
            ->first();

        if (! $record) {
            return 'skipped';
        }

        if ($record->trashed()) {
            $record->restore();
        }

        $title = trim((string) ($product['title'] ?? $record->ad ?? ''));
        $imagePayload = $this->copyProductImages($slug, $product);

        if ($imagePayload['main'] !== null) {
            $record->forceFill(['og_gorsel' => $imagePayload['main']])->save();
        }

        $this->syncProductImages($record, $imagePayload['gallery'], $title !== '' ? $title : (string) $record->ad);

        return 'updated';
    }

    private function resolveUsdTryRate(int $firmaId): float
    {
        $query = DovizKuru::query()
            ->where('kaynak_para_birimi', 'USD')
            ->where('hedef_para_birimi', 'TRY')
            ->whereIn('tanim_firma_kapsami', [0, $firmaId])
            ->orderByRaw('CASE WHEN tanim_firma_kapsami = ? THEN 0 ELSE 1 END', [$firmaId])
            ->orderByDesc('tarih')
            ->orderByDesc('id');

        $rate = $query->value('kur');

        return (float) $rate;
    }

    /**
     * @return array{price_new:string,price_old:string}|null
     */
    private function fetchLivePricePayload(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $url = 'https://hikvisionturkiye.net/'.$slug;
        $html = $this->httpGet($url);

        if ($html === null || trim($html) === '') {
            return null;
        }

        libxml_use_internal_errors(true);

        $encoding = mb_check_encoding($html, 'UTF-8')
            ? 'UTF-8'
            : (mb_detect_encoding($html, ['Windows-1254', 'ISO-8859-9', 'ISO-8859-1', 'ASCII'], true) ?: 'UTF-8');

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$this->toHtmlEntities($html, $encoding));
        $xpath = new \DOMXPath($dom);

        $priceGroup = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " product-price-group ")]')->item(0);

        if (! $priceGroup instanceof \DOMNode) {
            return null;
        }

        $priceNew = '';
        $priceOld = '';

        $priceNewNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " product-price-new ") or contains(concat(" ", normalize-space(@class), " "), " price-new ")]', $priceGroup)->item(0);
        if ($priceNewNode instanceof \DOMNode) {
            $priceNew = $this->cleanPriceText($priceNewNode->textContent);
        }

        $priceOldNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " product-price-old ") or contains(concat(" ", normalize-space(@class), " "), " price-old ")]', $priceGroup)->item(0);
        if ($priceOldNode instanceof \DOMNode) {
            $priceOld = $this->cleanPriceText($priceOldNode->textContent);
        }

        if ($priceNew === '') {
            $priceTexts = $this->extractPriceTextsFromGroup($priceGroup->textContent);
            $priceNew = $priceTexts['price_new'];
            $priceOld = $priceOld !== '' ? $priceOld : $priceTexts['price_old'];
        }

        if ($priceNew === '' && $priceOld === '') {
            return null;
        }

        return [
            'price_new' => $priceNew,
            'price_old' => $priceOld,
        ];
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($result) || $status >= 400) {
            return null;
        }

        return $result;
    }

    private function cleanPriceText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return trim($value);
    }

    /**
     * @return array{price_new:string,price_old:string}
     */
    private function extractPriceTextsFromGroup(string $text): array
    {
        $normalized = $this->cleanPriceText($text);
        $normalized = preg_replace('/Vergiler\s+Hariç\s*:\s*[0-9\.\,]+₺?/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[0-9]+\s+adet\s+ve\s+üzeri\s+[0-9\.\,]+₺?/iu', '', $normalized) ?? $normalized;
        $normalized = $this->cleanPriceText($normalized);

        preg_match_all('/[0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]+)?\s*(?:₺|\$|€|TL|TRY)?/u', $normalized, $matches);
        $prices = array_values(array_filter(array_map(
            fn (string $value): string => $this->cleanPriceText($value),
            $matches[0] ?? []
        ), static fn (string $value): bool => $value !== ''));

        return [
            'price_new' => $prices[0] ?? '',
            'price_old' => $prices[1] ?? '',
        ];
    }

    private function toHtmlEntities(string $html, string $encoding): string
    {
        return mb_convert_encoding($html, 'HTML-ENTITIES', $encoding);
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, string>
     */
    private function inferCategorySegments(array $product): array
    {
        $urlCategory = $this->categoryPartsFromString((string) ($product['url_category_path'] ?? ''));
        if ($urlCategory !== []) {
            return $urlCategory;
        }

        $derived = $this->categoryPartsFromUrl((string) ($product['url'] ?? ''), (string) ($product['title'] ?? ''));
        $categoryPath = $this->categoryPartsFromString((string) ($product['category_path'] ?? ''));
        $sourceCategoryPath = $this->categoryPartsFromString((string) ($product['source_category_path'] ?? ''));

        if (count($derived) >= 2) {
            return $derived;
        }

        if (count($categoryPath) >= 2) {
            return $categoryPath;
        }

        if (count($sourceCategoryPath) >= 2) {
            return $sourceCategoryPath;
        }

        if ($derived !== []) {
            return $derived;
        }

        if ($categoryPath !== []) {
            return $categoryPath;
        }

        if ($sourceCategoryPath !== []) {
            return $sourceCategoryPath;
        }

        return ['Diger Urunler'];
    }

    /**
     * @return array<int, string>
     */
    private function categoryPartsFromString(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $part): string => $this->normalizeCategoryName($part),
            explode('>', $value)
        )));
    }

    /**
     * @return array<int, string>
     */
    private function categoryPartsFromUrl(string $url, string $title): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path !== '') {
            $segments = array_values(array_filter(explode('/', $path)));
            if (count($segments) >= 2) {
                array_pop($segments);

                return array_values(array_filter(array_map(
                    fn (string $segment): string => $this->normalizeCategoryName($segment),
                    $segments
                )));
            }

            $slug = $segments[0] ?? '';

            return $this->inferRootSlugCategory($slug, $title);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function inferRootSlugCategory(string $slug, string $title): array
    {
        $value = Str::lower($slug.' '.$title);

        if (str_contains($value, 'solar') || str_contains($value, 'wifi 4g') || str_contains($value, 'wifi-4g')) {
            return ['Kamera', 'Solar Wifi 4G Kamera'];
        }

        if (str_contains($value, 'kamera setleri') || str_contains($value, 'kamera-setleri') || str_contains($value, 'kamerali')) {
            return ['Kamera', 'Kampanyali Kamera Setleri'];
        }

        if (str_contains($value, 'equipment') || str_contains($value, 'ups') || str_contains($value, 'switch') || str_contains($value, 'kabin') || str_contains($value, 'monitor') || str_contains($value, 'adapt')) {
            return ['Ekipman'];
        }

        if (str_contains($value, 'kayit')) {
            return ['Kayit Cihazi'];
        }

        if (str_contains($value, 'alarm')) {
            return ['Alarm Sistemleri'];
        }

        if (str_contains($value, 'interkom')) {
            return ['Interkom'];
        }

        if (str_contains($value, 'kamera')) {
            return ['Kamera'];
        }

        return ['Diger Urunler'];
    }

    private function normalizeCategoryName(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);
        $value = Str::title(Str::lower($value));

        $replacements = [
            'Ahd' => 'AHD',
            'Ip' => 'IP',
            'Izi' => 'İzi',
            'Wifi' => 'Wifi',
            '4g' => '4G',
            'Dis' => 'Dış',
            'Ic' => 'İç',
            'Kayit' => 'Kayıt',
            'Cihazi' => 'Cihazı',
            'Kontrol' => 'Kontrol',
            'Kapi' => 'Kapı',
            'Urunler' => 'Ürünler',
            'Urunleri' => 'Ürünleri',
            'Guneş' => 'Güneş',
            'Goruşlu' => 'Görüşlü',
            'Goruntulu' => 'Görüntülü',
        ];

        return strtr($value, $replacements);
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function upsertCategoryPath(int $firmaId, array $segments): string
    {
        $fullPath = [];
        $parentId = null;
        $result = 'noop';

        foreach ($segments as $segment) {
            $fullPath[] = $segment;
            $pathKey = implode(' > ', $fullPath);

            if (isset($this->categoryIdByPath[$pathKey])) {
                $parentId = $this->categoryIdByPath[$pathKey];
                continue;
            }

            $slug = Str::slug($segment);
            $record = StokKategorisi::withTrashed()
                ->where('firma_id', $firmaId)
                ->where('parent_id', $parentId)
                ->where('slug', $slug)
                ->first();

            $attributes = [
                'firma_id' => $firmaId,
                'parent_id' => $parentId,
                'kod' => $this->makeCategoryCode($pathKey),
                'ad' => $segment,
                'slug' => $slug !== '' ? $slug : Str::slug($pathKey),
                'aciklama' => $segment.' kategorisi',
                'aktif_mi' => true,
                'is_sabit' => false,
            ];

            if ($record) {
                if ($record->trashed()) {
                    $record->restore();
                }
                $record->fill($attributes);
                $record->save();
                $result = 'updated';
            } else {
                $record = StokKategorisi::create($attributes);
                $result = 'created';
            }

            $parentId = (int) $record->getKey();
            $this->categoryIdByPath[$pathKey] = $parentId;
        }

        return $result;
    }

    private function makeCategoryCode(string $path): string
    {
        return 'WEBCAT-'.strtoupper(substr(md5($path), 0, 10));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function extractPriceTry(array $product): float
    {
        $value = (string) ($product['price_numeric'] ?? '');
        if ($value !== '' && is_numeric($value)) {
            return (float) $value;
        }

        $display = (string) ($product['price_display'] ?? '');
        if (preg_match('/([0-9]+(?:\.[0-9]{3})*(?:,[0-9]+)?)/', $display, $match) === 1) {
            $numeric = str_replace('.', '', $match[1]);
            $numeric = str_replace(',', '.', $numeric);

            return (float) $numeric;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function upsertProduct(int $firmaId, array $product, float $usdTry, float $priceTry): string
    {
        $title = trim((string) ($product['title'] ?? ''));
        if ($title === '') {
            return 'skipped';
        }

        $slug = $this->makeProductSlug($product);
        if ($slug === '') {
            return 'skipped';
        }

        $categorySegments = $this->inferCategorySegments($product);
        $categoryKey = implode(' > ', $categorySegments);
        $categoryId = $categoryKey !== '' ? ($this->categoryIdByPath[$categoryKey] ?? null) : null;
        $category = $categoryId ? StokKategorisi::find($categoryId) : null;

        $baseUsd = round($priceTry / $usdTry, 2);
        $currentUsd = round(($priceTry * self::PRICE_MARKUP_MULTIPLIER) / $usdTry, 2);
        $oldTry = $this->extractPriceTry(['price_numeric' => null, 'price_display' => (string) ($product['price_old'] ?? '')]);
        $oldUsd = $oldTry > 0 ? round(($oldTry * self::PRICE_MARKUP_MULTIPLIER) / $usdTry, 2) : null;
        $saleUsd = $oldUsd !== null && $oldUsd > $currentUsd ? $oldUsd : $currentUsd;
        $discountUsd = $oldUsd !== null && $oldUsd > $currentUsd ? $currentUsd : null;
        $description = $this->buildDescription($product);
        $shortDescription = trim((string) ($product['summary_description'] ?? ''));
        $seoDescription = Str::limit($shortDescription !== '' ? $shortDescription : strip_tags($description), 160, '');
        $imagePayload = $this->copyProductImages($slug, $product);
        $code = $this->makeProductCode($product);

        $attributes = [
            'firma_id' => $firmaId,
            'kod' => $code,
            'ad' => $title,
            'kisa_ad' => Str::limit((string) ($product['model'] ?? $title), 128, ''),
            'slug' => $slug,
            'tur' => StokKartiTuru::ETicaret->value,
            'kategori_id' => $categoryId,
            'kategori_kodu' => $category?->kod,
            'birim' => 'AD',
            'alis_fiyati' => $baseUsd,
            'satis_fiyati' => $saleUsd,
            'indirimli_fiyat' => $discountUsd,
            'para_birimi' => 'USD',
            'kdv_orani' => 20,
            'kritik_seviye_miktar' => 0,
            'aciklama' => $description,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => false,
            'minimum_stok' => 0,
            'maksimum_stok' => null,
            'stok_miktari' => 0,
            'rezerve_miktar' => 0,
            'marka_uretici' => $this->inferBrand($product),
            'og_gorsel' => $imagePayload['main'],
            'seo_title' => Str::limit($title.' | Yalova Kamera', 255, ''),
            'seo_description' => $seoDescription,
            'seo_keywords' => Str::limit(implode(', ', array_filter([
                $title,
                $product['model'] ?? null,
                $product['brand'] ?? null,
                implode(', ', $categorySegments),
            ])), 255, ''),
            'og_baslik' => Str::limit($title, 255, ''),
            'og_aciklama' => $seoDescription,
            'og_etiket' => implode(' > ', $categorySegments),
        ];

        $record = StokKarti::withTrashed()
            ->where('firma_id', $firmaId)
            ->where('slug', $slug)
            ->first();

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }
            $record->fill($attributes);
            $record->save();
            $this->syncProductImages($record, $imagePayload['gallery'], $title);

            return 'updated';
        }

        $record = StokKarti::create($attributes);
        $this->syncProductImages($record, $imagePayload['gallery'], $title);

        return 'created';
    }

    /**
     * @param  array<int, string>  $galleryPaths
     */
    private function syncProductImages(StokKarti $record, array $galleryPaths, string $title): void
    {
        StokKartiGorseli::query()
            ->where('stok_karti_id', $record->getKey())
            ->delete();

        foreach (array_values(array_unique(array_filter($galleryPaths))) as $index => $path) {
            StokKartiGorseli::create([
                'stok_karti_id' => (int) $record->getKey(),
                'dosya_yolu' => $path,
                'alt_metin' => $title,
                'sira' => $index,
                'kapak_mi' => $index === 0,
                'aktif_mi' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function makeProductSlug(array $product): string
    {
        $url = trim((string) ($product['url'] ?? ''));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path !== '') {
            $segments = array_values(array_filter(explode('/', $path)));
            $last = end($segments);
            if (is_string($last) && $last !== '') {
                return Str::slug($last);
            }
        }

        return Str::slug((string) ($product['title'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function makeProductCode(array $product): string
    {
        $model = strtoupper(preg_replace('/[^A-Z0-9]+/', '', Str::upper((string) ($product['model'] ?? ''))) ?? '');
        $hash = strtoupper(substr(md5((string) ($product['url'] ?? ($product['title'] ?? ''))), 0, 6));
        if ($model !== '') {
            return 'HKV-'.substr($model, 0, 20).'-'.$hash;
        }

        return 'HKV-'.$hash;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{main:?string,gallery:array<int, string>}
     */
    private function copyProductImages(string $slug, array $product): array
    {
        $gallery = [];
        $main = null;
        $paths = [];

        foreach ((array) ($product['downloaded_images'] ?? []) as $imagePath) {
            if (is_string($imagePath) && $imagePath !== '') {
                $paths[] = $imagePath;
            }
        }

        $localMain = (string) ($product['local_main_image'] ?? '');
        if ($localMain !== '' && ! in_array($localMain, $paths, true)) {
            array_unshift($paths, $localMain);
        }

        $localImageDir = (string) ($product['local_image_dir'] ?? '');
        if ($localImageDir !== '' && is_dir($localImageDir)) {
            $dirFiles = glob($localImageDir.DIRECTORY_SEPARATOR.'*.{jpg,jpeg,png,webp,gif,avif}', GLOB_BRACE) ?: [];
            natcasesort($dirFiles);

            foreach ($dirFiles as $dirFile) {
                if (is_string($dirFile) && $dirFile !== '') {
                    $paths[] = $dirFile;
                }
            }
        }

        $paths = array_values(array_unique(array_filter($paths, static fn ($path): bool => is_string($path) && is_file($path))));

        foreach ($paths as $index => $sourcePath) {
            $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
            $extension = $extension !== '' ? $extension : 'jpg';
            $relative = $index === 0
                ? 'stok/main/hikvision/'.$slug.'.'.$extension
                : 'stok/gallery/hikvision/'.$slug.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.'.$extension;

            $target = storage_path('app/public/'.$relative);
            File::ensureDirectoryExists(dirname($target));
            File::copy($sourcePath, $target);

            if ($index === 0) {
                $main = $relative;
            }

            $gallery[] = $relative;
        }

        return [
            'main' => $main,
            'gallery' => $gallery,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function buildDescription(array $product): string
    {
        $parts = array_values(array_filter([
            trim((string) ($product['summary_description'] ?? '')),
            trim((string) ($product['detail_description_text'] ?? '')),
        ]));

        if ($parts === []) {
            return trim((string) ($product['title'] ?? ''));
        }

        return implode("\n\n", array_unique($parts));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function inferBrand(array $product): ?string
    {
        $brand = trim((string) ($product['brand'] ?? ''));
        if ($brand !== '') {
            return $brand;
        }

        $title = Str::lower((string) ($product['title'] ?? ''));
        if (str_contains($title, 'hilook')) {
            return 'HiLook';
        }

        if (str_contains($title, 'hikvision')) {
            return 'Hikvision';
        }

        return null;
    }
}
