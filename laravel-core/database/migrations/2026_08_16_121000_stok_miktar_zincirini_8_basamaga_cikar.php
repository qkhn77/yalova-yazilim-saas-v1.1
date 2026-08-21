<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, array{nullable: bool, default: int|null}>> */
    private array $kolonlar = [
        'stok_kartlari' => ['kritik_seviye_miktar' => ['nullable' => false, 'default' => 0], 'minimum_stok' => ['nullable' => true, 'default' => null], 'maksimum_stok' => ['nullable' => true, 'default' => null], 'stok_miktari' => ['nullable' => false, 'default' => 0], 'rezerve_miktar' => ['nullable' => false, 'default' => 0]],
        'stok_hareketleri' => ['miktar' => ['nullable' => false, 'default' => null], 'onceki_miktar' => ['nullable' => false, 'default' => 0], 'sonraki_miktar' => ['nullable' => false, 'default' => 0]],
        'stok_depo_bakiyeleri' => ['miktar' => ['nullable' => false, 'default' => 0], 'rezerve_miktar' => ['nullable' => false, 'default' => 0]],
        'stok_parcalari' => ['giren_miktar' => ['nullable' => false, 'default' => 0], 'kalan_miktar' => ['nullable' => false, 'default' => 0]],
        'stok_hareketi_partileri' => ['miktar' => ['nullable' => false, 'default' => null]],
        'fatura_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 0]],
        'teklif_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1]],
        'sepet_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1]],
        'siparis_kalemleri' => ['miktar' => ['nullable' => false, 'default' => null], 'stok_rezerv_miktari' => ['nullable' => false, 'default' => 0]],
        'muhasebe_barkodlu_satis_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1], 'iade_edilen_miktar' => ['nullable' => false, 'default' => 0]],
        'muhasebe_barkodlu_satis_iade_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1]],
        'restoran_adisyon_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1]],
        'restoran_recete_kalemleri' => ['miktar' => ['nullable' => false, 'default' => null]],
        'teknik_servis_kalemleri' => ['miktar' => ['nullable' => false, 'default' => 1]],
    ];

    public function up(): void
    {
        foreach ($this->kolonlar as $tablo => $kolonlar) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            Schema::table($tablo, function (Blueprint $table) use ($tablo, $kolonlar): void {
                foreach ($kolonlar as $kolon => $ayar) {
                    if (! Schema::hasColumn($tablo, $kolon)) {
                        continue;
                    }
                    $sutun = $table->decimal($kolon, 20, 8);
                    if ($ayar['nullable']) {
                        $sutun->nullable();
                    }
                    if ($ayar['default'] !== null) {
                        $sutun->default($ayar['default']);
                    }
                    $sutun->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Sekiz basamaklı gerçek veriyi sessizce dört basamağa düşürmek veri kaybıdır.
        // Bu migration ileri yönlü ve bilinçli olarak kayıpsız/geri döndürülemezdir.
    }
};
