<?php

namespace App\Services;

use App\Models\Ecommerce\Siparis;

class EcommerceKargoTakipServisi
{
    public function takipUrl(?string $kargoFirmasi, ?string $takipNo): ?string
    {
        $takipNo = trim((string) $takipNo);
        if ($takipNo === '') {
            return null;
        }

        $firma = mb_strtolower(trim((string) $kargoFirmasi), 'UTF-8');

        return match (true) {
            str_contains($firma, 'aras') => 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code='.rawurlencode($takipNo),
            str_contains($firma, 'ups') => 'https://www.ups.com/track?tracknum='.rawurlencode($takipNo).'&loc=tr_TR&requester=ST/',
            default => null,
        };
    }

    /**
     * @return array<int, array{title:string,description:string,done:bool}>
     */
    public function durumAdimlari(Siparis $siparis): array
    {
        $durum = (string) $siparis->durum;

        return [
            [
                'title' => 'Sipariş alındı',
                'description' => 'Sipariş kaydı oluşturuldu ve sistemde işleme alındı.',
                'done' => true,
            ],
            [
                'title' => 'Ödeme / onay',
                'description' => 'Ödeme doğrulaması tamamlanır ve operasyon hazırlığı başlar.',
                'done' => in_array($durum, [
                    Siparis::DURUM_ONAYLANDI_YENI,
                    Siparis::DURUM_ODENDI,
                    Siparis::DURUM_HAZIRLANIYOR,
                    Siparis::DURUM_GONDERILDI,
                    Siparis::DURUM_KARGOLANDI,
                    Siparis::DURUM_TESLIM_EDILDI,
                    Siparis::DURUM_TAMAMLANDI,
                ], true),
            ],
            [
                'title' => 'Kargoya verildi',
                'description' => $siparis->takip_no
                    ? 'Takip numarası oluşturuldu: '.$siparis->takip_no
                    : 'Kargo firması teslimat için paketi devraldığında tamamlanır.',
                'done' => in_array($durum, [Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI, Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI], true),
            ],
            [
                'title' => 'Teslim edildi',
                'description' => 'Sipariş müşteriye ulaştığında tamamlanır.',
                'done' => in_array($durum, [Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI], true),
            ],
        ];
    }

    public function durumMesaji(Siparis $siparis): string
    {
        return match ((string) $siparis->durum) {
            Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR => 'Siparişiniz alındı. Ödeme ve operasyon onayı bekleniyor.',
            Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR => 'Siparişiniz hazırlanıyor. Kargo bilgisi oluştuğunda takip numarası burada görünecek.',
            Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI => 'Siparişiniz kargoda. Takip numarası ile canlı durum takibi yapabilirsiniz.',
            Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI => 'Siparişiniz teslim edildi.',
            Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL => 'Siparişiniz iptal edildi.',
            default => 'Siparişiniz işleme alındı.',
        };
    }
}
