<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;
use App\Services\Ecommerce\Pazaryeri\Contracts\PazaryeriSiparisAdaptor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractPazaryeriSiparisAdaptor implements PazaryeriSiparisAdaptor
{
    /**
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    protected function varsayilanApiCagri(
        EcommercePazaryeriEntegrasyon $entegrasyon,
        array $headers = [],
    ): array {
        $ayarlar = (array) ($entegrasyon->ayarlar ?? []);
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);

        $endpoint = trim((string) (Arr::get($ayarlar, 'siparis_endpoint') ?? Arr::get($kimlik, 'siparis_endpoint') ?? ''));
        if ($endpoint === '') {
            throw new RuntimeException('Sipariş çekme endpoint bilgisi eksik.');
        }

        $method = strtoupper(trim((string) Arr::get($ayarlar, 'siparis_http_method', 'GET')));
        if (! in_array($method, ['GET', 'POST'], true)) {
            $method = 'GET';
        }

        $timeout = max(5, (int) Arr::get($ayarlar, 'timeout_saniye', 30));
        $connectTimeout = max(3, min($timeout, (int) Arr::get($ayarlar, 'connect_timeout_saniye', 10)));

        $query = [];
        if (filled(Arr::get($ayarlar, 'siparis_tarihinden'))) {
            $query['from'] = (string) Arr::get($ayarlar, 'siparis_tarihinden');
        }
        if (filled(Arr::get($ayarlar, 'siparis_tarihine'))) {
            $query['to'] = (string) Arr::get($ayarlar, 'siparis_tarihine');
        }
        $query['per_page'] = max(1, (int) Arr::get($ayarlar, 'sayfa_boyutu', 50));

        $request = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->acceptJson()
            ->withHeaders($headers);

        if ($method === 'POST') {
            $response = $request->post($endpoint, $query);
        } else {
            $response = $request->get($endpoint, $query);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Pazaryeri API hatasi: HTTP '.$response->status());
        }

        /** @var array<string, mixed>|list<mixed>|null $json */
        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        $list = Arr::get($json, 'orders');
        if (! is_array($list)) {
            $list = Arr::get($json, 'data');
        }
        if (! is_array($list)) {
            $list = Arr::get($json, 'items');
        }
        if (! is_array($list)) {
            $list = array_is_list($json) ? $json : [];
        }

        $normalized = [];
        foreach ($list as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $disSiparisNo = (string) (Arr::get($raw, 'orderNumber')
                ?? Arr::get($raw, 'orderNo')
                ?? Arr::get($raw, 'id')
                ?? '');
            if ($disSiparisNo === '') {
                continue;
            }

            $normalized[] = [
                'dis_siparis_no' => $disSiparisNo,
                'toplam' => (float) (Arr::get($raw, 'totalPrice')
                    ?? Arr::get($raw, 'total')
                    ?? Arr::get($raw, 'amount')
                    ?? 0),
                'para_birimi' => (string) (Arr::get($raw, 'currencyCode')
                    ?? Arr::get($raw, 'currency')
                    ?? 'TRY'),
                'durum' => (string) (Arr::get($raw, 'status') ?? ''),
                'siparis_tarihi' => (string) (Arr::get($raw, 'createdDate')
                    ?? Arr::get($raw, 'created_at')
                    ?? now()->toIso8601String()),
                'takip_no' => (string) (Arr::get($raw, 'trackingNumber') ?? ''),
                'ham_veri' => $raw,
            ];
        }

        return $normalized;
    }
}

