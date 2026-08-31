<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faturalar')) {
            Schema::table('faturalar', function (Blueprint $table): void {
                if (! Schema::hasColumn('faturalar', 'e_belge_uuid')) {
                    $table->char('e_belge_uuid', 36)->nullable()->after('e_belge_tipi');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_durumu')) {
                    $table->string('e_belge_durumu', 32)->nullable()->after('e_belge_uuid')->index();
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_saglayici')) {
                    $table->string('e_belge_saglayici', 64)->nullable()->after('e_belge_durumu');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_saglayici_belge_id')) {
                    $table->string('e_belge_saglayici_belge_id', 191)->nullable()->after('e_belge_saglayici')->index();
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_hash')) {
                    $table->string('e_belge_hash', 128)->nullable()->after('e_belge_saglayici_belge_id');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_gonderildi_at')) {
                    $table->timestamp('e_belge_gonderildi_at')->nullable()->after('e_belge_hash');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_yanit_kodu')) {
                    $table->string('e_belge_yanit_kodu', 64)->nullable()->after('e_belge_gonderildi_at');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_yanit_mesaji')) {
                    $table->text('e_belge_yanit_mesaji')->nullable()->after('e_belge_yanit_kodu');
                }
                if (! Schema::hasColumn('faturalar', 'e_belge_son_hata')) {
                    $table->text('e_belge_son_hata')->nullable()->after('e_belge_yanit_mesaji');
                }
            });
        }

        if (! Schema::hasTable('nette_fatura_gonderimleri')) {
            Schema::create('nette_fatura_gonderimleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->nullable()->index()->constrained('firmalar')->nullOnDelete();
                $table->foreignId('fatura_id')->nullable()->index()->constrained('faturalar')->nullOnDelete();
                $table->string('islem_tipi', 64)->default('sendDocument')->index();
                $table->string('durum', 32)->default('hazirlandi')->index();
                $table->string('endpoint', 512)->nullable();
                $table->string('dosya_adi', 191)->nullable();
                $table->string('document_hash', 128)->nullable();
                $table->string('request_hash', 128)->nullable();
                $table->string('provider_instance_identifier', 191)->nullable()->index();
                $table->text('response_message')->nullable();
                $table->text('error_message')->nullable();
                $table->json('request_meta')->nullable();
                $table->json('response_meta')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nette_fatura_gonderimleri');

        if (Schema::hasTable('faturalar')) {
            Schema::table('faturalar', function (Blueprint $table): void {
                foreach ([
                    'e_belge_son_hata',
                    'e_belge_yanit_mesaji',
                    'e_belge_yanit_kodu',
                    'e_belge_gonderildi_at',
                    'e_belge_hash',
                    'e_belge_saglayici_belge_id',
                    'e_belge_saglayici',
                    'e_belge_durumu',
                    'e_belge_uuid',
                ] as $kolon) {
                    if (Schema::hasColumn('faturalar', $kolon)) {
                        $table->dropColumn($kolon);
                    }
                }
            });
        }
    }
};
