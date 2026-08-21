<?php

namespace App\Services;

use App\Models\FirmaKullanici;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\Iletisim\KullaniciMesaji;
use App\Models\Iletisim\KullaniciMesajKatilimcisi;
use App\Models\Iletisim\KullaniciMesajKonusu;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MesajMerkeziServisi
{
    private const SAYAC_CACHE_SANIYE = 20;

    private const SAYAC_CACHE_SURUMU = 'v4';

    private const ALICI_CACHE_SANIYE = 20;

    private const ALICI_CACHE_SURUMU = 'v2';

    private const AKIS_CACHE_SANIYE = 8;

    private const AKIS_CACHE_SURUMU = 'v1';

    public function konuOlustur(User $gonderen, ?int $firmaId, array $aliciIds, string $baslik, string $mesaj, string $oncelik = 'normal'): KullaniciMesajKonusu
    {
        $aliciIds = $this->gecerliAliciIdleri($gonderen, $firmaId, $aliciIds);
        if ($aliciIds === []) {
            throw ValidationException::withMessages([
                'aliciIds' => 'En az bir gecerli alici secmelisiniz.',
            ]);
        }

        return DB::transaction(function () use ($gonderen, $firmaId, $aliciIds, $baslik, $mesaj, $oncelik): KullaniciMesajKonusu {
            $konu = KullaniciMesajKonusu::query()->create([
                'firma_id' => $firmaId,
                'olusturan_id' => $gonderen->id,
                'baslik' => trim($baslik),
                'oncelik' => $oncelik ?: 'normal',
                'durum' => 'acik',
            ]);

            $katilimciIds = collect($aliciIds)
                ->push((int) $gonderen->id)
                ->unique()
                ->values();

            $simdi = now();
            KullaniciMesajKatilimcisi::query()->insert(
                $katilimciIds->map(fn (int $kullaniciId): array => [
                    'konu_id' => $konu->id,
                    'kullanici_id' => $kullaniciId,
                    'son_okuma_at' => (int) $kullaniciId === (int) $gonderen->id ? $simdi : null,
                    'favori_mi' => false,
                    'arsivlendi_mi' => false,
                    'sessize_alindi_mi' => false,
                    'created_at' => $simdi,
                    'updated_at' => $simdi,
                ])->all()
            );

            $ilkMesaj = $this->mesajKaydet($konu, $gonderen, $mesaj);

            $konu->forceFill([
                'son_mesaj_id' => $ilkMesaj->id,
                'son_mesaj_at' => $ilkMesaj->created_at,
            ])->save();

            $this->bildirimOlustur($konu, $gonderen, $aliciIds, 'Yeni mesaj', $baslik, 'bilgi');
            $this->sayacCacheTemizle($gonderen, $firmaId);
            $this->akisCacheTemizle($gonderen, $firmaId);

            return $konu->refresh();
        });
    }

    public function yanitGonder(KullaniciMesajKonusu $konu, User $gonderen, string $mesaj): KullaniciMesaji
    {
        $this->kullaniciKonudaMi($konu, $gonderen);

        return DB::transaction(function () use ($konu, $gonderen, $mesaj): KullaniciMesaji {
            $mesajKaydi = $this->mesajKaydet($konu, $gonderen, $mesaj);

            $konu->forceFill([
                'durum' => 'acik',
                'son_mesaj_id' => $mesajKaydi->id,
                'son_mesaj_at' => $mesajKaydi->created_at,
            ])->save();

            KullaniciMesajKatilimcisi::query()
                ->where('konu_id', $konu->id)
                ->where('kullanici_id', $gonderen->id)
                ->update(['son_okuma_at' => now()]);

            $katilimcilar = $konu->katilimcilar()
                ->where('kullanici_id', '!=', $gonderen->id)
                ->get(['kullanici_id', 'sessize_alindi_mi']);

            $aliciIds = $katilimcilar
                ->where('sessize_alindi_mi', false)
                ->pluck('kullanici_id')
                ->all();
            $bildirimAliciIdleri = collect($aliciIds)
                ->map(fn ($kullaniciId): int => (int) $kullaniciId)
                ->unique();

            $this->bildirimOlustur($konu, $gonderen, $aliciIds, 'Yeni yanıt', $konu->baslik, 'bilgi');

            foreach ($katilimcilar->pluck('kullanici_id')->unique() as $kullaniciId) {
                if ($bildirimAliciIdleri->contains((int) $kullaniciId)) {
                    continue;
                }

                $this->sayacCacheTemizle((int) $kullaniciId, $konu->firma_id ? (int) $konu->firma_id : null);
                $this->akisCacheTemizle((int) $kullaniciId, $konu->firma_id ? (int) $konu->firma_id : null);
            }

            $this->sayacCacheTemizle($gonderen, $konu->firma_id ? (int) $konu->firma_id : null);
            $this->akisCacheTemizle($gonderen, $konu->firma_id ? (int) $konu->firma_id : null);

            return $mesajKaydi;
        });
    }

    public function okunduIsaretle(KullaniciMesajKonusu $konu, User $kullanici): void
    {
        $okunanMesajSayisi = KullaniciMesajKatilimcisi::query()
            ->where('konu_id', $konu->id)
            ->where('kullanici_id', $kullanici->id)
            ->where(function (Builder $query) use ($konu): void {
                $query->whereNull('son_okuma_at');

                if ($konu->son_mesaj_at !== null) {
                    $query->orWhere('son_okuma_at', '<', $konu->son_mesaj_at);
                }
            })
            ->update(['son_okuma_at' => now()]);

        $okunanBildirimSayisi = KullaniciBildirimi::query()
            ->where('kullanici_id', $kullanici->id)
            ->where('kaynak_turu', KullaniciMesajKonusu::class)
            ->where('kaynak_id', $konu->id)
            ->whereNull('okundu_at')
            ->update(['okundu_at' => now()]);

        if ($okunanMesajSayisi > 0 || $okunanBildirimSayisi > 0) {
            $this->sayacCacheTemizle($kullanici, $konu->firma_id ? (int) $konu->firma_id : null);
            $this->akisCacheTemizle($kullanici, $konu->firma_id ? (int) $konu->firma_id : null);
        }
    }

    public function akisCacheSaniye(): int
    {
        return self::AKIS_CACHE_SANIYE;
    }

    public function akisCacheSurumu(User|int $kullanici, ?int $firmaId = null): int
    {
        $kullaniciId = $kullanici instanceof User ? (int) $kullanici->id : (int) $kullanici;

        return (int) Cache::remember(
            $this->akisCacheSurumKey($kullaniciId, $firmaId),
            now()->addDay(),
            fn (): int => 1,
        );
    }

    public function akisCacheTemizle(User|int $kullanici, ?int $firmaId = null): void
    {
        $kullaniciId = $kullanici instanceof User ? (int) $kullanici->id : (int) $kullanici;
        $firmaIdleri = $firmaId === null ? [null] : [$firmaId, null];

        foreach ($firmaIdleri as $hedefFirmaId) {
            $key = $this->akisCacheSurumKey($kullaniciId, $hedefFirmaId);
            Cache::put($key, ((int) Cache::get($key, 1)) + 1, now()->addDay());
        }
    }

    public function kullaniciSecenekleri(User $kullanici, ?int $firmaId, string $arama = '', int $limit = 60): array
    {
        $limit = max(0, $limit);

        return Cache::remember(
            $this->aliciCacheKey('liste', $kullanici, $firmaId, $arama, $limit),
            now()->addSeconds(self::ALICI_CACHE_SANIYE),
            function () use ($kullanici, $firmaId, $arama, $limit): array {
                $sorgu = $this->kullaniciSecenekleriSorgusu($kullanici, $firmaId, $arama)
                    ->orderByRaw('COALESCE(ad_soyad, name, email)');

                if ($limit > 0) {
                    $sorgu->limit($limit);
                }

                return $sorgu->get()
                    ->mapWithKeys(fn (User $user): array => [
                        $user->id => trim((string) ($user->ad_soyad ?: $user->name ?: $user->email)).' <'.$user->email.'>',
                    ])
                    ->all();
            }
        );
    }

    public function kullaniciSecenekSayisi(User $kullanici, ?int $firmaId, string $arama = ''): int
    {
        return (int) Cache::remember(
            $this->aliciCacheKey('sayac', $kullanici, $firmaId, $arama, 0),
            now()->addSeconds(self::ALICI_CACHE_SANIYE),
            fn (): int => $this->kullaniciSecenekleriSorgusu($kullanici, $firmaId, $arama)->count()
        );
    }

    public function erisilebilirKonuSorgusu(User $kullanici, ?int $firmaId, bool $arsivlenenleriGoster = false): Builder
    {
        return KullaniciMesajKonusu::query()
            ->select([
                'kullanici_mesaj_konulari.id',
                'kullanici_mesaj_konulari.firma_id',
                'kullanici_mesaj_konulari.olusturan_id',
                'kullanici_mesaj_konulari.baslik',
                'kullanici_mesaj_konulari.oncelik',
                'kullanici_mesaj_konulari.durum',
                'kullanici_mesaj_konulari.son_mesaj_id',
                'kullanici_mesaj_konulari.son_mesaj_at',
                'kullanici_mesaj_konulari.created_at',
            ])
            ->with(['sonMesaj:id,konu_id,gonderen_id,mesaj,created_at', 'sonMesaj.gonderen:id,name,ad_soyad,email'])
            ->whereHas('katilimcilar', fn (Builder $query) => $query
                ->where('kullanici_id', $kullanici->id)
                ->where('arsivlendi_mi', $arsivlenenleriGoster))
            ->when($firmaId, fn (Builder $query) => $query->where('firma_id', $firmaId))
            ->orderByDesc('son_mesaj_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{okunmamis_mesaj:int, okunmamis_bildirim:int}
     */
    public function sayaclar(User $kullanici, ?int $firmaId = null): array
    {
        return Cache::remember(
            $this->sayacCacheKey((int) $kullanici->id, $firmaId),
            now()->addSeconds(self::SAYAC_CACHE_SANIYE),
            fn (): array => [
                'okunmamis_mesaj' => $this->okunmamisMesajSayisi($kullanici, $firmaId),
                'okunmamis_bildirim' => $this->okunmamisBildirimSayisi($kullanici, $firmaId),
            ],
        );
    }

    public function sayacCacheTemizle(User|int $kullanici, ?int $firmaId = null): void
    {
        $kullaniciId = $kullanici instanceof User ? (int) $kullanici->id : (int) $kullanici;

        Cache::forget($this->sayacCacheKey($kullaniciId, $firmaId));

        if ($firmaId !== null) {
            Cache::forget($this->sayacCacheKey($kullaniciId, null));
        }
    }

    public function okunmamisMesajSayisi(User $kullanici, ?int $firmaId = null): int
    {
        $kullaniciId = (int) $kullanici->id;

        return (int) DB::table('kullanici_mesaj_konulari as konu')
            ->join('kullanici_mesaj_katilimcilari as katilimci', function ($join) use ($kullaniciId): void {
                $join->on('katilimci.konu_id', '=', 'konu.id')
                    ->where('katilimci.kullanici_id', '=', $kullaniciId);
            })
            ->join('kullanici_mesajlari as son_mesaj', 'son_mesaj.id', '=', 'konu.son_mesaj_id')
            ->when($firmaId, fn ($query) => $query->where('konu.firma_id', $firmaId))
            ->whereNull('konu.deleted_at')
            ->whereNull('son_mesaj.deleted_at')
            ->where('son_mesaj.gonderen_id', '!=', $kullaniciId)
            ->where(function ($query): void {
                $query->whereNull('katilimci.son_okuma_at')
                    ->orWhereColumn('konu.son_mesaj_at', '>', 'katilimci.son_okuma_at');
            })
            ->count('konu.id');
    }

    public function okunmamisBildirimSayisi(User $kullanici, ?int $firmaId = null): int
    {
        return KullaniciBildirimi::query()
            ->where('kullanici_id', $kullanici->id)
            ->when($firmaId, fn (Builder $query) => $query->where(function (Builder $q) use ($firmaId): void {
                $q->whereNull('firma_id')->orWhere('firma_id', $firmaId);
            }))
            ->whereNull('okundu_at')
            ->count();
    }

    private function mesajKaydet(KullaniciMesajKonusu $konu, User $gonderen, string $mesaj): KullaniciMesaji
    {
        return KullaniciMesaji::query()->create([
            'konu_id' => $konu->id,
            'gonderen_id' => $gonderen->id,
            'mesaj' => trim($mesaj),
        ]);
    }

    private function bildirimOlustur(KullaniciMesajKonusu $konu, User $gonderen, array $aliciIds, string $baslik, string $mesaj, string $seviye): void
    {
        $simdi = now();
        $aksiyonUrl = \App\Filament\Clusters\Ayarlar\Pages\MesajMerkeziSayfasi::getUrl(['konu' => $konu->id]);
        $bildirimler = collect($aliciIds)
            ->unique()
            ->filter(fn ($id): bool => (int) $id !== (int) $gonderen->id)
            ->map(fn ($aliciId): array => [
                'firma_id' => $konu->firma_id,
                'kullanici_id' => (int) $aliciId,
                'kaynak_turu' => KullaniciMesajKonusu::class,
                'kaynak_id' => $konu->id,
                'baslik' => $baslik,
                'mesaj' => $mesaj,
                'seviye' => $seviye,
                'aksiyon_url' => $aksiyonUrl,
                'data' => null,
                'okundu_at' => null,
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ])
            ->values();

        foreach ($bildirimler->chunk(200) as $parca) {
            KullaniciBildirimi::query()->insert($parca->all());
        }

        foreach ($bildirimler->pluck('kullanici_id')->unique() as $kullaniciId) {
            $this->sayacCacheTemizle((int) $kullaniciId, $konu->firma_id ? (int) $konu->firma_id : null);
            $this->akisCacheTemizle((int) $kullaniciId, $konu->firma_id ? (int) $konu->firma_id : null);
        }
    }

    private function gecerliAliciIdleri(User $gonderen, ?int $firmaId, array $aliciIds): array
    {
        $istenenIds = collect($aliciIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $gonderen->id)
            ->unique()
            ->values();

        if ($istenenIds->isEmpty()) {
            return [];
        }

        return $this->kullaniciSecenekleriSorgusu($gonderen, $firmaId)
            ->whereIn('users.id', $istenenIds->all())
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function kullaniciSecenekleriSorgusu(User $kullanici, ?int $firmaId, string $arama = ''): Builder
    {
        $sorgu = User::query()
            ->select(['users.id', 'users.name', 'users.ad_soyad', 'users.email', 'users.kullanici_adi'])
            ->where('users.id', '!=', $kullanici->id);

        if (! $this->superAdminMi($kullanici) || $firmaId) {
            $sorgu->whereIn('users.id', FirmaKullanici::query()
                ->select('kullanici_id')
                ->where('firma_id', (int) $firmaId)
                ->where('durum', 'aktif'));
        }

        $arama = trim($arama);
        if ($arama !== '') {
            $sorgu->where(function (Builder $query) use ($arama): void {
                $query->where('users.name', 'like', '%'.$arama.'%')
                    ->orWhere('users.ad_soyad', 'like', '%'.$arama.'%')
                    ->orWhere('users.email', 'like', '%'.$arama.'%')
                    ->orWhere('users.kullanici_adi', 'like', '%'.$arama.'%');
            });
        }

        return $sorgu;
    }

    private function kullaniciKonudaMi(KullaniciMesajKonusu $konu, User $kullanici): void
    {
        abort_unless(
            KullaniciMesajKatilimcisi::query()
                ->where('konu_id', $konu->id)
                ->where('kullanici_id', $kullanici->id)
                ->exists(),
            403
        );
    }

    private function superAdminMi(User $kullanici): bool
    {
        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }

    private function sayacCacheKey(int $kullaniciId, ?int $firmaId): string
    {
        return 'mesaj-merkezi:sayaclar:'.self::SAYAC_CACHE_SURUMU.':'.$kullaniciId.':'.($firmaId ?: 'genel');
    }

    private function aliciCacheKey(string $tur, User $kullanici, ?int $firmaId, string $arama, int $limit): string
    {
        $aramaHash = md5(mb_strtolower(trim($arama), 'UTF-8'));

        return implode(':', [
            'mesaj-merkezi',
            'alicilar',
            self::ALICI_CACHE_SURUMU,
            $tur,
            (int) $kullanici->id,
            $firmaId ?: 'genel',
            $limit,
            $aramaHash,
        ]);
    }

    private function akisCacheSurumKey(int $kullaniciId, ?int $firmaId): string
    {
        return 'mesaj-merkezi:akis-surumu:'.self::AKIS_CACHE_SURUMU.':'.$kullaniciId.':'.($firmaId ?: 'genel');
    }
}
