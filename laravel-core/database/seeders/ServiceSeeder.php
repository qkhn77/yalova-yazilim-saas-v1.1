<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/data/service-master-list.tr.json');
        $raw = is_file($dataPath) ? file_get_contents($dataPath) : null;
        $categoriesInput = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($categoriesInput)) {
            $this->command?->warn('ServiceSeeder: Veri dosyası okunamadı: '.$dataPath);

            return;
        }

        Schema::disableForeignKeyConstraints();
        DB::table('services')->truncate();
        DB::table('service_categories')->truncate();
        Schema::enableForeignKeyConstraints();

        $company = [
            'name' => 'Yalova Bilgisayar Ve Kamera Sistemleri',
            'phone' => '0226 352 07 24',
            'phone_tel' => '+902263520724',
            'email' => 'info@yalovabilgisayar.com',
            'website' => 'yalovakamera.com',
            'address' => 'Sahil Mah. Yalı Cad. No:3/A',
            'maps_url' => 'https://share.google/waQeoSIiO5nEuUjJo',
        ];

        $usedCategorySlugs = [];
        $usedServiceSlugs = [];
        $resolved = $this->resolveAllSlugs($categoriesInput, $usedCategorySlugs, $usedServiceSlugs);

        DB::transaction(function () use ($resolved, $company): void {
            $categoryModels = [];

            foreach ($resolved as $categoryIndex => $category) {
                $categoryName = (string) ($category['kategori_adi'] ?? '');
                $categorySlug = (string) ($category['kategori_slug_resolved'] ?? Str::slug($categoryName));

                $categoryModels[$categorySlug] = ServiceCategory::create([
                    'name' => $categoryName,
                    'slug' => $categorySlug,
                    'description' => $this->categoryDescription($categoryName, $company),
                    'meta_title' => $this->categoryMetaTitle($categoryName),
                    'meta_description' => $this->categoryMetaDescription($categoryName, $company),
                    'meta_keywords' => $this->categoryMetaKeywords($categoryName),
                    'sort_order' => ($categoryIndex + 1) * 10,
                    'is_active' => true,
                ]);
            }

            foreach ($resolved as $categoryIndex => $category) {
                $categoryName = (string) ($category['kategori_adi'] ?? '');
                $categorySlug = (string) ($category['kategori_slug_resolved'] ?? '');
                $categoryModel = $categoryModels[$categorySlug] ?? null;

                if (! $categoryModel) {
                    continue;
                }

                $services = (array) ($category['servisler'] ?? []);
                $serviceSlugsInCategory = array_map(
                    fn (array $s): string => (string) ($s['slug_resolved'] ?? ''),
                    array_filter($services, fn ($s) => is_array($s))
                );

                foreach ($services as $serviceIndex => $serviceInput) {
                    if (! is_array($serviceInput)) {
                        continue;
                    }

                    $baseTitle = trim((string) ($serviceInput['baslik'] ?? ''));
                    if ($baseTitle === '') {
                        continue;
                    }

                    $serviceSlug = (string) ($serviceInput['slug_resolved'] ?? Str::slug($baseTitle));
                    $metaTitle = $this->serviceMetaTitle($baseTitle, $categoryName);
                    $metaDescription = $this->serviceMetaDescription($baseTitle, $categoryName, $company);
                    $metaKeywords = $this->serviceMetaKeywords($baseTitle, $categoryName);
                    $imagePath = $this->serviceUniqueImagePath($baseTitle, $categoryName, $categorySlug, $serviceSlug);
                    $icon = $this->serviceIcon($categorySlug, $serviceSlug);

                    $globalSort = (($categoryIndex + 1) * 100) + ($serviceIndex + 1);

                    Service::create([
                        'service_category_id' => $categoryModel->id,
                        'title' => $metaTitle,
                        'slug' => $serviceSlug,
                        'short_description' => $metaDescription,
                        'meta_keywords' => $metaKeywords,
                        'content' => $this->serviceContent(
                            baseTitle: $baseTitle,
                            categoryName: $categoryName,
                            serviceSlug: $serviceSlug,
                            categorySlug: $categorySlug,
                            company: $company,
                            serviceSlugsInCategory: $serviceSlugsInCategory
                        ),
                        'image' => $imagePath,
                        'icon' => $icon,
                        'sort_order' => $globalSort,
                        'is_active' => true,
                    ]);
                }
            }
        });

        Cache::flush();
    }

    /**
     * Kategori ve servis slug'larını benzersiz olacak şekilde önceden üretir.
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<string, bool>  $usedCategorySlugs
     * @param  array<string, bool>  $usedServiceSlugs
     * @return array<int, array<string, mixed>>
     */
    private function resolveAllSlugs(array $categories, array &$usedCategorySlugs, array &$usedServiceSlugs): array
    {
        $out = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $categoryName = trim((string) ($category['kategori_adi'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $categorySlugInput = trim((string) ($category['kategori_slug'] ?? ''));
            $categorySlugBase = $categorySlugInput !== '' ? $categorySlugInput : Str::slug($categoryName);
            $categorySlug = $this->uniqueSlug($categorySlugBase, $usedCategorySlugs);

            $servicesInput = (array) ($category['servisler'] ?? []);
            $servicesOut = [];

            foreach ($servicesInput as $service) {
                if (! is_array($service)) {
                    continue;
                }

                $baseTitle = trim((string) ($service['baslik'] ?? ''));
                if ($baseTitle === '') {
                    continue;
                }

                $serviceSlugBase = Str::slug($baseTitle);
                $serviceSlug = $this->uniqueSlug($serviceSlugBase, $usedServiceSlugs, suffixBase: $categorySlug);

                $servicesOut[] = [
                    'baslik' => $baseTitle,
                    'slug_resolved' => $serviceSlug,
                ];
            }

            $out[] = [
                'kategori_adi' => $categoryName,
                'kategori_slug_resolved' => $categorySlug,
                'servisler' => $servicesOut,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueSlug(string $base, array &$used, ?string $suffixBase = null): string
    {
        $base = trim($base) !== '' ? trim($base) : 'item';
        $candidate = $base;
        $i = 2;

        while (isset($used[$candidate])) {
            if ($suffixBase) {
                $candidate = "{$base}-{$suffixBase}";
                $suffixBase = null;
                continue;
            }

            $candidate = "{$base}-{$i}";
            $i++;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    private function categoryMetaTitle(string $categoryName): string
    {
        $title = "Yalova {$categoryName} Hizmetleri | Kurulum, Destek, Bakım";

        return Str::limit($title, 100, '');
    }

    private function categoryMetaDescription(string $categoryName, array $company): string
    {
        $text = "Yalova’da {$categoryName} için keşif, kurulum, devreye alma ve bakım desteği. {$company['name']} ile hızlı servis ve kurulum sonrası destek alın.";

        return Str::limit($text, 165, '');
    }

    private function categoryMetaKeywords(string $categoryName): string
    {
        $base = [
            "yalova {$categoryName}",
            "{$categoryName} yalova",
            "yalova’da {$categoryName}",
            'yalova teknik destek',
            'kurulum',
            'bakım',
            'destek',
        ];

        return implode(', ', array_values(array_unique(array_map('trim', $base))));
    }

    private function categoryDescription(string $categoryName, array $company): string
    {
        $text = "Yalova ve çevresinde {$categoryName} ihtiyaçlarınız için keşif, kurulum ve sürdürülebilir destek sunuyoruz. Bilgi: {$company['phone']}.";

        return Str::limit($text, 255, '');
    }

    private function serviceMetaTitle(string $baseTitle, string $categoryName): string
    {
        $titleCore = $this->shortenTitleCore($baseTitle);
        $title = "Yalova {$titleCore} Hizmeti";

        if (mb_strlen($title) > 70) {
            $title = "Yalova {$titleCore}";
        }

        if (mb_strlen($title) > 70) {
            $title = "Yalova ".Str::limit($titleCore, 55, '');
        }

        // Kategori ismini title'a eklemeyelim: kartlarda aşırı uzayabiliyor.
        return trim($title);
    }

    private function shortenTitleCore(string $baseTitle): string
    {
        $t = trim($baseTitle);

        $t = str_replace(['Kurulumu', 'kurulumu'], ['Kurulum', 'kurulum'], $t);
        $t = str_replace(['Yönetimi', 'Yapılandırma'], ['Yönetim', 'Yapılandırma'], $t);

        // Çok uzunsa parantez içlerini sadeleştir.
        if (mb_strlen($t) > 55) {
            $t = preg_replace('/\\s*\\([^)]*\\)\\s*/u', ' ', $t) ?? $t;
            $t = trim(preg_replace('/\\s+/u', ' ', $t) ?? $t);
        }

        $t = str_replace(' / ', '/', $t);

        return $t;
    }

    private function serviceMetaDescription(string $baseTitle, string $categoryName, array $company): string
    {
        $intent = $this->pick([
            'Keşif, kurulum ve devreye alma dahil.',
            'Kurulum sonrası bakım ve destek verilir.',
            'Yerinde kurulum, test ve teslim süreciyle.',
            'Hızlı arıza tespit ve sürdürülebilir destekle.',
        ], Str::slug($baseTitle).$categoryName, 1);

        $text = "Yalova’da {$baseTitle} için profesyonel hizmet. {$intent} Teklif için arayın: {$company['phone']}.";

        return Str::limit($text, 255, '');
    }

    private function serviceMetaKeywords(string $baseTitle, string $categoryName): string
    {
        $baseSlug = Str::slug($baseTitle);
        $clean = str_replace('-', ' ', $baseSlug);

        $keys = [
            "yalova {$clean}",
            "{$clean} yalova",
            "yalova’da {$clean}",
            $categoryName,
            'kurulum',
            'bakım',
            'destek',
            'yerinde servis',
            'uzaktan destek',
        ];

        return implode(', ', array_values(array_unique(array_map('trim', $keys))));
    }

    /**
     * @param  array<string>  $serviceSlugsInCategory
     */
    private function serviceContent(
        string $baseTitle,
        string $categoryName,
        string $serviceSlug,
        string $categorySlug,
        array $company,
        array $serviceSlugsInCategory
    ): string {
        $seed = $serviceSlug.'|'.$categorySlug;

        $intro = $this->pick([
            "Yalova’da {$baseTitle} ihtiyaçlarınız için keşif, kurulum ve devreye alma süreçlerini uçtan uca yönetiyoruz.",
            "Yalova ve çevresinde {$baseTitle} hizmetini; planlama, kurulum ve test adımlarıyla kurumsal standartlarda sunuyoruz.",
            "İşletmeniz veya eviniz için Yalova’da {$baseTitle} çözümlerini güvenli, ölçülebilir ve desteklenebilir şekilde kuruyoruz.",
        ], $seed, 0);

        $scope = $this->serviceScopeItems($baseTitle, $categoryName, $seed);
        $targets = $this->serviceTargetItems($categoryName, $seed);
        $reasons = $this->serviceReasonItems($seed);
        $locations = $this->serviceLocations($seed);

        $media = $this->serviceInlineMedia($baseTitle, $categoryName, $categorySlug, $serviceSlug);
        $figuresHtml = $this->figuresHtml($media);

        $whatYouGet = $this->serviceDeliverablesItems($baseTitle, $categoryName, $seed);
        $process = $this->serviceProcessItems($baseTitle, $categoryName, $seed);
        $technical = $this->serviceTechnicalNotesHtml($baseTitle, $categoryName, $categorySlug, $seed);
        $faq = $this->serviceFaqHtml($baseTitle, $categoryName, $seed);

        $assurance = $this->pick([
            'Kurulumun ardından sistemi birlikte test eder, raporlayarak teslim ederiz; sonrasında da ulaşılabilir destek sağlarız.',
            'Sadece “kurduk bitti” yaklaşımı değil; sürdürülebilir kullanım, bakım planı ve olası arızalarda hızlı müdahale hedefleriz.',
            'Kurumsal iş disiplinimiz; plan, test, teslim tutanağı ve gerektiğinde uzaktan/yerde destek süreçleriyle tamamlanır.',
        ], $seed, 7);

        return <<<HTML
<div class="service-content">
  <h2>{$baseTitle} (Yalova)</h2>
  <p>{$intro}</p>
  <p><strong>{$company['name']}</strong> olarak yaklaşımımız; doğru analiz, temiz kurulum, teslim sonrası destek ve periyodik bakım üzerine kuruludur. {$assurance}</p>

  {$figuresHtml}

  <h2>Hizmet Kapsamı</h2>
  <p>Bu hizmette hedefimiz, ihtiyacınıza uygun bir çözümü “doğru şekilde” kurmak ve uzun vadede sorunsuz çalışmasını sağlamaktır. Bu yüzden kurulum kadar, devreye alma ve test adımları da sürecin ayrılmaz parçasıdır.</p>
  <ul>
    {$scope}
  </ul>

  <h2>Süreç Nasıl İlerliyor?</h2>
  <p>Yalova’daki keşif ve kurulum süreçlerinde net bir iş akışı izliyoruz. Böylece hem zaman planı hem de teslim kalitesi kontrol altında olur.</p>
  <ol>
    {$process}
  </ol>

  <h2>Teslim Sonrası “Ne Alıyorum?”</h2>
  <p>Müşterilerimizin en çok önem verdiği konu, iş bittiğinde sistemin net şekilde teslim edilmesi ve sonrasında destek alınabilmesidir. Bu nedenle teslimde aşağıdaki çıktıları standart tutmaya çalışırız.</p>
  <ul>
    {$whatYouGet}
  </ul>

  {$technical}

  <h2>Kimler İçin Uygun?</h2>
  <ul>
    {$targets}
  </ul>

  <h2>Neden Bizi Tercih Etmelisiniz?</h2>
  <ul>
    {$reasons}
  </ul>

  <h2>Yalova Odaklı Hizmet</h2>
  <p>Yalova merkez başta olmak üzere uygun lokasyonlarda yerinde keşif ve kurulum planı oluşturuyoruz. İş yoğunluğuna göre aynı gün veya randevulu destek sağlarız. Kurulum sonrasında da uzaktan destek ile hızlı çözümler üretebilir, gerektiğinde yerinde müdahale planlayabiliriz.</p>
  <ul>
    {$locations}
  </ul>

  {$faq}
</div>
HTML;
    }

    private function serviceTechnicalNotesHtml(string $baseTitle, string $categoryName, string $categorySlug, string $seed): string
    {
        $t = mb_strtolower($baseTitle);
        $c = mb_strtolower($categoryName);

        $blocks = [];

        if ($categorySlug === 'sunucu-sistemleri' || str_contains($c, 'sunucu') || str_contains($t, 'server')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>Güvenlik ve güncelleme disiplini</h3>
  <p>Sunucu tarafında temel sertleştirme (gereksiz servislerin kapatılması, güçlü parola politikaları, yetki prensibi, güncelleme planı) gibi adımlar kritik önemdedir. Kurulum sonrası bakım planı bu yüzden özellikle vurgulanır.</p>
  <h3>Performans, log ve izleme</h3>
  <p>Disk/IO, RAM ve CPU kullanımına göre doğru yapılandırma yapılır; log’ların tutulması ve gerektiğinde izleme/uyarı mekanizması önerilir. Böylece olası sorunlar büyümeden tespit edilir.</p>
HTML;
        }

        if ($categorySlug === 'network-altyapi' || str_contains($c, 'network') || str_contains($c, 'altyapı')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>IP planlama ve kablolama standardı</h3>
  <p>Ağda kararlılık için doğru IP planı, etiketleme, patch panel düzeni ve kablolama standardı önemlidir. Kablolama/port düzeni doğru yapılırsa arıza anında müdahale süresi ciddi şekilde kısalır.</p>
  <h3>WiFi kapsama ve güvenlik</h3>
  <p>WiFi kurulumlarında kapsama, kanal planı, misafir ağları ve yetkilendirme gibi konulara dikkat ederiz. Kurulum sonrası performans testleriyle kapsama sorunlarını sahada görüp düzeltiriz.</p>
HTML;
        }

        if ($categorySlug === 'ag-siber-guvenlik' || str_contains($c, 'siber') || str_contains($t, 'vpn') || str_contains($t, 'firewall')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>Yetkilendirme ve erişim prensibi</h3>
  <p>Uzaktan erişim/VPN ve firewall kurulumlarında “minimum yetki” yaklaşımı esas alınır. Kimlerin hangi kaynağa erişeceği netleştirilir, gereksiz açıklar kapatılır.</p>
  <h3>Kayıt (log) ve takip</h3>
  <p>Güvenlik cihazlarında loglama/izleme, olası şüpheli durumların tespiti için gereklidir. İhtiyaca göre temel raporlama ve takip önerileri sunarız.</p>
HTML;
        }

        if ($categorySlug === 'guvenlik-sistemleri' || str_contains($t, 'kamera') || str_contains($t, 'alarm') || str_contains($t, 'plaka')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>Görüntü kalitesi, açı ve kayıt süreleri</h3>
  <p>Kamera sistemlerinde doğru lens/açı, gece görüş, ışık koşulları ve kayıt kapasitesi (kaç gün saklanacak) netleştirilir. Kurulum sonrası görüntü kalitesi ve uzaktan izleme birlikte test edilir.</p>
  <h3>Kablo güzergâhı ve temiz işçilik</h3>
  <p>Kablo güzergâhı ve bağlantı noktaları, hem estetik hem de arıza riskini azaltacak şekilde planlanır. Dış ortam noktalarında su/nem etkisine karşı uygun montaj uygulanır.</p>
HTML;
        }

        if ($categorySlug === 'teknik-servis' || str_contains($t, 'laptop') || str_contains($t, 'format') || str_contains($t, 'tamir')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>Veri güvenliği</h3>
  <p>Teknik servis işlemlerinde veri güvenliği çok önemlidir. İşleme başlamadan önce riskleri netleştirir, mümkünse yedekleme planı önerir ve işlem sonrası temel kontrolleri uygularız.</p>
  <h3>Şeffaf süreç</h3>
  <p>Arıza tespiti sonrasında uygulanacak işlemi, süreyi ve parça ihtiyacını netleştiririz. Onay alınmadan işlem ilerletilmez; teslim öncesi testlerle birlikte kontrol edilir.</p>
HTML;
        }

        if ($categorySlug === 'akilli-ev-sistemleri' || str_contains($c, 'akıllı') || str_contains($t, 'iot')) {
            $blocks[] = <<<HTML
  <h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar</h2>
  <h3>Senaryo ve otomasyon tasarımı</h3>
  <p>Akıllı ev sistemlerinde “hangi ihtiyacı çözüyoruz?” sorusu ile başlarız. Işık, perde, iklimlendirme, güvenlik ve enerji takibi gibi başlıklarda senaryo tasarımı yapıp kullanım kolaylığını hedefleriz.</p>
  <h3>Ağ altyapısı ve uzaktan erişim</h3>
  <p>Akıllı cihazların stabil çalışması için WiFi/mesh kapsaması ve ağ güvenliği doğru planlanmalıdır. Uygulama üzerinden uzaktan erişimde güvenli kullanıcı yetkileri tanımlanır.</p>
HTML;
        }

        if ($blocks === []) {
            return '';
        }

        // Bazı servislerde birden fazla blok yazılmış olabilir; sade birleştir.
        $html = implode("\n", $blocks);

        // Aynı H2 birden fazla gelmişse (farklı bloklar), sadece ilk H2 kalsın.
        $html = preg_replace('/(<h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar<\\/h2>)\\s*(<h2>Teknik Detaylar ve Dikkat Ettiğimiz Noktalar<\\/h2>)/u', '$1', $html) ?? $html;

        return trim($html);
    }

    private function serviceDeliverablesItems(string $baseTitle, string $categoryName, string $seed): string
    {
        $items = [
            'Kurulum ve temel yapılandırma özetinin yazılı paylaşımı',
            'Çalışma testleri (temel kontrol listesi) ve teslim onayı',
            'Kullanıcı bilgilendirmesi (kısa eğitim / kullanım notları)',
            'Olası bakım önerileri ve periyodik kontrol planı',
        ];

        $t = mb_strtolower($baseTitle);
        if (str_contains($t, 'vpn') || str_contains($t, 'firewall')) {
            $items[] = 'Erişim yetkileri ve kullanıcı listesi özeti (varsa)';
        }
        if (str_contains($t, 'yedek') || str_contains($t, 'backup')) {
            $items[] = 'Yedekleme planı ve geri yükleme testi bilgisi';
        }
        if (str_contains($t, 'kamera') || str_contains($t, 'alarm') || str_contains($t, 'plaka')) {
            $items[] = 'Uzaktan izleme erişimi ve kayıt ayarlarının kontrolü';
        }

        $picked = $this->pickMany($items, $seed.'deliverables', 5);

        return implode("\n    ", array_map(fn ($s) => '<li>'.$s.'</li>', $picked));
    }

    private function serviceProcessItems(string $baseTitle, string $categoryName, string $seed): string
    {
        $items = [
            'Ön görüşme: ihtiyacın netleştirilmesi ve ön bilgi toplama',
            'Keşif: mevcut altyapı/ortam kontrolü ve risklerin belirlenmesi',
            'Planlama: kullanılacak yöntem, cihaz/rol seçimi ve zaman planı',
            'Kurulum & yapılandırma: kurulumun temiz ve ölçülebilir şekilde yapılması',
            'Test & doğrulama: performans, erişim, kayıt/izleme gibi kontroller',
            'Teslim & bilgilendirme: kullanım notları ve destek kanallarının paylaşımı',
        ];

        $t = mb_strtolower($baseTitle);
        $c = mb_strtolower($categoryName);
        if (str_contains($t, 'backup') || str_contains($t, 'yedek') || str_contains($t, 'felaket')) {
            $items[4] = 'Test & doğrulama: yedekten geri dönüş (restore) kontrolü ve raporlama';
        }
        if (str_contains($t, 'wifi') || str_contains($t, 'mesh') || str_contains($c, 'altyapı')) {
            $items[4] = 'Test & doğrulama: kapsama/perf testleri, hız ve roaming kontrolleri';
        }
        if (str_contains($t, 'kamera') || str_contains($t, 'alarm') || str_contains($t, 'plaka')) {
            $items[4] = 'Test & doğrulama: görüntü kalitesi, gece görüş, kayıt ve uzaktan izleme kontrolleri';
        }

        return implode("\n    ", array_map(fn ($s) => '<li>'.$s.'</li>', $items));
    }

    private function serviceFaqHtml(string $baseTitle, string $categoryName, string $seed): string
    {
        $q1 = $this->pick([
            'Kurulum ne kadar sürer?',
            'Yerinde keşif gerekli mi?',
            'Kurulum sonrası destek veriyor musunuz?',
        ], $seed, 20);

        $a1 = $this->pick([
            'Süre; mevcut altyapıya, iş kapsamına ve kullanılacak ekipmana göre değişir. Keşif sonrası net bir zaman planı paylaşırız.',
            'Çoğu işte keşif, doğru çözüm ve doğru maliyet için önemlidir. Uygun durumlarda uzaktan ön değerlendirme ile süreci hızlandırabiliriz.',
            'Evet. Kurulum sonrası destek, bakım önerileri ve gerektiğinde yerinde müdahale ile süreci tamamlarız.',
        ], $seed, 21);

        $q2 = $this->pick([
            'Fiyatlandırma nasıl belirlenir?',
            'Hangi markalarla çalışıyorsunuz?',
            'Aynı gün destek mümkün mü?',
        ], $seed, 22);

        $a2 = $this->pick([
            'Fiyat; keşif, işçilik, kablolama/altyapı ihtiyacı ve gerekli cihazlara göre belirlenir. Önce kapsamı netleştirir, sonra teklif sunarız.',
            'İhtiyaca uygun ürün seçimi yaparız. Mevcut ürününüz varsa onu da değerlendirip uyumluluk ve performans açısından öneri sunarız.',
            'Yalova içi taleplerde yoğunluğa göre aynı gün veya randevulu destek planlayabiliriz. Acil durumlarda uzaktan ilk müdahale ile süreci hızlandırırız.',
        ], $seed, 23);

        $q3 = $this->pick([
            'İş bittikten sonra sistemde sorun olursa ne yapmalıyım?',
            'Uzaktan destek mümkün mü?',
            'Kurulumdan sonra bakım gerekiyor mu?',
        ], $seed, 24);

        $a3 = $this->pick([
            'İletişim kanallarımız üzerinden hızlıca ulaşabilirsiniz. Önce uzaktan kontrol ile teşhis eder, gerekiyorsa yerinde müdahale planlarız.',
            'Evet. Uygun işlerde uzaktan destek ile hızlı çözüm sunabiliriz. Güvenlik gerektiren durumlarda gerekli yetkilendirmeleri sağlayarak ilerleriz.',
            'Evet, özellikle ağ ve güvenlik sistemlerinde periyodik kontrol performansı artırır ve arızaları azaltır. İhtiyaca göre bakım planı öneririz.',
        ], $seed, 25);

        $q1e = e($q1);
        $a1e = e($a1);
        $q2e = e($q2);
        $a2e = e($a2);
        $q3e = e($q3);
        $a3e = e($a3);

        $title = e("Sık Sorulan Sorular ({$baseTitle})");

        return <<<HTML
  <h2>{$title}</h2>
  <h3>{$q1e}</h3>
  <p>{$a1e}</p>
  <h3>{$q2e}</h3>
  <p>{$a2e}</p>
  <h3>{$q3e}</h3>
  <p>{$a3e}</p>
HTML;
    }

    private function serviceIcon(string $categorySlug, string $serviceSlug): string
    {
        $icons = [
            'icon-service-item-1.svg',
            'icon-service-item-2.svg',
            'icon-service-item-3.svg',
            'icon-service-item-4.svg',
            'icon-service-item-5.svg',
            'icon-service-item-6.svg',
        ];

        return $icons[(int) (abs(crc32($categorySlug.'|'.$serviceSlug)) % count($icons))];
    }

    private function serviceUniqueImagePath(string $baseTitle, string $categoryName, string $categorySlug, string $serviceSlug): string
    {
        // Her servis sayfası için benzersiz görsel dosyası beklenir:
        // storage/app/public/services/pages/yalova-bilgisayar-<slug>.jpg  -> /uploads/services/pages/yalova-bilgisayar-<slug>.jpg
        $uniqueSeo = 'services/pages/yalova-bilgisayar-'.$serviceSlug.'.jpg';
        if ($this->publicDiskHas($uniqueSeo)) {
            return $uniqueSeo;
        }

        // Geriye dönük: önceki isimlendirme
        $uniqueLegacy = 'services/pages/'.$serviceSlug.'.jpg';
        if ($this->publicDiskHas($uniqueLegacy)) {
            return $uniqueLegacy;
        }

        // Unique görsel yoksa geriye dönük uyum için kategori bazlı fallback.
        $t = mb_strtolower($baseTitle);
        $c = mb_strtolower($categoryName);

        // Öncelikle kategori bazlı (servisle uyumlu) stok görseller.
        if ($categorySlug === 'sunucu-sistemleri' || str_contains($c, 'sunucu') || str_contains($t, 'server')) {
            if (str_contains($t, 'raid')) {
                return 'services/stock/raid-array.jpg';
            }
            if (str_contains($t, 'nas')) {
                return 'services/stock/nas-open.jpg';
            }
            if (str_contains($t, 'yedek') || str_contains($t, 'backup') || str_contains($t, 'felaket')) {
                return 'services/stock/nas-open.jpg';
            }

            return 'services/stock/server-rack.jpg';
        }

        if ($categorySlug === 'network-altyapi' || str_contains($c, 'network') || str_contains($c, 'altyapı') || str_contains($t, 'switch') || str_contains($t, 'router') || str_contains($t, 'vlan') || str_contains($t, 'wifi') || str_contains($t, 'kablo')) {
            if (str_contains($t, 'fiber') || str_contains($t, 'optik')) {
                return 'services/stock/fiber-patch-panel-rack.jpg';
            }
            if (str_contains($t, 'mesh')) {
                return 'services/stock/mesh-wifi-router.jpg';
            }
            if (str_contains($t, 'wifi') || str_contains($t, 'access point') || str_contains($t, 'hotspot')) {
                return 'services/stock/wireless-router.jpg';
            }

            return 'services/stock/network-switches-patch-panels.jpg';
        }

        if ($categorySlug === 'ag-siber-guvenlik' || str_contains($c, 'siber') || str_contains($c, 'güvenlik') || str_contains($t, 'firewall') || str_contains($t, 'vpn') || str_contains($t, 'antivirus') || str_contains($t, 'endpoint')) {
            if (str_contains($t, 'vpn')) {
                return 'services/stock/vpn-ipsec-diagram.svg';
            }
            if (str_contains($t, 'firewall')) {
                return 'services/stock/firewall.jpg';
            }

            return 'services/stock/cybersecurity.png';
        }

        if ($categorySlug === 'yedekleme-veri-yonetimi' || str_contains($c, 'yedek') || str_contains($c, 'veri')) {
            if (str_contains($t, 'kurtarma') || str_contains($t, 'felaket')) {
                return 'services/stock/nas-open.jpg';
            }
            if (str_contains($t, 'raid')) {
                return 'services/stock/raid-array.jpg';
            }

            return 'services/stock/nas-open.jpg';
        }

        if ($categorySlug === 'personel-takip-gecis-sistemleri' || str_contains($c, 'geçiş') || str_contains($c, 'pdks') || str_contains($t, 'kart') || str_contains($t, 'parmak') || str_contains($t, 'yüz') || str_contains($t, 'turnike')) {
            return 'services/stock/fingerprint-reader.jpg';
        }

        if ($categorySlug === 'iletisim-sistemleri' || str_contains($c, 'iletişim') || str_contains($t, 'voip') || str_contains($t, 'santral') || str_contains($t, 'çağrı')) {
            return 'services/stock/ip-phone.jpg';
        }

        if ($categorySlug === 'kurumsal-it-hizmetleri' || str_contains($c, 'kurumsal') || str_contains($t, 'danışmanlık') || str_contains($t, 'outsource') || str_contains($t, 'izleme')) {
            return 'services/stock/server-rack.jpg';
        }

        if ($categorySlug === 'teknik-servis' || str_contains($c, 'teknik') || str_contains($t, 'laptop') || str_contains($t, 'tamir') || str_contains($t, 'format') || str_contains($t, 'arıza')) {
            return 'services/stock/laptop-repair.jpg';
        }

        if ($categorySlug === 'bulut-hosting' || str_contains($c, 'bulut') || str_contains($c, 'hosting') || str_contains($t, 'vps') || str_contains($t, 'vds') || str_contains($t, 'domain')) {
            return 'services/stock/cloud-computing.jpg';
        }

        if ($categorySlug === 'guvenlik-sistemleri' || str_contains($c, 'güvenlik') || str_contains($t, 'kamera') || str_contains($t, 'alarm') || str_contains($t, 'plaka')) {
            return 'services/stock/cctv-installation.jpg';
        }

        if ($categorySlug === 'isletme-otomasyonlari' || str_contains($c, 'otomasyon') || str_contains($t, 'barkod') || str_contains($t, 'stok') || str_contains($t, 'erp') || str_contains($t, 'crm') || str_contains($t, 'muhasebe')) {
            return 'services/stock/barcode-scanner.jpg';
        }

        if ($categorySlug === 'enerji-altyapi' || str_contains($c, 'enerji') || str_contains($t, 'ups') || str_contains($t, 'elektrik')) {
            return 'services/stock/ups.jpg';
        }

        if ($categorySlug === 'akilli-ev-sistemleri' || str_contains($c, 'akıllı') || str_contains($t, 'iot') || str_contains($t, 'zigbee') || str_contains($t, 'z-wave') || str_contains($t, 'termostat') || str_contains($t, 'kilit')) {
            if (str_contains($t, 'termostat') || str_contains($t, 'klima') || str_contains($t, 'ısıtma')) {
                return 'services/stock/smart-thermostat.jpg';
            }
            if (str_contains($t, 'kilit') || str_contains($t, 'kapı')) {
                return 'services/stock/smart-lock.jpg';
            }

            return 'services/stock/smart-lock.jpg';
        }

        // Varsayılan
        return 'services/stock/server-rack.jpg';
    }

    private function publicDiskHas(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        $full = storage_path('app/public/'.$relativePath);

        return is_file($full) && is_readable($full);
    }

    /**
     * @return array<int, array{src:string, alt:string}>
     */
    private function serviceInlineMedia(string $baseTitle, string $categoryName, string $categorySlug, string $serviceSlug): array
    {
        // İçerik içinde tek görsel gösterelim: her sayfanın kendine özel ana görseli.
        $main = $this->serviceUniqueImagePath($baseTitle, $categoryName, $categorySlug, $serviceSlug);

        $altMain = "Yalova {$baseTitle} hizmeti";

        return [
            ['src' => '/uploads/'.ltrim($main, '/'), 'alt' => $altMain],
        ];
    }

    /**
     * @param  array<int, array{src:string, alt:string}>  $items
     */
    private function figuresHtml(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            $src = e((string) $item['src']);
            $alt = e((string) $item['alt']);
            $html .= "<figure class=\"mb-4\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"img-fluid rounded\"></figure>\n";
        }

        return trim($html);
    }

    private function serviceScopeItems(string $baseTitle, string $categoryName, string $seed): string
    {
        $common = [
            'Yerinde keşif ve ihtiyaç analizi',
            'Kurulum planı ve zamanlama',
            'Kurulum / devreye alma / test',
            'Dokümantasyon ve kullanıcı bilgilendirmesi',
            'Kurulum sonrası destek ve bakım önerileri',
        ];

        $specific = $this->specificScopeFromKeywords($baseTitle, $categoryName);
        $all = array_values(array_unique(array_merge($specific, $common)));

        $lines = [];
        foreach ($all as $i => $line) {
            if ($i >= 7) {
                break;
            }
            $lines[] = '<li>'.$this->pick([$line], $seed, $i).'</li>';
        }

        return implode("\n    ", $lines);
    }

    private function specificScopeFromKeywords(string $baseTitle, string $categoryName): array
    {
        $t = mb_strtolower($baseTitle);
        $out = [];

        if (str_contains($t, 'kurulum') || str_contains($t, 'kurulumu')) {
            $out[] = 'Kurulum öncesi uygunluk kontrolü ve ön hazırlık';
            $out[] = 'Gerekli lisans/hesap/erişim kontrolleri';
        }

        if (str_contains($t, 'server') || str_contains($t, 'sunucu')) {
            $out[] = 'Rol/servis planlaması (AD, DNS, DHCP vb. ihtiyaçlara göre)';
            $out[] = 'Güvenlik güncellemeleri ve temel sertleştirme adımları';
        }

        if (str_contains($t, 'yedek') || str_contains($t, 'backup')) {
            $out[] = 'Yedekleme politikası (günlük/haftalık/aylık) tasarımı';
            $out[] = 'Geri yükleme (restore) testleri ve raporlama';
        }

        if (str_contains($t, 'vpn') || str_contains($t, 'firewall') || str_contains($categoryName, 'Güvenlik')) {
            $out[] = 'Yetkilendirme ve erişim politikası oluşturma';
            $out[] = 'Loglama ve izleme önerileri';
        }

        if (str_contains($t, 'kamera') || str_contains($t, 'alarm') || str_contains($t, 'plaka')) {
            $out[] = 'Keşif ile kamera/algılayıcı konumlarının belirlenmesi';
            $out[] = 'Kayıt cihazı/NVR ve uzaktan izleme yapılandırması';
        }

        if (str_contains($t, 'wifi') || str_contains($t, 'access point') || str_contains($t, 'mesh')) {
            $out[] = 'Kapsama alanı ölçümü ve kanal planlaması';
            $out[] = 'Misafir ağı / VLAN / güvenlik ayarları';
        }

        if (str_contains($t, 'pdks') || str_contains($t, 'geçiş') || str_contains($t, 'turnike') || str_contains($t, 'kartlı') || str_contains($t, 'yüz') || str_contains($t, 'parmak')) {
            $out[] = 'Yetki profilleri ve geçiş senaryoları tanımlama';
            $out[] = 'Raporlama ve vardiya/mesai kuralları ayarları';
        }

        if (str_contains($t, 'voip') || str_contains($t, 'santral') || str_contains($t, 'çağrı')) {
            $out[] = 'Hat/numara planı ve iç hat düzeni';
            $out[] = 'IVR, yönlendirme ve kayıt seçenekleri (varsa)';
        }

        if (str_contains($t, 'format') || str_contains($t, 'tamir') || str_contains($t, 'arıza')) {
            $out[] = 'Arıza tespiti ve parça/işçilik bilgilendirmesi';
            $out[] = 'Veri güvenliği ve teslim öncesi kontrol listesi';
        }

        if (str_contains($t, 'ups') || str_contains($t, 'elektrik')) {
            $out[] = 'Yük hesabı ve uygun UPS kapasitesi seçimi';
            $out[] = 'Bypass/akü kontrolleri ve güvenli montaj';
        }

        if (str_contains($t, 'akıllı') || str_contains($t, 'iot') || str_contains($t, 'zigbee') || str_contains($t, 'z-wave') || str_contains($t, 'termostat')) {
            $out[] = 'Senaryo ve otomasyon kuralları tasarımı';
            $out[] = 'Uygulama üzerinden uzaktan erişim ve kullanıcı yetkileri';
        }

        return $out;
    }

    private function serviceTargetItems(string $categoryName, string $seed): string
    {
        $all = [
            'Ev kullanıcıları',
            'Ofisler',
            'Küçük ve orta ölçekli işletmeler',
            'Mağazalar',
            'Depolar ve atölyeler',
            'Kurumsal firmalar',
        ];

        $count = str_contains($categoryName, 'Teknik') ? 4 : 5;
        $picked = $this->pickMany($all, $seed.'targets', $count);

        return implode("\n    ", array_map(fn ($s) => '<li>'.$s.'</li>', $picked));
    }

    private function serviceReasonItems(string $seed): string
    {
        $all = [
            'Yalova’da yerel ve hızlı destek',
            'Kurulum sonrası bakım ve servis sürekliliği',
            'Saha tecrübesi ve doğru ürün/kurgu önerisi',
            'Güvenlik, performans ve ölçeklenebilirlik odaklı kurulum',
            'Ulaşılabilir destek kanalları ve net bilgilendirme',
        ];

        $picked = $this->pickMany($all, $seed.'reasons', 5);

        return implode("\n    ", array_map(fn ($s) => '<li>'.$s.'</li>', $picked));
    }

    private function serviceLocations(string $seed): string
    {
        $locs = [
            'Yalova Merkez',
            'Çiftlikköy',
            'Altınova',
            'Termal',
            'Çınarcık',
            'Armutlu',
        ];

        $picked = $this->pickMany($locs, $seed.'locs', 4);

        return implode("\n    ", array_map(fn ($s) => '<li>'.$s.'</li>', $picked));
    }

    private function pick(array $options, string $seed, int $offset = 0): string
    {
        if ($options === []) {
            return '';
        }

        $idx = (int) (abs(crc32($seed.'|'.$offset)) % count($options));

        return (string) $options[$idx];
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function pickMany(array $options, string $seed, int $count): array
    {
        $options = array_values(array_unique(array_filter(array_map('trim', $options))));

        if ($options === [] || $count <= 0) {
            return [];
        }

        $picked = [];
        $cursor = 0;
        while (count($picked) < min($count, count($options))) {
            $candidate = $this->pick($options, $seed, $cursor++);
            if (! in_array($candidate, $picked, true)) {
                $picked[] = $candidate;
            }
        }

        return $picked;
    }
}
