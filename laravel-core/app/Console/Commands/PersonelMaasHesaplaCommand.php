<?php

namespace App\Console\Commands;

use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\PersonelTakip\PersonelMaasHesaplamaServisi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PersonelMaasHesaplaCommand extends Command
{
    protected $signature = 'personel:maas-hesapla {donem_id : Personel maaş dönemi ID} {--firma= : Güvenlik için beklenen firma ID}';

    protected $description = 'Personel maaş dönemi için hakediş hareketlerini hesaplar.';

    public function handle(PersonelMaasHesaplamaServisi $hesaplamaServisi): int
    {
        $donemId = (int) $this->argument('donem_id');
        $donem = PersonelMaasDonemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->find($donemId);

        if (! $donem) {
            $this->error("Maaş dönemi bulunamadı: {$donemId}");

            return self::FAILURE;
        }

        $beklenenFirmaId = $this->option('firma');
        if ($beklenenFirmaId !== null && $beklenenFirmaId !== '' && (int) $beklenenFirmaId !== (int) $donem->firma_id) {
            $this->error('Firma doğrulaması başarısız. Dönem farklı firmaya ait.');

            return self::FAILURE;
        }

        try {
            $ozet = $hesaplamaServisi->donemiHesapla($donem);
        } catch (ValidationException $exception) {
            $this->error((string) (Arr::first(Arr::flatten($exception->errors())) ?: 'Maaş dönemi hesaplanamadı.'));

            return self::FAILURE;
        }

        $this->info('Personel maaş dönemi hesaplandı.');
        $this->table(['Hareket', 'Toplam Brüt', 'Toplam Kesinti', 'Toplam Net'], [[
            $ozet['hareket_sayisi'],
            number_format($ozet['toplam_brut'], 2, ',', '.'),
            number_format($ozet['toplam_kesinti'], 2, ',', '.'),
            number_format($ozet['toplam_net'], 2, ',', '.'),
        ]]);

        return self::SUCCESS;
    }
}
