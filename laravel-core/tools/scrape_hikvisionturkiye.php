<?php

declare(strict_types=1);

libxml_use_internal_errors(true);

const DEFAULT_SOURCE_DIR = __DIR__ . '/../hikvisionturkiye.net';
const DEFAULT_OUTPUT_DIR = __DIR__ . '/../hikvisionturkiye.net/urunler';
const SITE_BASE_URL = 'https://hikvisionturkiye.net';

main($argv);

function main(array $argv): void
{
    $options = parseOptions($argv);

    if (! is_dir($options['source_dir'])) {
        fwrite(STDERR, "Source directory not found: {$options['source_dir']}" . PHP_EOL);
        exit(1);
    }

    ensureDirectory($options['output_dir']);

    $products = extractProductsFromMirror($options['source_dir'], $options['limit']);

    if ($options['fetch_details']) {
        $totalProducts = count($products);
        $currentProduct = 0;

        foreach ($products as $index => $product) {
            $currentProduct++;
            fwrite(STDOUT, "[{$currentProduct}/{$totalProducts}] Fetching {$product['url']}" . PHP_EOL);
            $detail = fetchProductDetail($product['url']);

            if ($detail !== null) {
                $products[$index] = mergeProductData($product, $detail);
            }

            if ($options['sleep_ms'] > 0 && $currentProduct < $totalProducts) {
                usleep($options['sleep_ms'] * 1000);
            }
        }
    }

    if ($options['download_images']) {
        $imageDir = $options['output_dir'] . DIRECTORY_SEPARATOR . 'images';
        ensureDirectory($imageDir);

        foreach ($products as $index => $product) {
            $products[$index]['downloaded_images'] = downloadImagesForProduct($product, $imageDir);
        }
    }

    $products = normalizeProductsForOutput(enrichProductsForExport($products));

    $jsonPath = $options['output_dir'] . DIRECTORY_SEPARATOR . 'products.json';
    $xlsxPath = $options['output_dir'] . DIRECTORY_SEPARATOR . 'products.xlsx';

    file_put_contents(
        $jsonPath,
        json_encode(
            [
                'generated_at' => date(DATE_ATOM),
                'source_dir' => realpath($options['source_dir']) ?: $options['source_dir'],
                'fetch_details' => $options['fetch_details'],
                'download_images' => $options['download_images'],
                'product_count' => count($products),
                'products' => array_values($products),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )
    );

    writeExcelExportV4($xlsxPath, $products);

    fwrite(STDOUT, 'Done.' . PHP_EOL);
    fwrite(STDOUT, "JSON: {$jsonPath}" . PHP_EOL);
    fwrite(STDOUT, "Excel: {$xlsxPath}" . PHP_EOL);
}

function parseOptions(array $argv): array
{
    $options = [
        'source_dir' => DEFAULT_SOURCE_DIR,
        'output_dir' => DEFAULT_OUTPUT_DIR,
        'fetch_details' => true,
        'download_images' => false,
        'limit' => null,
        'sleep_ms' => 350,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--no-fetch-details') {
            $options['fetch_details'] = false;
            continue;
        }

        if ($arg === '--download-images') {
            $options['download_images'] = true;
            continue;
        }

        if (str_starts_with($arg, '--source=')) {
            $options['source_dir'] = normalizePath(substr($arg, 9));
            continue;
        }

        if (str_starts_with($arg, '--output=')) {
            $options['output_dir'] = normalizePath(substr($arg, 9));
            continue;
        }

        if (str_starts_with($arg, '--limit=')) {
            $options['limit'] = max(1, (int) substr($arg, 8));
            continue;
        }

        if (str_starts_with($arg, '--sleep-ms=')) {
            $options['sleep_ms'] = max(0, (int) substr($arg, 11));
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            printHelp();
            exit(0);
        }
    }

    return $options;
}

function printHelp(): void
{
    $lines = [
        'Usage: php tools/scrape_hikvisionturkiye.php [options]',
        '',
        'Options:',
        '  --source=PATH          Local mirror directory',
        '  --output=PATH          Output directory',
        '  --no-fetch-details     Skip live product detail requests',
        '  --download-images      Download all discovered product images',
        '  --limit=N              Limit product count',
        '  --sleep-ms=N           Delay between live requests',
        '  --help                 Show this message',
        '',
        'Output files:',
        '  products.json          Structured raw data',
        '  products.xlsx          Excel file with Turkish-safe text',
    ];

    fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
}

function normalizePath(string $path): string
{
    $trimmed = trim($path, "\"'");

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmed) === 1) {
        return $trimmed;
    }

    return realpath($trimmed) ?: $trimmed;
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException("Failed to create directory: {$path}");
    }
}

function extractProductsFromMirror(string $sourceDir, ?int $limit = null): array
{
    $products = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'html') {
            continue;
        }

        $html = file_get_contents($file->getPathname());

        if ($html === false || ! str_contains($html, 'class="product-thumb"')) {
            continue;
        }

        foreach (parseProductCards($html) as $product) {
            $url = $product['url'];

            if (! $url) {
                continue;
            }

            if (! isset($products[$url])) {
                $product['source_files'] = [$file->getPathname()];
                $products[$url] = $product;
            } else {
                $products[$url]['source_files'][] = $file->getPathname();
                $products[$url] = mergeProductData($products[$url], $product);
            }

            if ($limit !== null && count($products) >= $limit) {
                return $products;
            }
        }
    }

    return $products;
}

