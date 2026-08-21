<?php

namespace App\Services;

class AuditOlaySunumServisi
{
    /**
     * @return array<string, string>
     */
    private function etiketler(): array
    {
        return [
            'fatura.guncelle' => 'Fatura güncellendi',
            'fatura_kalemi.eklendi' => 'Fatura kalemi eklendi',
            'fatura_kalemi.guncelle' => 'Fatura kalemi değiştirildi',
            'fatura_kalemi.silindi' => 'Fatura kalemi silindi',
            'cari_karti.guncelle' => 'Cari kartı güncellendi',
            'cari.para_birimi_degisim_engellendi' => 'Cari para birimi değişimi engellendi',
            'stok_karti.guncelle' => 'Stok kartı güncellendi',
            'siparis.guncelle' => 'Sipariş güncellendi',
            'siparis.manuel_odeme_onayi' => 'Sipariş manuel onaylandı',
            'odeme_ayari.guncelle' => 'Ödeme ayarları güncellendi',
            'export.olusturuldu' => 'Muhasebe export dosyası oluşturuldu',
            'reconcile.tutarsizlik_bulundu' => 'Reconciliation tutarsızlığı bulundu',
            'reconcile.fix_basladi' => 'Reconciliation düzeltmesi başlatıldı',
            'reconcile.fix_basarili' => 'Reconciliation düzeltmesi uygulandı',
            'reconcile.fix_hata' => 'Reconciliation düzeltmesi hata verdi',
        ];
    }

    public function etiket(string $olay): string
    {
        return $this->etiketler()[$olay] ?? $olay;
    }

    public function kritikMi(string $olay): bool
    {
        return in_array($olay, [
            'cari.para_birimi_degisim_engellendi',
            'siparis.manuel_odeme_onayi',
            'reconcile.tutarsizlik_bulundu',
            'reconcile.fix_basladi',
            'reconcile.fix_basarili',
            'reconcile.fix_hata',
        ], true);
    }
}
