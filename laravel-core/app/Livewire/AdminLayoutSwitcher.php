<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\AdminLayoutPreference;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminLayoutSwitcher extends Component
{
    public string $layout = AdminLayoutPreference::DEFAULT;

    public function mount(): void
    {
        $this->layout = AdminLayoutPreference::forUser(Auth::user());
    }

    public function setLayout(string $layout): void
    {
        $layout = AdminLayoutPreference::normalize($layout);
        $user = Auth::user();

        if (! $user) {
            return;
        }

        User::query()
            ->whereKey($user->getAuthIdentifier())
            ->update(['admin_layout' => $layout]);

        $user->setAttribute('admin_layout', $layout);
        $this->layout = $layout;

        // Dikey sidebar ile yatay bar farklı kabuk konumlarında render edilir.
        // Tercih kaydedildikten sonra tek güvenli tam yenileme, yeni kabuğu kurar;
        // sonraki sayfa geçişleri Filament SPA olarak devam eder.
        $this->dispatch('saas-admin-layout-saved', layout: $layout);
    }

    public function render()
    {
        return view('livewire.admin-layout-switcher', [
            'options' => AdminLayoutPreference::options(),
            'icons' => [
                AdminLayoutPreference::MODERN_VERTICAL => 'heroicon-m-rectangle-group',
                AdminLayoutPreference::COMPACT_VERTICAL => 'heroicon-m-view-columns',
                AdminLayoutPreference::HORIZONTAL => 'heroicon-m-bars-3-bottom-left',
            ],
        ]);
    }
}
