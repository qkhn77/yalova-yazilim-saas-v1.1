<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi;
use App\Models\Restoran\RestoranReceteKalemi;
use Filament\Resources\Pages\CreateRecord;

class CreateRestoranRecete extends CreateRecord
{
    protected static string $resource = RestoranReceteKaynagi::class;

    /** @var array<int, array<string, mixed>> */
    private array $kalemler = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->kalemler = array_values(array_filter(
            (array) ($data['kalemler'] ?? []),
            static fn ($kalem): bool => is_array($kalem) && filled($kalem['stok_karti_id'] ?? null)
        ));

        unset($data['kalemler']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $firmaId = (int) $this->record->firma_id;
        $receteId = (int) $this->record->getKey();

        foreach ($this->kalemler as $kalem) {
            RestoranReceteKalemi::query()->create([
                'firma_id' => $firmaId,
                'recete_id' => $receteId,
                'stok_karti_id' => (int) ($kalem['stok_karti_id'] ?? 0),
                'miktar' => (float) ($kalem['miktar'] ?? 1),
                'fire_orani' => (float) ($kalem['fire_orani'] ?? 0),
                'notlar' => filled($kalem['notlar'] ?? null) ? trim((string) $kalem['notlar']) : null,
            ]);
        }
    }
}
