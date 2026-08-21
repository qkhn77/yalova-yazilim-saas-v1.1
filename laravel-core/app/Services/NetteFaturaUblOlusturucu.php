<?php

namespace App\Services;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Muhasebe\Enumlar\FaturaTuru;
use Illuminate\Support\Str;
use RuntimeException;
use XMLWriter;

class NetteFaturaUblOlusturucu
{
    public function __construct(
        private readonly NetteFaturaAyarServisi $ayarServisi,
    ) {}

    /**
     * @return array{xml:string,uuid:string,dosya_adi:string,hash:string}
     */
    public function olustur(Fatura $fatura, ?string $uuid = null): array
    {
        $fatura->load([
            'firma:id,ad,vergi_no,telefon,eposta,adres',
            'cari:id,ad,vergi_dairesi,vergi_no,tc_no,telefon,gsm,email,adres,il,ilce,posta_kodu',
            'kalemler' => fn ($q) => $q->orderBy('satir_no')->orderBy('id'),
            'kalemler.stokKarti:id,kod,ad',
        ]);

        if (! $fatura->cari) {
            throw new RuntimeException('UBL XML için fatura carisi seçili olmalı.');
        }

        if ($fatura->kalemler->isEmpty()) {
            throw new RuntimeException('UBL XML için faturada en az bir kalem olmalı.');
        }

        $ayarlar = $this->ayarServisi->ayarlariGetir((int) $fatura->firma_id);
        $uuid = $uuid ?: (string) ($fatura->e_belge_uuid ?: Str::uuid());
        $paraBirimi = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));
        $faturaNo = $this->faturaNo($fatura);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startElement('Invoice');
        $xml->writeAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xml->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xml->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->text($xml, 'cbc:UBLVersionID', '2.1');
        $this->text($xml, 'cbc:CustomizationID', 'TR1.2');
        $this->text($xml, 'cbc:ProfileID', $this->profilId($fatura));
        $this->text($xml, 'cbc:ID', $faturaNo);
        $this->text($xml, 'cbc:CopyIndicator', 'false');
        $this->text($xml, 'cbc:UUID', $uuid);
        $this->text($xml, 'cbc:IssueDate', $this->tarih($fatura->tarih));
        $this->text($xml, 'cbc:IssueTime', $this->saat($fatura->tarih));
        $this->text($xml, 'cbc:InvoiceTypeCode', $this->faturaTipKodu($fatura));
        $this->text($xml, 'cbc:DocumentCurrencyCode', $paraBirimi);
        $this->text($xml, 'cbc:LineCountNumeric', (string) $fatura->kalemler->count());

        $this->party($xml, 'cac:AccountingSupplierParty', [
            'unvan' => $ayarlar['nette_fatura_gonderici_unvan'] ?: ($fatura->firma?->ad ?? ''),
            'vergi_no' => $ayarlar['nette_fatura_gonderici_vergi_no'] ?: ($fatura->firma?->vergi_no ?? ''),
            'vergi_dairesi' => $ayarlar['nette_fatura_gonderici_vergi_dairesi'] ?: '',
            'adres' => $ayarlar['nette_fatura_gonderici_adres'] ?: ($fatura->firma?->adres ?? ''),
            'il' => $ayarlar['nette_fatura_gonderici_il'] ?: '',
            'ilce' => $ayarlar['nette_fatura_gonderici_ilce'] ?: '',
            'ulke' => $ayarlar['nette_fatura_gonderici_ulke'] ?: 'Türkiye',
            'email' => $ayarlar['nette_fatura_gonderici_eposta'] ?: ($fatura->firma?->eposta ?? ''),
            'telefon' => $ayarlar['nette_fatura_gonderici_telefon'] ?: ($fatura->firma?->telefon ?? ''),
        ]);

        $this->party($xml, 'cac:AccountingCustomerParty', [
            'unvan' => $fatura->cari->ad,
            'vergi_no' => $fatura->cari->vergi_no ?: $fatura->cari->tc_no,
            'vergi_dairesi' => $fatura->cari->vergi_dairesi,
            'adres' => $fatura->cari->adres,
            'il' => $fatura->cari->il,
            'ilce' => $fatura->cari->ilce,
            'posta_kodu' => $fatura->cari->posta_kodu,
            'ulke' => 'Türkiye',
            'email' => $fatura->cari->email,
            'telefon' => $fatura->cari->telefon ?: $fatura->cari->gsm,
        ]);

        $this->taxTotal($xml, (string) ($fatura->kdv_toplam ?? '0'), $paraBirimi, $this->kdvGruplari($fatura), true);
        $this->legalMonetaryTotal($xml, $fatura, $paraBirimi);

        foreach ($fatura->kalemler as $sira => $kalem) {
            $this->invoiceLine($xml, $kalem, $paraBirimi, $sira + 1);
        }

        $xml->endElement();
        $xml->endDocument();

        $icerik = $xml->outputMemory();

        return [
            'xml' => $icerik,
            'uuid' => $uuid,
            'dosya_adi' => $this->dosyaAdi($faturaNo),
            'hash' => hash('sha256', $icerik),
        ];
    }

    /**
     * @param array<string, mixed> $veri
     */
    private function party(XMLWriter $xml, string $root, array $veri): void
    {
        $xml->startElement($root);
        $xml->startElement('cac:Party');

        $vergiNo = preg_replace('/\D+/', '', (string) ($veri['vergi_no'] ?? '')) ?: '';
        $scheme = strlen($vergiNo) === 11 ? 'TCKN' : 'VKN';
        $this->elementWithAttrs($xml, 'cbc:EndpointID', $vergiNo, ['schemeID' => $scheme]);

        $xml->startElement('cac:PartyIdentification');
        $this->elementWithAttrs($xml, 'cbc:ID', $vergiNo, ['schemeID' => $scheme]);
        $xml->endElement();

        $xml->startElement('cac:PartyName');
        $this->text($xml, 'cbc:Name', $this->temizMetin($veri['unvan'] ?? ''));
        $xml->endElement();

        $xml->startElement('cac:PostalAddress');
        $this->text($xml, 'cbc:StreetName', $this->temizMetin($veri['adres'] ?? ''));
        $this->text($xml, 'cbc:CitySubdivisionName', $this->temizMetin($veri['ilce'] ?? ''));
        $this->text($xml, 'cbc:CityName', $this->temizMetin($veri['il'] ?? ''));
        if (! empty($veri['posta_kodu'])) {
            $this->text($xml, 'cbc:PostalZone', $this->temizMetin($veri['posta_kodu']));
        }
        $xml->startElement('cac:Country');
        $this->text($xml, 'cbc:Name', $this->temizMetin($veri['ulke'] ?? 'Türkiye'));
        $xml->endElement();
        $xml->endElement();

        if (! empty($veri['vergi_dairesi']) || $vergiNo !== '') {
            $xml->startElement('cac:PartyTaxScheme');
            if (! empty($veri['vergi_dairesi'])) {
                $this->text($xml, 'cbc:Name', $this->temizMetin($veri['vergi_dairesi']));
            }
            $xml->startElement('cac:TaxScheme');
            $this->text($xml, 'cbc:Name', 'KDV');
            $xml->endElement();
            $xml->endElement();
        }

        $xml->startElement('cac:Contact');
        if (! empty($veri['telefon'])) {
            $this->text($xml, 'cbc:Telephone', $this->temizMetin($veri['telefon']));
        }
        if (! empty($veri['email'])) {
            $this->text($xml, 'cbc:ElectronicMail', $this->temizMetin($veri['email']));
        }
        $xml->endElement();

        $xml->endElement();
        $xml->endElement();
    }

    /**
     * @param  array<int, array{oran:string,matrah:string,kdv:string}>  $gruplar
     */
    private function taxTotal(XMLWriter $xml, string $toplamKdv, string $paraBirimi, array $gruplar, bool $detayli): void
    {
        $xml->startElement('cac:TaxTotal');
        $this->amount($xml, 'cbc:TaxAmount', $toplamKdv, $paraBirimi);

        if ($detayli) {
            foreach ($gruplar as $grup) {
                $xml->startElement('cac:TaxSubtotal');
                $this->amount($xml, 'cbc:TaxableAmount', $grup['matrah'], $paraBirimi);
                $this->amount($xml, 'cbc:TaxAmount', $grup['kdv'], $paraBirimi);
                $this->text($xml, 'cbc:CalculationSequenceNumeric', '1');
                $this->text($xml, 'cbc:Percent', $this->oran($grup['oran']));
                $xml->startElement('cac:TaxCategory');
                $this->text($xml, 'cbc:Percent', $this->oran($grup['oran']));
                $this->kdvTaxScheme($xml);
                $xml->endElement();
                $xml->endElement();
            }
        }

        $xml->endElement();
    }

    private function legalMonetaryTotal(XMLWriter $xml, Fatura $fatura, string $paraBirimi): void
    {
        $xml->startElement('cac:LegalMonetaryTotal');
        $this->amount($xml, 'cbc:LineExtensionAmount', (string) ($fatura->ara_toplam ?? '0'), $paraBirimi);
        $this->amount($xml, 'cbc:TaxExclusiveAmount', (string) ($fatura->ara_toplam ?? '0'), $paraBirimi);
        $this->amount($xml, 'cbc:TaxInclusiveAmount', (string) ($fatura->genel_toplam ?? '0'), $paraBirimi);
        $this->amount($xml, 'cbc:AllowanceTotalAmount', (string) ($fatura->toplam_indirim ?? '0'), $paraBirimi);
        $this->amount($xml, 'cbc:PayableAmount', (string) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? '0'), $paraBirimi);
        $xml->endElement();
    }

    private function invoiceLine(XMLWriter $xml, FaturaKalemi $kalem, string $paraBirimi, int $sira): void
    {
        $xml->startElement('cac:InvoiceLine');
        $this->text($xml, 'cbc:ID', (string) ($kalem->satir_no ?: $sira));
        $this->elementWithAttrs($xml, 'cbc:InvoicedQuantity', $this->miktar((string) ($kalem->miktar ?? '0')), ['unitCode' => $this->unitCode((string) ($kalem->birim ?: 'AD'))]);
        $this->amount($xml, 'cbc:LineExtensionAmount', (string) ($kalem->satir_toplami ?? $kalem->net_tutar ?? '0'), $paraBirimi);

        $indirim = (string) ($kalem->satir_indirim_tutari ?? $kalem->indirim_tutari ?? '0');
        if ((float) $indirim > 0) {
            $xml->startElement('cac:AllowanceCharge');
            $this->text($xml, 'cbc:ChargeIndicator', 'false');
            $this->amount($xml, 'cbc:Amount', $indirim, $paraBirimi);
            $xml->endElement();
        }

        $this->taxTotal($xml, (string) ($kalem->kdv_tutari ?? '0'), $paraBirimi, [[
            'oran' => (string) ($kalem->kdv_orani ?? '0'),
            'matrah' => (string) ($kalem->satir_toplami ?? $kalem->net_tutar ?? '0'),
            'kdv' => (string) ($kalem->kdv_tutari ?? '0'),
        ]], true);

        $xml->startElement('cac:Item');
        $this->text($xml, 'cbc:Name', $this->kalemAdi($kalem));
        $xml->endElement();

        $xml->startElement('cac:Price');
        $this->elementWithAttrs($xml, 'cbc:PriceAmount', $this->para8((string) ($kalem->birim_fiyat ?? '0')), ['currencyID' => $paraBirimi]);
        $xml->endElement();

        $xml->endElement();
    }

    /**
     * @return array<int, array{oran:string,matrah:string,kdv:string}>
     */
    private function kdvGruplari(Fatura $fatura): array
    {
        $gruplar = [];
        foreach ($fatura->kalemler as $kalem) {
            $oran = $this->oran((string) ($kalem->kdv_orani ?? '0'));
            $gruplar[$oran] ??= ['oran' => $oran, 'matrah' => '0', 'kdv' => '0'];
            $gruplar[$oran]['matrah'] = bcadd($gruplar[$oran]['matrah'], (string) ($kalem->satir_toplami ?? $kalem->net_tutar ?? '0'), 8);
            $gruplar[$oran]['kdv'] = bcadd($gruplar[$oran]['kdv'], (string) ($kalem->kdv_tutari ?? '0'), 8);
        }

        return array_values($gruplar);
    }

    private function kdvTaxScheme(XMLWriter $xml): void
    {
        $xml->startElement('cac:TaxScheme');
        $this->text($xml, 'cbc:Name', 'KDV');
        $this->text($xml, 'cbc:TaxTypeCode', '0015');
        $xml->endElement();
    }

    /**
     * @param  array<string, string>  $attrs
     */
    private function elementWithAttrs(XMLWriter $xml, string $name, string $value, array $attrs): void
    {
        $xml->startElement($name);
        foreach ($attrs as $attr => $attrValue) {
            $xml->writeAttribute($attr, $attrValue);
        }
        $xml->text($value);
        $xml->endElement();
    }

    private function amount(XMLWriter $xml, string $name, string $value, string $paraBirimi): void
    {
        $this->elementWithAttrs($xml, $name, $this->para2($value), ['currencyID' => $paraBirimi]);
    }

    private function text(XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElement($name, $value);
    }

    private function profilId(Fatura $fatura): string
    {
        return (string) $fatura->e_belge_tipi === 'e_arsiv' ? 'EARSIVFATURA' : 'TICARIFATURA';
    }

    private function faturaTipKodu(Fatura $fatura): string
    {
        $tur = $fatura->tur instanceof FaturaTuru ? $fatura->tur->kanonik() : FaturaTuru::tryFrom((string) $fatura->tur)?->kanonik();

        return in_array($tur, [FaturaTuru::SatisIadesi, FaturaTuru::AlisIadesi], true) ? 'IADE' : 'SATIS';
    }

    private function faturaNo(Fatura $fatura): string
    {
        return $this->temizMetin($fatura->fatura_no ?: $fatura->belge_no ?: 'FTR-'.$fatura->getKey());
    }

    private function dosyaAdi(string $faturaNo): string
    {
        $guvenli = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $faturaNo) ?: 'fatura';

        return trim($guvenli, '-').'.xml';
    }

    private function kalemAdi(FaturaKalemi $kalem): string
    {
        $ad = $kalem->aciklama ?: $kalem->stokKarti?->ad ?: $kalem->stokKarti?->kod ?: 'Fatura kalemi';

        return $this->temizMetin($ad);
    }

    private function unitCode(string $birim): string
    {
        return match (Str::upper(trim($birim))) {
            'KG', 'KGM' => 'KGM',
            'M', 'MT', 'MTR' => 'MTR',
            'SAAT', 'HUR' => 'HUR',
            default => 'C62',
        };
    }

    private function tarih(mixed $tarih): string
    {
        return $tarih ? date('Y-m-d', strtotime((string) $tarih)) : now()->format('Y-m-d');
    }

    private function saat(mixed $tarih): string
    {
        return $tarih ? date('H:i:s', strtotime((string) $tarih)) : now()->format('H:i:s');
    }

    private function para2(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function para8(string $value): string
    {
        return number_format((float) $value, 8, '.', '');
    }

    private function miktar(string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function oran(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function temizMetin(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }
}
