<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firmaIds = DB::table('firma_ayarlari')
            ->whereIn('anahtar', [
                'barkodlu_satis_telegram_bot_token',
                'teknik_servis_telegram_bot_token',
                'barkodlu_satis_telegram_chat_id',
            ])
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('firma_id');

        foreach ($firmaIds as $firmaId) {
            $this->kopyala($firmaId, 'telegram_bot_token', [
                'barkodlu_satis_telegram_bot_token',
                'teknik_servis_telegram_bot_token',
            ]);
            $this->kopyala($firmaId, 'telegram_chat_id', ['barkodlu_satis_telegram_chat_id']);
        }
    }

    public function down(): void
    {
        // Ortak ayarlar kullanıcı tarafından güncellenmiş olabilir; geri alırken silinmez.
    }

    /** @param array<int, string> $eskiAnahtarlar */
    private function kopyala(int|string $firmaId, string $yeniAnahtar, array $eskiAnahtarlar): void
    {
        $mevcut = DB::table('firma_ayarlari')
            ->where('firma_id', $firmaId)
            ->where('anahtar', $yeniAnahtar)
            ->whereNull('deleted_at')
            ->exists();

        if ($mevcut) {
            return;
        }

        foreach ($eskiAnahtarlar as $eskiAnahtar) {
            $deger = DB::table('firma_ayarlari')
                ->where('firma_id', $firmaId)
                ->where('anahtar', $eskiAnahtar)
                ->whereNull('deleted_at')
                ->value('deger');

            if ($deger === null || $deger === '') {
                continue;
            }

            $silinmisMevcut = DB::table('firma_ayarlari')
                ->where('firma_id', $firmaId)
                ->where('anahtar', $yeniAnahtar)
                ->first();

            if ($silinmisMevcut) {
                DB::table('firma_ayarlari')
                    ->where('id', $silinmisMevcut->id)
                    ->update([
                        'deger' => $deger,
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);

                return;
            }

            DB::table('firma_ayarlari')->insert([
                'firma_id' => $firmaId,
                'anahtar' => $yeniAnahtar,
                'deger' => $deger,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }
    }
};
