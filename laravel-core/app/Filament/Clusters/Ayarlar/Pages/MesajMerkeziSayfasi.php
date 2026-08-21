<?php

namespace App\Filament\Clusters\Ayarlar\Pages;

use App\Filament\Clusters\Ayarlar;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\Iletisim\KullaniciMesajKatilimcisi;
use App\Models\Iletisim\KullaniciMesajKonusu;
use App\Models\User;
use App\Services\MesajMerkeziServisi;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MesajMerkeziSayfasi extends Page
{
    private const KONU_LIMITI = 12;

    private const KONU_LIMIT_ARTISI = 12;

    private const KONU_MAKSIMUM_LIMITI = 120;

    private const BILDIRIM_LIMITI = 30;

    private const BILDIRIM_LIMIT_ARTISI = 30;

    private const BILDIRIM_MAKSIMUM_LIMITI = 180;

    private const MESAJ_LIMITI = 50;

    private const MESAJ_LIMIT_ARTISI = 50;

    private const MESAJ_MAKSIMUM_LIMITI = 300;

    private const ALICI_BASLANGIC_LIMITI = 24;

    private const ALICI_LIMITI = 60;

    protected static ?string $cluster = Ayarlar::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Ekip Mesajları';

    protected static ?string $slug = 'mesaj-merkezi';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.clusters.ayarlar.pages.mesaj-merkezi-sayfasi';

    public string $aktifSekme = 'gelen';

    public ?int $seciliKonuId = null;

    public string $baslik = '';

    public array $aliciIds = [];

    public string $aliciArama = '';

    public string $oncelik = 'normal';

    public string $mesaj = '';

    public string $yanitMesaji = '';

    public string $konuArama = '';

    public string $oncelikFiltresi = 'tum';

    public bool $sadeceOkunmamis = false;

    public string $konuListeModu = 'aktif';

    public string $bildirimArama = '';

    public bool $sadeceOkunmamisBildirimler = false;

    public int $konuLimit = self::KONU_LIMITI;

    public int $bildirimLimit = self::BILDIRIM_LIMITI;

    public int $mesajLimit = self::MESAJ_LIMITI;

    private ?User $kullaniciMemo = null;

    private bool $aktifFirmaIdHazir = false;

    private ?int $aktifFirmaIdMemo = null;

    /** @var array{okunmamis_mesaj:int, okunmamis_bildirim:int}|null */
    private ?array $sayaclarMemo = null;

    public function mount(): void
    {
        $konuId = request()->integer('konu');
        $konu = $konuId > 0 ? $this->konuTemel($konuId) : null;
        if ($konu instanceof KullaniciMesajKonusu) {
            $this->seciliKonuId = $konuId;
            $this->aktifSekme = 'gelen';
            $this->okunduIsaretle($konu);
        }
    }

    public function sekmeDegistir(string $sekme): void
    {
        $this->aktifSekme = in_array($sekme, ['gelen', 'yeni', 'bildirimler'], true) ? $sekme : 'gelen';
    }

    public function dahaFazlaKonusma(): void
    {
        $this->konuLimit = min($this->konuLimit + self::KONU_LIMIT_ARTISI, self::KONU_MAKSIMUM_LIMITI);
    }

    public function dahaFazlaBildirim(): void
    {
        $this->bildirimLimit = min($this->bildirimLimit + self::BILDIRIM_LIMIT_ARTISI, self::BILDIRIM_MAKSIMUM_LIMITI);
    }

    public function dahaEskiMesajlariYukle(): void
    {
        $this->mesajLimit = min($this->mesajLimit + self::MESAJ_LIMIT_ARTISI, self::MESAJ_MAKSIMUM_LIMITI);
    }

    public function updatedKonuArama(): void
    {
        $this->konuLimit = self::KONU_LIMITI;
    }

    public function updatedOncelikFiltresi(): void
    {
        $this->konuLimit = self::KONU_LIMITI;
    }

    public function updatedSadeceOkunmamis(): void
    {
        $this->konuLimit = self::KONU_LIMITI;
    }

    public function updatedKonuListeModu(): void
    {
        $this->konuLimit = self::KONU_LIMITI;
        $this->seciliKonuId = null;
    }

    public function updatedBildirimArama(): void
    {
        $this->bildirimLimit = self::BILDIRIM_LIMITI;
    }

    public function updatedSadeceOkunmamisBildirimler(): void
    {
        $this->bildirimLimit = self::BILDIRIM_LIMITI;
    }

    public function filtreleriTemizle(): void
    {
        $this->konuArama = '';
        $this->oncelikFiltresi = 'tum';
        $this->sadeceOkunmamis = false;
        $this->konuListeModu = 'aktif';
        $this->konuLimit = self::KONU_LIMITI;
    }

    public function bildirimFiltreleriTemizle(): void
    {
        $this->bildirimArama = '';
        $this->sadeceOkunmamisBildirimler = false;
        $this->bildirimLimit = self::BILDIRIM_LIMITI;
    }

    public function aliciAramaTemizle(): void
    {
        $this->aliciArama = '';
    }

    public function konuSec(int $konuId): void
    {
        $konu = $this->konuTemel($konuId);
        abort_unless($konu instanceof KullaniciMesajKonusu, 403);

        $this->seciliKonuId = $konuId;
        $this->aktifSekme = 'gelen';
        $this->mesajLimit = self::MESAJ_LIMITI;
        $this->okunduIsaretle($konu);
    }

    public function konuOlustur(MesajMerkeziServisi $servis): void
    {
        $this->validate([
            'baslik' => ['required', 'string', 'min:3', 'max:160'],
            'aliciIds' => ['required', 'array', 'min:1'],
            'aliciIds.*' => ['integer'],
            'oncelik' => ['required', 'in:dusuk,normal,yuksek,acil'],
            'mesaj' => ['required', 'string', 'min:2', 'max:5000'],
        ], [], [
            'baslik' => 'Başlık',
            'aliciIds' => 'Alıcılar',
            'mesaj' => 'Mesaj',
        ]);

        $konu = $servis->konuOlustur(
            $this->kullanici(),
            $this->aktifFirmaId(),
            $this->aliciIds,
            $this->baslik,
            $this->mesaj,
            $this->oncelik,
        );

        $this->reset(['baslik', 'aliciIds', 'mesaj']);
        $this->oncelik = 'normal';
        $this->seciliKonuId = $konu->id;
        $this->aktifSekme = 'gelen';
        $this->konuLimit = self::KONU_LIMITI;
        $this->mesajLimit = self::MESAJ_LIMITI;

        Notification::make()
            ->success()
            ->title('Mesaj konusu oluşturuldu')
            ->send();
    }

    public function yanitGonder(MesajMerkeziServisi $servis): void
    {
        $this->validate([
            'yanitMesaji' => ['required', 'string', 'min:1', 'max:5000'],
        ], [], [
            'yanitMesaji' => 'Yanıt',
        ]);

        $konu = $this->seciliKonuTemel();
        abort_unless($konu instanceof KullaniciMesajKonusu, 404);

        $servis->yanitGonder($konu, $this->kullanici(), $this->yanitMesaji);

        $this->yanitMesaji = '';
        $this->okunduIsaretle();

        Notification::make()
            ->success()
            ->title('Yanıt gönderildi')
            ->send();
    }

    public function tumBildirimleriOku(): void
    {
        $kullanici = $this->kullanici();
        $firmaId = $this->aktifFirmaId();

        $okunanBildirimSayisi = KullaniciBildirimi::query()
            ->where('kullanici_id', $kullanici->id)
            ->when($firmaId, fn (Builder $query) => $query->where(function (Builder $q) use ($firmaId): void {
                $q->whereNull('firma_id')->orWhere('firma_id', $firmaId);
            }))
            ->whereNull('okundu_at')
            ->update(['okundu_at' => now()]);

        if ($okunanBildirimSayisi > 0) {
            app(MesajMerkeziServisi::class)->sayacCacheTemizle($kullanici, $firmaId);
            $this->akisCacheTemizle($kullanici);
        }

        Notification::make()
            ->success()
            ->title('Bildirimler okundu')
            ->send();
    }

    public function okunduIsaretle(?KullaniciMesajKonusu $konu = null): void
    {
        $konu ??= $this->seciliKonuTemel();
        if (! $konu instanceof KullaniciMesajKonusu) {
            return;
        }

        app(MesajMerkeziServisi::class)->okunduIsaretle($konu, $this->kullanici());
    }

    public function favoriDegistir(int $konuId): void
    {
        $katilimci = $this->katilimciKaydi($konuId);
        abort_unless($katilimci instanceof KullaniciMesajKatilimcisi, 404);

        $katilimci->forceFill([
            'favori_mi' => ! (bool) $katilimci->favori_mi,
        ])->save();

        $this->akisCacheTemizle();
    }

    public function konuArsivle(int $konuId): void
    {
        $katilimci = $this->katilimciKaydi($konuId);
        abort_unless($katilimci instanceof KullaniciMesajKatilimcisi, 404);

        $katilimci->forceFill(['arsivlendi_mi' => true])->save();
        $this->akisCacheTemizle();

        if ((int) $this->seciliKonuId === $konuId) {
            $this->seciliKonuId = null;
        }

        Notification::make()
            ->success()
            ->title('Konuşma arşivlendi')
            ->send();
    }

    public function konuArsivdenCikar(int $konuId): void
    {
        $katilimci = $this->katilimciKaydi($konuId);
        abort_unless($katilimci instanceof KullaniciMesajKatilimcisi, 404);

        $katilimci->forceFill(['arsivlendi_mi' => false])->save();
        $this->akisCacheTemizle();

        $this->konuListeModu = 'aktif';
        $this->seciliKonuId = $konuId;

        Notification::make()
            ->success()
            ->title('Konuşma arşivden çıkarıldı')
            ->send();
    }

    public function sessizDegistir(int $konuId): void
    {
        $katilimci = $this->katilimciKaydi($konuId);
        abort_unless($katilimci instanceof KullaniciMesajKatilimcisi, 404);

        $katilimci->forceFill([
            'sessize_alindi_mi' => ! (bool) $katilimci->sessize_alindi_mi,
        ])->save();

        $this->akisCacheTemizle();
    }

    public function tumKonusmalariOku(): void
    {
        $kullanici = $this->kullanici();
        $firmaId = $this->aktifFirmaId();
        $konuIdSorgusu = KullaniciMesajKonusu::query()
            ->select('id')
            ->when($firmaId, fn (Builder $query) => $query->where('firma_id', $firmaId));

        $okunanKonuSayisi = KullaniciMesajKatilimcisi::query()
            ->where('kullanici_id', $kullanici->id)
            ->where('arsivlendi_mi', false)
            ->whereIn('konu_id', $konuIdSorgusu)
            ->where(function (Builder $query): void {
                $query->whereNull('son_okuma_at')
                    ->orWhereExists(function ($altQuery): void {
                        $altQuery->selectRaw('1')
                            ->from('kullanici_mesaj_konulari as okunacak_konu')
                            ->whereColumn('okunacak_konu.id', 'kullanici_mesaj_katilimcilari.konu_id')
                            ->whereNull('okunacak_konu.deleted_at')
                            ->whereColumn('okunacak_konu.son_mesaj_at', '>', 'kullanici_mesaj_katilimcilari.son_okuma_at');
                    });
            })
            ->update(['son_okuma_at' => now()]);

        $okunanBildirimSayisi = KullaniciBildirimi::query()
            ->where('kullanici_id', $kullanici->id)
            ->where('kaynak_turu', KullaniciMesajKonusu::class)
            ->when($firmaId, fn (Builder $query) => $query->where(function (Builder $q) use ($firmaId): void {
                $q->whereNull('firma_id')->orWhere('firma_id', $firmaId);
            }))
            ->whereNull('okundu_at')
            ->update(['okundu_at' => now()]);

        if ($okunanKonuSayisi > 0 || $okunanBildirimSayisi > 0) {
            app(MesajMerkeziServisi::class)->sayacCacheTemizle($kullanici, $firmaId);
            $this->akisCacheTemizle($kullanici);
        }

        Notification::make()
            ->success()
            ->title('Konuşmalar okundu')
            ->send();
    }

    public function bildirimOku(int $bildirimId): void
    {
        $kullanici = $this->kullanici();
        $firmaId = $this->aktifFirmaId();

        $okunanBildirimSayisi = KullaniciBildirimi::query()
            ->whereKey($bildirimId)
            ->where('kullanici_id', $kullanici->id)
            ->when($firmaId, fn (Builder $query) => $query->where(function (Builder $q) use ($firmaId): void {
                $q->whereNull('firma_id')->orWhere('firma_id', $firmaId);
            }))
            ->whereNull('okundu_at')
            ->update(['okundu_at' => now()]);

        if ($okunanBildirimSayisi > 0) {
            app(MesajMerkeziServisi::class)->sayacCacheTemizle($kullanici, $firmaId);
            $this->akisCacheTemizle($kullanici);
        }
    }

    public function konular(): Collection
    {
        $kullanici = $this->kullanici();
        $kullaniciId = (int) $kullanici->id;
        $firmaId = $this->aktifFirmaId();
        $arsivlenenleriGoster = $this->konuListeModu === 'arsiv';
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
                'katilimci.son_okuma_at as katilimci_son_okuma_at',
                'katilimci.favori_mi as katilimci_favori_mi',
                'katilimci.arsivlendi_mi as katilimci_arsivlendi_mi',
                'katilimci.sessize_alindi_mi as katilimci_sessize_alindi_mi',
                'son_mesaj.mesaj as son_mesaj_metin',
                'son_mesaj.gonderen_id as son_mesaj_gonderen_id',
            ])
            ->selectRaw(
                'CASE WHEN son_mesaj.id IS NOT NULL AND son_mesaj.gonderen_id <> ? AND (katilimci.son_okuma_at IS NULL OR kullanici_mesaj_konulari.son_mesaj_at > katilimci.son_okuma_at) THEN 1 ELSE 0 END as okunmamis_mi',
                [$kullaniciId]
            )
            ->join('kullanici_mesaj_katilimcilari as katilimci', function ($join) use ($kullaniciId, $arsivlenenleriGoster): void {
                $join->on('katilimci.konu_id', '=', 'kullanici_mesaj_konulari.id')
                    ->where('katilimci.kullanici_id', '=', $kullaniciId)
                    ->where('katilimci.arsivlendi_mi', '=', $arsivlenenleriGoster);
            })
            ->leftJoin('kullanici_mesajlari as son_mesaj', function ($join): void {
                $join->on('son_mesaj.id', '=', 'kullanici_mesaj_konulari.son_mesaj_id')
                    ->whereNull('son_mesaj.deleted_at');
            })
            ->when($firmaId, fn (Builder $query) => $query->where('kullanici_mesaj_konulari.firma_id', $firmaId))
            ->when($this->konuListeModu === 'favori', fn (Builder $query) => $query->where('katilimci.favori_mi', true))
            ->when(trim($this->konuArama) !== '', function (Builder $query): void {
                $arama = trim($this->konuArama);
                $query->where(function (Builder $q) use ($arama): void {
                    $q->where('kullanici_mesaj_konulari.baslik', 'like', '%'.$arama.'%')
                        ->orWhere('son_mesaj.mesaj', 'like', '%'.$arama.'%');
                });
            })
            ->when($this->oncelikFiltresi !== 'tum', fn (Builder $query) => $query->where('kullanici_mesaj_konulari.oncelik', $this->oncelikFiltresi))
            ->when($this->sadeceOkunmamis, fn (Builder $query) => $query
                ->whereNotNull('son_mesaj.id')
                ->where('son_mesaj.gonderen_id', '!=', $kullaniciId)
                ->where(function (Builder $okumaQuery): void {
                    $okumaQuery
                        ->whereNull('katilimci.son_okuma_at')
                        ->orWhereColumn('kullanici_mesaj_konulari.son_mesaj_at', '>', 'katilimci.son_okuma_at');
                }))
            ->orderByDesc('kullanici_mesaj_konulari.son_mesaj_at')
            ->orderByDesc('kullanici_mesaj_konulari.id')
            ->limit($this->konuLimit + 1)
            ->get();
    }

    public function seciliKonu(): ?KullaniciMesajKonusu
    {
        if (! $this->seciliKonuId) {
            return null;
        }

        $kullaniciId = (int) $this->kullanici()->id;
        $firmaId = $this->aktifFirmaId();
        $seciliKonuId = (int) $this->seciliKonuId;
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
            ->withCount('katilimcilar')
            ->join('kullanici_mesaj_katilimcilari as secili_katilimci', function ($join) use ($kullaniciId): void {
                $join->on('secili_katilimci.konu_id', '=', 'kullanici_mesaj_konulari.id')
                    ->where('secili_katilimci.kullanici_id', '=', $kullaniciId);
            })
            ->when($firmaId, fn (Builder $query) => $query->where('kullanici_mesaj_konulari.firma_id', $firmaId))
            ->with([
                'mesajlar' => fn ($query) => $query
                    ->select(['id', 'konu_id', 'gonderen_id', 'mesaj', 'created_at'])
                    ->with('gonderen:id,name,ad_soyad,email')
                    ->latest()
                    ->limit($this->mesajLimit + 1),
                'katilimcilar' => fn ($query) => $query
                    ->select(['id', 'konu_id', 'kullanici_id'])
                    ->latest('id')
                    ->limit(8),
                'katilimcilar.kullanici:id,name,ad_soyad,email',
            ])
            ->find($seciliKonuId);
    }

    private function seciliKonuTemel(): ?KullaniciMesajKonusu
    {
        if (! $this->seciliKonuId) {
            return null;
        }

        return $this->konuTemel((int) $this->seciliKonuId);
    }

    private function konuTemel(int $konuId): ?KullaniciMesajKonusu
    {
        $kullaniciId = (int) $this->kullanici()->id;
        $firmaId = $this->aktifFirmaId();

        return KullaniciMesajKonusu::query()
            ->select([
                'kullanici_mesaj_konulari.id',
                'kullanici_mesaj_konulari.firma_id',
                'kullanici_mesaj_konulari.baslik',
            ])
            ->join('kullanici_mesaj_katilimcilari as secili_katilimci', function ($join) use ($kullaniciId): void {
                $join->on('secili_katilimci.konu_id', '=', 'kullanici_mesaj_konulari.id')
                    ->where('secili_katilimci.kullanici_id', '=', $kullaniciId);
            })
            ->when($firmaId, fn (Builder $query) => $query->where('kullanici_mesaj_konulari.firma_id', $firmaId))
            ->find($konuId);
    }

    public function bildirimler(): Collection
    {
        $kullanici = $this->kullanici();
        $kullaniciId = (int) $kullanici->id;
        $firmaId = $this->aktifFirmaId();
        return KullaniciBildirimi::query()
            ->select([
                'id',
                'firma_id',
                'kullanici_id',
                'kaynak_turu',
                'kaynak_id',
                'baslik',
                'mesaj',
                'seviye',
                'okundu_at',
                'aksiyon_url',
                'created_at',
            ])
            ->where('kullanici_id', $kullaniciId)
            ->when($firmaId, fn ($query) => $query->where(function ($q) use ($firmaId): void {
                $q->whereNull('firma_id')->orWhere('firma_id', $firmaId);
            }))
            ->when(trim($this->bildirimArama) !== '', function (Builder $query): void {
                $arama = trim($this->bildirimArama);
                $query->where(function (Builder $q) use ($arama): void {
                    $q->where('baslik', 'like', '%'.$arama.'%')
                        ->orWhere('mesaj', 'like', '%'.$arama.'%');
                });
            })
            ->when($this->sadeceOkunmamisBildirimler, fn (Builder $query) => $query->whereNull('okundu_at'))
            ->latest()
            ->limit($this->bildirimLimit + 1)
            ->get();
    }

    public function kullaniciSecenekleri(): array
    {
        return app(MesajMerkeziServisi::class)->kullaniciSecenekleri(
            $this->kullanici(),
            $this->aktifFirmaId(),
            $this->aliciArama,
            $this->aliciListeLimiti()
        );
    }

    public function aliciSecenekToplamSayisi(): int
    {
        return app(MesajMerkeziServisi::class)->kullaniciSecenekSayisi(
            $this->kullanici(),
            $this->aktifFirmaId(),
            $this->aliciArama
        );
    }

    public function aliciListeLimiti(): int
    {
        return trim($this->aliciArama) === ''
            ? self::ALICI_BASLANGIC_LIMITI
            : self::ALICI_LIMITI;
    }

    /**
     * @return array{okunmamis_mesaj:int, okunmamis_bildirim:int}
     */
    public function sayaclar(): array
    {
        return $this->sayaclarMemo ??= app(MesajMerkeziServisi::class)->sayaclar($this->kullanici(), $this->aktifFirmaId());
    }

    public function okunmamisMesajSayisi(): int
    {
        return (int) $this->sayaclar()['okunmamis_mesaj'];
    }

    public function okunmamisBildirimSayisi(): int
    {
        return (int) $this->sayaclar()['okunmamis_bildirim'];
    }

    private function katilimciKaydi(int $konuId): ?KullaniciMesajKatilimcisi
    {
        $kullaniciId = (int) $this->kullanici()->id;
        $firmaId = $this->aktifFirmaId();

        return KullaniciMesajKatilimcisi::query()
            ->select('kullanici_mesaj_katilimcilari.*')
            ->join('kullanici_mesaj_konulari as konu', 'konu.id', '=', 'kullanici_mesaj_katilimcilari.konu_id')
            ->whereNull('konu.deleted_at')
            ->where('kullanici_mesaj_katilimcilari.konu_id', $konuId)
            ->where('kullanici_mesaj_katilimcilari.kullanici_id', $kullaniciId)
            ->when($firmaId, fn (Builder $query) => $query->where('konu.firma_id', $firmaId))
            ->first();
    }

    private function kullanici(): User
    {
        if ($this->kullaniciMemo instanceof User) {
            return $this->kullaniciMemo;
        }

        $kullanici = Auth::user();
        abort_unless($kullanici instanceof User, 403);

        return $this->kullaniciMemo = $kullanici;
    }

    private function aktifFirmaId(): ?int
    {
        if (! $this->aktifFirmaIdHazir) {
            $this->aktifFirmaIdMemo = app(TenantContextService::class)->aktifFirmaId();
            $this->aktifFirmaIdHazir = true;
        }

        return $this->aktifFirmaIdMemo;
    }

    private function akisCacheTemizle(?User $kullanici = null): void
    {
        app(MesajMerkeziServisi::class)->akisCacheTemizle($kullanici ?? $this->kullanici(), $this->aktifFirmaId());
    }

}