function parseProductCards(string $html): array
{
    $products = [];

    if (! preg_match_all('/<div class="product-layout\b.*?<div class="name"><a href="([^"]+)">(.*?)<\/a><\/div><div class="description">(.*?)<\/div><div class="price"><div>(.*?)<\/div>(?:<span class="price-tax">(.*?)<\/span>)?/si', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $match) {
        $block = $match[0];
        $url = absolutizeUrl(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $name = cleanText(strip_tags($match[2]));

        if ($url === '' || $name === '') {
            continue;
        }

        preg_match('/<(?:img)[^>]+(?:data-src|src)="([^"]+)"/i', $block, $imageMatch);
        preg_match('/<span class="price-new">(.*?)<\/span>/si', $block, $priceNewMatch);
        preg_match('/<span class="price-old">(.*?)<\/span>/si', $block, $priceOldMatch);
        preg_match('/<span class="stat-1"><span class="stats-label">.*?<\/span>\s*<span><a[^>]*>(.*?)<\/a>/si', $block, $brandMatch);
        preg_match('/<span class="stat-2"><span class="stats-label">.*?<\/span>\s*<span>(.*?)<\/span>/si', $block, $modelMatch);

        $imageUrl = absolutizeUrl(html_entity_decode($imageMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $priceNew = cleanText(strip_tags($priceNewMatch[1] ?? ''));
        $priceOld = cleanText(strip_tags($priceOldMatch[1] ?? ''));
        $priceTax = cleanText(strip_tags($match[5] ?? ''));
        $brand = cleanText(strip_tags($brandMatch[1] ?? ''));
        $model = cleanText(strip_tags($modelMatch[1] ?? ''));
        $summaryDescription = cleanText(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $match[3])));

        $products[] = [
            'title' => $name,
            'url' => $url,
            'brand' => $brand,
            'model' => $model,
            'summary_description' => $summaryDescription,
            'detail_description_text' => null,
            'detail_description_html' => null,
            'price_new' => $priceNew,
            'price_old' => $priceOld,
            'price_tax' => $priceTax,
            'main_image' => $imageUrl,
            'images' => array_values(array_filter([$imageUrl])),
            'downloaded_images' => [],
        ];
    }

    return $products;
}

function fetchProductDetail(string $url): ?array
{
    $html = httpGet($url);

    if ($html === null || trim($html) === '') {
        return null;
    }

    $dom = loadHtmlDocument($html);
    $xpath = new DOMXPath($dom);

    $title = firstNodeText($xpath, [
        '//h1[contains(@class, "page-title")]',
        '//meta[@property="og:title"]/@content',
        '//title',
    ]);

    $descriptionNode = firstNode($xpath, [
        '//*[@id="tab-description"]',
        '//div[contains(@class, "product-description")]',
        '//*[@itemprop="description"]',
    ]);

    $detailHtml = null;
    $detailText = null;

    if ($descriptionNode instanceof DOMNode) {
        $detailHtml = innerHtml($descriptionNode);
        $detailText = cleanText($descriptionNode->textContent);
    }

    $priceNew = firstNodeText($xpath, [
        '//span[contains(@class, "product-price-new")]',
        '//span[contains(@class, "price-new")]',
        '//div[contains(@class, "price-group")]//span[1]',
    ]);

    $priceOld = firstNodeText($xpath, [
        '//span[contains(@class, "product-price-old")]',
        '//span[contains(@class, "price-old")]',
    ]);

    $priceTax = firstNodeText($xpath, [
        '//span[contains(@class, "product-tax")]',
        '//span[contains(@class, "price-tax")]',
    ]);

    $brand = firstNodeText($xpath, [
        '//div[contains(@class, "product-manufacturer")]//a',
        '//span[contains(@class, "stat-1")]//a',
    ]);

    $model = firstNodeText($xpath, [
        '//li[contains(@class, "product-model")]//span[last()]',
        '//span[contains(@class, "stat-2")]//span[last()]',
    ]);

    $images = extractDetailImages($xpath);

    return [
        'title' => $title,
        'brand' => $brand,
        'model' => $model,
        'detail_description_text' => $detailText,
        'detail_description_html' => $detailHtml,
        'price_new' => $priceNew,
        'price_old' => $priceOld,
        'price_tax' => $priceTax,
        'main_image' => $images[0] ?? null,
        'images' => $images,
    ];
}

function extractDetailImages(DOMXPath $xpath): array
{
    $queries = [
        '//div[contains(@class, "product-left")]//a[contains(@href, "/image/")]',
        '//div[contains(@class, "product-left")]//*[@data-image]',
        '//div[contains(@class, "product-left")]//img',
        '//a[contains(@data-src, "/image/")]',
    ];

    $images = [];

    foreach ($queries as $query) {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            continue;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $candidate = firstNonEmptyAttribute($node, ['href', 'data-image', 'data-src', 'src']);
            $url = canonicalizeProductImageUrl(absolutizeUrl($candidate));

            if ($url !== '' && isLikelyImageUrl($url)) {
                $images[$url] = $url;
            }
        }
    }

    return deduplicatePreferredImageUrls(array_values($images));
}

function mergeProductData(array $base, array $detail): array
{
    foreach ($detail as $key => $value) {
        if ($key === 'images') {
            $base['images'] = deduplicatePreferredImageUrls(
                array_values(array_unique(array_filter(array_merge($base['images'] ?? [], $value ?? []))))
            );
            continue;
        }

        if ($key === 'source_files') {
            $base['source_files'] = array_values(array_unique(array_merge($base['source_files'] ?? [], $value ?? [])));
            continue;
        }

        if ($value !== null && $value !== '') {
            if (
                isset($base[$key]) &&
                is_string($base[$key]) &&
                is_string($value) &&
                $base[$key] !== '' &&
                isLikelyMojibakeV2($value) &&
                ! isLikelyMojibakeV2($base[$key])
            ) {
                continue;
            }

            $base[$key] = $value;
        }
    }

    if ((! isset($base['main_image']) || $base['main_image'] === '') && ! empty($base['images'])) {
        $base['main_image'] = $base['images'][0];
    }

    return $base;
}

function downloadImagesForProduct(array $product, string $imageDir): array
{
    $downloaded = [];
    $slug = slugify($product['model'] ?: $product['title']);
    $productDir = $imageDir . DIRECTORY_SEPARATOR . $slug;
    ensureDirectory($productDir);

    foreach ($product['images'] as $index => $imageUrl) {
        $binary = null;
        $resolvedImageUrl = null;

        foreach (buildImageUrlCandidates($imageUrl) as $candidateUrl) {
            $binary = httpGet($candidateUrl, true);

            if ($binary !== null && $binary !== '') {
                $resolvedImageUrl = $candidateUrl;
                break;
            }
        }

        if ($binary === null || $binary === '' || $resolvedImageUrl === null) {
            continue;
        }

        $extension = pathinfo(parse_url($resolvedImageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        $extension = $extension !== '' ? strtolower($extension) : 'jpg';
        $filename = sprintf('%02d-%s.%s', $index + 1, substr(md5($resolvedImageUrl), 0, 10), $extension);
        $targetPath = $productDir . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($targetPath, $binary);
        $downloaded[] = $targetPath;
    }

    return $downloaded;
}

function writeExcel(string $path, array $products): void
{
    $rows = [[
        'Başlık',
        'Marka',
        'Model',
        'Ürün URL',
        'Kısa Açıklama',
        'Detay Açıklama',
        'Yeni Fiyat',
        'Eski Fiyat',
        'Vergi Bilgisi',
        'Ana Görsel',
        'Tüm Görseller',
        'Kaynak Dosyalar',
    ]];

    foreach ($products as $product) {
        $rows[] = [
            normalizeExcelText($product['title'] ?? ''),
            normalizeExcelText($product['brand'] ?? ''),
            normalizeExcelText($product['model'] ?? ''),
            normalizeExcelText($product['url'] ?? ''),
            normalizeExcelText($product['summary_description'] ?? ''),
            normalizeExcelText($product['detail_description_text'] ?? ''),
            normalizeExcelText($product['price_new'] ?? ''),
            normalizeExcelText($product['price_old'] ?? ''),
            normalizeExcelText($product['price_tax'] ?? ''),
            normalizeExcelText($product['main_image'] ?? ''),
            normalizeExcelText(implode("\n", $product['images'] ?? [])),
            normalizeExcelText(implode("\n", $product['source_files'] ?? [])),
        ];
    }

    writeXlsxFile($path, $rows);
}

function httpGet(string $url, bool $binary = false): ?string
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

function loadHtmlDocument(string $html): DOMDocument
{
    $encoding = 'UTF-8';

    if (! mb_check_encoding($html, 'UTF-8')) {
        $encoding = mb_detect_encoding($html, ['Windows-1254', 'ISO-8859-9', 'ISO-8859-1', 'ASCII'], true) ?: 'UTF-8';
    }

    $html = mb_convert_encoding($html, 'HTML-ENTITIES', $encoding);

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);

    return $dom;
}

function queryOne(DOMXPath $xpath, string $query, ?DOMNode $contextNode = null): ?DOMNode
{
    $nodes = $xpath->query($query, $contextNode);

    if ($nodes === false || $nodes->length === 0) {
        return null;
    }

    return $nodes->item(0);
}

function firstNode(DOMXPath $xpath, array $queries): ?DOMNode
{
    foreach ($queries as $query) {
        $node = queryOne($xpath, $query);

        if ($node !== null) {
            return $node;
        }
    }

    return null;
}

function firstNodeText(DOMXPath $xpath, array $queries): string
{
    $node = firstNode($xpath, $queries);

    if ($node === null) {
        return '';
    }

    return cleanText($node->textContent);
}

function firstNonEmptyAttribute(DOMElement $node, array $attributes): string
{
    foreach ($attributes as $attribute) {
        $value = trim($node->getAttribute($attribute));

        if ($value !== '' && ! str_starts_with($value, 'data:image/')) {
            return $value;
        }
    }

    return '';
}

function cleanText(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = repairMojibakeV2($value);
    $value = preg_replace('/\x{00A0}/u', ' ', $value) ?? $value;
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

    return trim($value, "\x00..\x1F \t\n\r\0\x0B");
}

function repairMojibake(string $value): string
{
    $current = $value;

    for ($i = 0; $i < 3; $i++) {
        if (! preg_match('/[ÃÄÅÂ]/u', $current)) {
            break;
        }

        $repaired = @mb_convert_encoding($current, 'UTF-8', 'Windows-1252');

        if (! is_string($repaired) || $repaired === '' || $repaired === $current) {
            break;
        }

        $current = $repaired;
    }

    return $current;
}

function repairMojibakeV2(string $value): string
{
    $current = strtr($value, [
        'â‚º' => '₺',
        'â‚¬' => '€',
        'â€“' => '-',
        'â€”' => '-',
        'â€¦' => '...',
        'Â ' => ' ',
        'Â' => '',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $best = $current;
        $bestScore = mojibakeScoreV2($best);

        foreach (['Windows-1252', 'ISO-8859-1', 'ISO-8859-9', 'Windows-1254'] as $encoding) {
            $candidate = @mb_convert_encoding($current, 'UTF-8', $encoding);

            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $candidate = strtr($candidate, [
                'â‚º' => '₺',
                'â‚¬' => '€',
                'Ä±' => 'ı',
                'Ä°' => 'İ',
                'Ã¼' => 'ü',
                'Ãœ' => 'Ü',
                'Ã¶' => 'ö',
                'Ã–' => 'Ö',
                'Ã§' => 'ç',
                'Ã‡' => 'Ç',
                'ÄŸ' => 'ğ',
                'Äž' => 'Ğ',
                'ÅŸ' => 'ş',
                'Åž' => 'Ş',
            ]);

            $candidateScore = mojibakeScoreV2($candidate);

            if ($candidateScore < $bestScore) {
                $best = $candidate;
                $bestScore = $candidateScore;
            }
        }

        if ($best === $current) {
            break;
        }

        $current = $best;
    }

    return $current;
}

function isLikelyMojibakeV2(string $value): bool
{
    return mojibakeScoreV2($value) > 0;
}

function mojibakeScoreV2(string $value): int
{
    preg_match_all('/(?:Ã.|Ä.|Å.|Â.|â.|�)/u', $value, $matches);

    return count($matches[0]);
}

function innerHtml(DOMNode $node): string
{
    $html = '';

    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument?->saveHTML($child) ?? '';
    }

    return trim($html);
}

function absolutizeUrl(string $value): string
{
    $value = trim($value);

    if ($value === '' || str_starts_with($value, 'javascript:')) {
        return '';
    }

    if (str_starts_with($value, '//')) {
        return 'https:' . $value;
    }

    if (preg_match('#^https?://#i', $value) === 1) {
        return normalizeUrl($value);
    }

    return normalizeUrl(rtrim(SITE_BASE_URL, '/') . '/' . ltrim($value, '/'));
}

function isLikelyImageUrl(string $url): bool
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');

    foreach (['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp'] as $extension) {
        if (str_ends_with($path, $extension)) {
            return true;
        }
    }

    return str_contains($path, '/image/');
}

function slugify(string $value): string
{
    $value = strtolower(cleanText($value));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    return $value !== '' ? $value : 'product-' . substr(md5($value), 0, 8);
}

function normalizeExcelText(string $value): string
{
    $value = cleanText($value);
    $value = repairMojibakeV2($value);
    $value = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\x{2060}]/u', '', $value) ?? $value;

    return $value;
}

