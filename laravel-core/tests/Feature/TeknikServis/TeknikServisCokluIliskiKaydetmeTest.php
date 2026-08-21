<?php

namespace Tests\Feature\TeknikServis;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\TenantContextService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class TeknikServisCokluIliskiKaydetmeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->testSemasiniKur();
    }

    protected function tearDown(): void
    {
        $this->testSemasiniTemizle();

        parent::tearDown();
    }

    public function test_degismeyen_coklu_iliskiler_pivot_yazisi_yapmaz(): void
    {
        $record = $this->testVerisiniKur();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->formMetodu('aksesuarIliskisiniKaydet')->invoke(null, $record, ['1', '2']);
        $this->formMetodu('arizaIliskisiniKaydet')->invoke(null, $record, ['1']);

        $pivotYazilari = array_filter(DB::getQueryLog(), static function (array $query): bool {
            $sql = strtolower((string) ($query['query'] ?? ''));

            return preg_match('/^(insert|update|delete)/', $sql) === 1
                && (
                    str_contains($sql, 'teknik_servis_aksesuar_kayitlari')
                    || str_contains($sql, 'teknik_servis_ariza_kayitlari')
                );
        });

        $this->assertCount(0, $pivotYazilari);
    }

    public function test_degisen_coklu_iliskiler_dogru_senkronlanir(): void
    {
        $record = $this->testVerisiniKur();

        $this->formMetodu('aksesuarIliskisiniKaydet')->invoke(null, $record, [2, 3]);
        $this->formMetodu('arizaIliskisiniKaydet')->invoke(null, $record, [2]);

        $this->assertSame([2, 3], DB::table('teknik_servis_aksesuar_kayitlari')
            ->where('teknik_servis_kaydi_id', 1)
            ->orderBy('aksesuar_id')
            ->pluck('aksesuar_id')
            ->map(static fn ($id): int => (int) $id)
            ->all());

        $this->assertSame([2], DB::table('teknik_servis_ariza_kayitlari')
            ->where('teknik_servis_kaydi_id', 1)
            ->orderBy('ariza_id')
            ->pluck('ariza_id')
            ->map(static fn ($id): int => (int) $id)
            ->all());
    }

    private function formMetodu(string $metot): ReflectionMethod
    {
        $yansima = new ReflectionMethod(TeknikServisKayitFormSchema::class, $metot);
        $yansima->setAccessible(true);

        return $yansima;
    }

    private function testVerisiniKur(): TeknikServisKaydi
    {
        $firma = Firma::query()->create([
            'ad' => 'Test Firma',
            'firma_kodu' => 'ts-coklu-iliski',
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        app(TenantContextService::class)->firmaAyarla($firma);

        DB::table('teknik_servis_kayitlari')->insert([
            'id' => 1,
            'firma_id' => $firma->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1, 2, 3] as $id) {
            DB::table('teknik_servis_tanim_aksesuarlar')->insert([
                'id' => $id,
                'firma_id' => null,
                'ad' => 'Aksesuar '.$id,
                'aktif' => true,
                'siralama' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('teknik_servis_tanim_arizalar')->insert([
                'id' => $id,
                'firma_id' => null,
                'ad' => 'Ariza '.$id,
                'aktif' => true,
                'siralama' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('teknik_servis_aksesuar_kayitlari')->insert([
            [
                'firma_id' => $firma->id,
                'teknik_servis_kaydi_id' => 1,
                'aksesuar_id' => 1,
                'adet' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'firma_id' => $firma->id,
                'teknik_servis_kaydi_id' => 1,
                'aksesuar_id' => 2,
                'adet' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('teknik_servis_ariza_kayitlari')->insert([
            'firma_id' => $firma->id,
            'teknik_servis_kaydi_id' => 1,
            'ariza_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TeknikServisKaydi::query()->withoutGlobalScopes()->findOrFail(1);
    }

    private function testSemasiniKur(): void
    {
        $this->testSemasiniTemizle();

        Schema::create('firmalar', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('firma_kodu')->unique();
            $table->string('durum')->default(Firma::DURUM_AKTIF);
            $table->boolean('onaylandi_mi')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_tanim_aksesuarlar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_tanim_arizalar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_aksesuar_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('aksesuar_id');
            $table->decimal('adet', 10, 2)->default(1);
            $table->string('not')->nullable();
            $table->timestamps();
            $table->unique(['teknik_servis_kaydi_id', 'aksesuar_id']);
        });

        Schema::create('teknik_servis_ariza_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('ariza_id');
            $table->timestamps();
            $table->unique(['teknik_servis_kaydi_id', 'ariza_id']);
        });
    }

    private function testSemasiniTemizle(): void
    {
        Schema::dropIfExists('teknik_servis_ariza_kayitlari');
        Schema::dropIfExists('teknik_servis_aksesuar_kayitlari');
        Schema::dropIfExists('teknik_servis_tanim_arizalar');
        Schema::dropIfExists('teknik_servis_tanim_aksesuarlar');
        Schema::dropIfExists('teknik_servis_kayitlari');
        Schema::dropIfExists('firmalar');
    }
}
