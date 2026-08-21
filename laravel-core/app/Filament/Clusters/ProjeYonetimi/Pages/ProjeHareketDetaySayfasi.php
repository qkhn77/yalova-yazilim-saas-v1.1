<?php

namespace App\Filament\Clusters\ProjeYonetimi\Pages;

use App\Filament\Clusters\ProjeYonetimi as ProjeYonetimiCluster;
use App\Services\TenantContextService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProjeHareketDetaySayfasi extends Page
{
    protected static ?string $cluster = ProjeYonetimiCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Proje hareket detayı';

    protected static ?string $slug = 'raporlar/hareket/{tur}/{record}';

    protected static string $view = 'filament.clusters.proje-yonetimi.pages.proje-hareket-detay';

    /** @var array<string, mixed> */
    public array $hareket = [];

    public function mount(string $tur, int|string $record): void
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $id = (int) $record;
        $tur = strtolower(trim($tur));

        if ($firmaId < 1 || $id < 1 || ! in_array($tur, ['masraf', 'fatura', 'finans', 'cari', 'stok'], true)) {
            abort(404);
        }

        $projeId = match ($tur) {
            'masraf' => DB::table('masraflar')->where('firma_id', $firmaId)->where('id', $id)->value('isletme_proje_id'),
            'fatura' => DB::table('faturalar')->where('firma_id', $firmaId)->where('id', $id)->value('isletme_proje_id'),
            'finans' => DB::table('finans_hareketleri')->where('firma_id', $firmaId)->where('id', $id)->value('isletme_proje_id'),
            'cari' => DB::table('cari_hareketleri')->where('firma_id', $firmaId)->where('id', $id)->value('isletme_proje_id'),
            'stok' => DB::table('stok_hareketleri as sh')
                ->join('faturalar as f', function ($join): void {
                    $join->on('f.id', '=', 'sh.belge_id')->where('sh.belge_turu', 'fatura');
                })
                ->where('sh.firma_id', $firmaId)->where('f.firma_id', $firmaId)->where('sh.id', $id)
                ->value('f.isletme_proje_id'),
        };

        $proje = DB::table('isletme_projeleri as p')
            ->where('p.firma_id', $firmaId)
            ->where('p.id', $projeId)
            ->first(['p.id', 'p.kod', 'p.ad', 'p.durum']);

        $gorunurMu = $proje && \App\Models\Proje\IsletmeProjesi::query()
            ->whereKey($proje->id)
            ->kullaniciIcinGorunur(null, $firmaId)
            ->exists();

        if (! $proje || ! $gorunurMu) {
            abort(404);
        }

        $this->hareket = $this->hareketiGetir($tur, $id, $firmaId, $proje);
    }

    public function getHeading(): string
    {
        return 'Proje hareket detayı';
    }

    public function getSubheading(): ?string
    {
        return ($this->hareket['hareket_turu'] ?? 'Hareket').' · '.($this->hareket['belge'] ?? '');
    }

    public function baglantiUrl(string $tur, int $id): string
    {
        return self::getUrl(['tur' => $tur, 'record' => $id]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('raporaDon')
                ->label('Rapora dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProjeRaporlariSayfasi::getUrl()),
        ];
    }

    /** @return array<string, mixed> */
    private function hareketiGetir(string $tur, int $id, int $firmaId, object $proje): array
    {
        $row = match ($tur) {
            'masraf' => DB::table('masraflar as m')->where('m.firma_id', $firmaId)->where('m.id', $id)->first([
                'm.id', 'm.tarih', 'm.aciklama', 'm.tutar', 'm.para_birimi', 'm.durum',
            ]),
            'fatura' => DB::table('faturalar as f')->where('f.firma_id', $firmaId)->where('f.id', $id)->first([
                'f.id', 'f.tarih', 'f.aciklama', 'f.genel_toplam as tutar', 'f.para_birimi', 'f.durum', 'f.fatura_no', 'f.belge_no', 'f.tur',
            ]),
            'finans' => DB::table('finans_hareketleri as f')->where('f.firma_id', $firmaId)->where('f.id', $id)->first([
                'f.id', 'f.tarih', 'f.aciklama', 'f.tutar', 'f.para_birimi', 'f.durum', 'f.tur', 'f.referans_turu', 'f.referans_id',
            ]),
            'cari' => DB::table('cari_hareketleri as c')->where('c.firma_id', $firmaId)->where('c.id', $id)->first([
                'c.id', 'c.islem_tarihi as tarih', 'c.aciklama', 'c.para_birimi', 'c.durum', 'c.belge_turu', 'c.borc', 'c.alacak',
            ]),
            'stok' => DB::table('stok_hareketleri as s')->where('s.firma_id', $firmaId)->where('s.id', $id)->first([
                's.id', 's.islem_tarihi', 's.tarih', 's.aciklama', 's.miktar', 's.toplam_maliyet', 's.toplam', 's.durum', 's.islem_turu',
            ]),
        };

        if (! $row) {
            abort(404);
        }

        $tutar = $tur === 'cari'
            ? ((float) ($row->borc ?? 0) > 0 ? $row->borc : $row->alacak)
            : ($row->tutar ?? $row->toplam_maliyet ?? $row->toplam ?? 0);
        $yon = match ($tur) {
            'masraf' => 'Çıkış',
            'fatura' => in_array((string) ($row->tur ?? ''), ['giden', 'giden_fatura'], true) ? 'Giriş' : 'Çıkış',
            'finans' => (string) ($row->tur ?? '') === 'tahsilat' ? 'Giriş' : ((string) ($row->tur ?? '') === 'odeme' ? 'Ödeme' : 'Nötr'),
            'cari' => (float) ($row->borc ?? 0) > 0 ? 'Borç' : 'Alacak',
            'stok' => in_array((string) ($row->islem_turu ?? ''), ['acilis', 'alis', 'satis_iadesi', 'transfer_giris'], true) ? 'Giriş' : 'Çıkış',
        };

        $baglantilar = $this->baglantilariGetir($tur, $id, $firmaId);

        return [
            'hareket_turu' => ucfirst($tur),
            'kaynak_id' => $id,
            'tarih' => $row->islem_tarihi ?? $row->tarih,
            'belge' => $row->fatura_no ?? $row->belge_no ?? ucfirst($tur).' #'.$id,
            'aciklama' => $row->aciklama,
            'yon' => $yon,
            'miktar' => $row->miktar ?? null,
            'tutar' => $tutar,
            'para_birimi' => strtoupper((string) ($row->para_birimi ?? 'TRY')),
            'durum' => $row->durum instanceof \BackedEnum ? $row->durum->value : (string) $row->durum,
            'proje_kodu' => $proje->kod,
            'proje' => $proje->ad,
            'proje_durumu' => $proje->durum,
            'tur' => $row->tur ?? $row->belge_turu ?? $row->islem_turu ?? null,
            'referans_turu' => $row->referans_turu ?? null,
            'referans_id' => $row->referans_id ?? null,
            'baglantilar' => $baglantilar,
        ];
    }

    /** @return array<int, array{tur:string,id:int,etiket:string,tutar:mixed,para_birimi:string,url:string}> */
    private function baglantilariGetir(string $tur, int $id, int $firmaId): array
    {
        $baglantilar = [];

        if ($tur === 'masraf') {
            $rows = DB::table('masraf_fatura_dagitilari as d')
                ->join('faturalar as f', 'f.id', '=', 'd.fatura_id')
                ->where('d.firma_id', $firmaId)->where('d.masraf_id', $id)->where('f.firma_id', $firmaId)
                ->get(['f.id', 'f.fatura_no', 'f.belge_no', 'd.tutar', 'd.para_birimi']);
            foreach ($rows as $row) {
                $baglantilar[] = $this->baglantiSatiri('fatura', (int) $row->id, 'Fatura / '.($row->fatura_no ?: $row->belge_no ?: '#'.$row->id), $row->tutar, $row->para_birimi);
            }
        }

        if ($tur === 'fatura') {
            $masraflar = DB::table('masraf_fatura_dagitilari as d')
                ->join('masraflar as m', 'm.id', '=', 'd.masraf_id')
                ->where('d.firma_id', $firmaId)->where('d.fatura_id', $id)->where('m.firma_id', $firmaId)
                ->get(['m.id', 'm.aciklama', 'd.tutar', 'd.para_birimi']);
            foreach ($masraflar as $row) {
                $baglantilar[] = $this->baglantiSatiri('masraf', (int) $row->id, 'Masraf / '.($row->aciklama ?: '#'.$row->id), $row->tutar, $row->para_birimi);
            }

            $finanslar = DB::table('fatura_finans_kapatmalari as k')
                ->join('finans_hareketleri as fh', 'fh.id', '=', 'k.finans_hareket_id')
                ->where('k.firma_id', $firmaId)->where('k.fatura_id', $id)->where('fh.firma_id', $firmaId)
                ->get(['fh.id', 'fh.tur', 'k.uygulanan_tutar as tutar', 'fh.para_birimi']);
            foreach ($finanslar as $row) {
                $baglantilar[] = $this->baglantiSatiri('finans', (int) $row->id, 'Finans / '.(string) $row->tur.' #'.$row->id, $row->tutar, $row->para_birimi);
            }

            $cariler = DB::table('cari_hareketleri')
                ->where('firma_id', $firmaId)->where('belge_turu', 'fatura')->where('belge_id', $id)
                ->get(['id', 'borc', 'alacak', 'para_birimi']);
            foreach ($cariler as $row) {
                $baglantilar[] = $this->baglantiSatiri('cari', (int) $row->id, 'Cari #'.$row->id, ((float) $row->borc > 0 ? $row->borc : $row->alacak), $row->para_birimi);
            }

            $stoklar = DB::table('stok_hareketleri as sh')
                ->join('faturalar as sf', function ($join): void {
                    $join->on('sf.id', '=', 'sh.belge_id')->where('sh.belge_turu', 'fatura');
                })
                ->where('sh.firma_id', $firmaId)->where('sf.firma_id', $firmaId)->where('sh.belge_id', $id)
                ->get(['sh.id', 'sh.islem_turu', 'sh.miktar', 'sh.toplam_maliyet', 'sh.toplam', 'sf.para_birimi']);
            foreach ($stoklar as $row) {
                $baglantilar[] = $this->baglantiSatiri('stok', (int) $row->id, 'Stok / '.(string) $row->islem_turu.' #'.$row->id, $row->toplam_maliyet ?? $row->toplam, $row->para_birimi ?: 'TRY');
            }
        }

        if ($tur === 'finans') {
            $rows = DB::table('fatura_finans_kapatmalari as k')
                ->join('faturalar as f', 'f.id', '=', 'k.fatura_id')
                ->where('k.firma_id', $firmaId)->where('k.finans_hareket_id', $id)->where('f.firma_id', $firmaId)
                ->get(['f.id', 'f.fatura_no', 'f.belge_no', 'k.uygulanan_tutar as tutar', 'f.para_birimi']);
            foreach ($rows as $row) {
                $baglantilar[] = $this->baglantiSatiri('fatura', (int) $row->id, 'Fatura / '.($row->fatura_no ?: $row->belge_no ?: '#'.$row->id), $row->tutar, $row->para_birimi);
            }
        }

        if ($tur === 'stok') {
            $row = DB::table('stok_hareketleri as sh')
                ->join('faturalar as f', function ($join): void {
                    $join->on('f.id', '=', 'sh.belge_id')->where('sh.belge_turu', 'fatura');
                })
                ->where('sh.firma_id', $firmaId)->where('sh.id', $id)->where('f.firma_id', $firmaId)
                ->first(['f.id', 'f.fatura_no', 'f.belge_no', 'f.para_birimi']);
            if ($row) {
                $baglantilar[] = $this->baglantiSatiri('fatura', (int) $row->id, 'Fatura / '.($row->fatura_no ?: $row->belge_no ?: '#'.$row->id), null, $row->para_birimi);
            }
        }

        return $baglantilar;
    }

    /** @return array{tur:string,id:int,etiket:string,tutar:mixed,para_birimi:string,url:string} */
    private function baglantiSatiri(string $tur, int $id, string $etiket, mixed $tutar, mixed $paraBirimi): array
    {
        return [
            'tur' => $tur,
            'id' => $id,
            'etiket' => $etiket,
            'tutar' => $tutar,
            'para_birimi' => strtoupper((string) ($paraBirimi ?: 'TRY')),
            'url' => $this->baglantiUrl($tur, $id),
        ];
    }
}
