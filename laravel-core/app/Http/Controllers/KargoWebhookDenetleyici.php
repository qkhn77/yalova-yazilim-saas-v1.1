<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Modules\Urun\Servisler\SiparisDurumGecisServisi;
use App\Modules\Urun\Servisler\SiparisGecmisServisi;
use App\Services\EcommerceBildirimServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class KargoWebhookDenetleyici extends Controller
{
    public function __construct(
        private readonly SiparisDurumGecisServisi $siparisDurumGecisServisi,
        private readonly SiparisGecmisServisi $siparisGecmisServisi,
        private readonly EcommerceBildirimServisi $bildirimServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    public function isle(Request $request, string $entegrasyon): Response
    {
        $entegrasyon = strtolower(trim($entegrasyon));
        if ($entegrasyon === '') {
            return response('Bad Request: entegrasyon zorunludur', 400);
        }

        try {
            $siparis = $this->siparisiBul($request);
            if (! $siparis) {
                $this->log('kargo.callback.hata', [
                    'entegrasyon' => $entegrasyon,
                    'sebep' => 'siparis_bulunamadi',
                ], 'warning');

                return response('Not Found: siparis bulunamadı', 404);
            }

            $kargoYontemi = EcommerceKargoYontemi::query()
                ->where('firma_id', (int) $siparis->firma_id)
                ->where('entegrasyon_aktif', true)
                ->where('entegrasyon', $entegrasyon)
                ->latest('id')
                ->first();

            if (! $kargoYontemi) {
                $this->log('kargo.callback.hata', [
                    'entegrasyon' => $entegrasyon,
                    'siparis_id' => (int) $siparis->id,
                    'sebep' => 'entegrasyon_yok',
                ], 'warning');

                return response('Forbidden: entegrasyon pasif', 403);
            }

            if (! $this->imzaDogrula($request, $kargoYontemi)) {
                $this->log('kargo.callback.hata', [
                    'entegrasyon' => $entegrasyon,
                    'siparis_id' => (int) $siparis->id,
                    'sebep' => 'signature_gecersiz',
                ], 'warning');

                return response('Forbidden: signature doğrulaması başarısız', 403);
            }

            $takipNo = trim((string) $request->input('takip_no', (string) ($siparis->takip_no ?? '')));
            $kargoFirmasi = trim((string) $request->input('kargo_firmasi', (string) ($siparis->kargo_firmasi ?? '')));
            $disDurum = strtolower(trim((string) $request->input('durum', '')));
            $icDurum = $this->durumEsle($disDurum);
            $takipBilgisiDegisti = false;

            $degisim = [];
            if ($takipNo !== '' && $takipNo !== (string) $siparis->takip_no) {
                $degisim['takip_no'] = $takipNo;
                $takipBilgisiDegisti = true;
            }
            if ($kargoFirmasi !== '' && $kargoFirmasi !== (string) $siparis->kargo_firmasi) {
                $degisim['kargo_firmasi'] = $kargoFirmasi;
                $takipBilgisiDegisti = true;
            }
            if ($icDurum === Siparis::DURUM_GONDERILDI && $siparis->kargo_tarihi === null) {
                $degisim['kargo_tarihi'] = now()->toDateString();
            }
            if ($icDurum === Siparis::DURUM_TESLIM_EDILDI && $siparis->teslim_tarihi === null) {
                $degisim['teslim_tarihi'] = now()->toDateString();
            }

            if ($degisim !== []) {
                $siparis->update($degisim);
            }

            $durumDegisti = false;
            if ($icDurum !== null && $icDurum !== $siparis->durum) {
                try {
                    $this->siparisDurumGecisServisi->durumuGuncelle($siparis->fresh(), $icDurum);
                    $durumDegisti = true;
                } catch (Throwable $e) {
                    // Geçersiz geçişte sipariş verisini yine de güncel tutup callback'i 202 ile kabul ediyoruz.
                    $this->log('kargo.callback.uyari', [
                        'entegrasyon' => $entegrasyon,
                        'siparis_id' => (int) $siparis->id,
                        'sebep' => 'durum_gecisi_engellendi',
                        'hata' => mb_substr($e->getMessage(), 0, 300),
                    ], 'warning');
                }
            }

            $guncel = $siparis->fresh();
            if ($takipBilgisiDegisti && ! ($durumDegisti && in_array($icDurum, [Siparis::DURUM_GONDERILDI, Siparis::DURUM_TESLIM_EDILDI], true))) {
                $this->bildirimServisi->kargoBilgisiGuncellendi($guncel);
            }
            $this->siparisGecmisServisi->kaydet(
                $guncel,
                SiparisGecmisi::OLAY_KARGO_GUNCELLENDI,
                'Kargo webhook güncellemesi alındı',
                [
                    'entegrasyon' => $entegrasyon,
                    'dis_durum' => $disDurum,
                    'ic_durum' => $icDurum,
                    'durum_degisti' => $durumDegisti,
                    'takip_no' => $takipNo !== '' ? $takipNo : null,
                ]
            );

            $this->log('kargo.callback.islendi', [
                'entegrasyon' => $entegrasyon,
                'siparis_id' => (int) $guncel->id,
                'durum' => $guncel->durum,
                'takip_no' => $guncel->takip_no,
            ]);

            return response('OK', 200)->header('Content-Type', 'text/plain');
        } catch (Throwable $e) {
            $this->log('kargo.callback.hata', [
                'entegrasyon' => $entegrasyon,
                'hata' => mb_substr($e->getMessage(), 0, 500),
            ], 'error');

            return response('Internal Server Error', 500);
        }
    }

    private function siparisiBul(Request $request): ?Siparis
    {
        $payload = $request->all();
        if ($payload === []) {
            $ham = (string) $request->getContent();
            $cozulmus = json_decode($ham, true);
            if (is_array($cozulmus)) {
                $payload = $cozulmus;
            }
        }

        $siparisId = (int) ($payload['siparis_id'] ?? 0);
        if ($siparisId > 0) {
            return Siparis::query()->find($siparisId);
        }

        $siparisNo = trim((string) ($payload['siparis_no'] ?? ''));
        if ($siparisNo !== '') {
            return Siparis::query()->where('siparis_no', $siparisNo)->latest('id')->first();
        }

        $takipNo = trim((string) ($payload['takip_no'] ?? ''));
        if ($takipNo !== '') {
            return Siparis::query()->where('takip_no', $takipNo)->latest('id')->first();
        }

        return null;
    }

    private function imzaDogrula(Request $request, EcommerceKargoYontemi $kargoYontemi): bool
    {
        $ayarlar = (array) ($kargoYontemi->entegrasyon_ayarlar ?? []);
        $secret = trim((string) ($ayarlar['webhook_secret'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('Kargo webhook secret tanımlı değil.');
        }

        $gelenImza = trim((string) $request->header('X-Webhook-Signature', ''));
        if ($gelenImza === '') {
            return false;
        }

        $hamIcerik = (string) $request->getContent();
        $beklenen = hash_hmac('sha256', $hamIcerik, $secret);

        if (hash_equals($beklenen, $gelenImza)) {
            return true;
        }

        $kanonik = (string) json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($kanonik !== '') {
            $kanonikImza = hash_hmac('sha256', $kanonik, $secret);
            if (hash_equals($kanonikImza, $gelenImza)) {
                return true;
            }
        }

        return false;
    }

    private function durumEsle(string $durum): ?string
    {
        return match ($durum) {
            'kargoda', 'dagitimda', 'gonderildi', 'in_transit', 'shipped', 'out_for_delivery' => Siparis::DURUM_GONDERILDI,
            'teslim_edildi', 'teslim', 'delivered' => Siparis::DURUM_TESLIM_EDILDI,
            'iade_yolda', 'iade_talebi', 'return_in_transit' => Siparis::DURUM_IADE_TALEBI,
            'iade_edildi', 'returned' => Siparis::DURUM_IADE_EDILDI,
            default => null,
        };
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function log(string $message, array $context, string $level = 'info'): void
    {
        Log::channel('stack')->{$level}($message, $context);
        $this->sistemOlayServisi->olayKaydet($message, $level, $message, $context);
    }
}