function normalizeProductsForOutput(array $products): array
{
    foreach ($products as $productKey => $product) {
        foreach ($product as $field => $value) {
            if (is_string($value)) {
                $products[$productKey][$field] = normalizeExcelText($value);
                continue;
            }

            if (is_array($value)) {
                $products[$productKey][$field] = array_map(
                    static fn ($item) => is_string($item) ? normalizeExcelText($item) : $item,
                    $value
                );
            }
        }
    }

    return $products;
}

function enrichProductsForExport(array $products): array
{
    foreach ($products as $productKey => $product) {
        $images = $product['images'] ?? [];
        $downloadedImages = $product['downloaded_images'] ?? [];
        $normalizedNewPrice = normalizePriceTextV2($product['price_new'] ?? '');
        $normalizedOldPrice = normalizePriceTextV2($product['price_old'] ?? '');
        $normalizedTaxPrice = normalizePriceTextV2($product['price_tax'] ?? '', true);
        $priceDisplay = firstNonEmptyValue([
            $normalizedNewPrice,
            $normalizedOldPrice,
            $normalizedTaxPrice,
        ]);
        $sourceCategoryPath = extractCategoryPathsFromSourceFiles($product['source_files'] ?? []);
        $urlCategoryPath = extractCategoryPathFromProductUrl($product['url'] ?? '');

        $products[$productKey]['price_new'] = $normalizedNewPrice;
        $products[$productKey]['price_old'] = $normalizedOldPrice;
        $products[$productKey]['price_tax'] = $normalizedTaxPrice;
        $products[$productKey]['price_display'] = $priceDisplay;
        $products[$productKey]['price_numeric'] = extractPriceNumeric($priceDisplay);
        $products[$productKey]['price_currency'] = detectCurrencyV4($priceDisplay);
        $products[$productKey]['image_count'] = count($images);
        $products[$productKey]['downloaded_image_count'] = count($downloadedImages);
        $products[$productKey]['local_image_dir'] = ! empty($downloadedImages) ? (dirname($downloadedImages[0]) ?: '') : '';
        $products[$productKey]['local_main_image'] = $downloadedImages[0] ?? '';
        $products[$productKey]['source_category_path'] = $sourceCategoryPath;
        $products[$productKey]['url_category_path'] = $urlCategoryPath;
        $products[$productKey]['category_path'] = firstNonEmptyValue([$urlCategoryPath, $sourceCategoryPath]);
    }

    return $products;
}

