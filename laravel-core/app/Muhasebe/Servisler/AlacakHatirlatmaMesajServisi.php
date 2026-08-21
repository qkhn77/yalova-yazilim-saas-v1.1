<?php

namespace App\Muhasebe\Servisler;

use App\Models\Firma;

final class AlacakHatirlatmaMesajServisi
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function mesajlar(
        int $firmaId,
        string $kanal = 'whatsapp',
        int $yaklasanGun = 7,
        int $limit = 20,
        ?string $sablon = null
    ): array {
        $kanal = $this->kanal($kanal);
        $firmaAd = (string) (Firma::query()->whereKey($firmaId)->value('ad') ?: 'Firmamiz');
        $ozet = app(AlacakHatirlatmaServisi::class)->ozet($firmaId, $yaklasanGun, $limit);
        $sablon = trim((string) ($sablon ?? ''));
        if ($sablon === '') {
            $sablon = $this->varsayilanSablon($kanal);
        }

        return collect($ozet['satirlar'] ?? [])
            ->map(function (array $satir) use ($kanal, $firmaAd, $sablon): array {
                $hedef = $this->hedef($satir, $kanal);
                $mesaj = $this->metniDoldur($sablon, $satir, $firmaAd);

                return $satir + [
                    'kanal' => $kanal,
                    'hedef' => $hedef,
                    'baslik' => $this->varsayilanBaslik($satir, $firmaAd),
                    'mesaj' => $mesaj,
                    'whatsapp_url' => $kanal === 'whatsapp' && $hedef !== ''
                        ? 'https://wa.me/'.$hedef.'?text='.urlencode($mesaj)
                        : null,
                    'hazir_mi' => $hedef !== '',
                    'durum' => $hedef !== '' ? 'hazir' : 'hedef_yok',
                ];
            })
            ->values()
            ->all();
    }

    public function whatsappUrl(array $satir, ?string $sablon = null): ?string
    {
        $firmaId = (int) ($satir['firma_id'] ?? 0);
        $firmaAd = $firmaId > 0
            ? (string) (Firma::query()->whereKey($firmaId)->value('ad') ?: 'Firmamiz')
            : 'Firmamiz';
        $telefon = $this->hedef($satir, 'whatsapp');
        if ($telefon === '') {
            return null;
        }

        $metin = $this->metniDoldur(
            trim((string) ($sablon ?? '')) !== '' ? (string) $sablon : $this->varsayilanSablon('whatsapp'),
            $satir,
            $firmaAd
        );

        return 'https://wa.me/'.$telefon.'?text='.urlencode($metin);
    }

    public function varsayilanSablon(string $kanal): string
    {
        return match ($this->kanal($kanal)) {
            'sms' => 'Sayin {cari_ad}, {ilk_vade_tarihi} tarihli vadeniz dahil {kalan_toplam} {para_birimi} acik bakiyeniz bulunuyor. Geciken: {geciken_toplam} {para_birimi}. {firma_ad}',
            'email' => implode("\n\n", [
                'Sayin {cari_ad},',
                '{firma_ad} cari hesap kayitlarina gore {ilk_vade_tarihi} tarihli ilk vadeniz dahil toplam {kalan_toplam} {para_birimi} acik bakiyeniz bulunmaktadir.',
                'Ozet:',
                '- Vade adedi: {vade_adedi}',
                '- Geciken toplam: {geciken_toplam} {para_birimi}',
                '- Bugun vadesi gelen: {bugun_toplam} {para_birimi}',
                'Odeme plani veya mutabakat icin bizimle iletisime gecebilirsiniz.',
                'Saygilarimizla,'."\n".'{firma_ad}',
            ]),
            default => implode("\n\n", [
                'Merhaba {cari_ad},',
                '{firma_ad} cari hesap kayitlarina gore {ilk_vade_tarihi} tarihli ilk vadeniz dahil toplam {kalan_toplam} {para_birimi} acik bakiyeniz bulunuyor.',
                "Ozet:\n- Vade adedi: {vade_adedi}\n- Geciken: {geciken_toplam} {para_birimi}\n- Bugun: {bugun_toplam} {para_birimi}",
                'Odeme plani veya mutabakat icin bizimle iletisime gecebilirsiniz.',
            ]),
        };
    }

    /**
     * @return array<string,string>
     */
    public function degiskenler(): array
    {
        return [
            '{firma_ad}' => 'Firma adı',
            '{cari_ad}' => 'Cari adı',
            '{cari_kod}' => 'Cari kodu',
            '{para_birimi}' => 'Para birimi',
            '{vade_adedi}' => 'Açık vade adedi',
            '{kalan_toplam}' => 'Toplam açık bakiye',
            '{geciken_toplam}' => 'Geciken bakiye',
            '{bugun_toplam}' => 'Bugünkü vade bakiyesi',
            '{ilk_vade_tarihi}' => 'İlk açık vade tarihi',
        ];
    }

    private function metniDoldur(string $sablon, array $satir, string $firmaAd): string
    {
        $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));

        return strtr($sablon, [
            '{firma_ad}' => $firmaAd,
            '{cari_ad}' => (string) ($satir['cari_ad'] ?? '-'),
            '{cari_kod}' => (string) ($satir['cari_kod'] ?? '-'),
            '{para_birimi}' => $paraBirimi,
            '{vade_adedi}' => (string) ($satir['vade_adedi'] ?? 0),
            '{kalan_toplam}' => $this->para((float) ($satir['kalan_toplam'] ?? 0)),
            '{geciken_toplam}' => $this->para((float) ($satir['geciken_toplam'] ?? 0)),
            '{bugun_toplam}' => $this->para((float) ($satir['bugun_toplam'] ?? 0)),
            '{ilk_vade_tarihi}' => $this->tarih((string) ($satir['ilk_vade_tarihi'] ?? '')),
        ]);
    }

    private function varsayilanBaslik(array $satir, string $firmaAd): string
    {
        return $firmaAd.' vade hatirlatmasi - '.(string) ($satir['cari_ad'] ?? '-');
    }

    private function hedef(array $satir, string $kanal): string
    {
        if ($kanal === 'email') {
            return trim((string) ($satir['cari_email'] ?? ''));
        }

        return $this->telefonNormalize((string) (($satir['cari_gsm'] ?? '') ?: ($satir['cari_telefon'] ?? '')));
    }

    private function telefonNormalize(string $telefon): string
    {
        $telefon = preg_replace('/\D+/', '', $telefon) ?? '';
        if ($telefon === '') {
            return '';
        }

        if (str_starts_with($telefon, '0')) {
            $telefon = '90'.substr($telefon, 1);
        } elseif (! str_starts_with($telefon, '90')) {
            $telefon = '90'.$telefon;
        }

        return strlen($telefon) >= 11 ? $telefon : '';
    }

    private function kanal(string $kanal): string
    {
        return in_array($kanal, ['whatsapp', 'sms', 'email'], true) ? $kanal : 'whatsapp';
    }

    private function para(float $tutar): string
    {
        return number_format($tutar, 2, ',', '.');
    }

    private function tarih(string $tarih): string
    {
        if ($tarih === '') {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($tarih)->format('d.m.Y');
        } catch (\Throwable) {
            return $tarih;
        }
    }
}
