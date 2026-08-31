<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\MuhasebeLogoTuru;
use App\Models\Muhasebe\MuhasebeMalzemeTuru;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\MuhasebeTasarim;
use App\Models\Muhasebe\MuhasebeVaryant;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Support\MuhasebeYetkiSablonlari;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStokKarti extends CreateRecord
{
    protected static string $resource = StokKartiKaynagi::class;

    protected static ?string $title = 'Stok ekle';

    private string $acilisStokMiktari = '0';
    private mixed $acilisUretimTarihi = null;
    private mixed $acilisSonKullanmaTarihi = null;
    /** @var array<string, mixed> */
    private array $acilisMermerAlanlari = [];
    private array $acilisSeriNolari = [];
    private mixed $garantiBaslangicTarihi = null;
    private mixed $garantiBitisTarihi = null;
    /** @var array<string, mixed> */
    private array $olcuVerisi = [];
    /** @var array<int, array<string, mixed>> */
    private array $olcuSatirlari = [];
    private string $olcuAcilisAdedi = '0';

    public function getTitle(): string|Htmlable
    {
        return static::getResource()::isWebUrunContext() ? 'Ürün ekle' : 'Stok ekle';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return static::getResource()::isWebUrunContext()
            ? 'Ürün başarıyla oluşturuldu.'
            : 'Stok kartı başarıyla oluşturuldu.';
    }

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (ValidationException $exception) {
            $mesajlar = collect($exception->errors())
                ->flatten()
                ->filter()
                ->unique()
                ->implode(' ');

            Notification::make()
                ->danger()
                ->title('Stok kartı oluşturulamadı.')
                ->body($mesajlar !== '' ? $mesajlar : 'Lütfen zorunlu alanları kontrol edin.')
                ->persistent()
                ->send();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $acilisMiktari = str_replace(',', '.', trim((string) ($data['stok_miktari'] ?? '0')));
        $this->acilisStokMiktari = is_numeric($acilisMiktari) && bccomp($acilisMiktari, '0', 4) > 0
            ? $acilisMiktari
            : '0';
        $data['stok_miktari'] = '0';
        $this->acilisUretimTarihi = $data['uretim_tarihi'] ?? null;
        $this->acilisSonKullanmaTarihi = $data['son_kullanma_tarihi'] ?? null;
        $this->acilisMermerAlanlari = array_intersect_key($data, array_flip([
            'blok_no', 'ocak_tedarikci', 'kalite_sinifi', 'renk_desen',
            'kalinlik_cm', 'metrekare', 'plaka_no',
        ]));
        unset($data['kalinlik_cm'], $data['metrekare']);
        $this->acilisSeriNolari = array_values(array_filter(array_map(
            static fn (string $seri): string => trim($seri),
            preg_split('/[\r\n,;]+/', (string) ($data['seri_nolari'] ?? '')) ?: []
        )));
        $this->garantiBaslangicTarihi = $data['garanti_baslangic_tarihi'] ?? null;
        $this->garantiBitisTarihi = $data['garanti_bitis_tarihi'] ?? null;
        $takipTuru = OlculuStokTakipTuru::tryFrom((string) ($data['olculu_takip_turu'] ?? 'standart')) ?? OlculuStokTakipTuru::Standart;
        if ($takipTuru->olculuMu()) {
            if (! MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::STOK_OLCU_OLUSTUR)) {
                throw ValidationException::withMessages(['olculu_takip_turu' => 'Ölçülü stok oluşturma yetkiniz bulunmuyor.']);
            }
            $this->olcuAcilisAdedi = str_replace(',', '.', trim((string) ($data['olcu_acilis_adet'] ?? '0')));
            $this->olcuVerisi = [
                'kod' => 'SABIT-'.strtoupper(Str::random(8)),
                'ad' => 'Sabit ölçü',
                'olcu_birimi' => $data['olcu_giris_birimi'] ?? null,
                'en' => $data['olcu_en'] ?? null,
                'boy' => $data['olcu_boy'] ?? null,
                'yukseklik' => $data['olcu_yukseklik'] ?? null,
                'bir_adet_agirlik' => $data['olcu_bir_adet_agirlik'] ?? null,
                'agirlik_birimi' => $data['agirlik_birimi'] ?? 'kg',
                'agirlik_turu' => 'sabit',
            ];
            if (($data['olcu_yapisi'] ?? 'sabit') === 'coklu') {
                $this->olcuSatirlari = array_values(array_filter((array) ($data['olcu_satirlari'] ?? []), static fn ($satir): bool => is_array($satir)));
                $this->olcuSatirlari = array_map(fn (array $satir): array => $satir + ['olcu_birimi' => $data['olcu_giris_birimi'] ?? null], $this->olcuSatirlari);
                if ($this->olcuSatirlari === []) {
                    throw ValidationException::withMessages(['olcu_satirlari' => 'En az bir ölçü satırı eklenmelidir.']);
                }
                $gorulen = [];
                foreach ($this->olcuSatirlari as $satir) {
                    $en = number_format((float) ($satir['en'] ?? 0), 8, '.', '');
                    $boy = number_format((float) ($satir['boy'] ?? 0), 8, '.', '');
                    $anahtar = $en.'|'.$boy;
                    if (isset($gorulen[$anahtar])) {
                        throw ValidationException::withMessages(['olcu_satirlari' => 'Aynı en ve boy ölçüsü bu stok kartında daha önce kayıt edildi.']);
                    }
                    $gorulen[$anahtar] = true;
                }
            } else {
                $this->olcuSatirlari = [$this->olcuVerisi + ['olcu_acilis_adet' => $this->olcuAcilisAdedi]];
            }
            $data['stok_miktari'] = '0';
            $data['depo_id'] = (int) ($data['depo_id'] ?? 0);
            if ($data['depo_id'] < 1) {
                $firmaId = (int) ($data['firma_id'] ?? app(TenantContextService::class)->aktifFirmaId() ?? 0);
                $data['depo_id'] = (int) Depo::withoutGlobalScopes()
                    ->where('firma_id', $firmaId)
                    ->where('kod', 'MERKEZ')
                    ->where('aktif_mi', true)
                    ->whereNull('deleted_at')
                    ->value('id');
            }
            if ($data['depo_id'] < 1) {
                throw ValidationException::withMessages([
                    'olculu_takip_turu' => 'Ölçülü stok oluşturmak için aktif bir depo tanımlanmalı ve seçilmelidir.',
                ]);
            }
            $anaBirimKodu = $takipTuru->anaBirimKodu();
            $birimler = Birim::withoutGlobalScopes()->where('tanim_firma_kapsami', 0)->whereIn('kod', [$anaBirimKodu, 'AD'])->where('aktif_mi', true)->pluck('id', 'kod');
            if (! isset($birimler[$anaBirimKodu], $birimler['AD'])) {
                throw ValidationException::withMessages(['olculu_takip_turu' => 'Ölçülü stok için sistem ana birimleri bulunamadı.']);
            }
            $data['ana_birim_id'] = $birimler[$anaBirimKodu];
            $data['ikincil_birim_id'] = $birimler['AD'];
            $data['varsayilan_islem_birimi_id'] = $birimler['AD'];
            $data['varsayilan_fiyat_birimi_id'] = $birimler[$anaBirimKodu];
            $data['birim'] = 'AD';
        }
        unset($data['uretim_tarihi'], $data['son_kullanma_tarihi'], $data['seri_nolari'], $data['garanti_baslangic_tarihi'], $data['garanti_bitis_tarihi'], $data['blok_no'], $data['ocak_tedarikci'], $data['kalite_sinifi'], $data['renk_desen'], $data['metrekare'], $data['plaka_no'], $data['olcu_giris_birimi'], $data['agirlik_birimi'], $data['olcu_en'], $data['olcu_boy'], $data['olcu_yukseklik'], $data['olcu_bir_adet_agirlik'], $data['olcu_acilis_adet'], $data['olcu_satirlari']);

        $kullanici = Auth::user();
        $super = $kullanici && StokKarti::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            $fid = app(TenantContextService::class)->aktifFirmaId();
            if (! $fid) {
                throw ValidationException::withMessages(['firma_id' => 'Aktif firma oturumu yok.']);
            }
            $data['firma_id'] = $fid;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? 0);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma seçilmelidir.']);
            }
        }

        $kod = trim((string) ($data['kod'] ?? ''));
        if ($kod === '') {
            $kod = StokKartiKaynagi::stokKodUret((int) $data['firma_id']);
        }
        $kod = strtoupper($kod);
        $data['kod'] = $kod;
        if (! StokKartiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, null)) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma için zaten kullanılıyor.']);
        }

        $barkod = trim((string) ($data['barkod'] ?? ''));
        if ($barkod !== '') {
            if (StokKarti::query()
                ->where('firma_id', (int) $data['firma_id'])
                ->whereRaw('UPPER(barkod) = ?', [strtoupper($barkod)])
                ->exists()
            ) {
                throw ValidationException::withMessages(['barkod' => 'Bu barkod bu firmada zaten kullanılıyor.']);
            }
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) ($data['ad'] ?? ''));
        }
        $slug = trim($slug);
        $data['slug'] = $slug !== '' ? $slug : null;
        if ($data['slug']) {
            if (StokKarti::query()
                ->where('firma_id', (int) $data['firma_id'])
                ->where('slug', $data['slug'])
                ->exists()
            ) {
                throw ValidationException::withMessages(['slug' => 'Bu slug bu firmada zaten kullanılıyor.']);
            }
        }

        $kategori = StokKartiKaynagi::kategoriDegerleriniHazirla((int) $data['firma_id'], (int) ($data['kategori_id'] ?? 0));
        $data['kategori_id'] = $kategori['kategori_id'];
        $data['kategori_kodu'] = $kategori['kategori_kodu'];
        $data = $this->detayAlanlariniDogrula($data, (int) $data['firma_id']);
        $data = StokKartiKaynagi::depoAlanlariniDogrula($data, (int) $data['firma_id']);

        if (static::getResource()::isWebUrunContext()) {
            $data['tur'] = \App\Muhasebe\Enumlar\StokKartiTuru::ETicaret->value;
        }

        return $data;
    }

    /**
     * Kart ve açılış alt kayıtlarını aynı transaction içinde oluşturur.
     * Filament panel transaction ayarı kapalı olsa dahi bu akış atomiktir.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $stok = parent::handleRecordCreation($data);
            $this->acilisKayitlariniOlustur($stok);

            return $stok;
        });
    }

    /** Domain yazmaları handleRecordCreation transaction'ına taşındı. */
    protected function afterCreate(): void
    {
    }

    protected function acilisKayitlariniOlustur(StokKarti $stok): void
    {
        if ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru && $stok->olculu_takip_turu->olculuMu()) {
            DB::transaction(function () use ($stok): void {
                $servis = app(StokOlcuBakiyeServisi::class);
                $hesap = app(\App\Muhasebe\Servisler\StokOlcuHesaplamaServisi::class);
                $birimMaliyet = (string) ($stok->alis_fiyati ?? 0);
                $dagilimlar = [];
                $toplamAnaMiktar = '0';
                foreach ($this->olcuSatirlari as $satir) {
                    $veri = [
                        'kod' => 'OLCU-'.strtoupper(Str::random(8)),
                        'ad' => trim((string) ($satir['en'] ?? '').'x'.(string) ($satir['boy'] ?? '')),
                        'olcu_birimi' => ($satir['olcu_birimi'] ?? null),
                        'en' => $satir['en'] ?? null, 'boy' => $satir['boy'] ?? null,
                        'yukseklik' => $satir['yukseklik'] ?? null, 'bir_adet_agirlik' => $satir['bir_adet_agirlik'] ?? null,
                        'agirlik_birimi' => $satir['agirlik_birimi'] ?? 'kg',
                        'agirlik_turu' => 'sabit',
                    ];
                    $olcu = $servis->olcuOlustur((int) $stok->firma_id, $stok, $veri);
                    $adet = str_replace(',', '.', trim((string) ($satir['olcu_acilis_adet'] ?? '0')));
                    if (bccomp($adet, '0', 8) <= 0) {
                        continue;
                    }
                    $anaMiktar = $hesap->adettenAnaMiktara($adet, (string) $olcu->bir_adet_ana_miktar);
                    $depo = Depo::withoutGlobalScopes()
                        ->where('firma_id', (int) $stok->firma_id)
                        ->whereKey((int) $stok->depo_id)
                        ->where('aktif_mi', true)
                        ->whereNull('deleted_at')
                        ->first();
                    if (! $depo) {
                        throw ValidationException::withMessages([
                            'olculu_takip_turu' => 'Ölçülü stok için firmanın aktif Merkez Deposu bulunamadı.',
                        ]);
                    }
                    $bakiye = $servis->bakiyeBulVeyaOlustur((int) $stok->firma_id, $stok, $olcu, $depo);
                    $servis->giris($bakiye, adet: $adet);
                    $toplamAnaMiktar = bcadd($toplamAnaMiktar, $anaMiktar, 8);
                    $dagilimlar[] = ['bakiye' => $bakiye, 'ana_miktar' => $anaMiktar, 'islem_birimi_id' => (int) $stok->ikincil_birim_id, 'girilen_miktar' => $adet];
                }
                if ($dagilimlar !== []) {
                    $hareket = app(StokHareketServisi::class)->kayitOlustur((int) $stok->firma_id, [
                        'stok_id' => (int) $stok->id, 'depo_id' => (int) $stok->depo_id, 'islem_turu' => StokHareketIslemTuru::Acilis,
                        'miktar' => $toplamAnaMiktar, 'birim_fiyat' => $birimMaliyet, 'birim_maliyet' => $birimMaliyet,
                        'belge_turu' => StokBelgeTuru::Acilis, 'belge_id' => (int) $stok->id, 'referans_tipi' => StokBelgeTuru::Acilis->value,
                        'referans_id' => (int) $stok->id, 'tarih' => now(), 'aciklama' => 'Ölçülü stok kartı açılış devri',
                    ]);
                    $servis->dagilimlariKaydet($hareket, $dagilimlar);
                }
            });
            return;
        }
        if (bccomp($this->acilisStokMiktari, '0', 4) <= 0) {
            return;
        }

        $birimMaliyet = (string) ($stok->alis_fiyati ?? 0);

        app(StokHareketServisi::class)->kayitOlustur((int) $stok->firma_id, [
            'stok_id' => (int) $stok->id,
            'depo_id' => (int) ($stok->depo_id ?? 0),
            'islem_turu' => StokHareketIslemTuru::Acilis,
            'miktar' => $this->acilisStokMiktari,
            'birim_fiyat' => $birimMaliyet,
            'birim_maliyet' => $birimMaliyet,
            'uretim_tarihi' => $this->acilisUretimTarihi,
            'son_kullanma_tarihi' => $this->acilisSonKullanmaTarihi,
            ...$this->acilisMermerAlanlari,
            'seri_nolari' => $this->acilisSeriNolari,
            'garanti_baslangic_tarihi' => $this->garantiBaslangicTarihi,
            'garanti_bitis_tarihi' => $this->garantiBitisTarihi,
            'belge_turu' => StokBelgeTuru::Acilis,
            'belge_id' => (int) $stok->id,
            'referans_tipi' => StokBelgeTuru::Acilis->value,
            'referans_id' => (int) $stok->id,
            'tarih' => now(),
            'aciklama' => 'İlk stok kartı açılış devri',
        ]);
    }

    /**
     * Oluşturma ekranında açılan tanım ilişkilerinin aktif firmaya ait
     * olduğunu doğrular. Form seçenekleri tenant ile sınırlı olsa da,
     * istemciden gönderilen değerler ayrıca kontrol edilmelidir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detayAlanlariniDogrula(array $data, int $firmaId): array
    {
        $tanimAlanlari = [
            'marka_id' => [MuhasebeMarka::class, 'Marka'],
            'tasarim_id' => [MuhasebeTasarim::class, 'Tasarım'],
            'malzeme_turu_id' => [MuhasebeMalzemeTuru::class, 'Malzeme türü'],
            'logo_turu_id' => [MuhasebeLogoTuru::class, 'Logo türü'],
            'varyant_id' => [MuhasebeVaryant::class, 'Varyant'],
        ];

        foreach ($tanimAlanlari as $alan => [$model, $etiket]) {
            $id = (int) ($data[$alan] ?? 0);
            if ($id < 1) {
                $data[$alan] = null;
                continue;
            }

            if (! $model::query()->gorunurFirmaIle($firmaId)->whereKey($id)->exists()) {
                throw ValidationException::withMessages([
                    $alan => $etiket.' aktif firmaya ait değil.',
                ]);
            }
        }

        $tedarikciId = (int) ($data['tedarikci_id'] ?? 0);
        if ($tedarikciId > 0 && ! Cari::query()
            ->where('firma_id', $firmaId)
            ->where('tur', CariTuru::Tedarikci->value)
            ->whereKey($tedarikciId)
            ->exists()) {
            throw ValidationException::withMessages([
                'tedarikci_id' => 'Tedarikçi aktif firmaya ait değil.',
            ]);
        }

        $markaId = (int) ($data['marka_id'] ?? 0);
        $modelId = (int) ($data['model_id'] ?? 0);
        if ($modelId > 0) {
            $modelSorgusu = MuhasebeStokModeli::query()
                ->gorunurFirmaIle($firmaId)
                ->whereKey($modelId);

            if ($markaId > 0) {
                $modelSorgusu->where('marka_id', $markaId);
            }

            if (! $modelSorgusu->exists()) {
                throw ValidationException::withMessages([
                    'model_id' => 'Model seçilen marka ve aktif firma ile eşleşmiyor.',
                ]);
            }
        }

        if ($markaId > 0) {
            $data['marka_uretici'] = MuhasebeMarka::query()
                ->gorunurFirmaIle($firmaId)
                ->whereKey($markaId)
                ->value('ad');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