function writeExcelExport(string $path, array $products): void
{
    $rows = [[
        'Başlık',
        'Marka',
        'Model',
        'Ürün URL',
        'Kısa Açıklama',
        'Detay Açıklama',
        'Kategori',
        'Kategori (Source)',
        'Kategori (URL)',
        'Fiyat',
        'Yeni Fiyat',
        'Eski Fiyat',
        'Vergi Bilgisi',
        'Fiyat Sayısal',
        'Para Birimi',
        'Ana Görsel URL',
        'Tüm Görseller URL',
        'Görsel Sayısı',
        'İndirilen Görsel Sayısı',
        'Yerel Görsel Klasörü',
        'Yerel Görsel Dosyaları',
        'Kaynak Dosyalar',
    ]];

    foreach ($products as $product) {
        $rows[] = [
            normalizeExcelText($product['title'] ?? ''),
            normalizeExcelText($product['brand'] ?? ''),
            normalizeExcelText($product['model'] ?? ''),
            normalizeExcelText($product['url'] ?? ''),
            normalizeExcelText($product['summary_description'] ?? ''),
            normalizeExcelText($product['detail_description_text'] ?? ''),
            normalizeExcelText($product['category_path'] ?? ''),
            normalizeExcelText($product['source_category_path'] ?? ''),
            normalizeExcelText($product['url_category_path'] ?? ''),
            normalizeExcelText($product['price_display'] ?? ''),
            normalizeExcelText($product['price_new'] ?? ''),
            normalizeExcelText($product['price_old'] ?? ''),
            normalizeExcelText($product['price_tax'] ?? ''),
            normalizeExcelText((string) ($product['price_numeric'] ?? '')),
            normalizeExcelText($product['price_currency'] ?? ''),
            normalizeExcelText($product['main_image'] ?? ''),
            normalizeExcelText(implode("\n", $product['images'] ?? [])),
            normalizeExcelText((string) ($product['image_count'] ?? 0)),
            normalizeExcelText((string) ($product['downloaded_image_count'] ?? 0)),
            normalizeExcelText($product['local_image_dir'] ?? ''),
            normalizeExcelText(implode("\n", $product['downloaded_images'] ?? [])),
            normalizeExcelText(implode("\n", $product['source_files'] ?? [])),
        ];
    }

    writeXlsxFile($path, $rows);
}

function firstNonEmptyValue(array $values): string
{
    foreach ($values as $value) {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
    }

    return '';
}

