<?php

namespace App\Services;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\NetteFaturaGonderimi;
use DOMDocument;
use RuntimeException;
use Throwable;

class NetteFaturaIstemcisi
{
    private const SOAP_NS = 'http://www.w3.org/2003/05/soap-envelope';

    private const EFATURA_NS = 'http://gib.gov.tr/vedop3/eFatura';

    public function __construct(
        private readonly NetteFaturaAyarServisi $ayarServisi,
        private readonly NetteFaturaUblOlusturucu $ublOlusturucu,
    ) {}

    /**
     * @return array{basarili:bool,http_kodu:int|null,mesaj:string,sure_ms:float|null}
     */
    public function baglantiTesti(int $firmaId): array
    {
        $ayarlar = $this->ayarServisi->ayarlariGetir($firmaId);
        $url = (string) $ayarlar['nette_fatura_wsdl_url'];
        $timeout = (int) $ayarlar['nette_fatura_zaman_asimi_saniye'];
        $baslangic = microtime(true);

        $log = NetteFaturaGonderimi::query()->create([
            'firma_id' => $firmaId,
            'islem_tipi' => 'connectionTest',
            'durum' => 'gonderiliyor',
            'endpoint' => $url,
            'request_meta' => [
                'test_modu' => (bool) $ayarlar['nette_fatura_test_modu'],
                'soap_extension_loaded' => extension_loaded('soap'),
            ],
            'sent_at' => now(),
        ]);

        try {
            $cevap = $this->httpGet($url, $timeout, (string) $ayarlar['nette_fatura_kullanici_adi'], (string) $ayarlar['nette_fatura_sifre']);
            $sureMs = round((microtime(true) - $baslangic) * 1000, 2);
            $basarili = $cevap['http_kodu'] >= 200 && $cevap['http_kodu'] < 400 && str_contains(mb_strtolower($cevap['body']), 'definitions');
            $mesaj = $basarili
                ? 'NetteFatura WSDL erişimi başarılı.'
                : 'NetteFatura WSDL yanıtı beklenen yapıda değil.';

            $log->update([
                'durum' => $basarili ? 'basarili' : 'hatali',
                'response_message' => $mesaj,
                'error_message' => $basarili ? null : mb_substr($cevap['body'], 0, 2000),
                'response_meta' => [
                    'http_kodu' => $cevap['http_kodu'],
                    'content_type' => $cevap['content_type'],
                    'body_prefix' => mb_substr($cevap['body'], 0, 500),
                    'sure_ms' => $sureMs,
                ],
                'responded_at' => now(),
            ]);

            return [
                'basarili' => $basarili,
                'http_kodu' => $cevap['http_kodu'],
                'mesaj' => $mesaj,
                'sure_ms' => $sureMs,
            ];
        } catch (RuntimeException $e) {
            $sureMs = round((microtime(true) - $baslangic) * 1000, 2);
            $log->update([
                'durum' => 'hatali',
                'error_message' => $e->getMessage(),
                'response_meta' => ['sure_ms' => $sureMs],
                'responded_at' => now(),
            ]);

            return [
                'basarili' => false,
                'http_kodu' => null,
                'mesaj' => $e->getMessage(),
                'sure_ms' => $sureMs,
            ];
        }
    }

