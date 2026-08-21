<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisHatirlatma;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class BakimHatirlatmalariSayfasi extends Page
{
    use TeknikServisSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $title = 'Bakım hatırlatmaları';

    protected static ?string $navigationLabel = 'Bakım hatırlatmaları';

    protected static ?string $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 53;

    protected static ?string $slug = 'operasyon/bakim-hatirlatmalari';

    protected static string $view = 'filament.clusters.teknik-servis.pages.bakim-hatirlatmalari-sayfasi';

    private ?string $whatsappSablonu = null;

    public function getHeading(): string|Htmlable
    {
        return 'Bakım hatırlatmaları';
    }

    public function getSubheading(): ?string
    {
        return 'Bakim tarihi gelen urunleri takip edin ve cariye tek tikla WhatsApp mesaji gonderin.';
    }

    protected function getViewData(): array
    {
        return [
            'arama' => $this->aramaMetni(),
            'kayitlar' => $this->hatirlatmalariGetir(),
            'aktifFiltreVarMi' => $this->aktifFiltreVarMi(),
            'aktifFiltreSayisi' => $this->aktifFiltreSayisi(),
            'sayfaBoyutu' => $this->sayfaBoyutu(),
            'sayfaBoyutlari' => [10, 25, 50],
        ];
    }

    private function hatirlatmalariGetir(): Paginator
    {
        $sorgu = TeknikServisHatirlatma::query()
            ->select([
                'teknik_servis_hatirlatmalari.id',
                'teknik_servis_hatirlatmalari.sonraki_tarih',
                'teknik_servis_hatirlatmalari.teknik_servis_kaydi_id',
                'tsk.musteri_tel',
                'cari.ad as cari_adi',
                'cari.telefon as cari_telefon',
                'cari.gsm as cari_gsm',
                'cihaz.ad as cihaz_adi',
            ])
            ->leftJoin('teknik_servis_kayitlari as tsk', 'tsk.id', '=', 'teknik_servis_hatirlatmalari.teknik_servis_kaydi_id')
            ->leftJoin('cariler as cari', function ($join): void {
                $join->on('cari.id', '=', 'tsk.cari_id')->whereNull('cari.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_cihazlar as cihaz', function ($join): void {
                $join->on('cihaz.id', '=', 'tsk.cihaz_id')->whereNull('cihaz.deleted_at');
            })
            ->where('teknik_servis_hatirlatmalari.hatirlatma_tipi', 'bakim')
            ->where('teknik_servis_hatirlatmalari.durum', 'aktif');

        $this->aramaUygula($sorgu);
        $this->filtreleriUygula($sorgu);

        return $sorgu
            ->orderBy('teknik_servis_hatirlatmalari.sonraki_tarih')
            ->orderBy('teknik_servis_hatirlatmalari.id')
            ->simplePaginate($this->sayfaBoyutu())
            ->withQueryString();
    }

    private function aramaUygula(Builder $sorgu): void
    {
        $arama = $this->aramaMetni();
        if ($arama === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $arama).'%';

        $sorgu->where(function (Builder $query) use ($like): void {
            $query
                ->where('tsk.musteri_tel', 'like', $like)
                ->orWhere('cari.ad', 'like', $like)
                ->orWhere('cari.telefon', 'like', $like)
                ->orWhere('cari.gsm', 'like', $like)
                ->orWhere('cihaz.ad', 'like', $like);
        });
    }

    private function filtreleriUygula(Builder $sorgu): void
    {
        $durum = (string) request()->query('durum', '');
        if ($durum === 'gecikti') {
            $sorgu->whereDate('teknik_servis_hatirlatmalari.sonraki_tarih', '<', now()->toDateString());
        } elseif ($durum === 'bugun') {
            $sorgu->whereDate('teknik_servis_hatirlatmalari.sonraki_tarih', '=', now()->toDateString());
        } elseif ($durum === 'planlandi') {
            $sorgu->whereDate('teknik_servis_hatirlatmalari.sonraki_tarih', '>', now()->toDateString());
        } elseif ($durum === 'tarih-yok') {
            $sorgu->whereNull('teknik_servis_hatirlatmalari.sonraki_tarih');
        }
    }

    private function aramaMetni(): string
    {
        return trim((string) request()->query('q', ''));
    }

    private function aktifFiltreVarMi(): bool
    {
        return $this->aramaMetni() !== ''
            || $this->aktifFiltreSayisi() > 0;
    }

    private function aktifFiltreSayisi(): int
    {
        return in_array((string) request()->query('durum', ''), ['gecikti', 'bugun', 'planlandi', 'tarih-yok'], true) ? 1 : 0;
    }

    private function sayfaBoyutu(): int
    {
        $adet = (int) request()->query('adet', 10);

        return in_array($adet, [10, 25, 50], true) ? $adet : 10;
    }

    public function telefonMetni(mixed $kayit): string
    {
        $telefon = trim((string) ($kayit->musteri_tel ?? ''));
        if ($telefon !== '') {
            return $telefon;
        }

        $telefon = trim((string) ($kayit->cari_telefon ?: $kayit->cari_gsm ?: ''));

        return $telefon !== '' ? $telefon : '-';
    }

    public function cihazMetni(mixed $kayit): string
    {
        $cihaz = trim((string) ($kayit->cihaz_adi ?? ''));
        return $cihaz !== '' ? $cihaz : '-';
    }

    public function durumMetni(mixed $kayit): string
    {
        $tarih = $kayit->sonraki_tarih ? Carbon::parse((string) $kayit->sonraki_tarih)->startOfDay() : null;
        if (! $tarih) {
            return 'Tarih yok';
        }

        $bugun = Carbon::today();
        if ($tarih->lt($bugun)) {
            return 'Bakım gecikti';
        }

        if ($tarih->equalTo($bugun)) {
            return 'Bugün';
        }

        return 'Planlandı';
    }

    public function whatsAppUrl(mixed $kayit): ?string
    {
        $telefon = $this->whatsAppTelefonu($kayit);
        if (! $telefon) {
            return null;
        }

        $cihaz = $this->cihazMetni($kayit);
        $bakimTarihi = $kayit->sonraki_tarih
            ? Carbon::parse((string) $kayit->sonraki_tarih)->format('d.m.Y')
            : '-';

        return 'https://wa.me/'.$telefon.'?text='.urlencode($this->whatsAppMesajMetni($cihaz, $bakimTarihi));
    }

    public function servisUrl(mixed $kayit): string
    {
        return TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $kayit->teknik_servis_kaydi_id]);
    }

    private function whatsAppMesajMetni(string $cihaz, string $bakimTarihi): string
    {
        $metin = $this->whatsappSablonu();
        if (trim($metin) === '') {
            return "Merhaba Sayin Musterimiz,\n\nBakim bilgisi:\n- Cihaz: {$cihaz}\n- Planlanan bakim tarihi: {$bakimTarihi}";
        }

        return strtr($metin, [
            '{cihaz}' => $cihaz,
            '{bakim_tarihi}' => $bakimTarihi,
        ]);
    }

    private function whatsappSablonu(): string
    {
        if ($this->whatsappSablonu !== null) {
            return $this->whatsappSablonu;
        }

        $firmaId = $this->aktifFirmaId();
        $sablon = TeknikServisMesajSablonu::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('kanal', 'whatsapp')
            ->where('aktif', true)
            ->orderByRaw("case when kod = 'termal_macun_bakim' then 0 else 1 end")
            ->orderBy('siralama')
            ->orderByDesc('updated_at')
            ->value('mesaj');

        $this->whatsappSablonu = (string) ($sablon ?? '');

        return $this->whatsappSablonu;
    }

    private function aktifFirmaId(): int
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        if ($firmaId > 0) {
            return $firmaId;
        }

        return (int) Firma::query()->orderBy('id')->value('id');
    }

    private function whatsAppTelefonu(mixed $kayit): ?string
    {
        $hamTelefon = $this->telefonMetni($kayit);
        if ($hamTelefon === '-' || $hamTelefon === '') {
            return null;
        }

        $telefon = preg_replace('/\D+/', '', $hamTelefon) ?? '';
        if ($telefon === '') {
            return null;
        }

        if (str_starts_with($telefon, '0')) {
            $telefon = '90'.substr($telefon, 1);
        } elseif (! str_starts_with($telefon, '90')) {
            $telefon = '90'.$telefon;
        }

        return strlen($telefon) >= 11 ? $telefon : null;
    }
}