function extractPriceNumeric(string $price): string
{
    $price = normalizeExcelText($price);

    if ($price === '') {
        return '';
    }

    if (preg_match('/([0-9\.\,]+)/u', $price, $match) !== 1) {
        return '';
    }

    $numeric = str_replace('.', '', $match[1]);
    $numeric = str_replace(',', '.', $numeric);

    return $numeric;
}

function normalizePriceTextV2(string $price, bool $keepTaxLabel = false): string
{
    $price = normalizeExcelText($price);

    if ($price === '') {
        return '';
    }

    if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]+)?)/u', $price, $match) !== 1) {
        return $price;
    }

    $number = $match[1];
    $currency = detectCurrencyV4($price);

    if ($currency === '' && preg_match('/(?:tl|try|₺|â)/iu', $price) === 1) {
        $currency = 'TRY';
    }

    $suffix = match ($currency) {
        'TRY' => '₺',
        'USD' => '$',
        'EUR' => '€',
        default => '',
    };

    $normalized = $number . $suffix;

    if ($keepTaxLabel && preg_match('/Vergiler\s+Hari/i', $price) === 1) {
        return 'Vergiler Hariç:' . $normalized;
    }

    return $normalized;
}

function detectCurrency(string $price): string
{
    if (str_contains($price, '₺')) {
        return 'TRY';
    }

    if (str_contains($price, '$')) {
        return 'USD';
    }

    if (str_contains($price, '€')) {
        return 'EUR';
    }

    return '';
}

function extractCategoryPathsFromSourceFiles(array $sourceFiles): string
{
    $paths = [];

    foreach ($sourceFiles as $sourceFile) {
        if (! is_string($sourceFile) || trim($sourceFile) === '') {
            continue;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceFile);
        $marker = DIRECTORY_SEPARATOR . 'hikvisionturkiye.net' . DIRECTORY_SEPARATOR;
        $position = stripos($normalized, $marker);

        if ($position === false) {
            continue;
        }

        $relative = substr($normalized, $position + strlen($marker));
        $relative = preg_replace('/\.html?$/i', '', $relative) ?? $relative;
        $parts = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $relative), static fn ($part) => $part !== ''));

        if (empty($parts)) {
            continue;
        }

        $path = implode(' > ', array_map('humanizeCategorySlug', $parts));

        if ($path !== '') {
            $paths[$path] = categoryPriorityScore($path);
        }
    }

    if ($paths === []) {
        return '';
    }

    asort($paths, SORT_NUMERIC);

    return (string) array_key_first($paths);
}

function extractCategoryPathFromProductUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);

    if (! is_string($path) || trim($path, '/') === '') {
        return '';
    }

    $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn ($part) => $part !== ''));

    if (count($parts) < 2) {
        return '';
    }

    array_pop($parts);

    return implode(' > ', array_map('humanizeCategorySlug', $parts));
}

function humanizeCategorySlug(string $value): string
{
    $value = rawurldecode($value);
    $value = str_replace(['-', '_'], ' ', $value);
    $value = cleanText($value);

    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

function categoryPriorityScore(string $path): int
{
    $score = substr_count($path, ' > ') * 10;

    if (str_contains($path, 'Kampanya')) {
        $score += 100;
    }

    if (str_contains($path, 'Index Php')) {
        $score += 100;
    }

    return $score;
}

function writeXlsxFile(string $path, array $rows): void
{
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create Excel file: {$path}");
    }

    $zip->addFromString('[Content_Types].xml', buildContentTypesXml());
    $zip->addFromString('_rels/.rels', buildRootRelsXml());
    $zip->addFromString('xl/workbook.xml', buildWorkbookXml());
    $zip->addFromString('xl/_rels/workbook.xml.rels', buildWorkbookRelsXml());
    $zip->addFromString('xl/styles.xml', buildStylesXml());
    $zip->addFromString('xl/worksheets/sheet1.xml', buildWorksheetXml($rows));

    $zip->close();
}

function writeExcelExportV2(string $path, array $products): void
{
    $rows = [[
        'Başlık',
        'Marka',
        'Model',
        'Ürün URL',
        'Kısa Açıklama',
        'Detay Açıklama',
        'Kategori',
        'Kategori (Source)',
        'Kategori (URL)',
        'Fiyat',
        'Yeni Fiyat',
        'Eski Fiyat',
        'Vergi Bilgisi',
        'Fiyat Sayısal',
        'Para Birimi',
        'Ana Görsel Aç',
        'Ana Görsel URL',
        'Ana Görsel Yerel Dosya',
        'Tüm Görseller URL',
        'Görsel Sayısı',
        'İndirilen Görsel Sayısı',
        'Yerel Görsel Klasörü',
        'Yerel Görsel Dosyaları',
        'Kaynak Dosyalar',
    ]];

    foreach ($products as $product) {
        $rows[] = [
            normalizeExcelText($product['title'] ?? ''),
            normalizeExcelText($product['brand'] ?? ''),
            normalizeExcelText($product['model'] ?? ''),
            normalizeExcelText($product['url'] ?? ''),
            normalizeExcelText($product['summary_description'] ?? ''),
            normalizeExcelText($product['detail_description_text'] ?? ''),
            normalizeExcelText($product['category_path'] ?? ''),
            normalizeExcelText($product['source_category_path'] ?? ''),
            normalizeExcelText($product['url_category_path'] ?? ''),
            normalizeExcelText($product['price_display'] ?? ''),
            normalizeExcelText($product['price_new'] ?? ''),
            normalizeExcelText($product['price_old'] ?? ''),
            normalizeExcelText($product['price_tax'] ?? ''),
            normalizeExcelText((string) ($product['price_numeric'] ?? '')),
            normalizeExcelText($product['price_currency'] ?? ''),
            buildHyperlinkCell($product['main_image'] ?? '', 'Görseli Aç'),
            normalizeExcelText($product['main_image'] ?? ''),
            normalizeExcelText($product['local_main_image'] ?? ''),
            normalizeExcelText(implode("\n", $product['images'] ?? [])),
            normalizeExcelText((string) ($product['image_count'] ?? 0)),
            normalizeExcelText((string) ($product['downloaded_image_count'] ?? 0)),
            normalizeExcelText($product['local_image_dir'] ?? ''),
            normalizeExcelText(implode("\n", $product['downloaded_images'] ?? [])),
            normalizeExcelText(implode("\n", $product['source_files'] ?? [])),
        ];
    }

    writeXlsxFileV2($path, $rows);
}

