<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SistemYedekleriServisi;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class SistemYedekleriSayfasi extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Veritabanı yedekleri';

    protected static ?string $slug = 'sistem-yedekleri';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static string $view = 'filament.pages.sistem-yedekleri-sayfasi';

    public string $arama = '';

    public static function canAccess(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public function updatedArama(): void
    {
        $this->resetPage();
    }

    public function getYedeklerProperty(): LengthAwarePaginator
    {
        $yedekServisi = app(SistemYedekleriServisi::class);
        $arama = mb_strtolower(trim($this->arama), 'UTF-8');
        $kayitlar = collect($yedekServisi->listele())
            ->when($arama !== '', fn ($items) => $items->filter(
                fn (array $kayit): bool => str_contains(mb_strtolower($kayit['name'], 'UTF-8'), $arama)
            ))
            ->values();

        $perPage = 20;
        $page = max(1, $this->getPage());

        return new LengthAwarePaginator(
            $kayitlar->forPage($page, $perPage)->values(),
            $kayitlar->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    public function sil(string $name, SistemYedekleriServisi $yedekServisi): void
    {
        abort_unless(static::canAccess(), 403);

        $yedekServisi->sil($name);

        Notification::make()
            ->title('Yedek silindi.')
            ->success()
            ->send();
    }

    public function formatBoyut(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, 2, ',', '.').' '.$unit;
            }
            $value /= 1024;
        }

        return number_format($value, 2, ',', '.').' PB';
    }
}
