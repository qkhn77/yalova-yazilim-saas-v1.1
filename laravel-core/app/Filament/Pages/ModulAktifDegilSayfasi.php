<?php

namespace App\Filament\Pages;

use App\Models\Modul;
use App\Models\FirmaKullanici;
use App\Models\User;
use App\Services\ModulErisimService;
use App\Services\MesajMerkeziServisi;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ModulAktifDegilSayfasi extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Modül aktif değil';
    protected static ?string $slug = 'modul-aktif-degil/{modulKodu}';
    protected static string $view = 'filament.pages.modul-aktif-degil-sayfasi';
    public ?Modul $modul = null;
    public bool $basvuruFormuAcik = false;
    public string $basvuruMesaji = '';
    public string $basvuruIletisim = '';

    public static function canAccess(): bool
    {
        return Auth::user() instanceof User
            && app(TenantContextService::class)->aktifFirmaId() !== null;
    }

    public function mount(string $modulKodu): void
    {
        $this->modul = Modul::query()->where('kod', $modulKodu)->where('aktif_mi', true)->firstOrFail();
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        $erisim = app(ModulErisimService::class);
        abort_unless($erisim->modulDurumu($firmaId, $modulKodu) === 'kapali', 404);
    }

    public function basvuruFormunuAc(): void
    {
        $this->basvuruFormuAcik = true;
    }

    public function basvuruGonder(): void
    {
        $this->validate([
            'basvuruMesaji' => ['required', 'string', 'min:10', 'max:2000'],
            'basvuruIletisim' => ['nullable', 'string', 'max:255'],
        ], [
            'basvuruMesaji.required' => 'Lütfen başvuru mesajınızı yazın.',
            'basvuruMesaji.min' => 'Başvuru mesajı en az 10 karakter olmalıdır.',
        ]);

        $kullanici = Auth::user();
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        $aliciIds = FirmaKullanici::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->whereHas('kullanici', fn ($query) => $query->where('is_admin', true)->orWhere('super_admin_mi', true))
            ->pluck('kullanici_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($aliciIds === []) {
            Notification::make()
                ->title('Başvuru gönderilemedi')
                ->body('Bu firma için başvuruyu alacak aktif bir yönetici bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        $iletisim = trim($this->basvuruIletisim);
        $mesaj = "Modül: {$this->modul?->ad}\n\n{$this->basvuruMesaji}";
        if ($iletisim !== '') {
            $mesaj .= "\n\nİletişim bilgisi: {$iletisim}";
        }

        app(MesajMerkeziServisi::class)->konuOlustur(
            $kullanici,
            $firmaId,
            $aliciIds,
            "Modül aktivasyon başvurusu: {$this->modul?->ad}",
            $mesaj,
            'normal',
        );

        $this->basvuruFormuAcik = false;
        $this->basvuruMesaji = '';
        $this->basvuruIletisim = '';

        Notification::make()
            ->title('Başvurunuz alındı')
            ->body('Başvurunuz firma yöneticilerinin mesaj merkezine iletildi.')
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        return ['modul' => $this->modul];
    }
}