function writeXlsxFileV2(string $path, array $rows): void
{
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create Excel file: {$path}");
    }

    $zip->addFromString('[Content_Types].xml', buildContentTypesXml());
    $zip->addFromString('_rels/.rels', buildRootRelsXml());
    $zip->addFromString('xl/workbook.xml', buildWorkbookXmlV2());
    $zip->addFromString('xl/_rels/workbook.xml.rels', buildWorkbookRelsXml());
    $zip->addFromString('xl/styles.xml', buildStylesXml());
    $zip->addFromString('xl/worksheets/sheet1.xml', buildWorksheetXmlV2($rows));

    $zip->close();
}

function buildWorkbookXmlV2(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Ürünler" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
}

function buildWorksheetXmlV2(array $rows): string
{
    $sheetRows = [];
    $maxColumns = 0;

    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        $maxColumns = max($maxColumns, count($row));

        foreach ($row as $columnIndex => $value) {
            $cellRef = excelColumnName($columnIndex + 1) . ($rowIndex + 1);
            $styleId = $rowIndex === 0 ? 1 : 0;
            $cells[] = buildWorksheetCellXmlV2($cellRef, $value, $styleId);
        }

        $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    $dimension = 'A1:' . excelColumnName(max(1, $maxColumns)) . max(1, count($rows));
    $sheetData = implode('', $sheetRows);

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="{$dimension}"/>
  <sheetViews>
    <sheetView workbookViewId="0"/>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <sheetData>{$sheetData}</sheetData>
</worksheet>
XML;
}

