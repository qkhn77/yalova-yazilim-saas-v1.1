<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferSayfasi;
use App\Models\Firma;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokTransferi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class StokDepoTransferSayfasiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_yetersiz_stok_transferi_controlled_validation_ile_reddedilir_ve_veri_degismez(): void
    {
        [$firma, $kaynak, $hedef, $stok] = $this->transferFixtures('10', '6');
        $hareketSayisi = StokHareketi::query()->count();
        $transferSayisi = StokTransferi::query()->count();

        Livewire::test(StokDepoTransferSayfasi::class)
            ->set('data', [
                'stok_id' => $stok->id,
                'kaynak_depo_id' => $kaynak->id,
                'hedef_depo_id' => $hedef->id,
                'miktar' => 10,
                'tarih' => now()->format('Y-m-d H:i:s'),
                'aciklama' => 'Insufficient stock regression',
            ])
            ->call('transferiKaydet')
            ->assertHasErrors('data.miktar');

        $this->assertSame('10.00000000', (string) $stok->fresh()->stok_miktari);
        $this->assertSame('6.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $kaynak->id)
            ->where('stok_id', $stok->id)
            ->value('miktar'));
        $this->assertSame('0.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $hedef->id)
            ->where('stok_id', $stok->id)
            ->value('miktar'));
        $this->assertSame($hareketSayisi, StokHareketi::query()->count());
        $this->assertSame($transferSayisi, StokTransferi::query()->count());
    }

    public function test_gecerli_depo_transferi_global_stogu_korur(): void
    {
        [$firma, $kaynak, $hedef, $stok] = $this->transferFixtures('10', '10');
        $hareketSayisi = StokHareketi::query()->count();

        $transfer = app(StokHareketServisi::class)->transferOlustur($firma->id, [
            'stok_id' => $stok->id,
            'kaynak_depo_id' => $kaynak->id,
            'hedef_depo_id' => $hedef->id,
            'miktar' => 4,
            'tarih' => now(),
            'aciklama' => 'Valid transfer regression',
        ]);

        $this->assertNotNull($transfer->id);
        $this->assertSame('6.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $kaynak->id)->where('stok_id', $stok->id)->value('miktar'));
        $this->assertSame('4.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $hedef->id)->where('stok_id', $stok->id)->value('miktar'));
        $this->assertSame('10.00000000', (string) $stok->fresh()->stok_miktari);
        $this->assertSame($hareketSayisi + 2, StokHareketi::query()->count());
    }

    public function test_kaynak_depo_bakiyesi_global_stoktan_dusuksa_transfer_reddedilir(): void
    {
        [$firma, $kaynak, $hedef, $stok] = $this->transferFixtures('10', '6');

        $this->expectExceptionMessage('Kaynak depoda yeterli stok bulunmuyor.');

        app(StokHareketServisi::class)->transferOlustur($firma->id, [
            'stok_id' => $stok->id,
            'kaynak_depo_id' => $kaynak->id,
            'hedef_depo_id' => $hedef->id,
            'miktar' => 9,
            'tarih' => now(),
            'aciklama' => 'Per-depot insufficient balance regression',
        ]);
    }

    public function test_tam_mevcut_bakiye_transfer_edilebilir(): void
    {
        [$firma, $kaynak, $hedef, $stok] = $this->transferFixtures('6', '6');

        app(StokHareketServisi::class)->transferOlustur($firma->id, [
            'stok_id' => $stok->id,
            'kaynak_depo_id' => $kaynak->id,
            'hedef_depo_id' => $hedef->id,
            'miktar' => 6,
            'tarih' => now(),
            'aciklama' => 'Exact balance regression',
        ]);

        $this->assertSame('0.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $kaynak->id)->where('stok_id', $stok->id)->value('miktar'));
        $this->assertSame('6.00000000', (string) StokDepoBakiyesi::query()
            ->where('depo_id', $hedef->id)->where('stok_id', $stok->id)->value('miktar'));
        $this->assertSame('6.00000000', (string) $stok->fresh()->stok_miktari);
    }

    /** @return array{0:Firma,1:Depo,2:Depo,3:StokKarti} */
    private function transferFixtures(string $globalMiktar, string $kaynakMiktar): array
    {
        $firma = Firma::query()->create([
            'ad' => 'Transfer Test Firması',
            'kisa_ad' => 'TTF',
            'firma_kodu' => 'TTF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Transfer Test User',
            'email' => 'transfer-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $kaynak = Depo::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'SRC-'.uniqid(),
            'ad' => 'Kaynak Depo',
            'varsayilan_mi' => true,
            'aktif_mi' => true,
        ]);
        $hedef = Depo::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'DST-'.uniqid(),
            'ad' => 'Hedef Depo',
            'varsayilan_mi' => false,
            'aktif_mi' => true,
        ]);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-'.uniqid(),
            'ad' => 'Transfer Stoku',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'AD',
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => $globalMiktar,
        ]);
        StokDepoBakiyesi::query()->create([
            'firma_id' => $firma->id,
            'depo_id' => $kaynak->id,
            'stok_id' => $stok->id,
            'miktar' => $kaynakMiktar,
            'rezerve_miktar' => '0',
        ]);
        StokDepoBakiyesi::query()->create([
            'firma_id' => $firma->id,
            'depo_id' => $hedef->id,
            'stok_id' => $stok->id,
            'miktar' => '0',
            'rezerve_miktar' => '0',
        ]);

        return [$firma, $kaynak, $hedef, $stok];
    }
}