    /**
     * @return array{basarili:bool,http_kodu:int|null,mesaj:string,uuid:string,dosya_adi:string,hash:string,provider_hash:?string,instance_identifier:?string}
     */
    public function belgeGonder(Fatura $fatura): array
    {
        $ayarlar = $this->ayarServisi->ayarlariGetir((int) $fatura->firma_id);
        if (! (bool) $ayarlar['nette_fatura_aktif_mi']) {
            throw new RuntimeException('NetteFatura entegrasyonu aktif değil.');
        }

        $ubl = $this->ublOlusturucu->olustur($fatura);
        $endpoint = (string) $ayarlar['nette_fatura_service_url'];
        $baslangic = microtime(true);

        $log = NetteFaturaGonderimi::query()->create([
            'firma_id' => (int) $fatura->firma_id,
            'fatura_id' => (int) $fatura->getKey(),
            'islem_tipi' => 'sendDocument',
            'durum' => 'gonderiliyor',
            'endpoint' => $endpoint,
            'dosya_adi' => $ubl['dosya_adi'],
            'document_hash' => $ubl['hash'],
            'request_hash' => hash('sha256', $ubl['xml']),
            'request_meta' => [
                'uuid' => $ubl['uuid'],
                'test_modu' => (bool) $ayarlar['nette_fatura_test_modu'],
                'byte_length' => strlen($ubl['xml']),
            ],
            'sent_at' => now(),
        ]);

        try {
            $soap = $this->soapEnvelope('documentRequest', [
                'fileName' => $ubl['dosya_adi'],
                'binaryData' => base64_encode($ubl['xml']),
                'hash' => $ubl['hash'],
            ]);
            $cevap = $this->httpPost($endpoint, $soap, 'sendDocument', (int) $ayarlar['nette_fatura_zaman_asimi_saniye'], (string) $ayarlar['nette_fatura_kullanici_adi'], (string) $ayarlar['nette_fatura_sifre']);
            $parsed = $this->parseSoap($cevap['body']);
            $providerHash = $parsed['hash'] ?? null;
            $mesaj = $parsed['msg'] ?? ($cevap['http_kodu'] >= 200 && $cevap['http_kodu'] < 300 ? 'Belge gönderim yanıtı alındı.' : 'Belge gönderim isteği başarısız.');
            $basarili = $cevap['http_kodu'] >= 200 && $cevap['http_kodu'] < 300 && ! $this->soapFaultVarMi($parsed);
            $sureMs = round((microtime(true) - $baslangic) * 1000, 2);

            $log->update([
                'durum' => $basarili ? 'basarili' : 'hatali',
                'provider_instance_identifier' => $providerHash,
                'response_message' => $mesaj,
                'error_message' => $basarili ? null : mb_substr($cevap['body'], 0, 2000),
                'response_meta' => [
                    'http_kodu' => $cevap['http_kodu'],
                    'content_type' => $cevap['content_type'],
                    'parsed' => $parsed,
                    'sure_ms' => $sureMs,
                ],
                'responded_at' => now(),
            ]);

            $fatura->forceFill([
                'e_belge_uuid' => $ubl['uuid'],
                'e_belge_durumu' => $basarili ? 'gonderildi' : 'hata',
                'e_belge_saglayici' => 'nette_fatura',
                'e_belge_saglayici_belge_id' => $providerHash,
                'e_belge_hash' => $ubl['hash'],
                'e_belge_gonderildi_at' => $basarili ? now() : $fatura->e_belge_gonderildi_at,
                'e_belge_yanit_mesaji' => $mesaj,
                'e_belge_son_hata' => $basarili ? null : mb_substr($cevap['body'], 0, 2000),
            ])->save();

            return [
                'basarili' => $basarili,
                'http_kodu' => $cevap['http_kodu'],
                'mesaj' => $mesaj,
                'uuid' => $ubl['uuid'],
                'dosya_adi' => $ubl['dosya_adi'],
                'hash' => $ubl['hash'],
                'provider_hash' => $providerHash,
                'instance_identifier' => $providerHash,
            ];
        } catch (Throwable $e) {
            $log->update([
                'durum' => 'hatali',
                'error_message' => $e->getMessage(),
                'response_meta' => ['sure_ms' => round((microtime(true) - $baslangic) * 1000, 2)],
                'responded_at' => now(),
            ]);
            $fatura->forceFill([
                'e_belge_durumu' => 'hata',
                'e_belge_saglayici' => 'nette_fatura',
                'e_belge_son_hata' => $e->getMessage(),
            ])->save();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array{basarili:bool,http_kodu:int|null,mesaj:string,application_response:?string}
     */
    public function uygulamaYanitiSorgula(Fatura $fatura, ?string $instanceIdentifier = null): array
    {
        $ayarlar = $this->ayarServisi->ayarlariGetir((int) $fatura->firma_id);
        $instanceIdentifier = trim((string) ($instanceIdentifier ?: $fatura->e_belge_saglayici_belge_id ?: $fatura->e_belge_hash));
        if ($instanceIdentifier === '') {
            throw new RuntimeException('Uygulama yanıtı sorgulamak için sağlayıcı belge id/hash bilgisi bulunamadı.');
        }

        $endpoint = (string) $ayarlar['nette_fatura_service_url'];
        $baslangic = microtime(true);
        $log = NetteFaturaGonderimi::query()->create([
            'firma_id' => (int) $fatura->firma_id,
            'fatura_id' => (int) $fatura->getKey(),
            'islem_tipi' => 'getApplicationResponse',
            'durum' => 'gonderiliyor',
            'endpoint' => $endpoint,
            'provider_instance_identifier' => $instanceIdentifier,
            'sent_at' => now(),
        ]);

        try {
            $soap = $this->soapEnvelope('getAppRespRequest', ['instanceIdentifier' => $instanceIdentifier]);
            $cevap = $this->httpPost($endpoint, $soap, 'getApplicationResponse', (int) $ayarlar['nette_fatura_zaman_asimi_saniye'], (string) $ayarlar['nette_fatura_kullanici_adi'], (string) $ayarlar['nette_fatura_sifre']);
            $parsed = $this->parseSoap($cevap['body']);
            $appResponse = $parsed['applicationResponse'] ?? null;
            $mesaj = $appResponse ? 'Uygulama yanıtı alındı.' : ($parsed['msg'] ?? 'Uygulama yanıtı henüz boş.');
            $basarili = $cevap['http_kodu'] >= 200 && $cevap['http_kodu'] < 300 && ! $this->soapFaultVarMi($parsed);

            $log->update([
                'durum' => $basarili ? 'basarili' : 'hatali',
                'response_message' => $mesaj,
                'error_message' => $basarili ? null : mb_substr($cevap['body'], 0, 2000),
                'response_meta' => [
                    'http_kodu' => $cevap['http_kodu'],
                    'content_type' => $cevap['content_type'],
                    'parsed' => $parsed,
                    'sure_ms' => round((microtime(true) - $baslangic) * 1000, 2),
                ],
                'responded_at' => now(),
            ]);

            $fatura->forceFill([
                'e_belge_durumu' => $appResponse ? 'yanit_alindi' : $fatura->e_belge_durumu,
                'e_belge_yanit_mesaji' => $mesaj,
                'e_belge_son_hata' => $basarili ? null : mb_substr($cevap['body'], 0, 2000),
            ])->save();

            return [
                'basarili' => $basarili,
                'http_kodu' => $cevap['http_kodu'],
                'mesaj' => $mesaj,
                'application_response' => $appResponse,
            ];
        } catch (Throwable $e) {
            $log->update([
                'durum' => 'hatali',
                'error_message' => $e->getMessage(),
                'response_meta' => ['sure_ms' => round((microtime(true) - $baslangic) * 1000, 2)],
                'responded_at' => now(),
            ]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<string, string>  $alanlar
     */
    private function soapEnvelope(string $rootElement, array $alanlar): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('soap12:Envelope');
        $xml->writeAttribute('xmlns:soap12', self::SOAP_NS);
        $xml->writeAttribute('xmlns:efat', self::EFATURA_NS);
        $xml->startElement('soap12:Body');
        $xml->startElement('efat:'.$rootElement);
        foreach ($alanlar as $ad => $deger) {
            $xml->writeElement($ad, $deger);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @return array{http_kodu:int,content_type:string,body:string}
     */
    private function httpGet(string $url, int $timeout, string $username = '', string $password = ''): array
    {
        return $this->curlRequest('GET', $url, null, [], $timeout, $username, $password);
    }

    /**
     * @return array{http_kodu:int,content_type:string,body:string}
     */
    private function httpPost(string $url, string $body, string $soapAction, int $timeout, string $username = '', string $password = ''): array
    {
        return $this->curlRequest('POST', $url, $body, [
            'Content-Type: application/soap+xml; charset=utf-8; action="'.$soapAction.'"',
            'SOAPAction: '.$soapAction,
        ], $timeout, $username, $password);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{http_kodu:int,content_type:string,body:string}
     */
    private function curlRequest(string $method, string $url, ?string $body, array $headers, int $timeout, string $username = '', string $password = ''): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('PHP curl eklentisi bulunamadı.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Curl oturumu başlatılamadı.');
        }

        $headers = array_merge([
            'Accept: text/xml, application/soap+xml, application/xml;q=0.9, */*;q=0.8',
        ], $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($username !== '' || $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch) ?: 'Bilinmeyen curl hatası';
            curl_close($ch);

            throw new RuntimeException($error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            'http_kodu' => $httpCode,
            'content_type' => $contentType,
            'body' => (string) $responseBody,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseSoap(string $body): array
    {
        $sonuc = [];
        if (trim($body) === '') {
            return $sonuc;
        }

        $dom = new DOMDocument();
        $onceki = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($body);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        if (! $loaded) {
            return ['raw' => mb_substr($body, 0, 2000)];
        }

        foreach (['msg', 'hash', 'applicationResponse', 'code'] as $ad) {
            $nodes = $dom->getElementsByTagName($ad);
            if ($nodes->length > 0) {
                $sonuc[$ad] = trim((string) $nodes->item(0)?->textContent);
            }
        }

        $faults = $dom->getElementsByTagNameNS('*', 'Fault');
        if ($faults->length > 0) {
            $sonuc['fault'] = trim((string) $faults->item(0)?->textContent);
        }

        return $sonuc;
    }

    /**
     * @param  array<string, string>  $parsed
     */
    private function soapFaultVarMi(array $parsed): bool
    {
        return isset($parsed['fault']) || isset($parsed['code']);
    }
}
