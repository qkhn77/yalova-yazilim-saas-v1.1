<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\HizliSatisFavorisi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokBarkodu;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKartiGorseli;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\VergiOrani;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\TenantContextService;
use App\Services\FirmaAyarDeposu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class HizliSatisSayfasi extends BarkodluSatisSayfasi
{
    use WithFileUploads;

    protected static ?string $title = 'Hızlı Satış';

    protected static ?string $slug = 'satis/hizli-satis';

    protected static string $view = 'filament.clusters.muhasebe.pages.hizli-satis-sayfasi';

    public ?int $hizliKategoriId = null;

    public int $hizliKategoriSatirSayisi = 2;

    public string $alinanPara = '';

    public bool $hizliCariSecenekleriYuklendi = false;

    public bool $hizliKalemDuzenlemeAcik = false;

    public ?int $hizliKalemDuzenlemeIndex = null;

    /** @var array{stok_id?:int,ad?:string,stok_miktari?:float|string,satis_fiyati?:float|string,indirimli_fiyat?:float|string,kdv_orani?:float|string} */
    public array $hizliKalemDuzenleme = [];

    public bool $hizliUrunEklemeAcik = false;

    /** @var array{barkod?:string,ad?:string,marka_uretici?:string,kategori_id?:int|string|null,stok_miktari?:float|string,birim?:string,alis_fiyati?:float|string,satis_fiyati?:float|string,kdv_orani?:float|string,kdv_dahil_mi?:bool,gorsel_url?:string,gorsel_data_url?:string,kaynak?:string} */
    public array $hizliUrunEkleme = [];

    public ?TemporaryUploadedFile $hizliUrunGorselDosyasi = null;

    private ?int $hizliSatisAktifFirmaIdCache = null;

    public function getHeading(): string|Htmlable
    {
        return 'Hızlı Satış';
    }

    public function getSubheading(): ?string
    {
        return 'Barkod okut, ürünü seç, ödemeyi tamamla.';
    }

    public function mount(): void
    {
        parent::mount();

        $this->hizliSekmeTercihiniYukle();
    }

    protected function getHeaderActions(): array
    {
        return [$this->parcaSecerekEkleAction()];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Hidden::make('satis_tarihi'),
                Forms\Components\Hidden::make('cari_id'),
                Forms\Components\Hidden::make('odeme_tipi'),
                Forms\Components\Hidden::make('kasa_hesap_id'),
                Forms\Components\Hidden::make('banka_hesap_id'),
                Forms\Components\Hidden::make('pos_hesap_id'),
                Forms\Components\Hidden::make('para_birimi'),
                Forms\Components\Hidden::make('not'),
                Forms\Components\Hidden::make('pesinat_tutari'),
                Forms\Components\Hidden::make('pesinat_odeme_tipi'),
                Forms\Components\Hidden::make('vade_farki_uygula'),
                Forms\Components\Hidden::make('vade_farki_tipi'),
                Forms\Components\Hidden::make('vade_farki_orani'),
                Forms\Components\Hidden::make('vade_farki_tutari'),
                Forms\Components\Hidden::make('vade_tarihi'),
                Forms\Components\Hidden::make('taksit_sayisi'),
                Forms\Components\Hidden::make('taksit_araligi_gun'),
                Forms\Components\Hidden::make('barkod'),
                Forms\Components\Hidden::make('hizli_urun_ara'),
            ]);
    }

    public function kategoriSec(?int $kategoriId = null): void
    {
        $this->hizliKategoriId = $kategoriId && $kategoriId > 0 ? $kategoriId : null;
        $this->hizliSekmeTercihiniKaydet();
    }

    public function favoriSekmesiniSec(): void
    {
        $this->hizliKategoriId = -1;
        $this->hizliSekmeTercihiniKaydet();
    }

    public function hizliCariSecenekleriniYukle(): void
    {
        $this->hizliCariSecenekleriYuklendi = true;
    }

    public function hizliSekmeGeriYukle(string $sekme, int $satirSayisi = 2): void
    {
        $this->hizliKategoriSatirSayisi = max(2, min(12, $satirSayisi));

        if ($sekme === 'favori') {
            $this->hizliKategoriId = -1;
            $this->hizliSekmeTercihiniKaydet();

            return;
        }

        if (str_starts_with($sekme, 'kategori:')) {
            $kategoriId = (int) substr($sekme, 9);
            $kategoriIds = collect($this->hizliKategoriler())->pluck('id')->all();
            $this->hizliKategoriId = in_array($kategoriId, $kategoriIds, true) ? $kategoriId : null;
            $this->hizliSekmeTercihiniKaydet();

            return;
        }

        $this->hizliKategoriId = null;
        $this->hizliSekmeTercihiniKaydet();
    }

    public function kategoriSekmeleriniGenislet(): void
    {
        $this->hizliKategoriSatirSayisi = min(12, $this->hizliKategoriSatirSayisi + 1);
        $this->hizliSekmeTercihiniKaydet();
    }

    public function hizliUrunEkleAc(?string $barkod = null): void
    {
        $this->hizliUrunEkleme = [
            'barkod' => trim((string) ($barkod ?? '')),
            'ad' => '',
            'marka_uretici' => '',
            'kategori_id' => $this->hizliKategoriId && $this->hizliKategoriId > 0 ? $this->hizliKategoriId : null,
            'stok_miktari' => 1,
            'birim' => 'AD',
            'alis_fiyati' => 0,
            'satis_fiyati' => 0,
            'kdv_orani' => 20,
            'kdv_dahil_mi' => false,
            'gorsel_url' => '',
            'gorsel_data_url' => '',
            'kaynak' => '',
        ];
        $this->hizliUrunEklemeAcik = true;
    }

    public function hizliUrunEkleKapat(): void
    {
        $this->hizliUrunEklemeAcik = false;
        $this->hizliUrunEkleme = [];
        $this->hizliUrunGorselDosyasi = null;
    }

    public function hizliUrunGorseliniTemizle(): void
    {
        $this->hizliUrunEkleme['gorsel_url'] = '';
        $this->hizliUrunEkleme['gorsel_data_url'] = '';
        $this->hizliUrunGorselDosyasi = null;
    }

    public function hizliUrunBarkoddanAra(?string $barkod = null): void
    {
        $barkod = $this->hizliBarkodAramaDegeri((string) ($barkod ?? $this->hizliUrunEkleme['barkod'] ?? ''));
        $this->hizliUrunEkleme['barkod'] = $barkod;

        if ($barkod === '') {
            Notification::make()->title('Barkod girin')->warning()->send();

            return;
        }

        $firmaId = $this->aktifFirmaIdForHizliSatis();
        $mevcutStok = $this->hizliBarkoddanStokBul($barkod, $firmaId);
        if ($mevcutStok) {
            $this->stoktanSepeteEkle($mevcutStok, $barkod);
            $this->hizliUrunEkleKapat();
            Notification::make()->title('Ürün zaten kayıtlı, sepete eklendi')->success()->send();

            return;
        }

        $bilgi = $this->openFoodFactsBarkodBilgisi($barkod);
        if ($bilgi === []) {
            Notification::make()->title('İnternette ürün bilgisi bulunamadı')->warning()->send();

            return;
        }

        $this->hizliUrunEkleme = array_merge($this->hizliUrunEkleme, $bilgi);
        Notification::make()->title('Ürün bilgisi dolduruldu')->success()->send();
    }

    /**
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    #[Renderless]
    public function hizliUrunInternetAdaylari(string $barkod): array
    {
        $barkod = $this->hizliBarkodAramaDegeri($barkod);
        if ($barkod === '') {
            return [];
        }

        return $this->hizliUrunBarkodAdaylari($barkod);
    }

    public function hizliUrunEkleKaydet(): void
    {
        if (! $this->islemYetkisiVarMi()) {
            Notification::make()->title('Bu işlem için yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaIdForHizliSatis();
        $barkod = $this->hizliBarkodAramaDegeri((string) ($this->hizliUrunEkleme['barkod'] ?? ''));
        $this->hizliUrunEkleme['barkod'] = $barkod;
        $ad = trim((string) ($this->hizliUrunEkleme['ad'] ?? ''));
        $markaUretici = trim((string) ($this->hizliUrunEkleme['marka_uretici'] ?? ''));
        $kategoriId = $this->hizliKategoriIdDegeri($this->hizliUrunEkleme['kategori_id'] ?? null, $firmaId);

        if ($firmaId < 1 || $ad === '') {
            Notification::make()->title('Ürün adı boş bırakılamaz')->warning()->send();

            return;
        }

        if ($barkod !== '') {
            $mevcutStok = $this->hizliBarkoddanStokBul($barkod, $firmaId);
            if ($mevcutStok) {
                $this->stoktanSepeteEkle($mevcutStok, $barkod);
                $this->hizliUrunEkleKapat();
                Notification::make()->title('Ürün zaten kayıtlı, sepete eklendi')->success()->send();

                return;
            }
        }

        $stokMiktari = max(0, (float) str_replace(',', '.', (string) ($this->hizliUrunEkleme['stok_miktari'] ?? 0)));
        $birim = $this->hizliBirimDegeri((string) ($this->hizliUrunEkleme['birim'] ?? 'AD'));
        $alisFiyati = max(0, (float) str_replace(',', '.', (string) ($this->hizliUrunEkleme['alis_fiyati'] ?? 0)));
        $satisFiyati = max(0, (float) str_replace(',', '.', (string) ($this->hizliUrunEkleme['satis_fiyati'] ?? 0)));
        $kdvOrani = max(0, (float) str_replace(',', '.', (string) ($this->hizliUrunEkleme['kdv_orani'] ?? 0)));
        $kdvDahilMi = filter_var($this->hizliUrunEkleme['kdv_dahil_mi'] ?? false, FILTER_VALIDATE_BOOL);
        $netSatisFiyati = $this->kdvDahilFiyatiNetTutaraCevir($satisFiyati, $kdvOrani, $kdvDahilMi);
        $kod = $this->hizliUrunKoduUret($firmaId, $barkod);

        $stok = StokKarti::query()->create([
            'firma_id' => $firmaId,
            'kod' => $kod,
            'barkod' => $barkod !== '' ? $barkod : null,
            'ad' => $ad,
            'marka_uretici' => $markaUretici !== '' ? mb_substr($markaUretici, 0, 191) : null,
            'kategori_id' => $kategoriId,
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => 0,
            'rezerve_miktar' => 0,
            'para_birimi' => (string) ($this->data['para_birimi'] ?? 'TRY'),
            'birim' => $birim,
            'satis_fiyati' => $netSatisFiyati,
            'alis_fiyati' => $alisFiyati,
            'kdv_orani' => $kdvOrani,
            'depo_id' => $this->hizliVarsayilanDepoId($firmaId),
        ]);

        $this->hizliUrunBaslangicStokHareketiOlustur($stok, $stokMiktari, $alisFiyati);

        if ($barkod !== '') {
            StokBarkodu::query()->updateOrCreate(
                ['firma_id' => $firmaId, 'barkod' => $barkod],
                ['stok_id' => (int) $stok->id, 'varsayilan_mi' => true, 'aktif' => true]
            );
        }

        $gorselKaydedildi = $this->hizliUrunYuklenenGorseliniKaydet($stok)
            || $this->hizliUrunBase64GorseliniKaydet($stok, (string) ($this->hizliUrunEkleme['gorsel_data_url'] ?? ''));

        if (! $gorselKaydedildi) {
            $this->hizliUrunGorseliniKaydet($stok, (string) ($this->hizliUrunEkleme['gorsel_url'] ?? ''));
        }

        $this->stoktanSepeteEkle($stok->fresh() ?: $stok, $barkod);
        $this->hizliSatisUrunCacheTemizle();
        $this->hizliUrunEkleKapat();

        Notification::make()->title('Ürün oluşturuldu ve sepete eklendi')->success()->send();
    }

    public function hizliUrunHizliKaydet(string $barkod, string $ad, float|string $stokMiktari, float|string $satisFiyati, float|string $kdvOrani, string $gorselUrl = '', bool|string|int $kdvDahilMi = false, string $gorselDataUrl = '', string $birim = 'AD', float|string $alisFiyati = 0, string $markaUretici = '', int|string|null $kategoriId = null): void
    {
        $this->hizliUrunEklemeAcik = true;
        $this->hizliUrunEkleme = [
            'barkod' => $barkod,
            'ad' => $ad,
            'marka_uretici' => $markaUretici,
            'kategori_id' => $kategoriId,
            'stok_miktari' => $stokMiktari,
            'birim' => $birim,
            'alis_fiyati' => $alisFiyati,
            'satis_fiyati' => $satisFiyati,
            'kdv_orani' => $kdvOrani,
            'kdv_dahil_mi' => filter_var($kdvDahilMi, FILTER_VALIDATE_BOOL),
            'gorsel_url' => $gorselUrl,
            'gorsel_data_url' => $gorselDataUrl,
            'kaynak' => '',
        ];

        $this->hizliUrunEkleKaydet();
    }

    public function hizliKalemDuzenleAc(int $index): void
    {
        if (! isset($this->kalemler[$index])) {
            return;
        }

        $stokId = (int) ($this->kalemler[$index]['stok_id'] ?? 0);
        $stok = $stokId > 0
            ? StokKarti::query()
                ->select(['id', 'firma_id', 'ad', 'stok_miktari', 'satis_fiyati', 'indirimli_fiyat', 'kdv_orani'])
                ->where('firma_id', $this->aktifFirmaIdForHizliSatis())
                ->whereKey($stokId)
                ->first()
            : null;

        if (! $stok) {
            Notification::make()->title('Ürün kaydı bulunamadı')->warning()->send();

            return;
        }

        $this->hizliKalemDuzenlemeIndex = $index;
        $this->hizliKalemDuzenleme = [
            'stok_id' => (int) $stok->id,
            'ad' => (string) $stok->ad,
            'stok_miktari' => (float) ($stok->stok_miktari ?? 0),
            'satis_fiyati' => (float) ($stok->satis_fiyati ?? 0),
            'indirimli_fiyat' => (float) ($stok->indirimli_fiyat ?? 0),
            'kdv_orani' => (float) ($stok->kdv_orani ?? 0),
        ];
        $this->hizliKalemDuzenlemeAcik = true;
    }

    public function hizliKalemDuzenleKapat(): void
    {
        $this->hizliKalemDuzenlemeAcik = false;
        $this->hizliKalemDuzenlemeIndex = null;
        $this->hizliKalemDuzenleme = [];
    }

    public function hizliKalemDuzenleKaydet(): void
    {
        $stokId = (int) ($this->hizliKalemDuzenleme['stok_id'] ?? 0);
        $ad = trim((string) ($this->hizliKalemDuzenleme['ad'] ?? ''));

        if (! $this->islemYetkisiVarMi()) {
            Notification::make()->title('Bu işlem için yetkiniz yok')->danger()->send();

            return;
        }

        if ($stokId < 1 || $ad === '') {
            Notification::make()->title('Ürün adı boş bırakılamaz')->warning()->send();

            return;
        }

        $stok = StokKarti::query()
            ->where('firma_id', $this->aktifFirmaIdForHizliSatis())
            ->whereKey($stokId)
            ->first();

        if (! $stok) {
            Notification::make()->title('Ürün kaydı bulunamadı')->warning()->send();

            return;
        }

        $satisFiyati = max(0, (float) str_replace(',', '.', (string) ($this->hizliKalemDuzenleme['satis_fiyati'] ?? 0)));
        $indirimliFiyat = max(0, (float) str_replace(',', '.', (string) ($this->hizliKalemDuzenleme['indirimli_fiyat'] ?? 0)));
        $stokMiktari = max(0, (float) str_replace(',', '.', (string) ($this->hizliKalemDuzenleme['stok_miktari'] ?? 0)));
        $kdvOrani = max(0, (float) str_replace(',', '.', (string) ($this->hizliKalemDuzenleme['kdv_orani'] ?? 0)));

        $stok->forceFill([
            'ad' => $ad,
            'stok_miktari' => $stokMiktari,
            'satis_fiyati' => $satisFiyati,
            'indirimli_fiyat' => $indirimliFiyat,
            'kdv_orani' => $kdvOrani,
        ])->save();

        $sepetFiyati = $indirimliFiyat > 0 ? $indirimliFiyat : $satisFiyati;
        foreach ($this->kalemler as $index => $kalem) {
            if ((int) ($kalem['stok_id'] ?? 0) !== $stokId) {
                continue;
            }

            $this->kalemler[$index]['stok_adi'] = $ad;
            $this->kalemler[$index]['stok_miktari'] = $stokMiktari;
            $this->kalemler[$index]['birim_fiyat'] = $sepetFiyati;
            $this->kalemler[$index]['indirimli_fiyat'] = $indirimliFiyat;
            $this->kalemler[$index]['kdv_orani'] = $kdvOrani;
        }

        $this->aktifSepetiKaydet();
        $this->hizliSatisUrunCacheTemizle();
        $this->hizliKalemDuzenleKapat();

        Notification::make()->title('Ürün güncellendi')->success()->send();
    }

    #[Renderless]
    public function hizliKalemHizliGuncelle(int $stokId, string $ad, float|string $stokMiktari, float|string $satisFiyati, float|string $indirimliFiyat, float|string $kdvOrani): void
    {
        $ad = trim($ad);

        if (! $this->islemYetkisiVarMi() || $stokId < 1 || $ad === '') {
            return;
        }

        $stok = StokKarti::query()
            ->where('firma_id', $this->aktifFirmaIdForHizliSatis())
            ->whereKey($stokId)
            ->first();

        if (! $stok) {
            return;
        }

        $satisFiyati = max(0, (float) str_replace(',', '.', (string) $satisFiyati));
        $indirimliFiyat = max(0, (float) str_replace(',', '.', (string) $indirimliFiyat));
        $stokMiktari = max(0, (float) str_replace(',', '.', (string) $stokMiktari));
        $kdvOrani = max(0, (float) str_replace(',', '.', (string) $kdvOrani));

        $stok->forceFill([
            'ad' => $ad,
            'stok_miktari' => $stokMiktari,
            'satis_fiyati' => $satisFiyati,
            'indirimli_fiyat' => $indirimliFiyat,
            'kdv_orani' => $kdvOrani,
        ])->save();

        $sepetFiyati = $indirimliFiyat > 0 ? $indirimliFiyat : $satisFiyati;
        foreach ($this->kalemler as $index => $kalem) {
            if ((int) ($kalem['stok_id'] ?? 0) !== $stokId) {
                continue;
            }

            $this->kalemler[$index]['stok_adi'] = $ad;
            $this->kalemler[$index]['stok_miktari'] = $stokMiktari;
            $this->kalemler[$index]['birim_fiyat'] = $sepetFiyati;
            $this->kalemler[$index]['indirimli_fiyat'] = $indirimliFiyat;
            $this->kalemler[$index]['kdv_orani'] = $kdvOrani;
        }

        $this->aktifSepetiKaydet();
        $this->hizliSatisUrunCacheTemizle();
    }

    #[Renderless]
    public function hizliSepetBeklet(): void
    {
        $this->sepetBeklet(bildirimGonder: false);
    }

    #[Renderless]
    public function hizliSepetiTemizle(): void
    {
        $this->sepetiTemizle(odakla: false);
    }

    #[Renderless]
    public function hizliBekleyenSepetiSil(int $index): void
    {
        $this->bekleyenSepetiSil($index);
    }

    #[Renderless]
    public function hizliKalemSil(int $index): void
    {
        $this->kalemSil($index);
    }

    /**
     * @return array<int, array{id:int,ad:string}>
     */
    public function hizliKategoriler(): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();

        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            $this->hizliSatisCacheKey('kategoriler'),
            now()->addMinutes(5),
            fn (): array => StokKategorisi::query()
                ->select(['id', 'ad'])
                ->where(function ($query) use ($firmaId): void {
                    $query->where('firma_id', $firmaId)
                        ->orWhere('tanim_firma_kapsami', $firmaId);
                })
                ->where('aktif_mi', true)
                ->orderBy('ad')
                ->get()
                ->map(fn (StokKategorisi $kategori): array => [
                    'id' => (int) $kategori->id,
                    'ad' => (string) $kategori->ad,
                ])
                ->all()
        );
    }

    /**
     * @return array<int, array{oran:float,etiket:string}>
     */
    public function hizliVergiOraniSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();

        if ($firmaId < 1) {
            return [
                ['oran' => 0.0, 'etiket' => '%0'],
                ['oran' => 20.0, 'etiket' => '%20'],
            ];
        }

        $secenekler = Cache::remember(
            $this->hizliSatisCacheKey('vergi-oranlari'),
            now()->addMinutes(10),
            fn (): array => VergiOrani::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('oran')
                ->get(['kod', 'ad', 'oran'])
                ->map(fn (VergiOrani $oran): array => [
                    'oran' => (float) $oran->oran,
                    'etiket' => trim((string) ($oran->kod ?: $oran->ad)) !== ''
                        ? trim((string) ($oran->kod ?: $oran->ad)).' - %'.number_format((float) $oran->oran, 2, ',', '.')
                        : '%'.number_format((float) $oran->oran, 2, ',', '.'),
                ])
                ->unique('oran')
                ->values()
                ->all()
        );

        if ($secenekler === []) {
            return [
                ['oran' => 0.0, 'etiket' => '%0'],
                ['oran' => 20.0, 'etiket' => '%20'],
            ];
        }

        $oranlar = collect($secenekler)->pluck('oran')->map(fn ($oran): float => (float) $oran)->all();
        if (! in_array(20.0, $oranlar, true)) {
            $secenekler[] = ['oran' => 20.0, 'etiket' => '%20'];
        }

        usort($secenekler, fn (array $a, array $b): int => ((float) $a['oran']) <=> ((float) $b['oran']));

        return $secenekler;
    }

    public function hizliYerelBarkodAdaylari(): array
    {
        return $this->yerelBarkodKatalogu();
    }

    public function hizliBirimSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();
        if ($firmaId < 1) {
            return [['kod' => 'AD', 'etiket' => 'AD']];
        }

        $secenekler = Cache::remember(
            $this->hizliSatisCacheKey('birim-secenekleri'),
            now()->addMinutes(10),
            fn (): array => Birim::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('kod')
                ->get(['kod', 'ad'])
                ->map(fn (Birim $birim): array => [
                    'kod' => (string) $birim->kod,
                    'etiket' => trim((string) $birim->ad) !== ''
                        ? (string) $birim->kod.' - '.(string) $birim->ad
                        : (string) $birim->kod,
                ])
                ->filter(fn (array $birim): bool => trim((string) ($birim['kod'] ?? '')) !== '')
                ->unique('kod')
                ->values()
                ->all()
        );

        if (! collect($secenekler)->contains(fn (array $birim): bool => strtoupper((string) ($birim['kod'] ?? '')) === 'AD')) {
            array_unshift($secenekler, ['kod' => 'AD', 'etiket' => 'AD']);
        }

        return $secenekler;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hizliSatisUrunleri(?int $kategoriId = null, bool $gecerliSekmeyiKullan = true): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();

        if ($firmaId < 1) {
            return [];
        }

        $favoriStokIds = $this->hizliFavoriStokIds();
        $kategoriId = $gecerliSekmeyiKullan ? $this->hizliKategoriId : $kategoriId;

        $urunler = Cache::remember(
            $this->hizliSatisCacheKey('urunler', [
                'kategori' => (string) ($kategoriId ?? 'genel'),
                'stok_turleri' => implode(',', $this->barkodluSatisGorunenStokTurleri()),
            ]),
            now()->addSeconds(45),
            fn (): array => StokKarti::query()
                ->select([
                    'id',
                    'firma_id',
                    'kategori_id',
                    'ad',
                    'kod',
                    'barkod',
                    'stok_miktari',
                    'birim',
                    'indirimli_fiyat',
                    'satis_fiyati',
                    'kdv_orani',
                    'satis_adedi',
                ])
                ->where('firma_id', $firmaId)
                ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
                ->when($kategoriId === -1, fn ($query) => $query->whereIn('id', $favoriStokIds))
                ->when($kategoriId !== null && $kategoriId > 0, fn ($query) => $query->where('kategori_id', $kategoriId))
                ->with(['gorseller' => fn ($query) => $query
                    ->select(['id', 'stok_karti_id', 'dosya_yolu', 'sira', 'kapak_mi', 'aktif_mi'])
                    ->where('aktif_mi', true)])
                ->orderByDesc('satis_adedi')
                ->orderBy('ad')
                ->limit(16)
                ->get()
                ->map(fn (StokKarti $stok): array => [
                    'id' => (int) $stok->id,
                    'ad' => (string) $stok->ad,
                    'kod' => (string) ($stok->kod ?? ''),
                    'barkod' => (string) ($stok->barkod ?? ''),
                    'stok' => (float) ($stok->stok_miktari ?? 0),
                    'birim' => (string) ($stok->birim ?: 'AD'),
                    'fiyat' => (float) ($stok->indirimli_fiyat ?: $stok->satis_fiyati ?: 0),
                    'indirimli_fiyat' => (float) ($stok->indirimli_fiyat ?? 0),
                    'kdv_orani' => max(0, (float) ($stok->kdv_orani ?? 0)),
                    'gorsel_url' => (string) ($stok->kapak_gorsel_url ?? ''),
                ])
                ->all()
        );

        return array_map(
            static fn (array $urun): array => $urun + [
                'favori_mi' => in_array((int) ($urun['id'] ?? 0), $favoriStokIds, true),
            ],
            $urunler
        );
    }

    public function hizliUrunKartindanEkle(int $stokId): void
    {
        if (! $this->islemYetkisiVarMi() || $stokId < 1) {
            return;
        }

        $urun = collect($this->hizliSatisUrunleri())
            ->first(fn (array $urun): bool => (int) ($urun['id'] ?? 0) === $stokId);

        if (! is_array($urun)) {
            $this->hizliAdaydanEkle($stokId);

            return;
        }

        $this->stokDizisindenSepeteEkle($urun, (string) (($urun['barkod'] ?? '') ?: ($urun['kod'] ?? '')));
        $this->data['hizli_urun_ara'] = null;
        $this->hizliUrunAdaylari = [];
        $this->dispatch('barkod-odakla');
    }

    /**
     * @param  array<int|string, int|string>  $stokIds
     */
    public function hizliUrunKartlariniTopluEkle(array $stokIds): void
    {
        if (! $this->islemYetkisiVarMi()) {
            return;
        }

        $stokIds = array_values(array_filter(array_map(
            static fn (mixed $stokId): int => (int) $stokId,
            $stokIds
        ), static fn (int $stokId): bool => $stokId > 0));

        if ($stokIds === []) {
            return;
        }

        $stokIds = array_slice($stokIds, 0, 50);
        $urunler = collect($this->hizliSatisUrunleri())
            ->keyBy(static fn (array $urun): int => (int) ($urun['id'] ?? 0));

        foreach ($stokIds as $stokId) {
            $urun = $urunler->get($stokId);
            if (! is_array($urun)) {
                continue;
            }

            $this->stokDizisindenSepeteEkleKaydetmeden($urun, (string) (($urun['barkod'] ?? '') ?: ($urun['kod'] ?? '')));
        }

        $this->data['hizli_urun_ara'] = null;
        $this->hizliUrunAdaylari = [];
        $this->aktifSepetiKaydet();
        $this->dispatch('barkod-odakla');
    }

    public function hizliFavoriDegistir(int $stokId, bool $bildirimGonder = true): void
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();
        $kullaniciId = (int) auth()->id();

        if ($firmaId < 1 || $kullaniciId < 1 || $stokId < 1) {
            return;
        }

        $stokVarMi = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereKey($stokId)
            ->exists();

        if (! $stokVarMi) {
            return;
        }

        $favori = HizliSatisFavorisi::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->where('stok_karti_id', $stokId)
            ->first();

        if ($favori) {
            $favori->delete();
            $this->hizliSatisFavoriCacheTemizle();
            if ($bildirimGonder) {
                Notification::make()->title('Favoriden kaldirildi')->success()->send();
            }

            return;
        }

        HizliSatisFavorisi::query()->create([
            'firma_id' => $firmaId,
            'kullanici_id' => $kullaniciId,
            'stok_karti_id' => $stokId,
        ]);

        $this->hizliSatisFavoriCacheTemizle();
        if ($bildirimGonder) {
            Notification::make()->title('Favorilere eklendi')->success()->send();
        }
    }

    /**
     * @return array<int|string, string>
     */
    public function hizliCariSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();

        if ($firmaId < 1) {
            return [];
        }

        if (! $this->hizliCariSecenekleriYuklendi) {
            $seciliCariId = (int) ($this->data['cari_id'] ?? 0);

            if ($seciliCariId < 1) {
                return [];
            }

            return Cari::query()
                ->select(['id', 'ad', 'kod'])
                ->where('firma_id', $firmaId)
                ->whereKey($seciliCariId)
                ->get()
                ->mapWithKeys(fn (Cari $cari): array => [
                    $cari->id => trim($cari->ad.($cari->kod ? ' - '.$cari->kod : '')),
                ])
                ->all();
        }

        return Cache::remember(
            $this->hizliSatisCacheKey('cariler'),
            now()->addMinutes(3),
            fn (): array => Cari::query()
                ->select(['id', 'ad', 'kod'])
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->limit(300)
                ->get()
                ->mapWithKeys(fn (Cari $cari): array => [
                    $cari->id => trim($cari->ad.($cari->kod ? ' - '.$cari->kod : '')),
                ])
                ->all()
        );
    }

    /**
     * @return array<int|string, string>
     */
    public function hizliKasaSecenekleri(): array
    {
        return $this->hizliHesapSecenekleri(KasaHesabi::class);
    }

    /**
     * @return array<int|string, string>
     */
    public function hizliBankaSecenekleri(): array
    {
        return $this->hizliHesapSecenekleri(BankaHesabi::class);
    }

    /**
     * @return array<int|string, string>
     */
    public function hizliPosSecenekleri(): array
    {
        return $this->hizliHesapSecenekleri(PosHesabi::class);
    }

    public function paraUstuTutari(): float
    {
        $alinan = (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $this->alinanPara) ?? '');
        $toplam = (float) ($this->sepetOzeti()['genel_toplam'] ?? 0);

        return max(0, $alinan - $toplam);
    }

    public function alinanParayaKupurEkle(int $tutar): void
    {
        if (! in_array($tutar, [5, 10, 20, 50, 100, 200], true)) {
            return;
        }

        $mevcut = (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $this->alinanPara) ?? '');
        $this->alinanPara = number_format(max(0, $mevcut + $tutar), 2, ',', '.');
    }

    /**
     * @param  class-string<KasaHesabi|BankaHesabi|PosHesabi>  $model
     * @return array<int|string, string>
     */
    private function hizliHesapSecenekleri(string $model): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();

        if ($firmaId < 1) {
            return [];
        }

        $paraBirimi = strtoupper((string) ($this->data['para_birimi'] ?? 'TRY'));
        $hesapTuru = str_replace('\\', '_', $model);

        return Cache::remember(
            $this->hizliSatisCacheKey('hesaplar', [
                'tur' => $hesapTuru,
                'para_birimi' => $paraBirimi,
            ]),
            now()->addMinutes(3),
            fn (): array => $model::query()
                ->select(['id', 'ad', 'para_birimi'])
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', $paraBirimi)
                ->orderBy('ad')
                ->get()
                ->mapWithKeys(fn ($hesap): array => [
                    $hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?? 'TRY')).')',
                ])
                ->all()
        );
    }

    private function hizliBarkoddanStokBul(string $barkod, int $firmaId): ?StokKarti
    {
        if ($firmaId < 1 || $barkod === '') {
            return null;
        }

        $stok = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->where('barkod', $barkod)
            ->first();

        if ($stok) {
            return $stok;
        }

        $stokId = StokBarkodu::query()
            ->where('firma_id', $firmaId)
            ->where('barkod', $barkod)
            ->where('aktif', true)
            ->value('stok_id');

        return $stokId
            ? StokKarti::query()
                ->where('firma_id', $firmaId)
                ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
                ->whereKey((int) $stokId)
                ->first()
            : null;
    }

    private function hizliBarkodAramaDegeri(string $barkod): string
    {
        $barkod = trim($barkod);
        $sayisal = preg_replace('/\D+/', '', $barkod) ?: '';

        return strlen($sayisal) >= 8 ? $sayisal : $barkod;
    }

    private function hizliBirimDegeri(string $birim): string
    {
        $birim = Str::upper(trim($birim));

        return $birim !== '' ? mb_substr($birim, 0, 32) : 'AD';
    }

    private function hizliVarsayilanDepoId(int $firmaId): ?int
    {
        $ayar = app(FirmaAyarDeposu::class);
        if (! (bool) $ayar->oku($firmaId, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $ayarDepoId = (int) ($ayar->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0);
        $adaylar = [$ayarDepoId];
        foreach ($adaylar as $depoId) {
            if ($depoId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
                ->where('firma_id', $firmaId)
                ->whereKey($depoId)
                ->where('aktif_mi', true)
                ->exists())) {
                return $depoId;
            }
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    private function hizliUrunBaslangicStokHareketiOlustur(StokKarti $stok, float $stokMiktari, float $alisFiyati): void
    {
        if ($stokMiktari <= 0) {
            return;
        }

        app(StokHareketServisi::class)->kayitOlustur((int) $stok->firma_id, [
            'stok_id' => (int) $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => number_format($stokMiktari, 4, '.', ''),
            'birim_fiyat' => number_format(max(0, $alisFiyati), 2, '.', ''),
            'birim_maliyet' => number_format(max(0, $alisFiyati), 2, '.', ''),
            'belge_turu' => StokBelgeTuru::Duzeltme,
            'belge_id' => (int) $stok->id,
            'referans_tipi' => StokBelgeTuru::Duzeltme->value,
            'referans_id' => (int) $stok->id,
            'aciklama' => 'Hızlı ürün ekleme başlangıç stoğu',
            'tarih' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stok
     */
    private function stokDizisindenSepeteEkleKaydetmeden(array $stok, string $girilenBarkod = ''): void
    {
        $stokId = (int) ($stok['id'] ?? 0);
        if ($stokId < 1) {
            return;
        }

        $index = collect($this->kalemler)->search(fn (array $kalem): bool => (int) ($kalem['stok_id'] ?? 0) === $stokId);
        if ($index !== false) {
            $this->kalemler[$index]['miktar'] = max(0, (float) ($this->kalemler[$index]['miktar'] ?? 0)) + 1;
            $this->seciliKalemIndex = (int) $index;

            return;
        }

        $this->kalemler[] = [
            'stok_id' => $stokId,
            'stok_kod' => (string) ($stok['kod'] ?? ''),
            'barkod' => $girilenBarkod !== '' ? $girilenBarkod : (string) (($stok['barkod'] ?? '') ?: ($stok['kod'] ?? '')),
            'stok_adi' => (string) ($stok['ad'] ?? ''),
            'stok_miktari' => max(0, (float) ($stok['stok'] ?? $stok['stok_miktari'] ?? 0)),
            'gorsel_url' => (string) ($stok['gorsel_url'] ?? ''),
            'birim' => (string) (($stok['birim'] ?? '') ?: 'AD'),
            'miktar' => 1.0,
            'birim_fiyat' => max(0, (float) ($stok['fiyat'] ?? 0)),
            'indirimli_fiyat' => max(0, (float) ($stok['indirimli_fiyat'] ?? 0)),
            'iskonto_tutari' => 0.0,
            'kdv_orani' => max(0, (float) ($stok['kdv_orani'] ?? 0)),
        ];
        $this->seciliKalemIndex = count($this->kalemler) - 1;
    }

    private function hizliKategoriIdDegeri(mixed $kategoriId, int $firmaId): ?int
    {
        $kategoriId = (int) $kategoriId;
        if ($firmaId < 1 || $kategoriId < 1) {
            return null;
        }

        return StokKategorisi::query()
            ->whereKey($kategoriId)
            ->where(function ($query) use ($firmaId): void {
                $query->where('firma_id', $firmaId)
                    ->orWhere('tanim_firma_kapsami', $firmaId);
            })
            ->where('aktif_mi', true)
            ->exists()
                ? $kategoriId
                : null;
    }

    /**
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function hizliUrunBarkodAdaylari(string $barkod): array
    {
        $adaylar = [];

        foreach (($this->yerelBarkodKatalogu()[$barkod] ?? []) as $aday) {
            if (is_array($aday)) {
                $adaylar[] = $this->hizliUrunAdayiniNormalizeEt($aday, $barkod);
            }
        }

        $adaylar = array_merge($adaylar, Cache::remember(
            'hizli_satis:barkod_adaylari:harici:v2:'.md5($barkod),
            now()->addHours(12),
            function () use ($barkod): array {
                $sonuclar = [];

                foreach ([
                    ['https://world.openfoodfacts.org', 'Open Food Facts'],
                    ['https://world.openproductsfacts.org', 'Open Products Facts'],
                ] as [$baseUrl, $kaynak]) {
                    $sonuclar = array_merge($sonuclar, $this->hariciBarkodAdaylari($baseUrl, $kaynak, $barkod));
                }

                foreach ([
                    ['https://nodar.com.tr', 'NODAR tedarikçi kataloğu'],
                    ['https://tesoro.com.tr', 'TESORO tedarikçi kataloğu'],
                ] as [$baseUrl, $kaynak]) {
                    $sonuclar = array_merge($sonuclar, $this->tedarikciBarkodAdaylari($baseUrl, $kaynak, $barkod));
                }

                return $sonuclar;
            }
        ));

        return $this->hizliUrunAdaylariniTekillestir($adaylar);
    }

    /**
     * @return array{ad?:string,gorsel_url?:string,kaynak?:string}
     */
    private function openFoodFactsBarkodBilgisi(string $barkod): array
    {
        $aday = $this->hizliUrunBarkodAdaylari($barkod)[0] ?? null;
        if (! is_array($aday)) {
            return [];
        }

        return array_filter([
            'ad' => (string) ($aday['name'] ?? ''),
            'marka_uretici' => (string) ($aday['brand'] ?? ''),
            'gorsel_url' => (string) ($aday['image'] ?? ''),
            'kaynak' => (string) ($aday['source'] ?? ''),
        ], static fn ($value): bool => $value !== '');
    }

    /**
     * @return array{ad?:string,gorsel_url?:string,kaynak?:string}
     */
    private function hariciBarkodBilgisi(string $baseUrl, string $kaynak, string $barkod): array
    {
        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get(rtrim($baseUrl, '/').'/api/v2/product/'.rawurlencode($barkod).'.json', [
                    'fields' => 'product_name,product_name_tr,generic_name,brands,image_front_url,image_url',
                ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $json = $response->json();
        if ((int) ($json['status'] ?? 0) !== 1 || ! is_array($json['product'] ?? null)) {
            return [];
        }

        $product = $json['product'];
        $ad = trim((string) (
            $product['product_name_tr']
            ?? $product['product_name']
            ?? $product['generic_name']
            ?? ''
        ));
        $marka = trim((string) ($product['brands'] ?? ''));
        if ($ad !== '' && $marka !== '' && ! str_contains(mb_strtolower($ad), mb_strtolower($marka))) {
            $ad = $marka.' '.$ad;
        }

        $gorsel = trim((string) ($product['image_front_url'] ?? $product['image_url'] ?? ''));

        return array_filter([
            'ad' => $ad,
            'marka_uretici' => $marka,
            'gorsel_url' => $gorsel,
            'kaynak' => $kaynak,
        ], static fn ($value): bool => $value !== '');
    }

    /**
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function hariciBarkodAdaylari(string $baseUrl, string $kaynak, string $barkod): array
    {
        $fields = 'code,product_name,product_name_tr,generic_name,brands,image_front_url,image_url';
        $adaylar = [];

        foreach ([
            rtrim($baseUrl, '/').'/api/v2/product/'.rawurlencode($barkod).'.json',
            rtrim($baseUrl, '/').'/cgi/search.pl',
        ] as $index => $url) {
            try {
                $query = $index === 0
                    ? ['fields' => $fields]
                    : [
                        'search_terms' => $barkod,
                        'search_simple' => 1,
                        'action' => 'process',
                        'json' => 1,
                        'page_size' => 8,
                        'fields' => $fields,
                    ];

                $response = Http::timeout(4)->acceptJson()->get($url, $query);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->ok()) {
                continue;
            }

            $json = $response->json();
            if ($index === 0 && (int) ($json['status'] ?? 0) === 1 && is_array($json['product'] ?? null)) {
                $adaylar[] = $this->hariciUrunJsonAdayi($json['product'], $kaynak, $barkod);
            }

            if ($index === 1 && is_array($json['products'] ?? null)) {
                foreach ($json['products'] as $product) {
                    if (is_array($product)) {
                        $adaylar[] = $this->hariciUrunJsonAdayi($product, $kaynak, $barkod);
                    }
                }
            }
        }

        return $this->hizliUrunAdaylariniTekillestir($adaylar);
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{name:string,brand:string,image:string,barcode:string,source:string}
     */
    private function hariciUrunJsonAdayi(array $product, string $kaynak, string $barkod): array
    {
        $ad = trim((string) (
            $product['product_name_tr']
            ?? $product['product_name']
            ?? $product['generic_name']
            ?? ''
        ));
        $marka = trim((string) ($product['brands'] ?? ''));
        if ($ad !== '' && $marka !== '' && ! str_contains(mb_strtolower($ad), mb_strtolower($marka))) {
            $ad = $marka.' '.$ad;
        }

        return $this->hizliUrunAdayiniNormalizeEt([
            'name' => $ad,
            'brand' => $marka,
            'barcode' => (string) ($product['code'] ?? $barkod),
            'image' => (string) ($product['image_front_url'] ?? $product['image_url'] ?? ''),
            'source' => $kaynak,
        ], $barkod);
    }

    /**
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function tedarikciBarkodAdaylari(string $baseUrl, string $kaynak, string $barkod): array
    {
        try {
            $aramaUrl = str_contains(mb_strtolower($kaynak), 'nodar')
                ? rtrim($baseUrl, '/').'/arama/'
                : rtrim($baseUrl, '/').'/';
            $aramaParametreleri = str_contains(mb_strtolower($kaynak), 'nodar')
                ? ['src' => $barkod]
                : ['s' => $barkod];

            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'YalovaKameraHizliSatis/1.0'])
                ->get($aramaUrl, $aramaParametreleri);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $html = (string) $response->body();
        $adaylar = $this->tedarikciHtmlindenBarkodAdaylari($html, rtrim($baseUrl, '/'), $kaynak, $barkod);

        if ($adaylar === [] && str_contains(mb_strtolower($kaynak), 'nodar')) {
            $adaylar = $this->nodarDetayBarkodAdayi($html, rtrim($baseUrl, '/'), $kaynak, $barkod);
        }

        return $adaylar;
    }

    /**
     * NODAR barkod araması doğrudan ürün detay sayfasına yönlenebilir.
     * Bu sayfada ürün kartı yerine Schema.org/Product alanları kullanılır.
     *
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function nodarDetayBarkodAdayi(string $html, string $baseUrl, string $kaynak, string $barkod): array
    {
        $onceki = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        $xpath = new \DOMXPath($dom);
        $ad = $this->xpathMetni($xpath, '//*[@itemprop="name"]//h1[1] | //h1[@itemprop="name"][1]', $dom);
        if ($ad === '') {
            return [];
        }

        $marka = $this->xpathMetni($xpath, '//a[contains(@href, "/markalar/")][1]', $dom);
        $gorsel = $this->mutlakUrl($this->xpathAttr($xpath, '//*[@itemprop="image"][@src][1]', 'src', $dom), $baseUrl);

        return [$this->hizliUrunAdayiniNormalizeEt([
            'name' => $ad,
            'brand' => $marka,
            'barcode' => $barkod,
            'image' => $gorsel,
            'source' => $kaynak,
        ], $barkod)];
    }

    /**
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function tedarikciHtmlindenBarkodAdaylari(string $html, string $baseUrl, string $kaynak, string $barkod): array
    {
        if ($html === '' || ! str_contains($html, $barkod)) {
            return [];
        }

        $onceki = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        $xpath = new \DOMXPath($dom);
        $blocks = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " productItemBlock ")]');
        if (! $blocks instanceof \DOMNodeList) {
            return [];
        }

        $adaylar = [];
        foreach ($blocks as $block) {
            if (! str_contains($block->textContent ?? '', $barkod)) {
                continue;
            }

            $ad = $this->xpathMetni($xpath, './/*[@itemprop="name"]', $block)
                ?: $this->xpathAttr($xpath, './/a[@title][1]', 'title', $block)
                ?: $this->xpathAttr($xpath, './/img[@alt][1]', 'alt', $block);
            $marka = $this->xpathMetni($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " productItemBrand ")][1]', $block);
            $gorsel = $this->mutlakUrl($this->xpathAttr($xpath, './/img[@src][1]', 'src', $block), $baseUrl);

            $adaylar[] = $this->hizliUrunAdayiniNormalizeEt([
                'name' => $ad,
                'brand' => $marka,
                'barcode' => $barkod,
                'image' => $gorsel,
                'source' => $kaynak,
            ], $barkod);
        }

        return $this->hizliUrunAdaylariniTekillestir($adaylar);
    }

    private function xpathMetni(\DOMXPath $xpath, string $query, \DOMNode $context): string
    {
        $node = $xpath->query($query, $context)?->item(0);

        return $node ? trim(html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    private function xpathAttr(\DOMXPath $xpath, string $query, string $attr, \DOMNode $context): string
    {
        $node = $xpath->query($query, $context)?->item(0);
        if (! $node instanceof \DOMElement) {
            return '';
        }

        return trim(html_entity_decode($node->getAttribute($attr), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function mutlakUrl(string $url, string $baseUrl): string
    {
        if ($url === '' || Str::startsWith($url, ['http://', 'https://', 'data:'])) {
            return $url;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($url, '/');
    }

    /**
     * @param  array<string, mixed>  $aday
     * @return array{name:string,brand:string,image:string,barcode:string,source:string}
     */
    private function hizliUrunAdayiniNormalizeEt(array $aday, string $barkod): array
    {
        return [
            'name' => trim((string) ($aday['name'] ?? $aday['ad'] ?? '')),
            'brand' => trim((string) ($aday['brand'] ?? $aday['marka_uretici'] ?? '')),
            'image' => trim((string) ($aday['image'] ?? $aday['gorsel_url'] ?? '')),
            'barcode' => trim((string) ($aday['barcode'] ?? $barkod)),
            'source' => trim((string) ($aday['source'] ?? $aday['kaynak'] ?? '')),
        ];
    }

    /**
     * @param  array<int, array{name?:string,brand?:string,image?:string,barcode?:string,source?:string}>  $adaylar
     * @return array<int, array{name:string,brand:string,image:string,barcode:string,source:string}>
     */
    private function hizliUrunAdaylariniTekillestir(array $adaylar): array
    {
        $sonuclar = [];
        $gorulen = [];

        foreach ($adaylar as $aday) {
            $aday = $this->hizliUrunAdayiniNormalizeEt($aday, (string) ($aday['barcode'] ?? ''));
            if ($aday['name'] === '' && $aday['image'] === '') {
                continue;
            }

            $anahtar = mb_strtolower($aday['barcode'].'|'.$aday['name'].'|'.$aday['image']);
            if (isset($gorulen[$anahtar])) {
                continue;
            }

            $gorulen[$anahtar] = true;
            $sonuclar[] = $aday;
            if (count($sonuclar) >= 8) {
                break;
            }
        }

        return $sonuclar;
    }

    /**
     * @return array{ad?:string,gorsel_url?:string,kaynak?:string}
     */
    private function yerelBarkodBilgisi(string $barkod): array
    {
        $adaylar = $this->yerelBarkodKatalogu()[$barkod] ?? [];
        $aday = is_array($adaylar) ? ($adaylar[0] ?? []) : [];

        if (! is_array($aday)) {
            return [];
        }

        return array_filter([
            'ad' => (string) ($aday['name'] ?? ''),
            'marka_uretici' => (string) ($aday['brand'] ?? ''),
            'gorsel_url' => (string) ($aday['image'] ?? ''),
            'kaynak' => (string) ($aday['source'] ?? ''),
        ], static fn ($value): bool => $value !== '');
    }

    private function yerelBarkodKatalogu(): array
    {
        return [
            '8684886010074' => [[
                'name' => 'Nodar ND1007 Type-C To Type-C Super Fast Şarj Seti 45W Siyah',
                'brand' => 'Nodar',
                'barcode' => '8684886010074',
                'image' => 'https://i0fz9hj7wjpz.merlincdn.net/Resim/Minik/1500x1500_thumb_nd1007.jpg?v=2',
                'source' => 'Nodar üretici kataloğu',
            ]],
            '8684886010104' => [[
                'name' => 'NODAR ND1010 Type-C QC 3.0 Hızlı Şarj Seti 18W 3.4A USB Adaptörlü 1 m PVC Kablolu Beyaz',
                'brand' => 'NODAR',
                'barcode' => '8684886010104',
                'image' => 'https://nodar.com.tr/Resim/Minik/1000x1000_thumb_nd1010.jpg?v=1',
                'source' => 'Nodar üretici kataloğu',
            ]],
            '8680469000555' => [[
                'name' => 'Hadron HD702/50 Samsung 19V 4.74A 5.5x3.0 Notebook Adaptör',
                'brand' => 'Hadron',
                'barcode' => '8680469000555',
                'image' => 'https://cdn.dsmcdn.com/mnresize/420/620/ty1614/prod/QC/20241221/04/e7f6c475-03d2-3340-964d-cc3f0228f97f/1_org.jpg',
                'source' => 'Doğrulanmış barkod kataloğu',
            ]],
        ];
    }

    private function hizliUrunKoduUret(int $firmaId, string $barkod): string
    {
        $taban = $barkod !== '' ? 'BRK-'.$barkod : 'HIZLI-'.now()->format('YmdHis');
        $taban = Str::upper(Str::slug($taban, '-')) ?: 'HIZLI-URUN';
        $kod = mb_substr($taban, 0, 48);
        $i = 1;

        while (StokKarti::query()->where('firma_id', $firmaId)->where('kod', $kod)->exists()) {
            $ek = '-'.$i++;
            $kod = mb_substr($taban, 0, 48 - mb_strlen($ek)).$ek;
        }

        return $kod;
    }

    private function hizliUrunGorseliniKaydet(StokKarti $stok, string $url): void
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return;
        }

        try {
            $response = Http::timeout(5)->get($url);
        } catch (\Throwable) {
            return;
        }

        $bytes = $response->body();

        if (! $response->ok() || $bytes === '' || strlen($bytes) > 4 * 1024 * 1024) {
            return;
        }

        $mime = $this->gorselMimeTipi($bytes);

        if ($mime === null) {
            return;
        }

        $uzanti = $this->gorselUzantisi($mime);
        $path = 'stok-kartlari/'.((int) $stok->id).'/kapak-'.Str::random(10).'.'.$uzanti;

        try {
            Storage::disk('public')->put($path, $bytes);
            $this->stokGorselKaydiOlustur($stok, $path);
        } catch (\Throwable) {
            return;
        }
    }

    private function hizliUrunYuklenenGorseliniKaydet(StokKarti $stok): bool
    {
        $yukleme = $this->hizliUrunGorselDosyasi;

        if (! $yukleme instanceof TemporaryUploadedFile) {
            return false;
        }

        try {
            $path = $yukleme->store('stok/gallery', 'public');
        } catch (\Throwable) {
            return false;
        }

        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        $this->stokGorselKaydiOlustur($stok, $path);

        return true;
    }

    private function hizliUrunBase64GorseliniKaydet(StokKarti $stok, string $dataUrl): bool
    {
        $dataUrl = trim($dataUrl);

        if ($dataUrl === '' || ! preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            return false;
        }

        $bytes = base64_decode($matches[2], true);

        if ($bytes === false || strlen($bytes) < 16 || strlen($bytes) > 4 * 1024 * 1024) {
            return false;
        }

        $mime = $this->gorselMimeTipi($bytes);

        if ($mime === null) {
            return false;
        }

        $extension = $this->gorselUzantisi($mime);
        $path = 'stok/gallery/hizli-urun-'.Str::uuid().'.'.$extension;

        try {
            Storage::disk('public')->put($path, $bytes);
        } catch (\Throwable) {
            return false;
        }

        $this->stokGorselKaydiOlustur($stok, $path);

        return true;
    }

    private function gorselMimeTipi(string $bytes): ?string
    {
        $imageInfo = @getimagesizefromstring($bytes);
        $mime = is_array($imageInfo) ? strtolower((string) ($imageInfo['mime'] ?? '')) : '';

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : null;
    }

    private function gorselUzantisi(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    private function stokGorselKaydiOlustur(StokKarti $stok, string $path): void
    {
        StokKartiGorseli::query()->create([
            'stok_karti_id' => (int) $stok->id,
            'dosya_yolu' => $path,
            'alt_metin' => (string) $stok->ad,
            'sira' => 0,
            'kapak_mi' => true,
            'aktif_mi' => true,
        ]);
    }

    private function kdvDahilFiyatiNetTutaraCevir(float $fiyat, float $kdvOrani, bool $kdvDahilMi): float
    {
        if (! $kdvDahilMi || $fiyat <= 0 || $kdvOrani <= 0) {
            return round($fiyat, 4);
        }

        return round($fiyat / (1 + ($kdvOrani / 100)), 4);
    }

    private function aktifFirmaIdForHizliSatis(): int
    {
        return $this->hizliSatisAktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    /**
     * @return array<int, int>
     */
    private function hizliFavoriStokIds(): array
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();
        $kullaniciId = (int) auth()->id();

        if ($firmaId < 1 || $kullaniciId < 1) {
            return [];
        }

        return Cache::remember(
            $this->hizliSatisCacheKey('favoriler'),
            now()->addMinutes(5),
            fn (): array => HizliSatisFavorisi::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', $kullaniciId)
                ->pluck('stok_karti_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }

    /**
     * @param  array<string, string>  $parcalar
     */
    private function hizliSatisCacheKey(string $tur, array $parcalar = []): string
    {
        $firmaId = $this->aktifFirmaIdForHizliSatis();
        $kullaniciId = (int) auth()->id();
        $ek = $parcalar === [] ? '' : ':'.md5(json_encode($parcalar, JSON_UNESCAPED_UNICODE) ?: '');

        return 'hizli_satis:firma:'.$firmaId.':kullanici:'.$kullaniciId.':'.$tur.$ek;
    }

    private function hizliSatisFavoriCacheTemizle(): void
    {
        Cache::forget($this->hizliSatisCacheKey('favoriler'));
    }

    private function hizliSatisUrunCacheTemizle(): void
    {
        $kategoriAnahtarlari = ['genel', '-1'];
        foreach ($this->hizliKategoriler() as $kategori) {
            $kategoriAnahtarlari[] = (string) ($kategori['id'] ?? '');
        }

        foreach (array_filter(array_unique($kategoriAnahtarlari)) as $kategori) {
            Cache::forget($this->hizliSatisCacheKey('urunler', ['kategori' => $kategori]));
        }
    }

    private function hizliSekmeTercihiniYukle(): void
    {
        $tercih = Cache::get($this->hizliSatisCacheKey('sekme-tercihi'));

        if (! is_array($tercih)) {
            return;
        }

        $kategoriId = $tercih['kategori_id'] ?? null;
        $this->hizliKategoriId = is_numeric($kategoriId) ? (int) $kategoriId : null;
        $this->hizliKategoriSatirSayisi = max(2, min(12, (int) ($tercih['satir_sayisi'] ?? 2)));
    }

    private function hizliSekmeTercihiniKaydet(): void
    {
        Cache::put($this->hizliSatisCacheKey('sekme-tercihi'), [
            'kategori_id' => $this->hizliKategoriId,
            'satir_sayisi' => $this->hizliKategoriSatirSayisi,
        ], now()->addDays(30));
    }
}
