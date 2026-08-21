<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\User;
use App\Services\ModulErisimService;
use App\Services\YetkiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SaasVerifyAccessCommand extends Command
{
    protected $signature = 'saas:verify-access {user : Email veya kullanici_adi} {firma : Firma kodu}';

    protected $description = 'Belirli kullanıcı/firma için modül erişimi ve etkin yetki özetini gösterir.';

    public function handle(ModulErisimService $modulErisimService, YetkiService $yetkiService): int
    {
        $userArg = (string) $this->argument('user');
        $firmaKodu = (string) $this->argument('firma');

        $kullanici = User::withoutGlobalScopes()
            ->where(function ($q) use ($userArg): void {
                $q->where('email', $userArg);
                if (Schema::hasColumn('users', 'kullanici_adi')) {
                    $q->orWhere('kullanici_adi', $userArg);
                }
            })
            ->first();

        if (! $kullanici) {
            $this->error("Kullanıcı bulunamadı: {$userArg}");
            return self::FAILURE;
        }

        $firma = Firma::query()->where('firma_kodu', $firmaKodu)->first();
        if (! $firma) {
            $this->error("Firma bulunamadı: {$firmaKodu}");
            return self::FAILURE;
        }

        $this->line("Kullanıcı: {$kullanici->name} ({$kullanici->email})");
        $this->line("Firma: {$firma->ad} ({$firma->firma_kodu})");

        $moduller = [
            'muhasebe',
            'teknik_servis',
            'barkodlu_satis',
            'depo',
            'restoran',
            'proje_yonetimi',
            'personel_takip',
            'teklif_yonetimi',
            'e_ticaret',
            'bt_varlik_yonetimi',
            'web',
            'urunler',
        ];

        $rows = [];
        foreach ($moduller as $modulKodu) {
            $rows[] = [
                'modul' => $modulKodu,
                'durum' => $modulErisimService->modulDurumu((int) $firma->id, $modulKodu),
            ];
        }

        $this->newLine();
        $this->table(['Modül', 'Durum'], $rows);

        $yetkiler = $yetkiService->etkinYetkiler($kullanici, (int) $firma->id);
        sort($yetkiler);

        $this->newLine();
        $this->info('Toplam etkin yetki: ' . count($yetkiler));
        $this->line(implode(', ', array_slice($yetkiler, 0, 25)));
        if (count($yetkiler) > 25) {
            $this->line('... (devamı var)');
        }

        return self::SUCCESS;
    }
}
