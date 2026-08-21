<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\BarkodluSatisIade;
use App\Services\FirmaAyarDeposu;
use App\Support\MuhasebeYetkiSablonlari;
use App\Support\Qr\QrSvgUretici;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class BarkodluSatisIadeFisiSayfasi extends Page
{
    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Iade fisi';

    protected static ?string $slug = 'satis/barkodlu-satis-iade-fisi';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-iade-fisi-sayfasi';

    public ?BarkodluSatisIade $iade = null;

    public ?string $firmaLogoUrl = null;

    public ?string $firmaUnvan = null;

    public ?string $firmaTelefon = null;

    public ?string $firmaEposta = null;

    public ?string $firmaAdres = null;

    public ?string $qrSvg = null;

    public bool $dogrulamaBasarili = false;

    public function mount(): void
    {
        $iadeId = (int) request()->query('iade');
        if ($iadeId > 0) {
            $this->iade = BarkodluSatisIade::query()
                ->with(['firma', 'satis.cari', 'kalemler.satisKalemi', 'olusturan'])
                ->whereKey($iadeId)
                ->first();
        }

        if (! $this->iade) {
            return;
        }

        $depo = app(FirmaAyarDeposu::class);
        $logo = (string) ($depo->oku((int) $this->iade->firma_id, 'logo', '') ?? '');
        $this->firmaLogoUrl = $this->logoUrlHazirla($logo);
        $this->firmaUnvan = (string) ($this->iade->firma?->ad ?? '');
        $this->firmaTelefon = (string) ($this->iade->firma?->telefon ?? '');
        $this->firmaEposta = (string) ($this->iade->firma?->eposta ?? '');
        $this->firmaAdres = (string) ($this->iade->firma?->adres ?? '');

        $beklenenKod = (string) ($this->iade->dogrulama_kodu ?? '');
        $gelenKod = trim((string) request()->query('kod', ''));
        $gelenImza = trim((string) request()->query('sig', ''));
        $beklenenImza = $this->imzaOlustur((int) $this->iade->id, $beklenenKod);
        $kodEslesiyor = $beklenenKod !== '' && hash_equals($beklenenKod, $gelenKod);
        $imzaEslesiyor = $beklenenImza !== '' && hash_equals($beklenenImza, $gelenImza);
        $this->dogrulamaBasarili = $kodEslesiyor && $imzaEslesiyor;

        $dogrulamaUrl = request()->url().'?iade='.(int) $this->iade->id.'&kod='.$beklenenKod.'&sig='.$beklenenImza;
        $this->qrSvg = QrSvgUretici::uret($dogrulamaUrl, 140, 4);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis iade fisi';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE,
        ]);
    }

    private function logoUrlHazirla(string $logo): ?string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, '/')) {
            return $logo;
        }

        return Storage::disk('public')->url($logo);
    }

    private function imzaOlustur(int $iadeId, string $dogrulamaKodu): string
    {
        if ($iadeId < 1 || trim($dogrulamaKodu) === '') {
            return '';
        }

        $anahtar = (string) config('app.key');
        if ($anahtar === '') {
            return '';
        }

        if (str_starts_with($anahtar, 'base64:')) {
            $cozulmus = base64_decode(substr($anahtar, 7), true);
            if ($cozulmus !== false) {
                $anahtar = $cozulmus;
            }
        }

        $mesaj = 'iade_fis|'.$iadeId.'|'.$dogrulamaKodu;

        return hash_hmac('sha256', $mesaj, $anahtar);
    }
}
