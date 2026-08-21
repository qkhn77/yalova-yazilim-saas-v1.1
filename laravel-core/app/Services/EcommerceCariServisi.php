<?php

namespace App\Services;

use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use Illuminate\Support\Facades\DB;

class EcommerceCariServisi
{
    public function kullaniciIcinCariOlusturVeyaGuncelle(User $kullanici, int $firmaId, array $alanlar = []): Cari
    {
        return DB::transaction(function () use ($kullanici, $firmaId, $alanlar): Cari {
            $cari = Cari::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', (int) $kullanici->id)
                ->first();

            if (! $cari) {
                $email = trim((string) ($alanlar['email'] ?? $kullanici->email ?? ''));
                $telefon = trim((string) ($alanlar['telefon'] ?? $kullanici->telefon ?? ''));

                if ($email !== '' || $telefon !== '') {
                    $cari = Cari::query()
                        ->withoutGlobalScopes()
                        ->where('firma_id', $firmaId)
                        ->where(function ($query) use ($email, $telefon): void {
                            if ($email !== '') {
                                $query->orWhere('email', $email);
                            }

                            if ($telefon !== '') {
                                $query->orWhere('telefon', $telefon)
                                    ->orWhere('gsm', $telefon);
                            }
                        })
                        ->orderBy('id')
                        ->first();
                }
            }

            $payload = [
                'firma_id' => $firmaId,
                'kullanici_id' => (int) $kullanici->id,
                'kod' => $cari?->kod ?: $this->yeniCariKodu($firmaId),
                'ad' => trim((string) ($alanlar['ad'] ?? $alanlar['ad_soyad'] ?? $kullanici->ad_soyad ?? $kullanici->name ?? 'E-Ticaret Müşterisi')),
                'kisa_ad' => trim((string) ($alanlar['ad'] ?? $alanlar['ad_soyad'] ?? $kullanici->ad_soyad ?? $kullanici->name ?? '')),
                'tur' => CariTuru::ETicaret,
                'vergi_dairesi' => trim((string) ($alanlar['vergi_dairesi'] ?? $cari?->vergi_dairesi ?? '')),
                'vergi_no' => trim((string) ($alanlar['vergi_no'] ?? $cari?->vergi_no ?? '')),
                'telefon' => trim((string) ($alanlar['telefon'] ?? $kullanici->telefon ?? $cari?->telefon ?? '')),
                'gsm' => trim((string) ($alanlar['telefon'] ?? $kullanici->telefon ?? $cari?->gsm ?? '')),
                'email' => trim((string) ($alanlar['email'] ?? $kullanici->email ?? $cari?->email ?? '')),
                'adres' => trim((string) ($alanlar['adres'] ?? $cari?->adres ?? '')),
                'il' => trim((string) ($alanlar['il'] ?? $cari?->il ?? '')),
                'ilce' => trim((string) ($alanlar['ilce'] ?? $cari?->ilce ?? '')),
                'posta_kodu' => trim((string) ($alanlar['posta_kodu'] ?? $cari?->posta_kodu ?? '')),
                'yetkili_kisi' => trim((string) ($alanlar['ad'] ?? $alanlar['ad_soyad'] ?? $kullanici->ad_soyad ?? $kullanici->name ?? '')),
                'para_birimi' => strtoupper((string) ($alanlar['para_birimi'] ?? $cari?->para_birimi ?? 'TRY')),
                'aciklama' => 'E-ticaret müşteri hesabı',
                'durum' => CariDurumu::Aktif,
            ];

            if ($cari) {
                $cari->fill($payload);
                $cari->save();

                return $cari->fresh() ?? $cari;
            }

            return Cari::query()->create($payload);
        });
    }

    public function siparisIcinCariOlusturVeyaGuncelle(Siparis $siparis): Cari
    {
        if (! $siparis->kullanici_id) {
            return $this->misafirSiparisiIcinCariOlustur($siparis);
        }

        $kullanici = $siparis->kullanici ?: User::query()->withoutGlobalScopes()->findOrFail((int) $siparis->kullanici_id);

        return $this->kullaniciIcinCariOlusturVeyaGuncelle($kullanici, (int) $siparis->firma_id, [
            'ad' => (string) $siparis->musteri_ad_soyad,
            'telefon' => (string) $siparis->musteri_telefon,
            'email' => (string) ($siparis->musteri_email ?? ''),
            'adres' => (string) ($siparis->teslimat_adresi ?? ''),
            'il' => (string) ($siparis->teslimat_il ?? ''),
            'ilce' => (string) ($siparis->teslimat_ilce ?? ''),
            'posta_kodu' => (string) ($siparis->teslimat_posta_kodu ?? ''),
            'para_birimi' => (string) ($siparis->para_birimi ?? 'TRY'),
        ]);
    }

    private function misafirSiparisiIcinCariOlustur(Siparis $siparis): Cari
    {
        return DB::transaction(function () use ($siparis): Cari {
            $email = trim((string) ($siparis->musteri_email ?? ''));
            $telefon = trim((string) ($siparis->musteri_telefon ?? ''));
            $cari = null;
            if ($email !== '' || $telefon !== '') {
                $cari = Cari::query()
                    ->withoutGlobalScopes()
                    ->where('firma_id', (int) $siparis->firma_id)
                    ->where(function ($query) use ($email, $telefon): void {
                        if ($email !== '') {
                            $query->orWhere('email', $email);
                        }

                        if ($telefon !== '') {
                            $query->orWhere('telefon', $telefon)
                                ->orWhere('gsm', $telefon);
                        }
                    })
                    ->orderBy('id')
                    ->first();
            }

            $payload = [
                'firma_id' => (int) $siparis->firma_id,
                'kod' => $cari?->kod ?: $this->yeniCariKodu((int) $siparis->firma_id),
                'ad' => (string) $siparis->musteri_ad_soyad,
                'kisa_ad' => (string) $siparis->musteri_ad_soyad,
                'tur' => CariTuru::ETicaret,
                'vergi_dairesi' => trim((string) ($siparis->vergi_dairesi ?? $cari?->vergi_dairesi ?? '')),
                'vergi_no' => trim((string) ($siparis->vergi_no ?? $cari?->vergi_no ?? '')),
                'telefon' => (string) $siparis->musteri_telefon,
                'gsm' => (string) $siparis->musteri_telefon,
                'email' => (string) ($siparis->musteri_email ?? ''),
                'adres' => (string) ($siparis->teslimat_adresi ?? ''),
                'il' => (string) ($siparis->teslimat_il ?? ''),
                'ilce' => (string) ($siparis->teslimat_ilce ?? ''),
                'posta_kodu' => (string) ($siparis->teslimat_posta_kodu ?? ''),
                'yetkili_kisi' => (string) $siparis->musteri_ad_soyad,
                'para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
                'aciklama' => 'E-ticaret misafir sipariş hesabı',
                'durum' => CariDurumu::Aktif,
            ];

            if ($cari) {
                $cari->fill($payload);
                $cari->save();

                return $cari->fresh() ?? $cari;
            }

            return Cari::query()->create($payload);
        });
    }

    private function yeniCariKodu(int $firmaId): string
    {
        $prefix = 'ETC-'.str_pad((string) $firmaId, 3, '0', STR_PAD_LEFT).'-';
        $sonKod = Cari::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kod', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('kod');

        $sira = 1;
        if (is_string($sonKod) && str_starts_with($sonKod, $prefix)) {
            $ham = (int) substr($sonKod, strlen($prefix));
            $sira = $ham + 1;
        }

        return $prefix.str_pad((string) $sira, 6, '0', STR_PAD_LEFT);
    }
}
