<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nette_fatura_gelen_belgeler')) {
            return;
        }

        Schema::create('nette_fatura_gelen_belgeler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->index()->constrained('firmalar')->cascadeOnDelete();
            $table->string('belge_turu', 32)->index();
            $table->string('provider_invoice_id', 64)->index();
            $table->string('invoice_number', 64)->nullable()->index();
            $table->date('invoice_date')->nullable()->index();
            $table->string('company_name', 255)->nullable();
            $table->decimal('total_amount', 24, 8)->default(0);
            $table->string('currency_code', 8)->nullable();
            $table->string('status', 128)->nullable()->index();
            $table->string('report_status', 128)->nullable();
            $table->string('cancel_report_status', 128)->nullable();
            $table->uuid('ettn')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'belge_turu', 'provider_invoice_id'], 'nette_gelen_belge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nette_fatura_gelen_belgeler');
    }
};