function buildWorksheetCellXmlV2(string $cellRef, mixed $value, int $styleId): string
{
    if (is_array($value) && ($value['type'] ?? '') === 'formula') {
        $formula = htmlspecialchars((string) ($value['formula'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="' . $cellRef . '" s="' . $styleId . '"><f>' . $formula . '</f></c>';
    }

    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<c r="' . $cellRef . '" t="inlineStr" s="' . $styleId . '"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
}

function buildHyperlinkCell(string $target, string $label): array|string
{
    $target = normalizeExcelText($target);

    if ($target === '') {
        return '';
    }

    $escapedTarget = str_replace('"', '""', $target);
    $escapedLabel = str_replace('"', '""', $label);

    return [
        'type' => 'formula',
        'formula' => 'HYPERLINK("' . $escapedTarget . '","' . $escapedLabel . '")',
    ];
}

function writeExcelExportV3(string $path, array $products): void
{
    $rows = [[
        'Başlık',
        'Marka',
        'Model',
        'Ürün URL',
        'Kısa Açıklama',
        'Detay Açıklama',
        'Kategori',
        'Kategori (Source)',
        'Kategori (URL)',
        'Fiyat',
        'Yeni Fiyat',
        'Eski Fiyat',
        'Vergi Bilgisi',
        'Fiyat Sayısal',
        'Para Birimi',
        'Ana Görsel Aç',
        'Ana Görsel URL',
        'Ana Görsel Yerel Dosya',
        'Tüm Görseller URL',
        'Görsel Sayısı',
        'İndirilen Görsel Sayısı',
        'Yerel Görsel Klasörü',
        'Yerel Görsel Dosyaları',
        'Kaynak Dosyalar',
    ]];

    foreach ($products as $product) {
        $rows[] = [
            normalizeExcelText($product['title'] ?? ''),
            normalizeExcelText($product['brand'] ?? ''),
            normalizeExcelText($product['model'] ?? ''),
            normalizeExcelText($product['url'] ?? ''),
            normalizeExcelText($product['summary_description'] ?? ''),
            normalizeExcelText($product['detail_description_text'] ?? ''),
            normalizeExcelText($product['category_path'] ?? ''),
            normalizeExcelText($product['source_category_path'] ?? ''),
            normalizeExcelText($product['url_category_path'] ?? ''),
            normalizeExcelText($product['price_display'] ?? ''),
            normalizeExcelText($product['price_new'] ?? ''),
            normalizeExcelText($product['price_old'] ?? ''),
            normalizeExcelText($product['price_tax'] ?? ''),
            normalizeExcelText((string) ($product['price_numeric'] ?? '')),
            detectCurrencyV3($product['price_display'] ?? ''),
            buildHyperlinkCell(normalizeExcelText($product['main_image'] ?? ''), 'Görseli Aç'),
            normalizeExcelText($product['main_image'] ?? ''),
            normalizeExcelText($product['local_main_image'] ?? ''),
            normalizeExcelText(implode("\n", $product['images'] ?? [])),
            normalizeExcelText((string) ($product['image_count'] ?? 0)),
            normalizeExcelText((string) ($product['downloaded_image_count'] ?? 0)),
            normalizeExcelText($product['local_image_dir'] ?? ''),
            normalizeExcelText(implode("\n", $product['downloaded_images'] ?? [])),
            normalizeExcelText(implode("\n", $product['source_files'] ?? [])),
        ];
    }

    writeXlsxFileV3($path, $rows);
}

function writeXlsxFileV3(string $path, array $rows): void
{
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create Excel file: {$path}");
    }

    $zip->addFromString('[Content_Types].xml', buildContentTypesXml());
    $zip->addFromString('_rels/.rels', buildRootRelsXml());
    $zip->addFromString('xl/workbook.xml', buildWorkbookXmlV3());
    $zip->addFromString('xl/_rels/workbook.xml.rels', buildWorkbookRelsXml());
    $zip->addFromString('xl/styles.xml', buildStylesXml());
    $zip->addFromString('xl/worksheets/sheet1.xml', buildWorksheetXmlV3($rows));

    $zip->close();
}

function buildWorkbookXmlV3(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Ürünler" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
}

function buildWorksheetXmlV3(array $rows): string
{
    $sheetRows = [];
    $maxColumns = 0;

    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        $maxColumns = max($maxColumns, count($row));

        foreach ($row as $columnIndex => $value) {
            $cellRef = excelColumnName($columnIndex + 1) . ($rowIndex + 1);
            $styleId = $rowIndex === 0 ? 1 : 0;
            $cells[] = buildWorksheetCellXmlV3($cellRef, $value, $styleId);
        }

        $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    $dimension = 'A1:' . excelColumnName(max(1, $maxColumns)) . max(1, count($rows));
    $sheetData = implode('', $sheetRows);

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="{$dimension}"/>
  <sheetViews>
    <sheetView workbookViewId="0"/>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <sheetData>{$sheetData}</sheetData>
</worksheet>
XML;
}

function buildWorksheetCellXmlV3(string $cellRef, mixed $value, int $styleId): string
{
    if (is_array($value) && ($value['type'] ?? '') === 'formula') {
        $formula = htmlspecialchars((string) ($value['formula'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="' . $cellRef . '" s="' . $styleId . '"><f>' . $formula . '</f></c>';
    }

    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<c r="' . $cellRef . '" t="inlineStr" s="' . $styleId . '"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
}

function detectCurrencyV3(string $price): string
{
    $price = normalizeExcelText($price);

    if (str_contains($price, '₺')) {
        return 'TRY';
    }

    if (str_contains($price, '$')) {
        return 'USD';
    }

    if (str_contains($price, '€')) {
        return 'EUR';
    }

    return '';
}

function detectCurrencyV4(string $price): string
{
    $price = normalizeExcelText($price);

    if (str_contains($price, '₺')) {
        return 'TRY';
    }

    if (str_contains($price, '$')) {
        return 'USD';
    }

    if (str_contains($price, '€')) {
        return 'EUR';
    }

    return '';
}

function writeExcelExportV4(string $path, array $products): void
{
    $rows = [[
        'Başlık',
        'Marka',
        'Model',
        'Ürün URL',
        'Kısa Açıklama',
        'Detay Açıklama',
        'Kategori',
        'Kategori (Source)',
        'Kategori (URL)',
        'Fiyat',
        'Yeni Fiyat',
        'Eski Fiyat',
        'Vergi Bilgisi',
        'Fiyat Sayısal',
        'Para Birimi',
        'Ana Görsel Aç',
        'Ana Görsel URL',
        'Ana Görsel Yerel Dosya',
        'Tüm Görseller URL',
        'Görsel Sayısı',
        'İndirilen Görsel Sayısı',
        'Yerel Görsel Klasörü',
        'Yerel Görsel Dosyaları',
        'Kaynak Dosyalar',
    ]];

    foreach ($products as $product) {
        $rows[] = [
            normalizeExcelText($product['title'] ?? ''),
            normalizeExcelText($product['brand'] ?? ''),
            normalizeExcelText($product['model'] ?? ''),
            normalizeExcelText($product['url'] ?? ''),
            normalizeExcelText($product['summary_description'] ?? ''),
            normalizeExcelText($product['detail_description_text'] ?? ''),
            normalizeExcelText($product['category_path'] ?? ''),
            normalizeExcelText($product['source_category_path'] ?? ''),
            normalizeExcelText($product['url_category_path'] ?? ''),
            normalizeExcelText($product['price_display'] ?? ''),
            normalizeExcelText($product['price_new'] ?? ''),
            normalizeExcelText($product['price_old'] ?? ''),
            normalizeExcelText($product['price_tax'] ?? ''),
            normalizeExcelText((string) ($product['price_numeric'] ?? '')),
            detectCurrencyV4($product['price_display'] ?? ''),
            buildHyperlinkCell(normalizeExcelText($product['main_image'] ?? ''), 'Görseli Aç'),
            normalizeExcelText($product['main_image'] ?? ''),
            normalizeExcelText($product['local_main_image'] ?? ''),
            normalizeExcelText(implode("\n", $product['images'] ?? [])),
            normalizeExcelText((string) ($product['image_count'] ?? 0)),
            normalizeExcelText((string) ($product['downloaded_image_count'] ?? 0)),
            normalizeExcelText($product['local_image_dir'] ?? ''),
            normalizeExcelText(implode("\n", $product['downloaded_images'] ?? [])),
            normalizeExcelText(implode("\n", $product['source_files'] ?? [])),
        ];
    }

    writeXlsxFileV4($path, $rows);
}

function writeXlsxFileV4(string $path, array $rows): void
{
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create Excel file: {$path}");
    }

    $zip->addFromString('[Content_Types].xml', buildContentTypesXml());
    $zip->addFromString('_rels/.rels', buildRootRelsXml());
    $zip->addFromString('xl/workbook.xml', buildWorkbookXmlV4());
    $zip->addFromString('xl/_rels/workbook.xml.rels', buildWorkbookRelsXml());
    $zip->addFromString('xl/styles.xml', buildStylesXml());
    $zip->addFromString('xl/worksheets/sheet1.xml', buildWorksheetXmlV3($rows));

    $zip->close();
}

function buildWorkbookXmlV4(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Ürünler" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
}

function buildContentTypesXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
}

function buildRootRelsXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
}

function buildWorkbookXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Ürünler" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
}

function buildWorkbookRelsXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
}

function buildStylesXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
XML;
}

function buildWorksheetXml(array $rows): string
{
    $sheetRows = [];
    $maxColumns = 0;

    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        $maxColumns = max($maxColumns, count($row));

        foreach ($row as $columnIndex => $value) {
            $cellRef = excelColumnName($columnIndex + 1) . ($rowIndex + 1);
            $styleId = $rowIndex === 0 ? 1 : 0;
            $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells[] = '<c r="' . $cellRef . '" t="inlineStr" s="' . $styleId . '"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
        }

        $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    $dimension = 'A1:' . excelColumnName(max(1, $maxColumns)) . max(1, count($rows));
    $sheetData = implode('', $sheetRows);

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="{$dimension}"/>
  <sheetViews>
    <sheetView workbookViewId="0"/>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <sheetData>{$sheetData}</sheetData>
</worksheet>
XML;
}

