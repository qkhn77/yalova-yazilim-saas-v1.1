<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\SatisFisSablonu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SatisFisSablonuServisi
{
    private ?bool $sablonTablosuVarMi = null;

    public function firmaSablonlariniHazirla(int $firmaId): void
    {
        if ($firmaId < 1) {
            return;
        }
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        $varMi = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->exists();

        if ($varMi) {
            return;
        }

        $hazir = $this->hazirSablonlar();
        foreach ($hazir as $index => $sablon) {
            SatisFisSablonu::query()->create([
                'firma_id' => $firmaId,
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

    public function seciliSablonGetir(int $firmaId, ?int $sablonId = null): ?SatisFisSablonu
    {
        if (! $this->sablonTablosuVarMi()) {
            return null;
        }

        $this->firmaSablonlariniHazirla($firmaId);

        if ($sablonId && $sablonId > 0) {
            $secili = SatisFisSablonu::query()
                ->where('firma_id', $firmaId)
                ->whereKey($sablonId)
                ->where('aktif', true)
                ->first();

            if ($secili) {
                return $secili;
            }
        }

        $varsayilan = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->first();

        return $varsayilan;
    }

    public function varsayilanYap(SatisFisSablonu $sablon): void
    {
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        DB::transaction(function () use ($sablon): void {
            SatisFisSablonu::query()
                ->where('firma_id', (int) $sablon->firma_id)
                ->update(['varsayilan_mi' => false]);

            $sablon->forceFill(['varsayilan_mi' => true])->save();
        });
    }

    public function benzersizKodUret(int $firmaId, string $ad): string
    {
        $temel = Str::of($ad)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $temel = $temel !== '' ? $temel : 'satis-fis-sablonu';
        $kod = $temel;
        $sayac = 2;

        while ($this->sablonTablosuVarMi() && SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('kod', $kod)
            ->exists()) {
            $kod = $temel.'-'.$sayac;
            $sayac++;
        }

        return $kod;
    }

    private function sablonTablosuVarMi(): bool
    {
        return $this->sablonTablosuVarMi ??= Schema::hasTable('muhasebe_satis_fis_sablonlari');
    }

    /**
     * @return array<int,array{ad:string,kod:string,sayfa_tipi:string,sablon_html:string,sablon_css:string}>
     */
    public function hazirSablonlar(): array
    {
        return [
            [
                'ad' => 'Standart 80mm',
                'kod' => 'standart-80mm',
                'sayfa_tipi' => '80mm',
                'sablon_html' => $this->temelSablonHtml(),
                'sablon_css' => $this->css80mm(),
            ],
            [
                'ad' => 'Standart 58mm',
                'kod' => 'standart-58mm',
                'sayfa_tipi' => '58mm',
                'sablon_html' => $this->temelSablonHtml(),
                'sablon_css' => $this->css58mm(),
            ],
            [
                'ad' => 'Standart A4',
                'kod' => 'standart-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->temelSablonHtml(),
                'sablon_css' => $this->cssA4(),
            ],
        ];
    }

    private function temelSablonHtml(): string
    {
        return <<<'HTML'
<div class="fis-kapsayici">
    <div class="fis-ust">
        <div>
            <div class="firma-ad">{{FIRMA_UNVAN}}</div>
            <div>Tel: {{FIRMA_TELEFON}}</div>
            <div>{{FIRMA_EPOSTA}}</div>
            <div>{{FIRMA_ADRES}}</div>
        </div>
        <div class="logo-alani">{{FIRMA_LOGO}}</div>
    </div>

    <div class="fis-bilgi-grid">
        <div><strong>Satis No:</strong> {{SATIS_NO}}</div>
        <div><strong>Tarih:</strong> {{SATIS_TARIHI}}</div>
        <div><strong>Cari:</strong> {{CARI_AD}}</div>
        <div><strong>Kasiyer:</strong> {{KASIYER}}</div>
        <div><strong>Odeme:</strong> {{ODEME_TIPI}}</div>
    </div>

    <table class="fis-tablo">
        <thead>
            <tr>
                <th>Urun</th>
                <th>Barkod</th>
                <th>Miktar</th>
                <th>Birim</th>
                <th>Tutar</th>
            </tr>
        </thead>
        <tbody>
            {{KALEMLER}}
        </tbody>
    </table>

    <div class="fis-toplamlar">
        <div><span>Ara Toplam</span><strong>{{ARA_TOPLAM}}</strong></div>
        <div><span>Iskonto</span><strong>{{ISKONTO_TOPLAMI}}</strong></div>
        <div><span>KDV</span><strong>{{KDV_TOPLAMI}}</strong></div>
        <div class="genel-toplam"><span>Genel Toplam</span><strong>{{GENEL_TOPLAM}}</strong></div>
    </div>

    {{ALACAK_PLAN_OZETI}}

    <div class="fis-not">{{SATIS_NOTU}}</div>
</div>
HTML;
    }

    private function cssA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
.fis-kapsayici { color: #111; }
.fis-ust { display: flex; justify-content: space-between; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 8px; }
.firma-ad { font-size: 18px; font-weight: 700; margin-bottom: 3px; }
.logo-alani img { max-height: 64px; max-width: 180px; }
.fis-bilgi-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 4px 12px; margin-bottom: 8px; }
.fis-tablo { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.fis-tablo th, .fis-tablo td { border: 1px solid #ddd; padding: 6px; text-align: left; }
.fis-tablo th:nth-child(n+3), .fis-tablo td:nth-child(n+3) { text-align: right; }
.fis-toplamlar { margin-left: auto; max-width: 320px; }
.fis-toplamlar > div { display: flex; justify-content: space-between; margin-bottom: 4px; }
.fis-toplamlar .genel-toplam { border-top: 1px solid #ddd; padding-top: 4px; font-size: 14px; }
.fis-finans-ozet { margin-top: 8px; border-top: 1px solid #ddd; padding-top: 6px; }
.fis-finans-ozet > div, .fis-taksit-ozet > div { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 3px; }
.fis-taksit-ozet { margin-top: 5px; padding-top: 5px; border-top: 1px dashed #bbb; }
.fis-not { margin-top: 8px; border: 1px solid #ddd; padding: 6px; min-height: 24px; }
CSS;
    }

    private function css80mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans Mono, Arial, sans-serif; font-size: 11px; }
.fis-kapsayici { color: #111; width: 76mm; }
.fis-ust { border-bottom: 1px dashed #888; padding-bottom: 6px; margin-bottom: 6px; }
.firma-ad { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
.logo-alani img { max-height: 42px; max-width: 100%; margin-top: 4px; }
.fis-bilgi-grid { display: grid; grid-template-columns: 1fr; gap: 2px; margin-bottom: 6px; }
.fis-tablo { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.fis-tablo th, .fis-tablo td { border-bottom: 1px dashed #bbb; padding: 3px 1px; }
.fis-tablo th:nth-child(n+3), .fis-tablo td:nth-child(n+3) { text-align: right; }
.fis-toplamlar > div { display: flex; justify-content: space-between; margin-bottom: 3px; }
.fis-toplamlar .genel-toplam { border-top: 1px dashed #888; padding-top: 4px; font-weight: 700; }
.fis-finans-ozet { margin-top: 6px; border-top: 1px dashed #888; padding-top: 5px; }
.fis-finans-ozet > div, .fis-taksit-ozet > div { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 2px; }
.fis-taksit-ozet { margin-top: 4px; padding-top: 4px; border-top: 1px dashed #bbb; }
.fis-not { margin-top: 6px; border-top: 1px dashed #888; padding-top: 4px; }
CSS;
    }

    private function css58mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans Mono, Arial, sans-serif; font-size: 10px; }
.fis-kapsayici { color: #111; width: 54mm; }
.fis-ust { border-bottom: 1px dashed #888; padding-bottom: 5px; margin-bottom: 5px; }
.firma-ad { font-size: 12px; font-weight: 700; margin-bottom: 2px; }
.logo-alani img { max-height: 30px; max-width: 100%; margin-top: 3px; }
.fis-bilgi-grid { display: grid; grid-template-columns: 1fr; gap: 2px; margin-bottom: 5px; }
.fis-tablo { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
.fis-tablo th, .fis-tablo td { border-bottom: 1px dashed #bbb; padding: 2px 0; }
.fis-tablo th:nth-child(2), .fis-tablo td:nth-child(2) { display: none; }
.fis-tablo th:nth-child(n+3), .fis-tablo td:nth-child(n+3) { text-align: right; }
.fis-toplamlar > div { display: flex; justify-content: space-between; margin-bottom: 2px; }
.fis-toplamlar .genel-toplam { border-top: 1px dashed #888; padding-top: 3px; font-weight: 700; }
.fis-finans-ozet { margin-top: 5px; border-top: 1px dashed #888; padding-top: 4px; }
.fis-finans-ozet > div, .fis-taksit-ozet > div { display: flex; justify-content: space-between; gap: 6px; margin-bottom: 2px; }
.fis-taksit-ozet { margin-top: 3px; padding-top: 3px; border-top: 1px dashed #bbb; }
.fis-not { margin-top: 5px; border-top: 1px dashed #888; padding-top: 3px; }
CSS;
    }
}
