<?php

namespace App\Services\PersonelTakip;

final class PersonelRaporCsvServisi
{
    public function csvIcerigi(array $rapor): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Kategori', 'Personel', 'Metrik', 'Deger'], ';');

        foreach (($rapor['kpi'] ?? []) as $metrik => $deger) {
            fputcsv($stream, ['KPI', '', (string) $metrik, $this->deger($deger)], ';');
        }

        foreach (($rapor['personel_performansi'] ?? []) as $satir) {
            $this->performansSatiriYaz($stream, 'Personel', $satir, [
                'giris_cikis_sayisi',
                'calisma_dakika',
                'fazla_mesai_dakika',
                'gec_kalma_dakika',
                'erken_cikis_dakika',
            ]);
        }

        foreach (($rapor['restoran_performansi']['garsonlar'] ?? []) as $satir) {
            $this->performansSatiriYaz($stream, 'Restoran Garson', $satir, ['adisyon_sayisi', 'ciro']);
        }

        foreach (($rapor['restoran_performansi']['kasiyerler'] ?? []) as $satir) {
            $this->performansSatiriYaz($stream, 'Restoran Kasiyer', $satir, ['adisyon_sayisi', 'ciro']);
        }

        foreach (($rapor['restoran_performansi']['mutfak'] ?? []) as $satir) {
            $this->performansSatiriYaz($stream, 'Restoran Mutfak', $satir, ['kalem_sayisi', 'toplam_tutar']);
        }

        foreach (($rapor['teknik_servis_performansi']['personeller'] ?? []) as $satir) {
            $this->performansSatiriYaz($stream, 'Teknik Servis', $satir, [
                'gorev_sayisi',
                'aktif_gorev_sayisi',
                'tamamlanan_gorev_sayisi',
            ]);
        }

        rewind($stream);
        $icerik = stream_get_contents($stream);
        fclose($stream);

        return (string) $icerik;
    }

    /**
     * @param resource $stream
     * @param array<string, mixed> $satir
     * @param array<int, string> $metrikler
     */
    private function performansSatiriYaz($stream, string $kategori, array $satir, array $metrikler): void
    {
        $personel = (string) ($satir['ad_soyad'] ?? '-');

        foreach ($metrikler as $metrik) {
            if (! array_key_exists($metrik, $satir)) {
                continue;
            }

            fputcsv($stream, [$kategori, $personel, $metrik, $this->deger($satir[$metrik])], ';');
        }
    }

    private function deger(mixed $deger): string
    {
        if (is_bool($deger)) {
            return $deger ? '1' : '0';
        }

        if (is_float($deger)) {
            return number_format($deger, 2, '.', '');
        }

        return (string) $deger;
    }
}