function excelColumnName(int $index): string
{
    $name = '';

    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }

    return $name;
}

function normalizeUrl(string $url): string
{
    $parts = parse_url($url);

    if ($parts === false) {
        return $url;
    }

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $user = $parts['user'] ?? '';
    $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
    $auth = $user !== '' ? $user . $pass . '@' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    if ($path !== '') {
        $segments = explode('/', $path);
        $segments = array_map(
            static function (string $segment): string {
                if ($segment === '') {
                    return '';
                }

                return rawurlencode(rawurldecode($segment));
            },
            $segments
        );

        $path = implode('/', $segments);
    }

    return $scheme . $auth . $host . $port . $path . $query . $fragment;
}

function normalizeUrlVariants(string $url): array
{
    $parts = parse_url($url);

    if ($parts === false) {
        return [$url];
    }

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $user = $parts['user'] ?? '';
    $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
    $auth = $user !== '' ? $user . $pass . '@' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    if ($path === '') {
        return [normalizeUrl($url)];
    }

    $segmentVariants = [];

    foreach (explode('/', $path) as $segment) {
        if ($segment === '') {
            $segmentVariants[] = [''];
            continue;
        }

        $decoded = rawurldecode($segment);
        $variants = [$decoded];
        $repaired = repairMojibakeV2($decoded);

        if ($repaired !== '' && $repaired !== $decoded) {
            $variants[] = $repaired;
        }

        $segmentVariants[] = array_values(array_unique(array_map(
            static fn (string $value): string => rawurlencode($value),
            $variants
        )));
    }

    $paths = [''];

    foreach ($segmentVariants as $variants) {
        $nextPaths = [];

        foreach ($paths as $basePath) {
            foreach ($variants as $variant) {
                if ($basePath === '' && $variant === '') {
                    $nextPaths[] = '';
                    continue;
                }

                if ($basePath === '') {
                    $nextPaths[] = '/' . ltrim($variant, '/');
                    continue;
                }

                $nextPaths[] = rtrim($basePath, '/') . '/' . ltrim($variant, '/');
            }
        }

        $paths = array_values(array_unique($nextPaths));
    }

    $urls = [];

    foreach ($paths as $candidatePath) {
        $urls[] = $scheme . $auth . $host . $port . $candidatePath . $query . $fragment;
    }

    return array_values(array_unique(array_map('normalizeUrl', $urls)));
}

function canonicalizeProductImageUrl(string $url): string
{
    if ($url === '') {
        return '';
    }

    $original = convertCachedImageUrlToOriginal($url);

    if ($original !== '') {
        return normalizeUrl($original);
    }

    return normalizeUrl($url);
}

function deduplicatePreferredImageUrls(array $urls): array
{
    $bestByKey = [];

    foreach ($urls as $url) {
        if (! is_string($url) || $url === '') {
            continue;
        }

        $normalized = normalizeUrl($url);
        $key = productImageDeduplicationKey($normalized);
        $score = productImagePriorityScore($normalized);

        if (! isset($bestByKey[$key]) || $score > $bestByKey[$key]['score']) {
            $bestByKey[$key] = [
                'url' => $normalized,
                'score' => $score,
            ];
        }
    }

    uasort($bestByKey, static function (array $left, array $right): int {
        return $right['score'] <=> $left['score'];
    });

    return array_values(array_map(
        static fn (array $item): string => $item['url'],
        $bestByKey
    ));
}

function productImageDeduplicationKey(string $url): string
{
    $preferred = convertCachedImageUrlToOriginal($url);
    $path = parse_url($preferred !== '' ? $preferred : $url, PHP_URL_PATH) ?: $url;
    $filename = strtolower(pathinfo($path, PATHINFO_FILENAME));
    $filename = preg_replace('/-(\d+x\d+)([a-z]*)$/i', '', $filename) ?? $filename;

    return $filename;
}

function productImagePriorityScore(string $url): int
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    $score = str_contains($path, '/image/catalog/') ? 100 : 60;

    if (preg_match('/-(\d+)x(\d+)([a-z]*)\.(webp|jpg|jpeg|png)$/i', $path, $matches) === 1) {
        $width = (int) $matches[1];
        $height = (int) $matches[2];

        if ($width <= 120 && $height <= 120) {
            return 10;
        }

        if ($width <= 320 && $height <= 320) {
            return 30;
        }

        if ($width >= 500 || $height >= 500) {
            return $score + 20;
        }
    }

    return $score;
}

function buildImageUrlCandidates(string $url): array
{
    if ($url === '') {
        return [];
    }

    $candidates = [];
    $original = convertCachedImageUrlToOriginal($url);
    if ($original !== '') {
        $candidates = array_merge($candidates, normalizeUrlVariants($original));
    }

    $candidates = array_merge($candidates, normalizeUrlVariants($url));

    return array_values(array_unique(array_filter($candidates)));
}

function convertCachedImageUrlToOriginal(string $url): string
{
    $parts = parse_url($url);

    if ($parts === false || empty($parts['path'])) {
        return '';
    }

    $path = $parts['path'];

    if (! str_contains($path, '/image/cache/')) {
        return '';
    }

    $originalPath = preg_replace('#/image/cache/#', '/image/', $path, 1);

    if (! is_string($originalPath)) {
        return '';
    }

    $segments = explode('/', $originalPath);
    $last = array_pop($segments);

    if ($last === null || $last === '') {
        return '';
    }

    $last = preg_replace('/-(\d+x\d+)([a-z]*)\.(webp|jpg|jpeg|png)$/i', '.$3', $last) ?? $last;
    $last = preg_replace('/-(\d+x\d+)([a-z]*)$/i', '', $last) ?? $last;

    $segments[] = $last;
    $rebuiltPath = implode('/', $segments);

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return $scheme . $host . $port . $rebuiltPath;
}
