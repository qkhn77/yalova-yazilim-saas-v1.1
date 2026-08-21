<?php

namespace App\TeknikServis\Servisler;

use App\Models\TeknikServis\TeknikServisBaskiSablonu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeknikServisBaskiSablonuServisi
{
    /**
     * @return array{ad:string,kod:string,sayfa_tipi:string,sablon_html:string,sablon_css:string}
     */
    public function kvkKabulFormuSablonu(string $sayfaTipi = 'a4'): array
    {
        if ($sayfaTipi === 'a5') {
            return [
                'ad' => "A5 KVK'lı kabul formu",
                'kod' => 'a5-kvk-li-kabul-formu',
                'sayfa_tipi' => 'a5',
                'sablon_html' => $this->kabulFormuKvkkA5Html(),
                'sablon_css' => $this->cssKabulFormuKvkkA5(),
            ];
        }

        return [
            'ad' => "KVK'lı kabul formu",
            'kod' => 'kvk-li-kabul-formu',
            'sayfa_tipi' => 'a4',
            'sablon_html' => $this->kabulFormuA4Html(),
            'sablon_css' => $this->cssKabulFormuA4(),
        ];
    }

    public function sablonTablosuVarMi(): bool
    {
        return Schema::hasTable('teknik_servis_baski_sablonlari');
    }

    public function firmaSablonlariniHazirla(int $firmaId, string $sablonTuru): void
    {
        if ($firmaId < 1 || $sablonTuru === '' || ! $this->sablonTablosuVarMi()) {
            return;
        }

        $varMi = TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', $sablonTuru)
            ->exists();

        if ($varMi) {
            return;
        }

        if ($sablonTuru === 'servis_fisi' && $this->servisFisiSablonlariniKabulFormundanHazirla($firmaId)) {
            return;
        }

        foreach ($this->hazirSablonlar($sablonTuru) as $index => $sablon) {
            TeknikServisBaskiSablonu::query()->create([
                'firma_id' => $firmaId,
                'sablon_turu' => $sablonTuru,
                'ad' => $sablon['ad'],
                'kod' => $sablon['kod'],
                'sayfa_tipi' => $sablon['sayfa_tipi'],
                'sablon_html' => $sablon['sablon_html'],
                'sablon_css' => $sablon['sablon_css'],
                'varsayilan_mi' => $index === 0,
                'aktif' => true,
            ]);
        }
    }

    private function servisFisiSablonlariniKabulFormundanHazirla(int $firmaId): bool
    {
        $kaynaklar = TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', 'kabul_formu')
            ->whereIn('kod', ['kabul_formu-80mm', 'kabul_formu-58mm'])
            ->orderByRaw("FIELD(kod, 'kabul_formu-80mm', 'kabul_formu-58mm')")
            ->get();

        if ($kaynaklar->count() !== 2) {
            return false;
        }

        foreach ($kaynaklar as $index => $kaynak) {
            TeknikServisBaskiSablonu::query()->create([
                'firma_id' => $firmaId,
                'sablon_turu' => 'servis_fisi',
                'ad' => 'Servis Fişi '.(string) $kaynak->sayfa_tipi,
                'kod' => 'servis-fisi-'.(string) $kaynak->sayfa_tipi,
                'sayfa_tipi' => (string) $kaynak->sayfa_tipi,
                'sablon_logo' => $kaynak->sablon_logo,
                'sablon_html' => (string) $kaynak->sablon_html,
                'sablon_css' => (string) $kaynak->sablon_css,
                'varsayilan_mi' => $index === 0,
                'aktif' => true,
            ]);
        }

        return true;
    }

    public function varsayilanYap(TeknikServisBaskiSablonu $sablon): void
    {
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        DB::transaction(function () use ($sablon): void {
            TeknikServisBaskiSablonu::query()
                ->where('firma_id', (int) $sablon->firma_id)
                ->where('sablon_turu', (string) $sablon->sablon_turu)
                ->update(['varsayilan_mi' => false]);

            $sablon->forceFill(['varsayilan_mi' => true])->save();
        });
    }

    public function benzersizKodUret(int $firmaId, string $sablonTuru, string $ad): string
    {
        $temel = Str::of($ad)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $temel = $temel !== '' ? $temel : 'teknik-servis-sablonu';
        $kod = $temel;
        $sayac = 2;

        while ($this->sablonTablosuVarMi() && TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', $sablonTuru)
            ->where('kod', $kod)
            ->exists()) {
            $kod = $temel.'-'.$sayac;
            $sayac++;
        }

        return $kod;
    }

    /**
     * @return array<int, array{ad:string,kod:string,sayfa_tipi:string,sablon_html:string,sablon_css:string}>
     */
    public function hazirSablonlar(string $sablonTuru): array
    {
        if ($sablonTuru === 'servis_formu') {
            return [
                [
                    'ad' => 'Teknik Servis Formu',
                    'kod' => 'teknik-servis-formu-a4',
                    'sayfa_tipi' => 'a4',
                    'sablon_html' => $this->teknikServisFormuA4Html(),
                    'sablon_css' => $this->cssTeknikServisFormuA4(),
                ],
                [
                    'ad' => 'KVK Teknik Servis Formu',
                    'kod' => 'kvk-teknik-servis-formu-a4',
                    'sayfa_tipi' => 'a4',
                    'sablon_html' => $this->kvkTeknikServisFormuA4Html(),
                    'sablon_css' => $this->cssKvkTeknikServisFormuA4(),
                ],
                [
                    'ad' => "Teknik Servis Formu KVK'lı",
                    'kod' => 'yalova-bilgisayar-servis-kabul-formu-a4',
                    'sayfa_tipi' => 'a4',
                    'sablon_html' => $this->yalovaBilgisayarServisKabulFormuA4Html(),
                    'sablon_css' => $this->cssYalovaBilgisayarServisKabulFormuA4(),
                ],
            ];
        }

        if ($sablonTuru === 'servis_fisi') {
            return [
                [
                    'ad' => 'Servis Fişi 80mm',
                    'kod' => 'servis-fisi-80mm',
                    'sayfa_tipi' => '80mm',
                    'sablon_html' => $this->temelSablonHtml($sablonTuru),
                    'sablon_css' => $this->css80mm(),
                ],
                [
                    'ad' => 'Servis Fişi 58mm',
                    'kod' => 'servis-fisi-58mm',
                    'sayfa_tipi' => '58mm',
                    'sablon_html' => $this->temelSablonHtml($sablonTuru),
                    'sablon_css' => $this->css58mm(),
                ],
            ];
        }

        $etiket = $this->sablonTuruEtiketi($sablonTuru);

        $sablonlar = [
            [
                'ad' => $etiket.' A4',
                'kod' => $sablonTuru.'-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->temelSablonHtml($sablonTuru),
                'sablon_css' => $sablonTuru === 'kabul_formu' ? $this->cssKabulFormuA4() : $this->cssA4(),
            ],
            [
                'ad' => $etiket.' A5',
                'kod' => $sablonTuru.'-a5',
                'sayfa_tipi' => 'a5',
                'sablon_html' => $this->temelSablonHtml($sablonTuru),
                'sablon_css' => $this->cssA5(),
            ],
            [
                'ad' => $etiket.' 80mm',
                'kod' => $sablonTuru.'-80mm',
                'sayfa_tipi' => '80mm',
                'sablon_html' => $this->temelSablonHtml($sablonTuru),
                'sablon_css' => $this->css80mm(),
            ],
            [
                'ad' => $etiket.' 58mm',
                'kod' => $sablonTuru.'-58mm',
                'sayfa_tipi' => '58mm',
                'sablon_html' => $this->temelSablonHtml($sablonTuru),
                'sablon_css' => $this->css58mm(),
            ],
            [
                'ad' => $etiket.' 10x10mm',
                'kod' => $sablonTuru.'-10x10mm',
                'sayfa_tipi' => '10x10mm',
                'sablon_html' => $this->miniSablonHtml($sablonTuru),
                'sablon_css' => $this->css10x10mm(),
            ],
        ];

        if ($sablonTuru === 'kabul_formu') {
            $sablonlar[] = [
                'ad' => 'Receipt 80mm',
                'kod' => 'receipt-80mm',
                'sayfa_tipi' => '80mm',
                'sablon_html' => $this->kabulFormuReceipt80mmHtml(),
                'sablon_css' => $this->cssKabulFormuReceipt80mm(),
            ];
        }

        return $sablonlar;
    }

    private function sablonTuruEtiketi(string $sablonTuru): string
    {
        return match ($sablonTuru) {
            'talep_formu' => 'Servis Talep Formu',
            'servis_formu' => 'Servis Formu',
            'kabul_formu' => 'Servis Kabul Formu',
            'servis_fisi' => 'Servis Fişi',
            'teslim_belgesi' => 'Teslim Edildi Belgesi',
            default => 'Teknik Servis Şablonu',
        };
    }

    private function belgeBasligi(string $sablonTuru): string
    {
        return match ($sablonTuru) {
            'talep_formu' => 'SERVIS TALEP FORMU',
            'servis_formu' => 'TEKNIK SERVIS FORMU',
            'kabul_formu' => 'SERVIS KABUL FORMU',
            'servis_fisi' => 'SERVIS FISI',
            'teslim_belgesi' => 'TESLIM EDILDI BELGESI',
            default => 'TEKNIK SERVIS BELGESI',
        };
    }

    private function temelSablonHtml(string $sablonTuru): string
    {
        if ($sablonTuru === 'kabul_formu') {
            return $this->kabulFormuA4Html();
        }

        $baslik = $this->belgeBasligi($sablonTuru);
        $toplamBolumu = $sablonTuru === 'teslim_belgesi'
            ? ''
            : <<<HTML
    <div class="servis-toplam">
        <span>Toplam Tutar</span>
        <strong>{{TOPLAM_TUTAR}}</strong>
    </div>
HTML;

        return <<<HTML
<div class="servis-kapsayici">
    <div class="servis-ust">
        <div class="servis-ust-ana">
            <div class="logo-alani">{{FIRMA_LOGO}}</div>
            <div class="servis-iletisim">
                <div>Tel: {{FIRMA_TELEFON}}</div>
                <div>{{FIRMA_EPOSTA}}</div>
            </div>
        </div>
        <div class="servis-firma-alt">
            <div class="firma-ad">{{FIRMA_UNVAN}}</div>
            <div>{{FIRMA_ADRES}}</div>
        </div>
    </div>

    <div class="belge-baslik">{$baslik}</div>

    <div class="servis-bilgi-grid">
        <div><strong>Servis No:</strong> {{SERVIS_NO}}</div>
        <div><strong>Kabul Tarihi:</strong> {{KABUL_TARIHI}}</div>
        <div><strong>Müşteri:</strong> {{MUSTERI_AD}}</div>
        <div><strong>Telefon:</strong> {{MUSTERI_TEL}}</div>
        <div><strong>Cihaz:</strong> {{CIHAZ}}</div>
        <div><strong>Marka / Model:</strong> {{MARKA}} / {{MODEL_NO}}</div>
        <div><strong>Seri No:</strong> {{SERI_NO}}</div>
        <div><strong>Durum:</strong> {{SERVIS_DURUMU}}</div>
    </div>

    <div class="servis-blok">
        <div class="blok-baslik">Arıza / Talep</div>
        <div>{{ARIZA_ACIKLAMASI}}</div>
    </div>

    <div class="servis-blok">
        <div class="blok-baslik">Teslim / Notlar</div>
        <div>{{TESLIM_NOTU}}</div>
    </div>

{$toplamBolumu}

    <div class="imza-grid">
        <div class="imza-kutu">Müşteri İmza</div>
        <div class="imza-kutu">Yetkili İmza</div>
    </div>
</div>
HTML;
    }

    private function kabulFormuA4Html(): string
    {
        return <<<'HTML'
<div class="servis-kabul-a4">
    <header class="sk-header">
        <div class="sk-header-left">
            <div class="sk-eyebrow">Yalova Bilgisayar Teknik Servis Belgesi</div>
            <h1>TEKNİK SERVİS CİHAZ KABUL FORMU, HİZMET SÖZLEŞMESİ VE KVKK AYDINLATMA METNİ</h1>
        </div>
        <div class="sk-logo">{{FIRMA_LOGO}}</div>
    </header>

    <section class="sk-section">
        <div class="sk-section-title">1. Taraf Bilgileri</div>
        <div class="sk-grid sk-grid-2">
            <div class="sk-card">
                <div class="sk-card-title">Servis (Firma)</div>
                <div><strong>Unvan:</strong> Yalova Bilgisayar Teknik Servis</div>
                <div><strong>Adres:</strong> Sahil Mah. Yalı Cad. No:3/A Çiftlikköy/Yalova</div>
                <div><strong>Vergi No:</strong> 45199618384</div>
                <div><strong>Vergi Dr.:</strong> Yalova</div>
                <div><strong>Telefon:</strong> 0 (226) 352 07 24</div>
                <div><strong>E-posta:</strong> info@yalovabilgisayar.com</div>
            </div>
            <div class="sk-card">
                <div class="sk-card-title">Müşteri</div>
                <div><strong>Ad Soyad:</strong> {{MUSTERI_AD}}</div>
                <div><strong>T.C. No:</strong> {{MUSTERI_TC_NO}}</div>
                <div><strong>Telefon:</strong> {{MUSTERI_TEL}}</div>
                <div><strong>Adres:</strong> {{MUSTERI_ADRES}}</div>
                <div><strong>Servis No:</strong> {{SERVIS_NO}}</div>
                <div><strong>Kabul Tarihi:</strong> {{KABUL_TARIHI}}</div>
            </div>
        </div>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">2. Cihaz Bilgileri</div>
        <div class="sk-grid sk-grid-2">
            <div><strong>Cihaz Türü:</strong> {{CIHAZ_TURU}}</div>
            <div><strong>Marka / Model:</strong> {{MARKA}} / {{MODEL_NO}}</div>
            <div><strong>Seri No / IMEI:</strong> {{SERI_NO}}</div>
            <div><strong>Aksesuarlar:</strong> {{AKSESUARLAR}}</div>
        </div>
        <div class="sk-note-box"><strong>Fiziksel Durum:</strong> {{FIZIKSEL_DURUM}}</div>
        {{CIHAZ_FOTOGRAFLARI}}
    </section>

    <section class="sk-section">
        <div class="sk-section-title">3. Arıza Beyanı</div>
        <div class="sk-text-area">{{ARIZA_ACIKLAMASI}}</div>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">4. Hizmet Koşulları</div>
        <ol class="sk-list">
            <li>Müşteri cihazı kendi rızası ile teslim ettiğini kabul eder.</li>
            <li>Veri kaybı riski müşteriye aittir. Firma veri kaybından sorumlu değildir.</li>
            <li>Sıvı temaslı veya ağır hasarlı cihazlarda işlem sırasında cihaz tamamen çalışmaz hale gelebilir; müşteri bunu kabul eder.</li>
            <li>Arıza tespiti ücretlidir: {{TESPIT_UCRETI}}</li>
            <li>Onay alınmadan ücretli işlem yapılmaz.</li>
            <li>Parça değişimlerinde muadil/orijinal seçenek müşteriye bildirilir.</li>
            <li>Tamir süresi tahmini olup bağlayıcı değildir.</li>
            <li>Yapılan işlem dışında cihazın diğer arızalarından firma sorumlu değildir.</li>
            <li>Tamir sonrası garanti yalnızca yapılan işlem için geçerlidir.</li>
            <li>Cihaz tesliminden sonra 7 gün içinde kontrol edilmelidir.</li>
        </ol>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">5. Sorumluluk Sınırları</div>
        <ul class="sk-list sk-list-disc">
            <li>Cihazın teslim anındaki mevcut arızaları dışında oluşabilecek teknik riskler müşteri tarafından kabul edilmiştir.</li>
            <li>Anakart, sıvı teması ve yüksek riskli arızalarda başarı garantisi verilmez.</li>
            <li>Yazılım işlemlerinde veri silinmesi normal kabul edilir.</li>
        </ul>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">6. Teslim ve Bekletme</div>
        <ul class="sk-list sk-list-disc">
            <li>Cihaz hazır olduktan sonra müşteri bilgilendirilir.</li>
            <li>90 gün içinde teslim alınmayan cihazlar terk edilmiş sayılır.</li>
            <li>Firma bu cihazlar üzerinde tasarruf hakkına sahiptir.</li>
        </ul>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">7. Ödeme</div>
        <ul class="sk-list sk-list-disc">
            <li>Ücret ödenmeden cihaz teslim edilmez.</li>
            <li>Arıza tespiti sonrası işlem iptal edilse dahi tespit ücreti alınır.</li>
        </ul>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">8. KVKK Aydınlatma</div>
        <div class="sk-paragraph">Müşteriye ait kişisel veriler yalnızca servis süreci kapsamında işlenir ve üçüncü kişilerle paylaşılmaz.</div>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">9. Hukuki Yetki</div>
        <div class="sk-paragraph">Bu sözleşmeden doğabilecek uyuşmazlıklarda <strong>{{SEHIR}} Mahkemeleri ve İcra Daireleri yetkilidir.</strong></div>
    </section>

    <section class="sk-section">
        <div class="sk-section-title">10. Onay</div>
        <div class="sk-paragraph">Müşteri bu sözleşmenin tüm maddelerini okuduğunu, anladığını ve kabul ettiğini beyan eder.</div>
        <div class="sk-signature-meta"><strong>Tarih:</strong> {{KABUL_TARIHI}}</div>
        <div class="sk-signatures">
            <div class="sk-signature-box">
                <div class="sk-signature-line"></div>
                <div>Müşteri İmza</div>
            </div>
            <div class="sk-signature-box">
                <div class="sk-signature-line"></div>
                <div>Yetkili İmza / Kaşe</div>
            </div>
        </div>
    </section>
</div>
HTML;
    }

    private function miniSablonHtml(string $sablonTuru): string
    {
        $baslik = $this->belgeBasligi($sablonTuru);

        return <<<HTML
<div class="mini-kapsayici">
    <div class="mini-baslik">{$baslik}</div>
    <div class="mini-satir">{{SERVIS_NO}}</div>
    <div class="mini-satir">{{MUSTERI_AD}}</div>
</div>
HTML;
    }

    private function kabulFormuKvkkA5Html(): string
    {
        return <<<'HTML'
<div class="servis-kabul-a5">
    <header class="sk5-header">
        <div class="sk5-eyebrow">Yalova Bilgisayar Teknik Servis Belgesi</div>
        <h1>TEKNİK SERVİS CİHAZ KABUL FORMU, HİZMET SÖZLEŞMESİ VE KVKK AYDINLATMA METNİ</h1>
    </header>

    <section class="sk5-section">
        <div class="sk5-title">1. Taraf Bilgileri</div>
        <div class="sk5-grid sk5-grid-2">
            <div class="sk5-box">
                <div class="sk5-box-title">Servis (Firma)</div>
                <div><strong>Unvan:</strong> Yalova Bilgisayar Teknik Servis</div>
                <div><strong>Adres:</strong> Sahil Mah. Yalı Cad. No:3/A Çiftlikköy/Yalova</div>
                <div><strong>Vergi No:</strong> 45199618384</div>
                <div><strong>Vergi Dr.:</strong> Yalova</div>
                <div><strong>Telefon:</strong> 0 (226) 352 07 24</div>
                <div><strong>E-posta:</strong> info@yalovabilgisayar.com</div>
            </div>
            <div class="sk5-box">
                <div class="sk5-box-title">Müşteri</div>
                <div><strong>Ad Soyad:</strong> {{MUSTERI_AD}}</div>
                <div><strong>T.C. No:</strong> {{MUSTERI_TC_NO}}</div>
                <div><strong>Telefon:</strong> {{MUSTERI_TEL}}</div>
                <div><strong>Adres:</strong> {{MUSTERI_ADRES}}</div>
                <div><strong>Servis No:</strong> {{SERVIS_NO}}</div>
                <div><strong>Kabul Tarihi:</strong> {{KABUL_TARIHI}}</div>
            </div>
        </div>
    </section>

    <section class="sk5-section">
        <div class="sk5-title">2. Cihaz Bilgileri</div>
        <div class="sk5-grid sk5-grid-2">
            <div><strong>Cihaz Türü:</strong> {{CIHAZ_TURU}}</div>
            <div><strong>Marka / Model:</strong> {{MARKA}} / {{MODEL_NO}}</div>
            <div><strong>Seri No / IMEI:</strong> {{SERI_NO}}</div>
            <div><strong>Aksesuarlar:</strong> {{AKSESUARLAR}}</div>
        </div>
        <div class="sk5-box sk5-compact"><strong>Fiziksel Durum:</strong> {{FIZIKSEL_DURUM}}</div>
        {{CIHAZ_FOTOGRAFLARI}}
    </section>

    <section class="sk5-section">
        <div class="sk5-title">3. Arıza Beyanı</div>
        <div class="sk5-box sk5-compact">{{ARIZA_ACIKLAMASI}}</div>
    </section>

    <section class="sk5-section">
        <div class="sk5-title">4. Hizmet ve Yasal Koşullar</div>
        <div class="sk5-legal-grid">
            <div class="sk5-box sk5-compact">
                <ol class="sk5-list">
                    <li>Müşteri cihazı kendi rızası ile teslim ettiğini kabul eder.</li>
                    <li>Veri kaybı riski müşteriye aittir. Firma veri kaybından sorumlu değildir.</li>
                    <li>Sıvı temaslı veya ağır hasarlı cihazlarda işlem sırasında cihaz tamamen çalışmaz hale gelebilir.</li>
                    <li>Arıza tespiti ücretlidir: {{TESPIT_UCRETI}}</li>
                    <li>Onay alınmadan ücretli işlem yapılmaz.</li>
                    <li>Parça değişimlerinde muadil/orijinal seçenek müşteriye bildirilir.</li>
                    <li>Tamir süresi tahmini olup bağlayıcı değildir.</li>
                    <li>Yapılan işlem dışında cihazın diğer arızalarından firma sorumlu değildir.</li>
                    <li>Tamir sonrası garanti yalnızca yapılan işlem için geçerlidir.</li>
                    <li>Cihaz tesliminden sonra 7 gün içinde kontrol edilmelidir.</li>
                </ol>
            </div>
            <div class="sk5-box sk5-compact">
                <ul class="sk5-list sk5-list-disc">
                    <li>Cihazın teslim anındaki mevcut arızaları dışında oluşabilecek teknik riskler müşteri tarafından kabul edilmiştir.</li>
                    <li>Anakart, sıvı teması ve yüksek riskli arızalarda başarı garantisi verilmez.</li>
                    <li>Yazılım işlemlerinde veri silinmesi normal kabul edilir.</li>
                    <li>Cihaz hazır olduktan sonra müşteri bilgilendirilir.</li>
                    <li>90 gün içinde teslim alınmayan cihazlar terk edilmiş sayılır.</li>
                    <li>Firma bu cihazlar üzerinde tasarruf hakkına sahiptir.</li>
                    <li>Ücret ödenmeden cihaz teslim edilmez.</li>
                    <li>Arıza tespiti sonrası işlem iptal edilse dahi tespit ücreti alınır.</li>
                    <li>Kişisel veriler yalnızca servis süreci kapsamında işlenir ve üçüncü kişilerle paylaşılmaz.</li>
                    <li>Uyuşmazlıklarda <strong>{{SEHIR}} Mahkemeleri ve İcra Daireleri yetkilidir.</strong></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="sk5-section">
        <div class="sk5-title">5. Onay</div>
        <div class="sk5-box sk5-compact">
            Müşteri bu sözleşmenin tüm maddelerini okuduğunu, anladığını ve kabul ettiğini beyan eder.
            <div class="sk5-date"><strong>Tarih:</strong> {{KABUL_TARIHI}}</div>
        </div>
        <div class="sk5-footer">
            <div class="sk5-sign">
                <div class="sk5-sign-line"></div>
                <div>Müşteri Ad Soyad / İmza</div>
            </div>
            <div class="sk5-sign">
                <div class="sk5-sign-line"></div>
                <div>Yetkili / Kaşe</div>
            </div>
        </div>
    </section>
</div>
HTML;
    }

    private function kabulFormuReceipt80mmHtml(): string
    {
        return <<<'HTML'
<div class="sk-receipt80">
    <div class="sr80-brand">
        <div class="sr80-logo">{{FIRMA_LOGO}}</div>
        <div class="sr80-title">Yalova Bilgisayar Teknik Servis</div>
        <div class="sr80-meta">
            <div>Sahil Mah. Yalı Cad. No:3/A</div>
            <div>Çiftlikköy / Yalova</div>
            <div>0 (226) 352 07 24</div>
            <div>info@yalovabilgisayar.com</div>
        </div>
    </div>

    <div class="sr80-divider"></div>

    <div class="sr80-headline">SERVİS KABUL FİŞİ</div>
    <div class="sr80-subline">RECEIPT 80MM</div>

    <div class="sr80-divider"></div>

    <div class="sr80-pairs">
        <div class="sr80-row"><span>Servis No</span><span>{{SERVIS_NO}}</span></div>
        <div class="sr80-row"><span>Tarih</span><span>{{KABUL_TARIHI}}</span></div>
        <div class="sr80-row"><span>Müşteri</span><span>{{MUSTERI_AD}}</span></div>
        <div class="sr80-row"><span>Telefon</span><span>{{MUSTERI_TEL}}</span></div>
        <div class="sr80-row"><span>Cihaz</span><span>{{CIHAZ_TURU}}</span></div>
        <div class="sr80-row"><span>Marka/Model</span><span>{{MARKA}} / {{MODEL_NO}}</span></div>
        <div class="sr80-row"><span>Seri No</span><span>{{SERI_NO}}</span></div>
        <div class="sr80-row"><span>Durum</span><span>{{SERVIS_DURUMU}}</span></div>
    </div>

    <div class="sr80-divider"></div>

    <table class="sr80-table">
        <thead>
            <tr>
                <th class="sr80-col-left">Bilgi</th>
                <th class="sr80-col-right">Detay</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Aksesuar</td>
                <td>{{AKSESUARLAR}}</td>
            </tr>
            <tr>
                <td>Fiziksel</td>
                <td>{{FIZIKSEL_DURUM}}</td>
            </tr>
            <tr>
                <td>Arıza</td>
                <td>{{ARIZA_ACIKLAMASI}}</td>
            </tr>
            <tr>
                <td>Teslim Notu</td>
                <td>{{TESLIM_NOTU}}</td>
            </tr>
        </tbody>
    </table>

    <div class="sr80-divider"></div>

    <div class="sr80-summary">
        <div class="sr80-row"><span>Arıza Tespit</span><span>{{TESPIT_UCRETI}}</span></div>
        <div class="sr80-row sr80-total"><span>Toplam</span><span>{{TOPLAM_TUTAR}}</span></div>
    </div>

    <div class="sr80-divider"></div>

    <div class="sr80-note">
        Cihaz tesliminde bu fişin ibraz edilmesi önerilir. Veri yedekleme sorumluluğu müşteriye aittir.
    </div>

    <div class="sr80-signs">
        <div class="sr80-sign">
            <div class="sr80-sign-line"></div>
            <div>Müşteri</div>
        </div>
        <div class="sr80-sign">
            <div class="sr80-sign-line"></div>
            <div>Yetkili</div>
        </div>
    </div>

    <div class="sr80-footer">
        <div>Teşekkür ederiz</div>
        <div>{{SEHIR}} / Türkiye</div>
        <div>{{SERVIS_NO}}</div>
    </div>
</div>
HTML;
    }

    private function teknikServisFormuA4Html(): string
    {
        return <<<'HTML'
<div class="tsf-doc">
    <header class="tsf-header">
        <div class="tsf-brand">
            <div class="tsf-brand-copy">
                <div class="tsf-kicker">Yalova Bilgisayar</div>
                <h1>TEKNIK SERVIS FORMU</h1>
                <div class="tsf-contact">Sahil Mah. Yalı Cad. No:3/A Çiftlikköy/Yalova</div>
                <div class="tsf-contact">Tel: 0 (226) 352 07 24 | E-posta: info@yalovabilgisayar.com</div>
                <div class="tsf-contact">Cep: 0 (553) 979 32 55 | Web site: www.yalovabilgisayar.com</div>
            </div>
            <div class="tsf-logo">{{FIRMA_LOGO}}</div>
        </div>
        <div class="tsf-meta-card">
            <div class="tsf-meta-row"><span>Servis No</span><strong>{{SERVIS_NO}}</strong></div>
            <div class="tsf-meta-row"><span>Kabul Tarihi</span><strong>{{KABUL_TARIHI}}</strong></div>
            <div class="tsf-meta-row"><span>Durum</span><strong>{{SERVIS_DURUMU}}</strong></div>
            <div class="tsf-meta-row"><span>Teslim Tarihi</span><strong>{{TESLIM_TARIHI}}</strong></div>
        </div>
    </header>

    <section class="tsf-section">
        <div class="tsf-section-title">Musteri ve Cihaz Bilgileri</div>
        <div class="tsf-grid tsf-grid-2">
            <div class="tsf-card">
                <div class="tsf-card-title">Musteri Bilgileri</div>
                <div><strong>Ad Soyad:</strong> {{MUSTERI_AD}}</div>
                <div><strong>T.C. No:</strong> {{MUSTERI_TC_NO}}</div>
                <div><strong>Telefon:</strong> {{MUSTERI_TEL}}</div>
                <div><strong>Adres:</strong> {{MUSTERI_ADRES}}</div>
            </div>
            <div class="tsf-card">
                <div class="tsf-card-title">Cihaz Bilgileri</div>
                <div><strong>Cihaz:</strong> {{CIHAZ}}</div>
                <div><strong>Marka / Model:</strong> {{MARKA}} / {{MODEL_NO}}</div>
                <div><strong>Seri No:</strong> {{SERI_NO}}</div>
                <div><strong>Aksesuarlar:</strong> {{AKSESUARLAR}}</div>
            </div>
        </div>
    </section>

    <section class="tsf-section">
        <div class="tsf-section-title">Servis Aciklamalari</div>
        <div class="tsf-grid tsf-grid-2">
            <div class="tsf-box">
                <div class="tsf-box-title">Ariza / Musteri Beyani</div>
                <div>{{ARIZA_ACIKLAMASI}}</div>
            </div>
            <div class="tsf-box">
                <div class="tsf-box-title">Servis Notu</div>
                <div>{{FIZIKSEL_DURUM}}</div>
            </div>
        </div>
        <div class="tsf-box tsf-full">
            <div class="tsf-box-title">Yapilan Islemler</div>
            <div>{{YAPILAN_ISLEMLER}}</div>
        </div>
        {{TESLIM_NOTU_BLOKU}}
    </section>

    <section class="tsf-section">
        <div class="tsf-section-title">Gorsel Kayit</div>
        {{CIHAZ_FOTOGRAFLARI}}
    </section>

    <section class="tsf-section">
        <div class="tsf-section-title">Stok ve Hizmet Kalemleri</div>
        {{STOK_KALEMLERI_TABLOSU}}
        <div class="tsf-footnote">Fiyatlarımıza KDV Dahil Değildir.</div>
    </section>

    <section class="tsf-section">
        <div class="tsf-section-title">Finans ve Odeme Bilgileri</div>
        <div class="tsf-grid tsf-grid-2">
            <div class="tsf-box">
                <div class="tsf-box-title">Toplam Ozeti</div>
                {{TOPLAM_OZETI}}
            </div>
            <div class="tsf-box">
                <div class="tsf-box-title">Odeme Ozeti</div>
                {{ODEME_OZETI}}
            </div>
        </div>
    </section>

    <footer class="tsf-footer">
        <div class="tsf-signature">
            <div class="tsf-sign-line"></div>
            <div>Musteri Imza</div>
        </div>
        <div class="tsf-signature">
            <div class="tsf-sign-line"></div>
            <div>Yetkili Imza / Kase</div>
        </div>
    </footer>
</div>
HTML;
    }

    private function kvkTeknikServisFormuA4Html(): string
    {
        return <<<'HTML'
<div class="kts-doc">
    <header class="kts-header">
        <div class="kts-brand">
            <div class="kts-brand-copy">
                <div class="kts-kicker">Yalova Bilgisayar</div>
                <h1>TEKNİK SERVİS FORMU</h1>
                <div class="kts-contact">Hizmet sözleşmesi, teknik risk bildirimi ve KVKK aydınlatma metni</div>
                <div class="kts-contact">Sahil Mah. Yalı Cad. No:3/A Çiftlikköy / Yalova</div>
                <div class="kts-contact">Tel: 0 (226) 352 07 24 | Cep: 0 (553) 979 32 55 | info@yalovabilgisayar.com</div>
            </div>
            <div class="kts-logo">{{FIRMA_LOGO}}</div>
        </div>
        <div class="kts-meta">
            <div class="kts-meta-row"><span>Servis No</span><strong>{{SERVIS_NO}}</strong></div>
            <div class="kts-meta-row"><span>Kabul</span><strong>{{KABUL_TARIHI}}</strong></div>
            <div class="kts-meta-row"><span>Durum</span><strong>{{SERVIS_DURUMU}}</strong></div>
            <div class="kts-meta-row"><span>Teslim</span><strong>{{TESLIM_TARIHI}}</strong></div>
        </div>
    </header>

    <section class="kts-section">
        <div class="kts-section-title">Taraf ve Cihaz Ozeti</div>
        <div class="kts-grid kts-grid-2">
            <div class="kts-card">
                <div class="kts-card-title">1. Musteri Bilgileri</div>
                <div><strong>Ad Soyad:</strong> {{MUSTERI_AD}}</div>
                <div><strong>T.C. No:</strong> {{MUSTERI_TC_NO}}</div>
                <div><strong>Telefon:</strong> {{MUSTERI_TEL}}</div>
                <div><strong>Adres:</strong> {{MUSTERI_ADRES}}</div>
            </div>
            <div class="kts-card">
                <div class="kts-card-title">2. Cihaz ve Kabul Bilgileri</div>
                <div><strong>Cihaz:</strong> {{CIHAZ}}</div>
                <div><strong>Marka / Model:</strong> {{MARKA}} / {{MODEL_NO}}</div>
                <div><strong>Seri No:</strong> {{SERI_NO}}</div>
                <div><strong>Aksesuarlar:</strong> {{AKSESUARLAR}}</div>
            </div>
        </div>
    </section>

    <section class="kts-section">
        <div class="kts-grid kts-grid-2 kts-grid-tight">
            <div class="kts-box">
                <div class="kts-box-title">3. Ariza / Musteri Beyani</div>
                <div>{{ARIZA_ACIKLAMASI}}</div>
            </div>
            <div class="kts-box">
                <div class="kts-box-title">4. Fiziksel Durum ve Servis Notu</div>
                <div>{{FIZIKSEL_DURUM}}</div>
            </div>
        </div>
        <div class="kts-box kts-full">
            <div class="kts-box-title">5. Yapilan Islemler ve Teslim Notu</div>
            <div class="kts-inline-stack">
                <div>{{YAPILAN_ISLEMLER}}</div>
                <div><strong>Teslim Notu:</strong> {{TESLIM_NOTU}}</div>
            </div>
        </div>
    </section>

    <section class="kts-section">
        <div class="kts-grid kts-grid-legal">
            <div class="kts-box">
                <div class="kts-box-title">6. Stok ve Hizmet Kalemleri</div>
                {{STOK_KALEMLERI_TABLOSU}}
                <div class="kts-footnote">Fiyatlarımıza KDV dahil değildir.</div>
            </div>
            <div class="kts-side-stack">
                <div class="kts-box">
                    <div class="kts-box-title">7. Toplam ve Odeme Ozeti</div>
                    {{TOPLAM_OZETI}}
                    <div class="kts-divider"></div>
                    {{ODEME_OZETI}}
                </div>
            </div>
        </div>
    </section>

    {{CIHAZ_FOTOGRAFLARI_BLOKU}}

    <section class="kts-section">
        <div class="kts-section-title">8. KVK, Hizmet ve Teslim Kosullari</div>
        <div class="kts-legal-grid">
            <div class="kts-legal-box">
                <div class="kts-legal-title">8.1 Hizmet Kosullari</div>
                <ol class="kts-list kts-list-ordered">
                    <li>Ariza tespiti ucretli olup tespit bedeli {{TESPIT_UCRETI}} olarak uygulanir.</li>
                    <li>Musterinin acik onayi alinmadan ucretli islem, parca degisimi veya ilave hizmet uygulanmaz.</li>
                    <li>Tamir suresi tahmini nitelikte olup teknik inceleme, test ve parca tedarik sureclerine gore degisebilir.</li>
                    <li>Parca degisimi gerekli hallerde orijinal veya muadil parca secenegi musterinin bilgisine sunulur.</li>
                </ol>
            </div>
            <div class="kts-legal-box">
                <div class="kts-legal-title">8.2 Veri ve Teknik Riskler</div>
                <ol class="kts-list kts-list-ordered">
                    <li>Veri yedekleme sorumlulugu musterinin kendisine aittir; servis surecinde veri kaybi riski bulunabilir.</li>
                    <li>Sivi temasi, oksitlenme, anakart arizasi ve agir darbe kaynakli cihazlarda basari garantisi verilmez.</li>
                    <li>Cihazin mevcut arizalari disinda, daha once fark edilmemis gizli kusurlar teknik islem sirasinda ortaya cikabilir.</li>
                </ol>
            </div>
            <div class="kts-legal-box">
                <div class="kts-legal-title">8.3 Odeme, Teslim ve Bekletme</div>
                <ol class="kts-list kts-list-ordered">
                    <li>Islem bedeli tahsil edilmeden cihaz teslim edilmez; islem iptal edilse dahi ariza tespit ucreti tahsil edilir.</li>
                    <li>Cihazin hazir olmasini takiben musteri bilgilendirilir; teslim aninda cihaz ve aksesuar kontrolu musterinin yukumlulugundedir.</li>
                    <li>Makul sure icinde teslim alinmayan cihazlar icin bekletme ve depolama sureci uygulanabilir.</li>
                </ol>
            </div>
        </div>
        <div class="kts-legal-grid kts-legal-grid-2">
            <div class="kts-legal-box">
                <div class="kts-legal-title">8.4 KVKK Aydinlatma</div>
                <div class="kts-legal-text">Musteriye ait kisisel veriler; servis kaydi olusturma, teknik inceleme, tekliflendirme, onay sureci, tahsilat ve teslim operasyonlari kapsaminda ilgili mevzuata uygun sekilde islenir. Bu veriler, yasal zorunluluklar disinda ucuncu kisilerle paylasilmaz.</div>
            </div>
            <div class="kts-legal-box">
                <div class="kts-legal-title">8.5 Beyan ve Yetki</div>
                <div class="kts-legal-text">Musteri, isbu formdaki bilgilerin dogrulugunu, hizmet kosullarini, odeme esaslarini ve veri isleme aydinlatmasini okuyup anladigini kabul eder. Uyusmazlik halinde {{SEHIR}} Mahkemeleri ve Icra Daireleri yetkilidir.</div>
            </div>
        </div>
    </section>

    <footer class="kts-footer">
        <div class="kts-sign">
            <div class="kts-sign-line"></div>
            <div>Musteri Ad Soyad / Imza</div>
        </div>
        <div class="kts-sign">
            <div class="kts-sign-line"></div>
            <div>Yetkili / Kase</div>
        </div>
    </footer>
</div>
HTML;
    }

    private function yalovaBilgisayarServisKabulFormuA4Html(): string
    {
        return <<<'HTML'
<div class="ysf-doc">
    <header class="ysf-header">
        <div class="ysf-brand">
            <div class="ysf-brand-copy">
                <div class="ysf-kicker">Yalova Bilgisayar</div>
                <h1>TEKNİK SERVİS FORMU</h1>
                <div class="ysf-contact">Hizmet sözleşmesi, teknik risk bildirimi ve KVKK aydınlatma metni</div>
                <div class="ysf-contact">Sahil Mah. Yalı Cad. No:3/A Çiftlikköy / Yalova</div>
                <div class="ysf-contact">Tel: 0 (226) 352 07 24 | Cep: 0 (553) 979 32 55 | info@yalovabilgisayar.com</div>
            </div>
            <div class="ysf-logo-shell">
                <div class="ysf-logo">{{FIRMA_LOGO}}</div>
            </div>
        </div>
        <div class="ysf-meta">
            <div class="ysf-meta-row"><span>Servis No</span><strong>{{SERVIS_NO}}</strong></div>
            <div class="ysf-meta-row"><span>Kabul Tarihi</span><strong>{{KABUL_TARIHI}}</strong></div>
            <div class="ysf-meta-row"><span>Tahmini Teslim</span><strong>{{TAHMINI_TESLIM}}</strong></div>
            <div class="ysf-meta-row"><span>Durum</span><strong>{{SERVIS_DURUMU}}</strong></div>
        </div>
    </header>

    <section class="ysf-panel">
        <div class="ysf-title">Servis, Müşteri ve Cihaz Özeti</div>
        <div class="ysf-grid ysf-grid-2">
            <div class="ysf-card">
                <div class="ysf-card-title">Müşteri Bilgileri</div>
                <div class="ysf-kv"><span>Ad Soyad</span><strong>{{MUSTERI_AD}}</strong></div>
                <div class="ysf-kv"><span>T.C. No</span><strong>{{MUSTERI_TC_NO}}</strong></div>
                <div class="ysf-kv"><span>Telefon</span><strong>{{MUSTERI_TEL}}</strong></div>
                <div class="ysf-kv"><span>Adres</span><strong>{{MUSTERI_ADRES}}</strong></div>
            </div>
            <div class="ysf-card">
                <div class="ysf-card-title">Cihaz Bilgileri</div>
                <div class="ysf-kv"><span>Cihaz Türü</span><strong>{{CIHAZ_TURU}}</strong></div>
                <div class="ysf-kv"><span>Marka / Model</span><strong>{{MARKA}} / {{MODEL_NO}}</strong></div>
                <div class="ysf-kv"><span>Seri No</span><strong>{{SERI_NO}}</strong></div>
                <div class="ysf-kv"><span>Şifre</span><strong>{{CIHAZ_SIFRESI}}</strong></div>
                <div class="ysf-kv"><span>Aksesuarlar</span><strong>{{AKSESUARLAR}}</strong></div>
            </div>
        </div>
    </section>

    <section class="ysf-panel">
        <div class="ysf-grid ysf-grid-2 ysf-grid-tight">
            <div class="ysf-box">
                <div class="ysf-box-title">Arıza / Müşteri Beyanı</div>
                <div>{{ARIZA_ACIKLAMASI}}</div>
            </div>
            <div class="ysf-box">
                <div class="ysf-box-title">Fiziksel Durum Tespiti</div>
                <div>{{FIZIKSEL_DURUM}}</div>
            </div>
        </div>
        <div class="ysf-grid ysf-grid-2 ysf-grid-tight ysf-stack-top">
            <div class="ysf-box">
                <div class="ysf-box-title">Yapılacak İşlem / Servis Notu</div>
                <div>{{YAPILAN_ISLEMLER}}</div>
            </div>
            <div class="ysf-box">
                <div class="ysf-box-title">Parça Bilgisi ve Görsel Kayıt</div>
                {{CIHAZ_FOTOGRAFLARI}}
            </div>
        </div>
    </section>

    <section class="ysf-panel">
        <div class="ysf-title">Parça, Hizmet ve Ücretlendirme</div>
        <div class="ysf-grid ysf-grid-pricing">
            <div class="ysf-box">
                <div class="ysf-box-title">Kalemler</div>
                {{STOK_KALEMLERI_TABLOSU}}
                <div class="ysf-table-note">Fiyatlarımıza KDV dahil değildir.</div>
            </div>
            <div class="ysf-side-stack">
                <div class="ysf-box">
                    <div class="ysf-box-title">Toplam Özeti</div>
                    {{TOPLAM_OZETI}}
                </div>
                <div class="ysf-box">
                    <div class="ysf-box-title">Ödeme Özeti</div>
                    {{ODEME_OZETI}}
                </div>
                <div class="ysf-box">
                    <div class="ysf-box-title">Onay ve İşlem Bilgisi</div>
                    <div class="ysf-kv"><span>Arıza Tespit Ücreti</span><strong>{{TESPIT_UCRETI}}</strong></div>
                    <div class="ysf-kv"><span>Müşteri Onayı</span><strong>{{MUSTERI_ONAY_DURUMU}}</strong></div>
                    <div class="ysf-inline-note">{{ONAY_NOTU}}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="ysf-panel">
        <div class="ysf-title">Hizmet, KVK ve Hukuki Şartlar</div>
        <div class="ysf-legal-grid">
            <div class="ysf-legal-box">
                <div class="ysf-legal-title">Veri ve Onay</div>
                <p>Müşteri, cihazdaki verilerin yedeğini aldığını ve servis sırasında veri kaybı oluşabileceğini kabul eder. Teknik işlem için cihaz verilerine erişim gerekebilir. Onay alınmadan ücretli işlem, parça değişimi veya ilave hizmet uygulanmaz.</p>
            </div>
            <div class="ysf-legal-box">
                <div class="ysf-legal-title">Onarım ve Riskler</div>
                <p>Arıza tespiti sonrasında işlem süresi teknik inceleme ve parça tedarikine göre değişebilir. Yapılan işlemler 90 gün servis garantisi kapsamındadır; kullanıcı hatası, sıvı teması, darbe, yetkisiz müdahale ve saklama-taşıma koşulları garanti dışıdır.</p>
            </div>
            <div class="ysf-legal-box">
                <div class="ysf-legal-title">Sorumluluk ve Teslim</div>
                <p>Elektronik cihazların doğası gereği işlem sırasında yeni veya gizli arızalar ortaya çıkabilir; voltaj, adaptör uyumsuzluğu ve kargo/taşıma kaynaklı hasarlardan firma sorumlu değildir. Teslimde cihazın kontrolü müşteriye aittir. Müşteri, cihazı teslim aldığı anda gerekli kontrolleri ve testleri yaptığını, cihazı mevcut durumu ile kabul ettiğini ve teslim sonrasındaki kullanım, muhafaza ve sorumluluğun kendisine ait olduğunu beyan ve kabul eder.</p>
            </div>
            <div class="ysf-legal-box">
                <div class="ysf-legal-title">İptal, Bekletme ve KVKK</div>
                <p>Onarım başladıktan sonra iptal edilirse yapılan işlem ve kullanılan parçalar ücretlendirilir. 0-60 gün ücretsiz bekleme, 60-180 gün ücretli muhafaza süreci uygulanabilir; 180 gün sonunda cihaz üzerindeki haklardan feragat edilmiş sayılır. Kişisel veriler servis süreci için işlenir, yasal zorunluluk dışında paylaşılmaz.</p>
            </div>
        </div>
        <div class="ysf-footnote">Elektronik onaylar (SMS, telefon, WhatsApp veya e-posta) yazılı onay hükmündedir. Uyuşmazlıklarda {{SEHIR}} Mahkemeleri ve İcra Daireleri yetkilidir.</div>
    </section>

    <footer class="ysf-footer">
        <div class="ysf-sign">
            <div class="ysf-sign-line"></div>
            <div>Müşteri Ad Soyad / İmza</div>
        </div>
        <div class="ysf-sign">
            <div class="ysf-sign-line"></div>
            <div>Yetkili / Kaşe / İmza</div>
        </div>
    </footer>
</div>
HTML;
    }

    private function cssA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
.servis-ust { display: flex; flex-direction: column; align-items: center; border-bottom: 1px solid #d1d5db; padding-bottom: 8px; margin-bottom: 10px; text-align: center; }
.servis-ust > div { width: 100%; }
.servis-ust-ana { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: 8px; width: 100%; }
.servis-ust .logo-alani { order: initial; margin-bottom: 0; }
.servis-ust .servis-iletisim { text-align: left; }
.servis-firma-alt { margin-top: 6px; text-align: center; }
.firma-ad { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
.logo-alani img { max-height: 64px; max-width: 180px; }
.belge-baslik { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: .06em; margin-bottom: 12px; }
.servis-bilgi-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 6px 12px; margin-bottom: 12px; }
.servis-blok { border: 1px solid #d1d5db; padding: 8px; margin-bottom: 10px; min-height: 52px; }
.blok-baslik { font-weight: 700; margin-bottom: 6px; }
.servis-toplam { display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; margin-top: 10px; }
.imza-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; margin-top: 28px; }
.imza-kutu { border-top: 1px solid #111; padding-top: 8px; min-height: 36px; text-align: center; }
CSS;
    }

    private function cssKabulFormuKvkkA5(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.2px; line-height: 1.18; color: #111827; margin: 0; }
.servis-kabul-a5 { width: 100%; }
.sk5-header { border-bottom: 1px solid #111827; padding-bottom: 4px; margin-bottom: 5px; }
.sk5-eyebrow { text-align: center; font-size: 7px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; margin-bottom: 2px; }
.sk5-header h1 { margin: 0; font-size: 10.4px; line-height: 1.14; font-weight: 800; text-align: center; }
.sk5-section { margin-bottom: 4px; page-break-inside: avoid; }
.sk5-title { font-size: 8px; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid #9ca3af; padding-bottom: 1px; margin-bottom: 2px; }
.sk5-grid { display: grid; gap: 2px 8px; }
.sk5-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.sk5-legal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }
.sk5-box { border: 1px solid #d1d5db; border-radius: 5px; padding: 4px 5px; background: #fff; }
.sk5-box-title { font-weight: 800; margin-bottom: 2px; }
.sk5-compact { padding-top: 3px; padding-bottom: 3px; }
.sk5-list { margin: 0; padding-left: 12px; }
.sk5-list li { margin-bottom: 1px; }
.sk5-list-disc { list-style: disc; }
.sk-photo-gallery-wrap { margin-top: 3px; text-align: center; }
.sk-photo-gallery-title { font-weight: 700; margin-bottom: 2px; font-size: 7.2px; }
.sk-photo-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; max-width: 100%; }
.sk-photo-item { border: 1px solid #d1d5db; border-radius: 4px; background: #fff; padding: 2px; }
.sk-photo-item img { width: 100%; height: 76px; object-fit: cover; border-radius: 2px; display: block; }
.sk-photo-placeholder { width: 100%; height: 76px; border-radius: 2px; background: linear-gradient(135deg, #eef2f7 0%, #dbe3ee 100%); display: flex; align-items: center; justify-content: center; color: #475569; font-size: 6.5px; font-weight: 700; }
.sk5-date { margin-top: 3px; }
.sk5-footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-top: 8px; }
.sk5-sign { text-align: center; min-height: 40px; display: flex; flex-direction: column; justify-content: flex-end; }
.sk5-sign-line { border-top: 1px solid #172033; margin-bottom: 5px; }
@media print {
  .servis-kabul-a5 { page-break-inside: avoid; }
  .sk-photo-gallery .sk-photo-item:nth-child(n+3) { display: none; }
}
CSS;
    }

    private function cssKabulFormuA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.45; color: #111827; }
.servis-kabul-a4 { width: 100%; }
.sk-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 18px; }
.sk-header-left { flex: 1; }
.sk-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
.sk-header h1 { margin: 0; font-size: 22px; line-height: 1.2; font-weight: 800; text-align: center; }
.sk-logo img { max-height: 74px; max-width: 180px; }
.sk-section { margin-bottom: 14px; page-break-inside: avoid; }
.sk-section-title { font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #9ca3af; padding-bottom: 4px; margin-bottom: 8px; }
.sk-grid { display: grid; gap: 10px 18px; }
.sk-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.sk-card { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; background: #f9fafb; }
.sk-card-title { font-weight: 800; margin-bottom: 6px; font-size: 13px; }
.sk-note-box, .sk-text-area, .sk-paragraph { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; background: #fff; }
.sk-text-area { min-height: 78px; }
.sk-photo-gallery-wrap { margin-top: 10px; text-align: center; }
.sk-photo-gallery-title { font-weight: 700; margin-bottom: 8px; }
.sk-photo-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; justify-content: center; align-items: start; }
.sk-photo-item { border: 1px solid #d1d5db; border-radius: 8px; background: #fff; padding: 6px; text-align: center; }
.sk-photo-item img { width: 100%; height: 164px; object-fit: cover; border-radius: 6px; display: block; }
.sk-photo-placeholder { width: 100%; height: 164px; border-radius: 6px; background: linear-gradient(135deg, #eef2f7 0%, #dbe3ee 100%); display: flex; align-items: center; justify-content: center; color: #475569; font-size: 11px; font-weight: 700; }
.sk-list { margin: 0; padding-left: 20px; }
.sk-list li { margin-bottom: 4px; }
.sk-list-disc { list-style: disc; }
.sk-signature-meta { margin: 10px 0 20px; }
.sk-signatures { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 30px; margin-top: 26px; }
.sk-signature-box { text-align: center; min-height: 72px; display: flex; flex-direction: column; justify-content: flex-end; }
.sk-signature-line { border-top: 1px solid #111827; margin-bottom: 8px; }
@media print {
  .servis-kabul-a4 { page-break-inside: avoid; }
}
CSS;
    }

    private function cssA5(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
.servis-ust { display: flex; flex-direction: column; align-items: center; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin-bottom: 8px; text-align: center; }
.servis-ust > div { width: 100%; }
.servis-ust-ana { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: 6px; width: 100%; }
.servis-ust .logo-alani { order: initial; margin-bottom: 0; }
.servis-ust .servis-iletisim { text-align: left; }
.servis-firma-alt { margin-top: 4px; text-align: center; }
.firma-ad { font-size: 15px; font-weight: 700; margin-bottom: 3px; }
.logo-alani img { max-height: 48px; max-width: 120px; }
.belge-baslik { text-align: center; font-size: 14px; font-weight: 700; letter-spacing: .05em; margin-bottom: 10px; }
.servis-bilgi-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 4px 10px; margin-bottom: 10px; }
.servis-blok { border: 1px solid #d1d5db; padding: 6px; margin-bottom: 8px; min-height: 40px; }
.blok-baslik { font-weight: 700; margin-bottom: 4px; }
.servis-toplam { display: flex; justify-content: space-between; align-items: center; font-weight: 700; margin-top: 8px; }
.imza-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; margin-top: 22px; }
.imza-kutu { border-top: 1px solid #111; padding-top: 6px; min-height: 28px; text-align: center; }
CSS;
    }

    private function css80mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans Mono, Arial, sans-serif; font-size: 10px; color: #111; }
.servis-kapsayici { width: 76mm; }
.servis-ust { display: flex; flex-direction: column; align-items: center; border-bottom: 1px dashed #888; padding-bottom: 3px; margin-bottom: 4px; text-align: center; line-height: 1.15; }
.servis-ust > div { width: 100%; }
.servis-ust-ana { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: 4px; width: 100%; }
.servis-ust .logo-alani { order: initial; margin-bottom: 0; display: flex; justify-content: center; align-items: center; width: 100%; text-align: center; }
.servis-ust .servis-iletisim { min-width: 0; text-align: left; word-break: break-word; }
.servis-firma-alt { margin-top: 2px; text-align: center; }
.firma-ad { font-size: 11px; font-weight: 700; margin-bottom: 1px; }
.logo-alani img { display: block; max-height: 24px; max-width: 100%; margin: 1px auto 0; }
.belge-baslik { text-align: center; font-size: 10px; font-weight: 700; margin-bottom: 3px; }
.servis-bilgi-grid { display: grid; grid-template-columns: 1fr; gap: 1px; margin-bottom: 3px; }
.servis-blok { border-top: 1px dashed #888; padding-top: 2px; margin-top: 3px; line-height: 1.2; }
.blok-baslik { font-weight: 700; margin-bottom: 1px; }
.servis-toplam { display: flex; justify-content: space-between; margin-top: 3px; font-weight: 700; border-top: 1px dashed #888; padding-top: 2px; }
.imza-grid { display: grid; grid-template-columns: 1fr; gap: 5px; margin-top: 7px; }
.imza-kutu { border-top: 1px dashed #111; padding-top: 2px; text-align: center; min-height: 14px; }
CSS;
    }

    private function css58mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans Mono, Arial, sans-serif; font-size: 9px; color: #111; }
.servis-kapsayici { width: 54mm; }
.servis-ust { display: flex; flex-direction: column; align-items: center; border-bottom: 1px dashed #888; padding-bottom: 2px; margin-bottom: 3px; text-align: center; line-height: 1.1; }
.servis-ust > div { width: 100%; }
.servis-ust-ana { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: 3px; width: 100%; }
.servis-ust .logo-alani { order: initial; margin-bottom: 0; display: flex; justify-content: center; align-items: center; width: 100%; text-align: center; }
.servis-ust .servis-iletisim { min-width: 0; text-align: left; word-break: break-word; }
.servis-firma-alt { margin-top: 1px; text-align: center; }
.firma-ad { font-size: 10px; font-weight: 700; margin-bottom: 1px; }
.logo-alani img { display: block; max-height: 20px; max-width: 100%; margin: 1px auto 0; }
.belge-baslik { text-align: center; font-size: 9px; font-weight: 700; margin-bottom: 2px; }
.servis-bilgi-grid { display: grid; grid-template-columns: 1fr; gap: 1px; margin-bottom: 2px; }
.servis-blok { border-top: 1px dashed #888; padding-top: 1px; margin-top: 2px; line-height: 1.15; }
.blok-baslik { font-weight: 700; margin-bottom: 1px; }
.servis-toplam { display: flex; justify-content: space-between; margin-top: 2px; font-weight: 700; border-top: 1px dashed #888; padding-top: 1px; }
.imza-grid { display: grid; grid-template-columns: 1fr; gap: 4px; margin-top: 5px; }
.imza-kutu { border-top: 1px dashed #111; padding-top: 1px; text-align: center; min-height: 12px; }
CSS;
    }

    private function cssKabulFormuReceipt80mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans Mono, Arial, sans-serif; font-size: 9.4px; line-height: 1.45; color: #111; margin: 0; }
.sk-receipt80 { width: 76mm; margin: 0 auto; }
.sr80-brand { text-align: center; }
.sr80-logo { margin-bottom: 4px; }
.sr80-logo img { max-height: 30px; max-width: 100%; }
.sr80-title { font-size: 12.6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.sr80-meta { margin-top: 3px; font-size: 8.7px; line-height: 1.4; }
.sr80-headline { text-align: center; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; margin-top: 2px; }
.sr80-subline { text-align: center; font-size: 9px; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 2px; }
.sr80-divider { border-top: 1px dashed #6b7280; margin: 7px 0; }
.sr80-pairs { display: grid; gap: 2px; }
.sr80-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.sr80-row span:first-child { flex: 0 0 28mm; }
.sr80-row span:last-child { flex: 1 1 auto; text-align: right; word-break: break-word; }
.sr80-table { width: 100%; border-collapse: collapse; }
.sr80-table thead th { font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #111; padding: 0 0 4px; }
.sr80-table td { padding: 4px 0; border-bottom: 1px dashed #d1d5db; vertical-align: top; }
.sr80-col-left { width: 22mm; text-align: left; }
.sr80-col-right { text-align: right; }
.sr80-table td:first-child { width: 22mm; font-weight: 700; }
.sr80-table td:last-child { text-align: right; word-break: break-word; }
.sr80-summary { display: grid; gap: 3px; }
.sr80-total { font-size: 11px; font-weight: 700; }
.sr80-note { text-align: center; font-size: 8.7px; line-height: 1.5; }
.sr80-signs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
.sr80-sign { text-align: center; font-size: 8.3px; min-height: 28px; display: flex; flex-direction: column; justify-content: flex-end; }
.sr80-sign-line { border-top: 1px dashed #111; margin-bottom: 4px; }
.sr80-footer { text-align: center; font-size: 8.4px; margin-top: 10px; display: grid; gap: 2px; }
CSS;
    }

    private function css10x10mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 2.4px; color: #111; margin: 0; }
.mini-kapsayici { width: 10mm; height: 10mm; overflow: hidden; border: 0.2px solid #333; padding: 0.4mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; }
.mini-baslik { font-weight: 700; text-align: center; line-height: 1.1; margin-bottom: 0.4mm; }
.mini-satir { line-height: 1.1; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
CSS;
    }

    private function cssTeknikServisFormuA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11.5px; line-height: 1.45; color: #172033; background: #fff; }
.tsf-doc { width: 100%; }
.tsf-header { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(220px, 0.9fr); gap: 12px; align-items: start; margin-bottom: 12px; }
.tsf-brand { display: grid; grid-template-columns: minmax(0, 1fr) 180px; align-items: center; gap: 14px; border: 1px solid #cfd8e3; border-radius: 14px; padding: 10px 12px; background: linear-gradient(135deg, #f8fbff 0%, #edf4fb 100%); }
.tsf-brand-copy { flex: 1 1 auto; min-width: 0; }
.tsf-logo { width: 180px; min-width: 180px; min-height: 64px; display: flex; align-items: center; justify-content: center; justify-self: end; overflow: hidden; }
.tsf-logo img { max-width: 170px; max-height: 64px; object-fit: contain; object-position: center right; }
.tsf-kicker { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #5c6f86; margin-bottom: 3px; }
.tsf-brand h1 { margin: 0 0 3px; font-size: 20px; line-height: 1.02; letter-spacing: 0.03em; color: #16324f; }
.tsf-contact { color: #4a5b70; font-size: 9.8px; line-height: 1.22; margin-top: 1px; }
.tsf-meta-card { border: 1px solid #cfd8e3; border-radius: 14px; padding: 10px 12px; background: #16324f; color: #f8fbff; display: grid; gap: 6px; }
.tsf-meta-row { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid rgba(255,255,255,.14); padding-bottom: 5px; }
.tsf-meta-row:last-child { border-bottom: 0; padding-bottom: 0; }
.tsf-meta-row span { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #c6d3e0; }
.tsf-meta-row strong { font-size: 10.5px; text-align: right; line-height: 1.15; }
.tsf-section { margin-bottom: 16px; }
.tsf-section:last-of-type { margin-bottom: 0; }
.tsf-section-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #16324f; padding-bottom: 6px; margin-bottom: 10px; border-bottom: 2px solid #d9e4ef; }
.tsf-grid { display: grid; gap: 12px; }
.tsf-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.tsf-card, .tsf-box { border: 1px solid #d8e0ea; border-radius: 14px; padding: 12px 14px; background: #fff; }
.tsf-card { background: #fdfefe; }
.tsf-card-title, .tsf-box-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #51657d; margin-bottom: 8px; }
.tsf-full { margin-top: 12px; }
.tsf-photo-gallery-wrap, .sk-photo-gallery-wrap { margin-top: 6px; }
.tsf-table { width: 100%; border-collapse: collapse; }
.tsf-table th { background: #eff5fb; color: #29496b; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 8px; border-bottom: 1px solid #d5deea; text-align: left; }
.tsf-table td { padding: 9px 8px; border-bottom: 1px solid #e7edf4; vertical-align: top; }
.tsf-table tbody tr:nth-child(even) td { background: #fbfdff; }
.tsf-table .is-right { text-align: right; }
.tsf-footnote { margin-top: 7px; font-size: 10px; color: #475569; }
.tsf-summary { display: grid; gap: 8px; }
.tsf-summary-row { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px dashed #d5deea; padding-bottom: 6px; }
.tsf-summary-row:last-child { border-bottom: 0; padding-bottom: 0; }
.tsf-summary-row strong { color: #16324f; }
.tsf-footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px; margin-top: 0; }
.tsf-signature { text-align: center; min-height: 72px; display: flex; flex-direction: column; justify-content: flex-end; }
.tsf-sign-line { border-top: 1px solid #172033; margin-bottom: 8px; }
.tsf-muted { color: #6b7b8f; }
.sk-photo-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.sk-photo-item { border: 1px solid #d8e0ea; border-radius: 10px; padding: 5px; background: #fff; }
.sk-photo-item img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; display: block; }
.sk-photo-gallery-title { font-size: 11px; font-weight: 700; margin-bottom: 8px; color: #51657d; }
@media print {
  .tsf-section, .tsf-card, .tsf-box, .tsf-header { page-break-inside: avoid; break-inside: avoid; }
}
CSS;
    }

    private function cssKvkTeknikServisFormuA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.2px; line-height: 1.28; color: #172033; background: #fff; }
.kts-doc { width: 100%; }
.kts-header { display: grid; grid-template-columns: minmax(0, 1.5fr) 218px; gap: 10px; align-items: start; margin-bottom: 10px; }
.kts-brand { display: grid; grid-template-columns: minmax(0, 1fr) 120px; gap: 10px; align-items: center; border: 1px solid #cfd8e3; border-radius: 12px; padding: 9px 10px; background: linear-gradient(135deg, #f8fbff 0%, #edf4fb 100%); }
.kts-brand-copy { min-width: 0; }
.kts-kicker { font-size: 8px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #5c6f86; margin-bottom: 2px; }
.kts-brand h1 { margin: 0 0 2px; font-size: 18px; line-height: 1.02; letter-spacing: .02em; color: #16324f; }
.kts-contact { color: #4a5b70; font-size: 8.6px; line-height: 1.2; }
.kts-logo { min-height: 48px; display: flex; align-items: center; justify-content: center; }
.kts-logo img { max-width: 112px; max-height: 48px; object-fit: contain; }
.kts-meta { border: 1px solid #cfd8e3; border-radius: 12px; padding: 9px 10px; background: #16324f; color: #f8fbff; display: grid; gap: 5px; }
.kts-meta-row { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px solid rgba(255,255,255,.14); padding-bottom: 4px; }
.kts-meta-row:last-child { border-bottom: 0; padding-bottom: 0; }
.kts-meta-row span { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #c6d3e0; }
.kts-meta-row strong { font-size: 9px; text-align: right; line-height: 1.15; }
.kts-section { margin-bottom: 8px; page-break-inside: avoid; break-inside: avoid; }
.kts-section-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #16324f; padding-bottom: 4px; margin-bottom: 6px; border-bottom: 1px solid #d9e4ef; }
.kts-grid { display: grid; gap: 8px; }
.kts-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.kts-grid-tight { gap: 6px; }
.kts-grid-legal { grid-template-columns: minmax(0, 1.45fr) minmax(220px, .9fr); gap: 8px; align-items: start; }
.kts-card, .kts-box, .kts-legal-box { border: 1px solid #d8e0ea; border-radius: 10px; padding: 8px 9px; background: #fff; }
.kts-card { background: #fdfefe; }
.kts-card-title, .kts-box-title, .kts-legal-title { font-size: 8.7px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #51657d; margin-bottom: 5px; }
.kts-footnote { margin-top: 5px; font-size: 8px; color: #475569; }
.kts-full { margin-top: 6px; }
.kts-inline-stack { display: grid; gap: 5px; }
.kts-side-stack { display: grid; gap: 8px; }
.kts-divider { border-top: 1px dashed #d5deea; margin: 6px 0; }
.kts-legal-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 7px; }
.kts-legal-text { line-height: 1.32; }
.kts-list { margin: 0; padding-left: 14px; }
.kts-list li { margin-bottom: 2px; }
.kts-footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-top: 8px; }
.kts-sign { text-align: center; min-height: 40px; display: flex; flex-direction: column; justify-content: flex-end; }
.kts-sign-line { border-top: 1px solid #172033; margin-bottom: 5px; }
.kts-box .tsf-table { width: 100%; border-collapse: collapse; }
.kts-box .tsf-table th { background: #eff5fb; color: #29496b; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; padding: 6px 5px; border-bottom: 1px solid #d5deea; text-align: left; }
.kts-box .tsf-table td { padding: 5px; border-bottom: 1px solid #e7edf4; vertical-align: top; font-size: 8.5px; }
.kts-box .tsf-table .is-right { text-align: right; }
.kts-box .tsf-summary { display: grid; gap: 5px; }
.kts-box .tsf-summary-row { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px dashed #d5deea; padding-bottom: 4px; font-size: 8.6px; }
.kts-box .tsf-summary-row:last-child { border-bottom: 0; padding-bottom: 0; }
.kts-box .tsf-summary-row strong { color: #16324f; }
.kts-box .sk-photo-gallery-wrap, .kts-box .tsf-muted { margin-top: 0; }
.kts-box .sk-photo-gallery-title { font-size: 8px; margin-bottom: 4px; color: #51657d; }
.kts-box .sk-photo-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; }
.kts-box .sk-photo-item { border: 1px solid #d8e0ea; border-radius: 6px; padding: 3px; background: #fff; }
.kts-box .sk-photo-item img, .kts-box .sk-photo-placeholder { height: 52px; border-radius: 4px; }
.kts-box .sk-photo-placeholder { width: 100%; background: linear-gradient(135deg, #eef2f7 0%, #dbe3ee 100%); display: flex; align-items: center; justify-content: center; color: #475569; font-size: 7px; font-weight: 700; }
@media print {
  .kts-section, .kts-card, .kts-box, .kts-legal-box, .kts-header { page-break-inside: avoid; break-inside: avoid; }
}
CSS;
    }

    private function cssYalovaBilgisayarServisKabulFormuA4(): string
    {
        return <<<'CSS'
@page { size: A4; margin: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.6px; line-height: 1.16; color: #172033; background: #fff; }
.ysf-doc { width: 100%; }
.ysf-header { display: grid; grid-template-columns: minmax(0, 1.72fr) 198px; gap: 6px; align-items: stretch; margin-bottom: 5px; }
.ysf-brand { display: grid; grid-template-columns: minmax(0, 1fr) 116px; gap: 7px; align-items: center; padding: 6px 8px; border: 1px solid #cdd8e5; border-radius: 10px; background: linear-gradient(135deg, #f7fbff 0%, #eaf2fb 100%); }
.ysf-brand-copy { min-width: 0; }
.ysf-kicker { font-size: 8px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #5d7089; margin-bottom: 2px; }
.ysf-brand h1 { margin: 0 0 2px; font-size: 15.4px; line-height: 1; letter-spacing: .03em; color: #16324f; }
.ysf-contact { color: #465a72; font-size: 7.8px; line-height: 1.12; }
.ysf-logo-shell { display: flex; align-items: stretch; justify-content: center; }
.ysf-logo { width: 100%; min-height: 48px; border: 1px dashed #b7c8da; border-radius: 8px; background: rgba(255,255,255,.72); display: flex; align-items: center; justify-content: center; padding: 4px; overflow: hidden; }
.ysf-logo:empty::before { content: "LOGO"; font-size: 9px; font-weight: 700; letter-spacing: .18em; color: #8ca0b6; }
.ysf-logo img { max-width: 102px; max-height: 40px; object-fit: contain; }
.ysf-meta { border: 1px solid #cdd8e5; border-radius: 10px; padding: 6px 8px; background: #16324f; color: #f8fbff; display: grid; gap: 3px; }
.ysf-meta-row { display: flex; justify-content: space-between; gap: 6px; padding-bottom: 3px; border-bottom: 1px solid rgba(255,255,255,.14); }
.ysf-meta-row:last-child { padding-bottom: 0; border-bottom: 0; }
.ysf-meta-row span { font-size: 7.2px; letter-spacing: .08em; text-transform: uppercase; color: #d3deea; }
.ysf-meta-row strong { font-size: 8.3px; line-height: 1.08; text-align: right; }
.ysf-panel { margin-bottom: 5px; page-break-inside: avoid; break-inside: avoid; }
.ysf-title { font-size: 9.6px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #16324f; margin-bottom: 4px; padding-bottom: 2px; border-bottom: 1px solid #d9e4ef; }
.ysf-grid { display: grid; gap: 4px; }
.ysf-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.ysf-grid-tight { gap: 4px; }
.ysf-grid-pricing { grid-template-columns: minmax(0, 1.42fr) minmax(188px, .82fr); gap: 5px; align-items: start; }
.ysf-card, .ysf-box, .ysf-legal-box { border: 1px solid #d7e1ec; border-radius: 8px; padding: 5px 6px; background: #fff; }
.ysf-card { background: #fcfeff; }
.ysf-card-title, .ysf-box-title, .ysf-legal-title { font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #51657d; margin-bottom: 3px; }
.ysf-kv { display: flex; justify-content: space-between; gap: 6px; padding: 1px 0; border-bottom: 1px dashed #e0e8f0; }
.ysf-kv:last-child { border-bottom: 0; padding-bottom: 0; }
.ysf-kv span { color: #5a6d84; }
.ysf-kv strong { text-align: right; font-weight: 700; }
.ysf-stack-top { margin-top: 4px; }
.ysf-mini-note { margin-bottom: 4px; color: #334155; }
.ysf-side-stack { display: grid; gap: 4px; }
.ysf-inline-note { margin-top: 4px; padding-top: 4px; border-top: 1px dashed #d7e1ec; color: #475569; }
.ysf-legal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; }
.ysf-legal-box p { margin: 0; color: #334155; }
.ysf-footnote { margin-top: 4px; font-size: 7.4px; color: #475569; }
.ysf-footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 5px; }
.ysf-sign { min-height: 26px; display: flex; flex-direction: column; justify-content: flex-end; text-align: center; }
.ysf-sign-line { border-top: 1px solid #172033; margin-bottom: 3px; }
.ysf-box .tsf-table { width: 100%; border-collapse: collapse; }
.ysf-box .tsf-table th { background: #eff5fb; color: #29496b; font-size: 7.5px; text-transform: uppercase; letter-spacing: .04em; padding: 4px 3px; border-bottom: 1px solid #d5deea; text-align: left; }
.ysf-box .tsf-table td { padding: 3px; border-bottom: 1px solid #e7edf4; vertical-align: top; font-size: 7.9px; }
.ysf-box .tsf-table .is-right { text-align: right; }
.ysf-box .tsf-summary { display: grid; gap: 3px; }
.ysf-box .tsf-summary-row { display: flex; justify-content: space-between; gap: 6px; border-bottom: 1px dashed #d5deea; padding-bottom: 2px; font-size: 8px; }
.ysf-box .tsf-summary-row:last-child { border-bottom: 0; padding-bottom: 0; }
.ysf-box .tsf-summary-row strong { color: #16324f; }
.ysf-box .tsf-muted { color: #64748b; }
.ysf-table-note { margin-top: 4px; font-size: 7.4px; color: #475569; }
.ysf-box .sk-photo-gallery-wrap { margin-top: 3px; }
.ysf-box .sk-photo-gallery-title { font-size: 7.6px; margin-bottom: 2px; color: #51657d; }
.ysf-box .sk-photo-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 3px; }
.ysf-box .sk-photo-item { border: 1px solid #d8e0ea; border-radius: 5px; padding: 2px; background: #fff; }
.ysf-box .sk-photo-item img, .ysf-box .sk-photo-placeholder { height: 34px; border-radius: 3px; }
.ysf-box .sk-photo-placeholder { width: 100%; background: linear-gradient(135deg, #eef2f7 0%, #dbe3ee 100%); display: flex; align-items: center; justify-content: center; color: #475569; font-size: 6.4px; font-weight: 700; }
@media print {
  @page { size: A4; margin: 0; }
  .ysf-panel, .ysf-card, .ysf-box, .ysf-legal-box, .ysf-header { page-break-inside: avoid; break-inside: avoid; }
}
CSS;
    }
}
