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

class QaFullSaaSTestSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $password = (string) env('QA_FULL_TEST_PASSWORD', 'QaFullTest!2026');

        $firma = Firma::query()->withTrashed()->updateOrCreate(
            ['firma_kodu' => 'qa-full-20260821'],
            [
                'ad' => 'QA Full SaaS Test Firması',
                'kisa_ad' => 'QA Full Test',
                'vergi_no' => '9999999999',
                'telefon' => '05550000000',
                'eposta' => 'qa-full-test@yalovayazilim.test',
                'durum' => Firma::DURUM_AKTIF,
                'onaylandi_mi' => true,
                'onay_tarihi' => now(),
            ],
        );

        if (method_exists($firma, 'trashed') && $firma->trashed()) {
            $firma->restore();
        }

        $admin = User::withoutGlobalScopes()->withTrashed()->updateOrCreate(
            ['email' => 'qa-full-admin@yalovayazilim.test'],
            [
                'name' => 'QA Full Test Yöneticisi',
                'ad_soyad' => 'QA Full Test Yöneticisi',
                'kullanici_adi' => 'qa_full_admin',
                'password' => Hash::make($password),
                'super_admin_mi' => false,
                'aktif_mi' => true,
            ],
        );

        if (method_exists($admin, 'trashed') && $admin->trashed()) {
            $admin->restore();
        }

        $rol = Rol::query()->whereIn('kod', ['firma_yoneticisi', 'firma_sahibi'])->orderByRaw("CASE WHEN kod = 'firma_yoneticisi' THEN 0 ELSE 1 END")->firstOrFail();

        FirmaKullanici::query()->updateOrCreate(
            ['firma_id' => $firma->id, 'kullanici_id' => $admin->id],
            [
                'rol_id' => $rol->id,
                'durum' => 'aktif',
                'onay_durumu' => 'aktif',
                'varsayilan_firma_mi' => true,
            ],
        );

        foreach (Modul::query()->where('aktif_mi', true)->get() as $modul) {
            FirmaModulu::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'modul_id' => $modul->id],
                [
                    'durum' => 'aktif',
                    'baslangic_tarihi' => $today->toDateString(),
                    'bitis_tarihi' => null,
                ],
            );
        }

        if ($plan = Plan::query()->where('aktif_mi', true)->orderBy('id')->first()) {
            FirmaAboneligi::query()->updateOrCreate(
                ['firma_id' => $firma->id, 'plan_id' => $plan->id],
                [
                    'durum' => 'aktif',
                    'baslangic_tarihi' => $today->toDateString(),
                    'bitis_tarihi' => $today->copy()->addDays(max(1, (int) ($plan->sure_gun ?? 365)))->toDateString(),
                    'otomatik_yenileme' => false,
                ],
            );
        }

        $this->command?->info('QA firması oluşturuldu: '.$firma->firma_kodu.' (ID '.$firma->id.')');
        $this->command?->info('QA yönetici: '.$admin->email.' / qa_full_admin');
        $this->command?->info('QA parola: '.$password);
        $this->command?->info('Aktif modül sayısı: '.FirmaModulu::query()->where('firma_id', $firma->id)->where('durum', 'aktif')->count());
    }
}
