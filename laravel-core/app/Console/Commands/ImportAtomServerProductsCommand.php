<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKartiGorseli;
use App\Models\Muhasebe\StokKategorisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\DovizKurServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportAtomServerProductsCommand extends Command
{
    protected $signature = 'atom:import-server-products
        {--firma-id= : Stok kategorileri ve stok kartlari icin firma ID}
        {--skip-images : Atom urun gorsellerini indirme ve eslestirme}
        {--dry-run : Sadece planlanan kayitlari goster}';

    protected $description = 'Imports Atom Bilisim server products into legacy products, stok_kartlari, and stok_karti_gorselleri.';

    public function handle(DovizKurServisi $kurServisi): int
    {
        $firmaId = $this->resolveFirmaId();
        if ($firmaId < 1) {
            $this->error('Gecerli bir --firma-id vermelisiniz veya aktif bir firma kaydi olmalidir.');

            return self::FAILURE;
        }

        $rate = 44.7485;

        try {
            $liveRate = $kurServisi->otomatikKurGetir('USD', 'TRY');
            $candidate = (float) ($liveRate['kur'] ?? 0);
            if ($candidate > 0) {
                $rate = $candidate;
            }
        } catch (\Throwable $e) {
            $this->warn('USD/TRY kur alinamadi, yedek kur kullaniliyor: '.$rate);
        }

        $category = ProductCategory::query()->firstOrCreate(
            ['slug' => 'sunucu'],
            [
                'name' => 'Sunucu',
                'description' => 'Sunucu ürünleri',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $products = $this->products();
        $created = 0;
        $updated = 0;
        $stokCreated = 0;
        $stokUpdated = 0;
        $imageSynced = 0;
        $stokCategory = $this->upsertStokCategory($firmaId, $this->option('dry-run'));

        foreach ($products as $index => $productData) {
            $sourcePriceTry = $this->extractLivePriceTry((string) $productData['source_url'])
                ?? (float) $productData['price_try'];
            $priceTry = $sourcePriceTry * 2;
            $priceUsd = round($priceTry / $rate, 2);
            $slug = $productData['slug'];
            $payload = [
                'category_id' => (int) $category->getKey(),
                'name' => $productData['name'],
                'slug' => $slug,
                'short_description' => $productData['short_description'],
                'description' => $productData['description'],
                'sku' => $productData['sku'],
                'brand' => $productData['brand'],
                'para_birimi' => 'USD',
                'price' => $priceUsd,
                'discounted_price' => null,
                'stock_status' => Product::STOCK_IN_STOCK,
                'image' => null,
                'gallery' => [],
                'technical_specs' => array_merge($productData['technical_specs'], [
                    'source_url' => $productData['source_url'],
                    'source_price_try' => $sourcePriceTry,
                    'price_multiplier' => 2,
                    'sell_price_try' => $priceTry,
                    'usd_try_rate' => $rate,
                    'base_currency' => 'USD',
                    'base_price_usd' => $priceUsd,
                ]),
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => $index + 1,
                'seo_title' => $productData['seo_title'],
                'seo_description' => $productData['seo_description'],
            ];

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '[dry-run] %s | %s | %s USD -> %s TRY | stok kategori: %s',
                    $productData['sku'],
                    $productData['name'],
                    number_format($priceUsd, 2, '.', ''),
                    number_format($priceTry, 2, '.', ''),
                    $stokCategory?->ad ?? 'Sunucu'
                ));

                continue;
            }

            $record = Product::query()->updateOrCreate(
                ['slug' => $slug],
                $payload
            );

            $record->gallery = [];
            $record->save();

            if ($record->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            [$stokResult, $stokRecord] = $this->upsertStokProduct($firmaId, $stokCategory, $productData, $priceUsd, $index);
            if ($stokResult === 'created') {
                $stokCreated++;
            } elseif ($stokResult === 'updated') {
                $stokUpdated++;
            }

            if (! $this->option('skip-images')) {
                $imageSynced += $this->syncProductImages($record, $stokRecord, $productData);
            }
        }

        $this->info(sprintf(
            'Tamamlandi. Legacy yeni: %d, legacy guncel: %d, stok yeni: %d, stok guncel: %d, gorsel: %d, kur: %s',
            $created,
            $updated,
            $stokCreated,
            $stokUpdated,
            $imageSynced,
            number_format($rate, 4, '.', '')
        ));

        return self::SUCCESS;
    }

    private function resolveFirmaId(): int
    {
        $optionFirmaId = (int) ($this->option('firma-id') ?: 0);
        if ($optionFirmaId > 0) {
            return $optionFirmaId;
        }

        return (int) (Firma::query()
            ->where('durum', 'aktif')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertStokCategory(int $firmaId, bool $dryRun = false): ?StokKategorisi
    {
        if ($dryRun) {
            return new StokKategorisi([
                'firma_id' => $firmaId,
                'kod' => 'WEB-SUNUCU',
                'ad' => 'Sunucu',
                'slug' => 'sunucu',
                'aktif_mi' => true,
            ]);
        }

        $category = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::withTrashed()
            ->where('firma_id', $firmaId)
            ->where(function ($query): void {
                $query->where('slug', 'sunucu')
                    ->orWhere('ad', 'Sunucu');
            })
            ->orderByRaw("CASE WHEN slug = 'sunucu' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first());

        $attributes = [
            'firma_id' => $firmaId,
            'parent_id' => $category?->parent_id,
            'kod' => $category?->kod ?: $this->uniqueCategoryCode($firmaId, 'WEB-SUNUCU'),
            'ad' => 'Sunucu',
            'slug' => 'sunucu',
            'aciklama' => 'Sunucu urunleri',
            'aktif_mi' => true,
            'is_sabit' => false,
        ];

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            $category->fill($attributes);
            $category->save();

            return $category->fresh() ?? $category;
        }

        return StokKategorisi::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $productData
     * @return array{0: string, 1: StokKarti}
     */
    private function upsertStokProduct(int $firmaId, ?StokKategorisi $category, array $productData, float $priceUsd, int $index): array
    {
        $slug = (string) $productData['slug'];
        $sku = (string) $productData['sku'];
        $name = (string) $productData['name'];
        $description = $this->buildStokDescription($productData);

        $attributes = [
            'firma_id' => $firmaId,
            'kod' => $sku,
            'ad' => $name,
            'kisa_ad' => Str::limit($name, 128, ''),
            'slug' => $slug,
            'tur' => StokKartiTuru::ETicaret->value,
            'kategori_id' => $category?->id,
            'kategori_kodu' => $category?->kod,
            'birim' => 'AD',
            'alis_fiyati' => 0,
            'satis_fiyati' => $priceUsd,
            'indirimli_fiyat' => null,
            'para_birimi' => 'USD',
            'kdv_orani' => 20,
            'kritik_seviye_miktar' => 0,
            'aciklama' => $description,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'minimum_stok' => 0,
            'maksimum_stok' => null,
            'stok_miktari' => 1,
            'rezerve_miktar' => 0,
            'marka_uretici' => (string) $productData['brand'],
            'satis_adedi' => 0,
            'goruntulenme_sayisi' => 0,
            'seo_title' => (string) $productData['seo_title'],
            'seo_description' => (string) $productData['seo_description'],
            'seo_keywords' => Str::limit(implode(', ', array_filter([
                (string) $productData['brand'],
                'sunucu',
                'server',
                (string) ($productData['technical_specs']['cpu'] ?? ''),
                (string) ($productData['technical_specs']['ram'] ?? ''),
            ])), 255, ''),
            'og_baslik' => Str::limit($name, 255, ''),
            'og_aciklama' => (string) $productData['seo_description'],
            'og_etiket' => 'Sunucu',
        ];

        $record = StokKarti::withTrashed()
            ->where('firma_id', $firmaId)
            ->where(function ($query) use ($slug, $sku): void {
                $query->where('slug', $slug)
                    ->orWhere('kod', $sku);
            })
            ->first();

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }

            $record->fill($attributes);
            $record->save();

            return ['updated', $record->fresh() ?? $record];
        }

        $record = StokKarti::create($attributes);

        return ['created', $record];
    }

    /**
     * @param  array<string, mixed>  $productData
     */
    private function syncProductImages(Product $legacyProduct, StokKarti $stokProduct, array $productData): int
    {
        $urls = $this->extractProductImageUrls((string) $productData['source_url'], (string) $productData['slug']);

        if ($urls === []) {
            $this->warn('Gorsel bulunamadi: '.(string) $productData['sku']);

            return 0;
        }

        $paths = [];
        $safeSku = $this->safeSku((string) $productData['sku'], (string) $productData['slug']);
        foreach ($urls as $index => $url) {
            $path = $this->downloadProductImage(
                url: $url,
                slug: (string) $productData['slug'],
                safeSku: $safeSku,
                title: (string) $productData['name'],
                index: $index
            );

            if ($path === null) {
                continue;
            }

            $paths[] = $path;

            StokKartiGorseli::query()->updateOrCreate(
                [
                    'stok_karti_id' => (int) $stokProduct->getKey(),
                    'dosya_yolu' => $path,
                ],
                [
                    'alt_metin' => Str::limit((string) $productData['name'], 255, ''),
                    'sira' => $index + 1,
                    'kapak_mi' => $index === 0,
                    'aktif_mi' => true,
                ]
            );
        }

        if ($paths !== []) {
            $this->pruneRemovedProductImages((int) $stokProduct->getKey(), $safeSku, $paths);
            StokKartiGorseli::normalizeCoverForProduct((int) $stokProduct->getKey());

            $legacyProduct->fill([
                'image' => $paths[0],
                'gallery' => $paths,
            ]);
            $legacyProduct->save();
        }

        return count($paths);
    }

    /**
     * @return array<int, string>
     */
    private function extractProductImageUrls(string $sourceUrl, string $slug): array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; YalovaBilgisayarImporter/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($sourceUrl);
        } catch (\Throwable $e) {
            $this->warn('Atom sayfasi okunamadi: '.$sourceUrl.' | '.$e->getMessage());

            return [];
        }

        if (! $response->successful()) {
            $this->warn('Atom sayfasi basarisiz cevap verdi: '.$sourceUrl.' | HTTP '.$response->status());

            return [];
        }

        $html = $response->body();
        preg_match_all('/(?:src|data-src|data-original|data-large|data-zoom-image|href)\s*=\s*["\']([^"\']+\.(?:jpe?g|png|webp)(?:\?[^"\']*)?)["\']/iu', $html, $matches);

        $urls = [];
        foreach ($matches[1] ?? [] as $candidate) {
            $url = $this->absoluteUrl(html_entity_decode((string) $candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $sourceUrl);

            if (! $this->isProductGalleryImageUrl($url, $slug)) {
                continue;
            }

            $urls[$this->galleryImageKey($url)] = $url;
        }

        return array_values($urls);
    }

    private function downloadProductImage(string $url, string $slug, string $safeSku, string $title, int $index): ?string
    {
        $response = null;
        $resolvedUrl = null;

        foreach ($this->preferredImageUrlCandidates($url) as $candidateUrl) {
            try {
                $candidateResponse = Http::timeout(45)
                    ->retry(2, 500)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; YalovaBilgisayarImporter/1.0)',
                        'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                        'Referer' => 'https://www.atombilisim.com.tr/',
                    ])
                    ->get($candidateUrl);
            } catch (\Throwable $e) {
                continue;
            }

            if ($candidateResponse->successful() && $candidateResponse->body() !== '') {
                $response = $candidateResponse;
                $resolvedUrl = $candidateUrl;
                break;
            }
        }

        if ($response === null || $resolvedUrl === null) {
            $this->warn('Gorsel indirilemedi: '.$url);

            return null;
        }

        $extension = strtolower(pathinfo(parse_url($resolvedUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $baseName = Str::limit(Str::slug($title) ?: $slug, 72, '');
        $filename = sprintf(
            'yalova-bilgisayar-%s-%s-%02d.%s',
            $safeSku,
            $baseName,
            $index + 1,
            $extension
        );
        $path = 'products/atom-server/'.$safeSku.'/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * @param  array<int, string>  $currentPaths
     */
    private function pruneRemovedProductImages(int $stokKartiId, string $safeSku, array $currentPaths): void
    {
        $prefix = 'products/atom-server/'.$safeSku.'/';

        StokKartiGorseli::query()
            ->where('stok_karti_id', $stokKartiId)
            ->where('dosya_yolu', 'like', $prefix.'%')
            ->whereNotIn('dosya_yolu', $currentPaths)
            ->get()
            ->each(function (StokKartiGorseli $image): void {
                Storage::disk('public')->delete((string) $image->dosya_yolu);
                $image->delete();
            });
    }

    private function safeSku(string $sku, string $slug): string
    {
        return Str::slug($sku) ?: Str::limit($slug, 48, '');
    }

    private function extractLivePriceTry(string $sourceUrl): ?float
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; YalovaBilgisayarImporter/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($sourceUrl);
        } catch (\Throwable $e) {
            $this->warn('Atom fiyati okunamadi: '.$sourceUrl.' | '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->warn('Atom fiyati basarisiz cevap verdi: '.$sourceUrl.' | HTTP '.$response->status());

            return null;
        }

        $text = html_entity_decode(strip_tags($response->body()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        $patterns = [
            '/Urun\s*Fiyati\s*:?\s*([0-9][0-9\.\s]*(?:,[0-9]+)?)\s*TL/iu',
            '/Ürün\s*Fiyatı\s*:?\s*([0-9][0-9\.\s]*(?:,[0-9]+)?)\s*TL/iu',
            '/([0-9][0-9\.\s]*(?:,[0-9]+)?)\s*TL\s*\+\s*KDV/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            $price = $this->normalizeTryPrice((string) $match[1]);
            if ($price > 0) {
                return $price;
            }
        }

        return null;
    }

    private function normalizeTryPrice(string $value): float
    {
        $value = trim($value);
        $value = str_replace(['.', ' '], '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (preg_match('~^https?://~i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'www.atombilisim.com.tr';

        if (str_starts_with($url, '/')) {
            return $scheme.'://'.$host.$url;
        }

        $path = isset($parts['path']) ? dirname($parts['path']) : '';

        return $scheme.'://'.$host.'/'.trim($path.'/'.$url, '/');
    }

    private function isProductGalleryImageUrl(string $url, string $slug): bool
    {
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        $slug = strtolower($slug);

        if ($path === '' || ! str_contains($path, $slug)) {
            return false;
        }

        if (str_contains($path, '/theme/') || str_contains($path, '/editorfiles/')) {
            return false;
        }

        return preg_match('/\.(?:jpe?g|png|webp)$/i', $path) === 1;
    }

    private function galleryImageKey(string $url): string
    {
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        $path = preg_replace('/-[kbo](\.(?:jpe?g|png|webp))$/i', '$1', $path) ?: $path;

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function preferredImageUrlCandidates(string $url): array
    {
        $candidates = [];

        foreach (['B', 'O'] as $suffix) {
            $candidate = preg_replace('/-K(\.(?:jpe?g|png|webp)(?:\?.*)?)$/i', '-'.$suffix.'$1', $url);
            if (is_string($candidate) && $candidate !== $url) {
                $candidates[] = $candidate;
            }
        }

        $candidates[] = $url;

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>  $productData
     */
    private function buildStokDescription(array $productData): string
    {
        $specRows = collect($productData['technical_specs'] ?? [])
            ->map(fn ($value, $key): string => '<li><strong>'.e((string) $key).':</strong> '.e((string) $value).'</li>')
            ->implode('');

        return trim(implode('', [
            '<p>'.e((string) $productData['short_description']).'</p>',
            (string) $productData['description'],
            $specRows !== '' ? '<h3>Teknik Ozellikler</h3><ul>'.$specRows.'</ul>' : '',
            '<p><strong>Kaynak:</strong> <a href="'.e((string) $productData['source_url']).'" target="_blank" rel="noopener">Atom Bilisim</a></p>',
        ]));
    }

    private function uniqueCategoryCode(int $firmaId, string $base): string
    {
        $code = $base;
        $counter = 1;

        while (StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::withTrashed()->where('firma_id', $firmaId)->where('kod', $code)->exists())) {
            $counter++;
            $code = $base.'-'.$counter;
        }

        return $code;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'name' => '2.El Server Dell R640 2x6138 Gold 64GB RAM 4x16GB 2400T 3 Adet HDD Kizak 8x2,5 SFF 2x750W Power Supply RAID +Batarya',
                'slug' => '2el-server-dell-r640-2x6138-gold-64gb-ram-4x16gb-2400t-3-adet-hdd-kizak-8x25-sff-2x750w-power-supply-raid-batarya',
                'sku' => 'SRV-R640-G10-40',
                'brand' => 'Dell',
                'source_url' => 'https://www.atombilisim.com.tr/2el-server-dell-r640-2x6138-gold-64gb-ram-4x16gb-2400t-3-adet-hdd-kizak-8x25-sff-2x750w-power-supply-raid-batarya',
                'price_try' => 40050.00,
                'short_description' => 'Dell R640 sunucu; 2x6138 Gold CPU, 64GB RAM ve RAID + batarya ile.',
                'description' => '<p>2.el Dell PowerEdge R640 server.</p><ul><li>2x Intel Xeon Gold 6138</li><li>64GB RAM (4x16GB 2400T)</li><li>3 adet HDD kizak</li><li>8x2.5 SFF kasa</li><li>2x750W power supply</li><li>RAID karti ve batarya</li></ul>',
                'seo_title' => 'Dell R640 2x6138 Gold 64GB Sunucu',
                'seo_description' => 'Dell R640 2x6138 Gold islemci, 64GB RAM, 3 adet HDD kizak ve 2x750W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Gold 6138',
                    'ram' => '64GB (4x16GB 2400T)',
                    'disk_yuvasi' => '8x2.5 SFF',
                    'raid' => 'RAID + Batarya',
                    'guc_kaynagi' => '2x750W',
                ],
            ],
            [
                'name' => '2.El Server Dell R740 2 x 8160 Platinum 256 GB RAM 4x64GB 2400T 16x2,5 SFF 3xHDD Kizak RAID +Batery 2x750W Power',
                'slug' => '2el-server-dell-r740-2-x-8160-platinum-256-gb-ram-4x64gb-2400t-16x25-sff-3xhdd-kizak-raid-batery-2x750w-power',
                'sku' => 'SRV-R740-G10-92',
                'brand' => 'Dell',
                'source_url' => 'https://www.atombilisim.com.tr/2el-server-dell-r740-2-x-8160-platinum-256-gb-ram-4x64gb-2400t-16x25-sff-3xhdd-kizak-raid-batery-2x750w-power',
                'price_try' => 100570.00,
                'short_description' => 'Dell R740 sunucu; 2x8160 Platinum CPU, 256GB RAM ve 16x2.5 SFF kasa ile.',
                'description' => '<p>2.el Dell PowerEdge R740 server.</p><ul><li>2x Intel Xeon Platinum 8160</li><li>256GB RAM (4x64GB 2400T)</li><li>16x2.5 SFF kasa</li><li>3x HDD kizak</li><li>2x750W power supply</li><li>RAID + batarya</li></ul>',
                'seo_title' => 'Dell R740 2x8160 Platinum 256GB Sunucu',
                'seo_description' => 'Dell R740 2x8160 Platinum, 256GB RAM, 16x2.5 SFF kasa ve 2x750W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Platinum 8160',
                    'ram' => '256GB (4x64GB 2400T)',
                    'disk_yuvasi' => '16x2.5 SFF',
                    'raid' => 'RAID + Batery',
                    'guc_kaynagi' => '2x750W',
                ],
            ],
            [
                'name' => '2.El Server Dell R740 2 x 8168 Platinum CPU 256 GB RAM 4x64GB 2666V 8 x 2,5 SFF H740 RAID +Battery 2x750W Power',
                'slug' => '2el-server-dell-r740-2-x-8168-platinum-cpu-256-gb-ram-4x64gb-2666v-8-x-25-sff-h740-raid-battery-2x750w-power',
                'sku' => 'SRV-R740-G10-91',
                'brand' => 'Dell',
                'source_url' => 'https://www.atombilisim.com.tr/2el-server-dell-r740-2-x-8168-platinum-cpu-256-gb-ram-4x64gb-2666v-8-x-25-sff-h740-raid-battery-2x750w-power',
                'price_try' => 107913.00,
                'short_description' => 'Dell R740 sunucu; 2x8168 Platinum CPU, 256GB RAM ve H740 RAID ile.',
                'description' => '<p>2.el Dell PowerEdge R740 server.</p><ul><li>2x Intel Xeon Platinum 8168</li><li>256GB RAM (4x64GB 2666V)</li><li>8x2.5 SFF kasa</li><li>H740 RAID + batarya</li><li>2x750W power supply</li></ul>',
                'seo_title' => 'Dell R740 2x8168 Platinum 256GB Sunucu',
                'seo_description' => 'Dell R740 2x8168 Platinum, 256GB RAM, H740 RAID ve 2x750W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Platinum 8168',
                    'ram' => '256GB (4x64GB 2666V)',
                    'disk_yuvasi' => '8x2.5 SFF',
                    'raid' => 'H740 RAID + Batarya',
                    'guc_kaynagi' => '2x750W',
                ],
            ],
            [
                'name' => '2.El HP DL380 Gen10 8160 Platinum 2x CPU 128GB RAM 4x32GB 2400T 3xHDD Kizak P408i RAID +Battery 2x500W Power Supply',
                'slug' => '2el-hp-dl380-gen10-8160-platinum-2x-cpu-128gb-ram-4x32gb-2400t-3xhdd-kizak-p408i-raid-battery-2x500w-power-supply',
                'sku' => 'SRV-380-G10-50',
                'brand' => 'HP',
                'source_url' => 'https://www.atombilisim.com.tr/2el-hp-dl380-gen10-8160-platinum-2x-cpu-128gb-ram-4x32gb-2400t-3xhdd-kizak-p408i-raid-battery-2x500w-power-supply',
                'price_try' => 64525.00,
                'short_description' => 'HP DL380 Gen10 sunucu; 2x8160 Platinum CPU, 128GB RAM ve P408i RAID ile.',
                'description' => '<p>2.el HP ProLiant DL380 Gen10 server.</p><ul><li>2x Intel Xeon Platinum 8160</li><li>128GB RAM (4x32GB 2400T)</li><li>3x HDD kizak</li><li>P408i RAID + batarya</li><li>2x500W power supply</li></ul>',
                'seo_title' => 'HP DL380 Gen10 2x8160 Platinum 128GB Sunucu',
                'seo_description' => 'HP DL380 Gen10 2x8160 Platinum, 128GB RAM, P408i RAID ve 2x500W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Platinum 8160',
                    'ram' => '128GB (4x32GB 2400T)',
                    'disk_yuvasi' => '3x HDD Kizak',
                    'raid' => 'P408i RAID + Batarya',
                    'guc_kaynagi' => '2x500W',
                ],
            ],
            [
                'name' => '2.El Server Dell R640 2x6230 Gold 128GB RAM 4x32GB 2666V 3 Adet Kizak RAID +Battery 2x750W Power Supply',
                'slug' => '2el-server-dell-r640-2x6230-gold-128gb-ram4x32gb-2666v-3-adet-kizak-raid-battery-2x750w-power-supply',
                'sku' => 'SRV-R640-G10-36',
                'brand' => 'Dell',
                'source_url' => 'https://www.atombilisim.com.tr/2el-server-dell-r640-2x6230-gold-128gb-ram4x32gb-2666v-3-adet-kizak-raid-battery-2x750w-power-supply',
                'price_try' => 64970.00,
                'short_description' => 'Dell R640 sunucu; 2x6230 Gold CPU, 128GB RAM ve 3 adet kizak ile.',
                'description' => '<p>2.el Dell PowerEdge R640 server.</p><ul><li>2x Intel Xeon Gold 6230</li><li>128GB RAM (4x32GB 2666V)</li><li>3 adet kizak</li><li>RAID + batarya</li><li>2x750W power supply</li></ul>',
                'seo_title' => 'Dell R640 2x6230 Gold 128GB Sunucu',
                'seo_description' => 'Dell R640 2x6230 Gold, 128GB RAM, 3 adet kizak ve 2x750W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Gold 6230',
                    'ram' => '128GB (4x32GB 2666V)',
                    'disk_yuvasi' => '3 adet kizak',
                    'raid' => 'RAID + Batarya',
                    'guc_kaynagi' => '2x750W',
                ],
            ],
            [
                'name' => '2.El HP DL 380 Gen 10 6152 Gold x 2 CPU 256GB DDR4 RAM 4x64GB 2400T 8x25 By HDD Yok P408i RAID +Battery 2 x 500W Power Supply',
                'slug' => '2el-hp-dl-380-gen-10-6152-gold-x-2-cpu-256gb-ddr4-ram-4x64gb-2400t-8x25-by-hdd-yok-p408i-raid-battery-2-x-500w-power-supply',
                'sku' => 'SRV-380-G10-97',
                'brand' => 'HP',
                'source_url' => 'https://www.atombilisim.com.tr/2el-hp-dl-380-gen-10-6152-gold-x-2-cpu-256gb-ddr4-ram-4x64gb-2400t-8x25-by-hdd-yok-p408i-raid-battery-2-x-500w-power-supply',
                'price_try' => 80545.00,
                'short_description' => 'HP DL380 Gen10 sunucu; 2x6152 Gold CPU, 256GB RAM ve P408i RAID ile.',
                'description' => '<p>2.el HP ProLiant DL380 Gen10 server.</p><ul><li>2x Intel Xeon Gold 6152</li><li>256GB DDR4 RAM (4x64GB 2400T)</li><li>8x2.5 by kasa</li><li>HDD yok</li><li>P408i RAID + batarya</li><li>2x500W power supply</li></ul>',
                'seo_title' => 'HP DL380 Gen10 2x6152 Gold 256GB Sunucu',
                'seo_description' => 'HP DL380 Gen10 2x6152 Gold, 256GB RAM, P408i RAID ve 2x500W guc kaynagi ile sunucu.',
                'technical_specs' => [
                    'cpu' => '2x Intel Xeon Gold 6152',
                    'ram' => '256GB DDR4 (4x64GB 2400T)',
                    'disk_yuvasi' => '8x2.5 by',
                    'raid' => 'P408i RAID + Batarya',
                    'guc_kaynagi' => '2x500W',
                ],
            ],
        ];
    }
}
