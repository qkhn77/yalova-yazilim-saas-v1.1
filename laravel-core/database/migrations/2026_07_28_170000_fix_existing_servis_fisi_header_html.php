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

        $header = <<<'HTML'
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

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->whereIn('kod', ['servis-fisi-80mm', 'servis-fisi-58mm'])
            ->get(['id', 'sablon_html'])
            ->each(function (object $sablon) use ($header): void {
                $html = (string) $sablon->sablon_html;
                $start = strpos($html, '<div class="servis-ust">');
                $end = strpos($html, '<div class="belge-baslik">', $start === false ? 0 : $start);

                if ($start === false || $end === false) {
                    return;
                }

                $html = substr($html, 0, $start).$header.substr($html, $end);

                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $sablon->id)
                    ->update(['sablon_html' => $html, 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        // Yeni başlık yapısı korunur.
    }
};
