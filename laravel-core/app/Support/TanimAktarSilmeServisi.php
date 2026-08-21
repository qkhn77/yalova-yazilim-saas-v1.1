<?php

namespace App\Support;

use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TanimAktarSilmeServisi
{
    /** @var array<int,string> */
    private const TANIM_TABLOLARI = [
        'muhasebe_birimler', 'muhasebe_cari_gruplari', 'muhasebe_depolar',
        'muhasebe_doviz_kurlari', 'muhasebe_logo_turleri', 'muhasebe_malzeme_turleri',
        'muhasebe_markalar', 'muhasebe_marka_ureticileri', 'muhasebe_modeller',
        'muhasebe_tasarimlar', 'muhasebe_varyantlar', 'muhasebe_odeme_yontemleri',
        'muhasebe_para_birimleri', 'muhasebe_vergi_oranlari', 'stok_kategorileri',
        'masraf_kategorileri', 'personel_departmanlari', 'personel_gorevleri',
        'personel_vardiya_sablonlari', 'restoran_menu_kategorileri', 'restoran_masalari',
        'restoran_salonlari', 'teknik_servis_tanim_cihazlar', 'teknik_servis_tanim_markalar',
        'teknik_servis_tanim_aksesuarlar', 'teknik_servis_tanim_arizalar',
        'teknik_servis_tanim_servis_durumlari',
    ];

    public static function uygulanabilirMi(Model $record): bool
    {
        return in_array($record->getTable(), self::TANIM_TABLOLARI, true);
    }

    /** @return array<string,string> */
    public static function hedefSecenekleri(Model $record): array
    {
        if (! self::uygulanabilirMi($record)) {
            return [];
        }

        $query = $record::query()->whereKeyNot($record->getKey())->orderBy('ad');
        if (Schema::hasColumn($record->getTable(), 'firma_id')) {
            $query->where('firma_id', $record->firma_id);
        }

        return $query->pluck('ad', 'id')->all();
    }

    /** @return array<int,\Filament\Forms\Components\Component> */
    public static function form(Model $record): array
    {
        if (! self::uygulanabilirMi($record)) {
            return [];
        }

        return [
            Select::make('hedef_id')
                ->label('Hedef tanım')
                ->options(fn (): array => self::hedefSecenekleri($record))
                ->required()
                ->searchable()
                ->placeholder('Aktarılacak tanımı seçin'),
        ];
    }

    public static function uygula(Model $record, array $data = []): bool
    {
        if (! self::uygulanabilirMi($record)) {
            return (bool) $record->delete();
        }

        $hedefId = (int) ($data['hedef_id'] ?? 0);
        $hedef = $hedefId > 0 ? $record::query()->find($hedefId) : null;
        $referanslar = self::referanslar($record->getTable());

        if ($referanslar !== [] && ! $hedef) {
            Notification::make()
                ->title('Hedef tanım seçilmelidir')
                ->body('Bağlı kayıtlar bulunduğu için aktarılacak tanımı seçin.')
                ->danger()
                ->send();

            return false;
        }

        if ($hedef && (int) $hedef->getKey() === (int) $record->getKey()) {
            return false;
        }

        return (bool) DB::transaction(function () use ($record, $hedef, $referanslar): bool {
            if ($hedef) {
                foreach ($referanslar as $referans) {
                    $query = DB::table($referans['table'])->where($referans['column'], $record->getKey());
                    if (Schema::hasColumn($referans['table'], 'firma_id') && Schema::hasColumn($record->getTable(), 'firma_id')) {
                        $query->where('firma_id', $record->firma_id);
                    }
                    $query->update([$referans['column'] => $hedef->getKey()]);
                }
            }

            return (bool) $record->delete();
        });
    }

    /** @return array<int,array{table:string,column:string}> */
    private static function referanslar(string $tablo): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return [];
        }

        return array_map(static fn (object $satir): array => [
            'table' => (string) $satir->table,
            'column' => (string) $satir->column,
        ], DB::select(
            'select TABLE_NAME as `table`, COLUMN_NAME as `column` from information_schema.KEY_COLUMN_USAGE where CONSTRAINT_SCHEMA = DATABASE() and REFERENCED_TABLE_NAME = ? and REFERENCED_COLUMN_NAME = ?',
            [$tablo, 'id'],
        ));
    }
}
