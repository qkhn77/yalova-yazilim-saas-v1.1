<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaOlcuKalemiServisi;
use App\Muhasebe\Servisler\FaturaParaBirimiDogrulamaServisi;
use App\Muhasebe\Servisler\FaturaToplamSenkronizasyonServisi;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateFatura extends CreateRecord
{
    protected static string $resource = FaturaKaynagi::class;

    private bool $onayliOlarakOlustur = false;

    /** @var array<int, array<string, mixed>> */
    private array $onayOncesiKalemler = [];

    public function mount(): void
    {
        parent::mount();

        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        $varsayilanTur = $this->varsayilanTur();

        $this->form->fill(array_filter([
            'firma_id' => $firmaId > 0 ? $firmaId : null,
            'tur' => $varsayilanTur?->value,
            'durum' => 'taslak',
            'tarih' => now(),
            'para_birimi' => 'TRY',
            'doviz_kuru' => 1,
            'kdv_dahil_fiyatlandirma_mi' => false,
            'tevkifat_orani' => 0,
            // Form doldurulurken Repeater'ın defaultItems değeri atlanabildiği
            // için ilk fatura satırını açık şekilde başlat.
            'kalemler' => [[]],
        ], static fn (mixed $value): bool => $value !== null));

        // Onaylı faturadaki "İade Et" akışı, yeni bağlı iade faturası formunu
        // kaynak fatura ve kalemlerle hazır açar. Kullanıcı miktarları azaltarak
        // kısmi iade yapabilir; kaynak fatura değişmez.
        $kaynakId = (int) request()->query('kaynak_fatura_id', 0);
        if ($kaynakId > 0 && $varsayilanTur !== null) {
            $kaynakTur = $varsayilanTur === FaturaTuru::AlisIadesi
                ? FaturaTuru::Gelen
                : FaturaTuru::Giden;
            $kaynak = Fatura::withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->whereKey($kaynakId)
                ->where('tur', $kaynakTur->value)
                ->where('durum', FaturaDurumu::Onayli->value)
                ->with('kalemler.olcuDagilimlari')
                ->first();

            if ($kaynak) {
                $kalemler = FaturaKaynagi::kaynakIadeFaturasiKalemleriniFormata(
                    (int) $kaynak->getKey(),
                    $firmaId,
                    $varsayilanTur->value,
                );
                $ozet = FaturaKaynagi::hesaplaFormKalemleriVeOzet([
                    'kalemler' => $kalemler,
                    'odendi_tutari' => 0,
                    'tevkifat_orani' => 0,
                    'para_birimi' => (string) ($kaynak->para_birimi ?: 'TRY'),
                ]);
                // Form::fill() replaces the complete state. Include the
                // return defaults explicitly so the source invoice does not
                // clear the subclass-provided type and required fields.
                $this->form->fill([
                    'firma_id' => $firmaId,
                    'tur' => $varsayilanTur->value,
                    // Kaynak faturadan açılan iade, kullanıcı ayrıca taslak
                    // seçmediği sürece standart onay akışına girsin.
                    'durum' => FaturaDurumu::Onayli->value,
                    'tarih' => now(),
                    'kdv_dahil_fiyatlandirma_mi' => false,
                    'tevkifat_orani' => 0,
                    'cari_id' => (int) $kaynak->cari_id,
                    'bagli_fatura_id' => (int) $kaynak->getKey(),
                    'para_birimi' => (string) ($kaynak->para_birimi ?: 'TRY'),
                    'doviz_kuru' => (string) ($kaynak->doviz_kuru ?: 1),
                    'kalemler' => $ozet['kalemler'] ?? $kalemler,
                    'ara_toplam' => $ozet['ara_toplam'] ?? 0,
                    'toplam_indirim' => $ozet['toplam_indirim'] ?? 0,
                    'kdv_toplam' => $ozet['kdv_toplam'] ?? 0,
                    'genel_toplam' => $ozet['genel_toplam'] ?? 0,
                    'odenecek_tutar' => $ozet['odenecek_tutar'] ?? 0,
                    'odendi_tutari' => 0,
                    'acik_tutar' => $ozet['acik_tutar'] ?? 0,
                ]);
            }
        }
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function varsayilanTur(): ?FaturaTuru
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $varsayilanTur = $this->varsayilanTur();
        if ($varsayilanTur !== null && empty($data['tur'])) {
            $data['tur'] = $varsayilanTur->value;
        }

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $seciliTur = (string) ($data['tur'] ?? '');
        if ($seciliTur === '') {
            $varsayilanTur = $this->varsayilanTur();
            if ($varsayilanTur !== null) {
                $seciliTur = $varsayilanTur->value;
            }
        }

        if ($seciliTur === '') {
            throw ValidationException::withMessages([
                'tur' => 'Fatura türü seçilmelidir.',
            ]);
        }

        $data['tur'] = $seciliTur;
        // The return form preloads its relationship repeater from the source
        // invoice. Filament may omit that disabled/relationship state from
        // getState(), although the Livewire form state still contains it.
        // Preserve the payload for the strict source-line validation below;
        // the source IDs and quantities are still checked afterwards.
        if (in_array($seciliTur, [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value], true)
            && (! is_array($data['kalemler'] ?? null) || $data['kalemler'] === [])
            && is_array($this->data['kalemler'] ?? null)
            && $this->data['kalemler'] !== []) {
            $data['kalemler'] = $this->data['kalemler'];
        }
        if (in_array($seciliTur, [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value], true)) {
            $data['fatura_sinifi'] = FaturaSinifi::Gider->value;
        } elseif (in_array($seciliTur, [FaturaTuru::Gelen->value, FaturaTuru::GelenFatura->value], true)) {
            $data['fatura_sinifi'] = $data['fatura_sinifi'] ?? FaturaSinifi::StokAlisi->value;
        } else {
            $data['fatura_sinifi'] = null;
        }
        $firmaId = $this->resolveFirmaId($data);
        $data['firma_id'] = $firmaId;
        $this->validateTamSatisIadesiKaynak($data, $firmaId);
        $this->validateTamAlisIadesiKaynak($data, $firmaId);
        $data = FaturaKaynagi::olcuKartlariniFaturaVerisineDonustur($data, $firmaId);
        $data = FaturaKaynagi::hesaplaFormKalemleriVeOzet($data);
        $this->onayOncesiKalemler = array_values(is_array($data['kalemler'] ?? null) ? $data['kalemler'] : []);
        $this->validateReferences($data, $firmaId);
        $this->validateCariParaBirimi($data, $firmaId);

        // Onaylı oluşturma seçeneği standart onay servisinden geçsin; böylece
        // fatura numarası, cari ve stok hareketleri eksik kalmasın.
        if (($data['durum'] ?? null) === FaturaDurumu::Onayli->value) {
            $this->onayliOlarakOlustur = true;
            $data['durum'] = FaturaDurumu::Taslak->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->onayOncesiKalemleriGerekirseKaydet($this->record);
        $fatura = $this->record->fresh(['kalemler']);
        $formKalemleri = array_values(is_array($this->data['kalemler'] ?? null) ? $this->data['kalemler'] : []);

        try {
            DB::transaction(function () use ($fatura, $formKalemleri): void {
                app(FaturaToplamSenkronizasyonServisi::class)->senkronla($fatura);

                foreach ($fatura->kalemler as $kalem) {
                    $dagilimlar = FaturaKaynagi::seciliOlcuDagilimlariniAyikla(
                        is_array($formKalemleri[$kalem->satir_no - 1]['olcu_dagilimlari'] ?? null)
                            ? $formKalemleri[$kalem->satir_no - 1]['olcu_dagilimlari']
                            : [],
                    );
                    $cikis = in_array($fatura->tur->kanonik(), [FaturaTuru::Giden, FaturaTuru::AlisIadesi], true);
                    // Canlı form state'i dağılımı taşıyıp bakiye seçimini
                    // taşımamış olabilir. Tek uygun bakiye varsa dağılımı
                    // mevcut stok/depo bakiyesiyle tamamla; aksi halde yanlış
                    // depodan düşüm yapılmasına izin verme.
                    if ($cikis && count($dagilimlar) === 1
                        && (int) ($dagilimlar[0]['stok_olcu_bakiyesi_id'] ?? 0) < 1) {
                        $stok = StokKarti::withoutGlobalScopes()->find((int) $kalem->stok_id);
                        $olcuId = (int) ($dagilimlar[0]['stok_olcusu_id'] ?? 0);
                        $depoId = (int) ($dagilimlar[0]['depo_id'] ?? $kalem->depo_id ?? $stok?->depo_id ?? 0);
                        if ($stok && $olcuId > 0) {
                            $bakiyeler = StokOlcuBakiyesi::withoutGlobalScopes()
                                ->where('firma_id', $kalem->firma_id)
                                ->where('stok_id', $stok->id)
                                ->where('stok_olcusu_id', $olcuId)
                                ->when($depoId > 0, fn ($query) => $query->where('depo_id', $depoId))
                                ->where('ana_miktar', '>', 0)
                                ->get(['id', 'depo_id']);
                            if ($bakiyeler->count() === 1) {
                                $dagilimlar[0]['stok_olcu_bakiyesi_id'] = (int) $bakiyeler->first()->id;
                                $dagilimlar[0]['depo_id'] = (int) $bakiyeler->first()->depo_id;
                            }
                        }
                    }
                    // Eski/uyumsuz form state'lerinde ölçü satırı taşınmamışsa
                    // ortak servis snapshot'taki ana/adet birimini dikkate
                    // alarak güvenli varsayılan dağılımı oluştursun. Burada
                    // ikincil birimi körlemesine varsaymak 2 m²'yi 12 m²'ye
                    // dönüştürebiliyordu.
                    if ($dagilimlar === [] && $kalem->stok_id !== null) {
                        app(FaturaOlcuKalemiServisi::class)->tekOlcuDagiliminiOtomatikTamamla($kalem, $cikis);
                    } elseif ($dagilimlar !== []) {
                        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, $dagilimlar, $cikis);
                    }
                }

                if ($this->onayliOlarakOlustur) {
                    // Servis, cari/stok/numara işlemlerini tamamladıktan sonra
                    // aynı transaction içinde en son durum=onayli yapar.
                    app(FaturaIslemServisi::class)->faturayiOnayla($this->record->fresh(['kalemler']));
                }
            });
        } catch (Throwable $e) {
            if (! $this->onayliOlarakOlustur) {
                throw $e;
            }

            // Onay akışındaki tek bir hata bile faturayı onaylı bırakmamalı.
            $this->record->newQuery()->whereKey($this->record->getKey())->update(['durum' => FaturaDurumu::Taslak->value]);
            report($e);
            Notification::make()
                ->warning()
                ->title('Fatura onaylanamadı')
                ->body($e instanceof IsKuraliIstisnasi
                    ? $e->getMessage().' Fatura taslak olarak kaydedildi; eksikleri düzelttikten sonra tekrar onaylayabilirsiniz.'
                    : 'Cari/stok/numara işlemlerinden biri tamamlanamadı. Fatura taslak olarak kaydedildi; eksikleri düzelttikten sonra tekrar onaylayabilirsiniz.')
                ->persistent()
                ->send();
        }
    }

    private function onayOncesiKalemleriGerekirseKaydet(Fatura $fatura): void
    {
        if ($fatura->onayKalemleri()->exists() || $this->onayOncesiKalemler === []) {
            return;
        }

        foreach ($this->onayOncesiKalemler as $index => $kalem) {
            unset($kalem['id'], $kalem['fatura_id'], $kalem['created_at'], $kalem['updated_at']);
            $kalem['firma_id'] = (int) $fatura->firma_id;
            $kalem['satir_no'] = $index + 1;
            $kalem['para_birimi'] = strtoupper((string) ($kalem['para_birimi'] ?? $fatura->para_birimi ?? 'TRY'));

            $fatura->onayKalemleri()->create($kalem);
        }
    }

    private function resolveFirmaId(array $data): int
    {
        if ((Auth::user()?->is_admin || Auth::user()?->super_admin_mi) && ! empty($data['firma_id'])) {
            return (int) $data['firma_id'];
        }

        return (int) app(TenantContextService::class)->aktifFirmaId();
    }

    private function validateReferences(array $data, int $firmaId): void
    {
        if (! empty($data['cari_id']) && ! Cari::query()->where('firma_id', $firmaId)->whereKey((int) $data['cari_id'])->exists()) {
            throw ValidationException::withMessages(['cari_id' => 'Seçilen cari aktif firmaya ait değil.']);
        }

        $stokSatirlari = [];
        foreach (($data['kalemler'] ?? []) as $i => $kalem) {
            if (($kalem['kalem_tipi'] ?? '') === 'stok_kalemi' && empty($kalem['stok_id'])) {
                throw ValidationException::withMessages(["kalemler.{$i}.stok_id" => 'Stok kaleminde stok seçimi zorunludur.']);
            }
            if (! empty($kalem['stok_id'])) {
                $stokSatirlari[$i] = (int) $kalem['stok_id'];
            }
        }

        if ($stokSatirlari === []) {
            return;
        }

        $gecerliStokIdleri = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereIn('id', array_values(array_unique($stokSatirlari)))
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        foreach ($stokSatirlari as $i => $stokId) {
            if (! isset($gecerliStokIdleri[$stokId])) {
                throw ValidationException::withMessages(["kalemler.{$i}.stok_id" => 'Seçilen stok kartı aktif firmaya ait değil.']);
            }
        }
    }

    private function validateCariParaBirimi(array $data, int $firmaId): void
    {
        if (empty($data['cari_id'])) {
            return;
        }

        try {
            app(FaturaParaBirimiDogrulamaServisi::class)->dogrula(
                $firmaId,
                (int) $data['cari_id'],
                (string) ($data['para_birimi'] ?? 'TRY'),
            );
        } catch (IsKuraliIstisnasi $e) {
            throw ValidationException::withMessages([
                'cari_id' => $e->getMessage(),
                'para_birimi' => $e->getMessage(),
            ]);
        }
    }

    private function validateTamSatisIadesiKaynak(array $data, int $firmaId): void
    {
        if (($data['tur'] ?? null) !== FaturaTuru::SatisIadesi->value) {
            return;
        }
        $kaynakId = (int) ($data['bagli_fatura_id'] ?? 0);
        $kaynak = Fatura::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereKey($kaynakId)
            ->where('cari_id', (int) ($data['cari_id'] ?? 0))
            ->where('tur', FaturaTuru::Giden->value)
            ->where('durum', 'onayli')
            ->whereNull('iptal_edildi_at')
            ->with('kalemler.olcuDagilimlari')
            ->first();
        if (! $kaynak) {
            throw ValidationException::withMessages(['bagli_fatura_id' => 'Onaylı, iptal edilmemiş aynı cariye ait satış faturası seçilmelidir.']);
        }

        $beklenen = FaturaKaynagi::kaynakSatisFaturasiKalemleriniFormata($kaynak->id, $firmaId);
        $gelen = array_values(is_array($data['kalemler'] ?? null) ? $data['kalemler'] : []);
        if (count($gelen) !== count($beklenen)) {
            throw ValidationException::withMessages(['kalemler' => 'Tam satış iadesinde kaynak faturanın bütün kalemleri aktarılmalıdır.']);
        }

        foreach ($beklenen as $index => $beklenenKalem) {
            $gelenKalem = (array) ($gelen[$index] ?? []);
            foreach (['kaynak_fatura_kalemi_id', 'stok_id', 'depo_id'] as $alan) {
                if ((int) ($gelenKalem[$alan] ?? 0) !== (int) ($beklenenKalem[$alan] ?? 0)) {
                    throw ValidationException::withMessages(["kalemler.{$index}.{$alan}" => 'İade kalemi kaynak satış kalemiyle değiştirilemez.']);
                }
            }
            if (bccomp($this->decimal((string) ($gelenKalem['miktar'] ?? '0')), $this->decimal((string) ($beklenenKalem['miktar'] ?? '0')), 8) > 0) {
                throw ValidationException::withMessages(["kalemler.{$index}.miktar" => 'İade miktarı kaynak faturadaki miktarı aşamaz.']);
            }
            $this->validateIadeMiktariKalan((int) $beklenenKalem['kaynak_fatura_kalemi_id'], $kaynak->id, $gelenKalem, $beklenenKalem, $index, FaturaTuru::SatisIadesi);

            $beklenenDagilimlar = array_values($beklenenKalem['olcu_dagilimlari'] ?? []);
            $gelenDagilimlar = array_values(is_array($gelenKalem['olcu_dagilimlari'] ?? null) ? $gelenKalem['olcu_dagilimlari'] : []);
            if (count($gelenDagilimlar) !== count($beklenenDagilimlar)) {
                throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari" => 'Kaynak ölçü dağılımlarının tamamı aktarılmalıdır.']);
            }
            foreach ($beklenenDagilimlar as $dagilimIndex => $beklenenDagilim) {
                $gelenDagilim = (array) ($gelenDagilimlar[$dagilimIndex] ?? []);
                foreach (['kaynak_olcu_dagilimi_id', 'stok_olcusu_id', 'stok_olcu_bakiyesi_id', 'depo_id', 'islem_birimi_id'] as $alan) {
                    if ((int) ($gelenDagilim[$alan] ?? 0) !== (int) ($beklenenDagilim[$alan] ?? 0)) {
                        throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari.{$dagilimIndex}.{$alan}" => 'Ölçü dağılımı kaynak dağılımla değiştirilemez.']);
                    }
                }
                if (bccomp($this->decimal((string) ($gelenDagilim['girilen_miktar'] ?? '0')), $this->decimal((string) ($beklenenDagilim['girilen_miktar'] ?? '0')), 8) > 0) {
                    throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari.{$dagilimIndex}.girilen_miktar" => 'İade ölçüsü kaynak dağılımı aşamaz.']);
                }
            }
        }
    }

    private function validateTamAlisIadesiKaynak(array $data, int $firmaId): void
    {
        if (($data['tur'] ?? null) !== FaturaTuru::AlisIadesi->value) {
            return;
        }
        $kaynakId = (int) ($data['bagli_fatura_id'] ?? 0);
        $kaynak = Fatura::withoutGlobalScopes()->where('firma_id', $firmaId)->whereKey($kaynakId)
            ->where('cari_id', (int) ($data['cari_id'] ?? 0))->where('tur', FaturaTuru::Gelen->value)
            ->where('durum', 'onayli')->whereNull('iptal_edildi_at')->with('kalemler.olcuDagilimlari')->first();
        if (! $kaynak) {
            throw ValidationException::withMessages(['bagli_fatura_id' => 'Onaylı, iptal edilmemiş aynı cariye ait alış faturası seçilmelidir.']);
        }
        $beklenen = FaturaKaynagi::kaynakIadeFaturasiKalemleriniFormata($kaynak->id, $firmaId, FaturaTuru::AlisIadesi->value);
        $gelen = array_values(is_array($data['kalemler'] ?? null) ? $data['kalemler'] : []);
        if (count($gelen) !== count($beklenen)) {
            throw ValidationException::withMessages(['kalemler' => 'Tam alış iadesinde kaynak faturanın bütün kalemleri aktarılmalıdır.']);
        }
        foreach ($beklenen as $index => $beklenenKalem) {
            $gelenKalem = (array) ($gelen[$index] ?? []);
            foreach (['kaynak_fatura_kalemi_id', 'stok_id', 'depo_id'] as $alan) {
                if ((int) ($gelenKalem[$alan] ?? 0) !== (int) ($beklenenKalem[$alan] ?? 0)) {
                    throw ValidationException::withMessages(["kalemler.{$index}.{$alan}" => 'İade kalemi kaynak alış kalemiyle değiştirilemez.']);
                }
            }
            if (bccomp($this->decimal((string) ($gelenKalem['miktar'] ?? '0')), $this->decimal((string) ($beklenenKalem['miktar'] ?? '0')), 8) > 0) {
                throw ValidationException::withMessages(["kalemler.{$index}.miktar" => 'İade miktarı kaynak faturadaki miktarı aşamaz.']);
            }
            $this->validateIadeMiktariKalan((int) $beklenenKalem['kaynak_fatura_kalemi_id'], $kaynak->id, $gelenKalem, $beklenenKalem, $index, FaturaTuru::AlisIadesi);
            $beklenenDagilimlar = array_values($beklenenKalem['olcu_dagilimlari'] ?? []);
            $gelenDagilimlar = array_values(is_array($gelenKalem['olcu_dagilimlari'] ?? null) ? $gelenKalem['olcu_dagilimlari'] : []);
            if (count($gelenDagilimlar) !== count($beklenenDagilimlar)) {
                throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari" => 'Kaynak alış ölçü dağılımlarının tamamı aktarılmalıdır.']);
            }
            foreach ($beklenenDagilimlar as $dagilimIndex => $beklenenDagilim) {
                $gelenDagilim = (array) ($gelenDagilimlar[$dagilimIndex] ?? []);
                foreach (['kaynak_olcu_dagilimi_id', 'stok_olcusu_id', 'stok_olcu_bakiyesi_id', 'depo_id', 'islem_birimi_id'] as $alan) {
                    if ((int) ($gelenDagilim[$alan] ?? 0) !== (int) ($beklenenDagilim[$alan] ?? 0)) {
                        throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari.{$dagilimIndex}.{$alan}" => 'Alış iadesi ölçü dağılımı kaynak dağılımla değiştirilemez.']);
                    }
                }
                if (bccomp($this->decimal((string) ($gelenDagilim['girilen_miktar'] ?? '0')), $this->decimal((string) ($beklenenDagilim['girilen_miktar'] ?? '0')), 8) > 0) {
                    throw ValidationException::withMessages(["kalemler.{$index}.olcu_dagilimlari.{$dagilimIndex}.girilen_miktar" => 'İade ölçüsü kaynak dağılımı aşamaz.']);
                }
            }
        }
    }

    private function decimal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0.00000000';
        }
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }

        return bcadd($value, '0', 8);
    }

    private function validateIadeMiktariKalan(int $kaynakKalemId, int $kaynakFaturaId, array $gelen, array $beklenen, int $index, FaturaTuru $iadeTuru): void
    {
        $alan = $beklenen['ana_miktar'] !== null ? 'ana_miktar' : 'miktar';
        $onceki = (float) \App\Models\Muhasebe\FaturaKalemi::withoutGlobalScopes()
            ->where('kaynak_fatura_kalemi_id', $kaynakKalemId)
            ->whereHas('fatura', fn ($fatura) => $fatura
                ->withoutGlobalScopes()
                ->where('bagli_fatura_id', $kaynakFaturaId)
                ->where('tur', $iadeTuru->value)
                ->where('durum', FaturaDurumu::Onayli->value))
            ->sum($alan);
        $yeni = (float) ($gelen[$alan] ?? $gelen['miktar'] ?? 0);
        $kaynak = (float) ($beklenen[$alan] ?? $beklenen['miktar'] ?? 0);
        if ($onceki + $yeni > $kaynak + 0.00000001) {
            throw ValidationException::withMessages(["kalemler.{$index}.miktar" => 'Bu kalemin iade edilebilir kalan miktarı aşılmış.']);
        }
    }
}
