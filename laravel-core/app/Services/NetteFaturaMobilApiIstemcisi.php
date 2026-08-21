<?php

namespace App\Services;

use App\Models\Muhasebe\NetteFaturaGelenBelge;
use App\Models\Muhasebe\NetteFaturaGonderimi;
use Illuminate\Support\Carbon;
use RuntimeException;

class NetteFaturaMobilApiIstemcisi
{
    public function __construct(
        private readonly NetteFaturaAyarServisi $ayarServisi,
    ) {}

    /**
     * @return array{eklenen:int,guncellenen:int,toplam:int,e_fatura:int,e_arsiv:int,company_id:int}
     */
    public function gelenBelgeleriSenkronizeEt(int $firmaId, ?Carbon $baslangic = null, ?Carbon $bitis = null): array
    {
        $ayarlar = $this->ayarServisi->ayarlariGetir($firmaId);
        $login = $this->login($firmaId, $ayarlar);
        $companyId = (int) ($ayarlar['nette_fatura_company_id'] ?: $login['company_id']);
        if ($companyId < 1) {
            throw new RuntimeException('NetteFatura CompanyId bulunamadı. API girişinden firma listesi dönmedi.');
        }

        $baslangic ??= now()->subDays(30)->startOfDay();
        $bitis ??= now()->endOfDay();

        $eFatura = $this->listeCek($firmaId, $ayarlar, $login['token'], 'GetIncomingEInvoiceList', [
            'CompanyId' => $companyId,
            'FirstInvoiceDate' => $baslangic->format('Y-m-d'),
            'LastInvoiceDate' => $bitis->format('Y-m-d'),
            'PageIndex' => 0,
            'PageSize' => 100,
            'IsArchiveIncluded' => true,
        ]);

        $eArsiv = $this->listeCek($firmaId, $ayarlar, $login['token'], 'GetEArchiveInvoiceList', [
            'CompanyId' => $companyId,
            'FirstInvoiceDate' => $baslangic->format('Y-m-d'),
            'LastInvoiceDate' => $bitis->format('Y-m-d'),
            'IsStaging' => false,
            'IsCanceled' => false,
            'IsSended' => true,
            'IsErrorReport' => false,
            'PageIndex' => 0,
            'PageSize' => 100,
            'IsArchiveIncluded' => true,
        ]);

        $sayac = ['eklenen' => 0, 'guncellenen' => 0, 'toplam' => 0, 'e_fatura' => 0, 'e_arsiv' => 0, 'company_id' => $companyId];
        foreach ([['e_fatura', $eFatura], ['e_arsiv', $eArsiv]] as [$tur, $liste]) {
            foreach ($liste as $belge) {
                $sonuc = $this->gelenBelgeKaydet($firmaId, $tur, $belge);
                $sayac[$sonuc]++;
                $sayac[$tur]++;
                $sayac['toplam']++;
            }
        }

        return $sayac;
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @return array{token:string,company_id:int}
     */
    private function login(int $firmaId, array $ayarlar): array
    {
        $username = trim((string) ($ayarlar['nette_fatura_kullanici_adi'] ?? ''));
        $password = (string) ($ayarlar['nette_fatura_sifre'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('NetteFatura kullanıcı adı ve şifre girilmeden gelen belge çekilemez.');
        }

        $cevap = $this->postJson($firmaId, $ayarlar, 'Account/Login', [
            'IdentificationNumber' => $username,
            'Password' => $password,
        ], null, 'mobileLogin', ['IdentificationNumber']);

        $token = trim((string) ($cevap['Token'] ?? ''));
        if ($token === '') {
            $hata = trim((string) ($cevap['ErrorMessage'] ?? ''));
            throw new RuntimeException($hata !== '' ? $hata : 'NetteFatura mobil API token dönmedi.');
        }

        $companies = is_array($cevap['CompanyList'] ?? null) ? $cevap['CompanyList'] : [];
        $companyId = 0;
        if ($companies !== []) {
            $companyId = (int) ($companies[0]['IdFirma'] ?? 0);
        }

        return ['token' => $token, 'company_id' => $companyId];
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function listeCek(int $firmaId, array $ayarlar, string $token, string $endpoint, array $payload): array
    {
        $cevap = $this->postJson($firmaId, $ayarlar, 'Invoice/'.$endpoint, $payload, $token, $endpoint);
        if (! is_array($cevap['Invoices'] ?? null)) {
            return [];
        }

        return $cevap['Invoices'];
    }

    /**
     * @param  array<string, mixed>  $belge
     */
    private function gelenBelgeKaydet(int $firmaId, string $tur, array $belge): string
    {
        $providerId = (string) ($belge['InvoiceId'] ?? '');
        if ($providerId === '') {
            $providerId = (string) ($belge['Ettn'] ?? $belge['InvoiceNumber'] ?? md5(json_encode($belge)));
        }

        $kayit = NetteFaturaGelenBelge::query()->updateOrCreate(
            [
                'firma_id' => $firmaId,
                'belge_turu' => $tur,
                'provider_invoice_id' => $providerId,
            ],
            [
                'invoice_number' => $this->str($belge['InvoiceNumber'] ?? null, 64),
                'invoice_date' => $this->dateOrNull($belge['InvoiceDate'] ?? null),
                'company_name' => $this->str($belge['RecipientCompanyName'] ?? null, 255),
                'total_amount' => number_format((float) ($belge['InvoiceTotalLineAmount'] ?? 0), 8, '.', ''),
                'currency_code' => $this->str($belge['CurrencyCode'] ?? null, 8),
                'status' => $this->str($belge['Status'] ?? null, 128),
                'report_status' => $this->str($belge['ReportStatus'] ?? null, 128),
                'cancel_report_status' => $this->str($belge['CancelReportStatus'] ?? null, 128),
                'ettn' => $this->uuidOrNull($belge['Ettn'] ?? null),
                'raw_payload' => $belge,
                'last_synced_at' => now(),
            ]
        );

        return $kayit->wasRecentlyCreated ? 'eklenen' : 'guncellenen';
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $logPayloadKeys
     * @return array<string, mixed>
     */
    private function postJson(int $firmaId, array $ayarlar, string $path, array $payload, ?string $token, string $islemTipi, array $logPayloadKeys = []): array
    {
        $base = rtrim((string) $ayarlar['nette_fatura_mobile_api_url'], '/');
        $url = $base.'/'.ltrim($path, '/');
        $timeout = (int) $ayarlar['nette_fatura_zaman_asimi_saniye'];
        $baslangic = microtime(true);

        $log = NetteFaturaGonderimi::query()->create([
            'firma_id' => $firmaId,
            'islem_tipi' => $islemTipi,
            'durum' => 'gonderiliyor',
            'endpoint' => $url,
            'request_meta' => [
                'payload' => array_intersect_key($payload, array_flip($logPayloadKeys)),
            ],
            'sent_at' => now(),
        ]);

        $headers = [
            'Accept: application/json, text/json, */*',
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: YalovaKamera/1.0',
        ];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
            $headers[] = 'Token: '.$token;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Curl oturumu başlatılamadı.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        $sureMs = round((microtime(true) - $baslangic) * 1000, 2);
        if ($response === false) {
            $log->update([
                'durum' => 'hatali',
                'error_message' => $error ?: 'Bilinmeyen curl hatası',
                'response_meta' => ['sure_ms' => $sureMs],
                'responded_at' => now(),
            ]);

            throw new RuntimeException($error ?: 'Bilinmeyen curl hatası');
        }

        $decoded = json_decode((string) $response, true);
        $basarili = $http >= 200 && $http < 300 && is_array($decoded);
        $hata = $basarili ? null : trim(mb_substr((string) $response, 0, 1000));
        if (! $basarili && $hata === '') {
            $hata = match ($http) {
                401 => 'HTTP 401: NetteFatura mobil API kullanıcıyı yetkisiz gördü. Web/Mobile API erişimi veya kullanıcı bilgileri kontrol edilmeli.',
                403 => 'HTTP 403: NetteFatura mobil API bu kullanıcı için işlemi yasakladı.',
                default => 'HTTP '.$http.': NetteFatura mobil API boş hata yanıtı döndü.',
            };
        }
        $log->update([
            'durum' => $basarili ? 'basarili' : 'hatali',
            'response_message' => $basarili ? 'Mobil API yanıtı alındı.' : 'Mobil API isteği başarısız.',
            'error_message' => $hata !== '' ? $hata : null,
            'response_meta' => [
                'http_kodu' => $http,
                'content_type' => $contentType,
                'sure_ms' => $sureMs,
            ],
            'responded_at' => now(),
        ]);

        if (! $basarili) {
            throw new RuntimeException($hata);
        }

        return $decoded;
    }

    private function str(mixed $value, int $limit): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return preg_match('/^[0-9a-fA-F-]{36}$/', $text) ? $text : null;
    }
}
