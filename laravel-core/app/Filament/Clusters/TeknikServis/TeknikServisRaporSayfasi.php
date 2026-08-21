<?php

namespace App\Filament\Clusters\TeknikServis;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Services\TenantContextService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

abstract class TeknikServisRaporSayfasi extends Page
{
    protected static ?string $cluster = TeknikServisCluster::class;

    protected static string $view = 'filament.clusters.teknik-servis.pages.rapor';

    public ?string $baslangicTarihi = null;

    public ?string $bitisTarihi = null;

    public bool $raporYuklendi = false;

    /** @var array<string, mixed>|null */
    private ?array $raporMemo = null;

    private ?string $raporMemoAnahtari = null;

    public function mount(): void
    {
        $this->baslangicTarihi ??= now()->startOfMonth()->toDateString();
        $this->bitisTarihi ??= now()->toDateString();
    }

    public function getSubheading(): ?string
    {
        return 'Dönem seçip raporu güncelleyerek teknik servis performansını izleyin.';
    }

    public function raporuYukle(): void
    {
        $this->raporYuklendi = true;
    }

    public function raporuGuncelle(): void
    {
        $this->raporMemo = null;
        $this->raporMemoAnahtari = null;
        $this->raporYuklendi = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rapor(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [
                'kartlar' => [],
                'tablolar' => [],
                'uyari' => 'Aktif firma bağlamı bulunamadı.',
            ];
        }

        $baslangic = Carbon::parse($this->baslangicTarihi ?: now()->startOfMonth()->toDateString())->startOfDay();
        $bitis = Carbon::parse($this->bitisTarihi ?: now()->toDateString())->endOfDay();

        if ($bitis->lt($baslangic)) {
            [$baslangic, $bitis] = [$bitis->copy()->startOfDay(), $baslangic->copy()->endOfDay()];
        }

        $cacheKey = implode(':', [
            'teknik-servis',
            'rapor',
            str_replace('\\', '.', static::class),
            (int) $firmaId,
            $baslangic->toDateString(),
            $bitis->toDateString(),
        ]);

        if ($this->raporMemo !== null && $this->raporMemoAnahtari === $cacheKey) {
            return $this->raporMemo;
        }

        $this->raporMemoAnahtari = $cacheKey;
        $this->raporMemo = Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            fn (): array => $this->raporVerisi((int) $firmaId, $baslangic, $bitis)
        );

        return $this->raporMemo;
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array;
}
