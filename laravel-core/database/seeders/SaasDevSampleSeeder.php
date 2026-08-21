<?php

namespace Database\Seeders;

use App\Models\Firma;
use App\Models\FirmaAboneligi;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SaasDevSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $varsayilanSifre = (string) env('SAAS_SEED_DEFAULT_PASSWORD', 'Password123!');

        $firma = Firma::query()->updateOrCreate(
            ['firma_kodu' => 'demo-firma'],
            [
                'ad' => 'Demo Firma Teknoloji A.Ş.',
                'kisa_ad' => 'Demo Firma',
                'durum' => 'aktif',
                'onaylandi_mi' => true,
                'eposta' => 'demo-firma@example.test',
                'telefon' => '05555555555',
            ]
        );

        $sahip = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'sahip@demo-firma.test'],
            array_merge([
                'name' => 'Demo Firma Sahibi',
                'password' => Hash::make($varsayilanSifre),
                'super_admin_mi' => false,
            ], $this->opsiyonelUserAlanlari('Demo Firma Sahibi', 'demo_sahip'))
        );

        $teknik = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'teknik@demo-firma.test'],
            array_merge([
                'name' => 'Demo Teknik Personel',
                'password' => Hash::make($varsayilanSifre),
                'super_admin_mi' => false,
            ], $this->opsiyonelUserAlanlari('Demo Teknik Personel', 'demo_teknik'))
        );

        $goruntuleyici = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'izleyici@demo-firma.test'],
            array_merge([
                'name' => 'Demo Görüntüleyici',
                'password' => Hash::make($varsayilanSifre),
                'super_admin_mi' => false,
            ], $this->opsiyonelUserAlanlari('Demo Görüntüleyici', 'demo_izleyici'))
        );

        $sahipRol = Rol::query()->where('kod', 'firma_sahibi')->first();
        $teknikRol = Rol::query()->where('kod', 'teknik_servis_personeli')->first();
        $izleyiciRol = Rol::query()->where('kod', 'goruntuleyici')->first();

        if ($sahipRol) {
            FirmaKullanici::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'kullanici_id' => $sahip->id],
                ['rol_id' => $sahipRol->id, 'durum' => 'aktif', 'varsayilan_firma_mi' => true]
            );
        }

        if ($teknikRol) {
            FirmaKullanici::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'kullanici_id' => $teknik->id],
                ['rol_id' => $teknikRol->id, 'durum' => 'aktif', 'varsayilan_firma_mi' => false]
            );
        }

        if ($izleyiciRol) {
            FirmaKullanici::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'kullanici_id' => $goruntuleyici->id],
                ['rol_id' => $izleyiciRol->id, 'durum' => 'aktif', 'varsayilan_firma_mi' => false]
            );
        }

        $standartPlan = Plan::query()->where('kod', 'standart')->first();
        if ($standartPlan) {
            $bugun = Carbon::today();
            FirmaAboneligi::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'plan_id' => $standartPlan->id, 'durum' => 'aktif'],
                [
                    'baslangic_tarihi' => $bugun->toDateString(),
                    'bitis_tarihi' => $bugun->copy()->addDays((int) $standartPlan->sure_gun)->toDateString(),
                    'otomatik_yenileme' => false,
                ]
            );
        }

        // Plan modülü dışında manuel durum senaryosu doğrulaması için örnek kayıtlar.
        $depo = Modul::query()->where('kod', 'depo')->first();
        if ($depo) {
            FirmaModulu::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'modul_id' => $depo->id],
                ['durum' => 'salt_okunur', 'baslangic_tarihi' => Carbon::today()->toDateString()]
            );
        }

        $restoran = Modul::query()->where('kod', 'restoran')->first();
        if ($restoran) {
            FirmaModulu::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'modul_id' => $restoran->id],
                ['durum' => 'kapali']
            );
        }
    }

    /**
     * Bazı ortamlarda users tablosu tam SaaS kolonlarına sahip olmayabilir.
     *
     * @return array<string, string>
     */
    protected function opsiyonelUserAlanlari(string $adSoyad, string $kullaniciAdi): array
    {
        $alanlar = [];

        if (Schema::hasColumn('users', 'ad_soyad')) {
            $alanlar['ad_soyad'] = $adSoyad;
        }

        if (Schema::hasColumn('users', 'kullanici_adi')) {
            $alanlar['kullanici_adi'] = $kullaniciAdi;
        }

        return $alanlar;
    }
}
