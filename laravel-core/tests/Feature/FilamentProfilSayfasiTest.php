<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\ProfilDuzenle;
use App\Models\User;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FilamentProfilSayfasiTest extends TestCase
{
    public function test_admin_profil_rotasi_tanimli(): void
    {
        $this->assertTrue(Route::has('filament.admin.auth.profile'));

        $rota = Route::getRoutes()->getByName('filament.admin.auth.profile');

        $this->assertNotNull($rota);
        $this->assertContains('GET', $rota->methods());
        $this->assertSame('admin/profil', $rota->uri());
        $this->assertSame(ProfilDuzenle::class, $rota->getActionName());
    }

    public function test_kullanici_modeli_filament_avatar_destegi_verir(): void
    {
        $this->assertContains(HasAvatar::class, class_implements(User::class));
    }
}
