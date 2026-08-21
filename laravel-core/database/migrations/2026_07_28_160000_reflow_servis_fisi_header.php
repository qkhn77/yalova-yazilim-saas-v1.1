<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_baski_sablonlari')) {
            return;
        }

        $eski = <<<'HTML'
<div class="servis-ust">
        <div class="logo-alani">{{FIRMA_LOGO}}</div>
        <div>
            <div class="firma-ad">{{FIRMA_UNVAN}}</div>
            <div>Tel: {{FIRMA_TELEFON}}</div>
            <div>{{FIRMA_EPOSTA}}</div>
            <div>{{FIRMA_ADRES}}</div>
        </div>
    </div>
HTML;

        $yeni = <<<'HTML'
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
HTML;

        $css = <<<'CSS'

/* Servis fişi üst bilgi düzeni */
.servis-ust-ana {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    gap: 4px;
    width: 100%;
}
.servis-ust .logo-alani {
    order: initial;
    margin-bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}
.servis-ust .servis-iletisim {
    min-width: 0;
    text-align: left;
    word-break: break-word;
}
.servis-firma-alt {
    width: 100%;
    margin-top: 2px;
    text-align: center;
}
CSS;

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->whereIn('kod', ['servis-fisi-80mm', 'servis-fisi-58mm'])
            ->get(['id', 'sablon_html', 'sablon_css'])
            ->each(function (object $sablon) use ($eski, $yeni, $css): void {
                $html = str_replace($eski, $yeni, (string) $sablon->sablon_html);
                $mevcutCss = (string) ($sablon->sablon_css ?? '');

                if (! str_contains($mevcutCss, 'Servis fişi üst bilgi düzeni')) {
                    $mevcutCss .= $css;
                }

                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $sablon->id)
                    ->update([
                        'sablon_html' => $html,
                        'sablon_css' => $mevcutCss,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Başlık düzeni geri alınmaz; mevcut kayıtların içerik yapısı korunur.
    }
};
