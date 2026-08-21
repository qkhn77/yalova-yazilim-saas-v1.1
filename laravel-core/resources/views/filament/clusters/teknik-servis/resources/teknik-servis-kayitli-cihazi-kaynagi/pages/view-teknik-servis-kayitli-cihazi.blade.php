<x-filament-panels::page>
    <div class="ts-cork-screen ts-cork-device-view grid gap-6 lg:grid-cols-3">
        <x-filament::section class="ts-cork-card lg:col-span-1">
            <x-slot name="heading">Cihaz bilgileri</x-slot>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-500">Cihaz no</dt><dd class="font-semibold">{{ $record->cihaz_no }}</dd></div>
                <div><dt class="text-gray-500">Cari</dt><dd>{{ $record->cari?->ad ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Cihaz</dt><dd>{{ $record->cihaz?->ad ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Marka / Model</dt><dd>{{ $record->marka?->ad ?: '-' }} / {{ $record->model_no ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Seri no</dt><dd>{{ $record->seri_no ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Garanti başlangıcı</dt><dd>{{ $record->garanti_baslangic_tarihi?->format('d.m.Y') ?: 'Tarih girilmemiş' }}</dd></div>
                <div><dt class="text-gray-500">Garanti bitişi</dt><dd>{{ $record->garanti_bitis_tarihi?->format('d.m.Y') ?: 'Tarih girilmemiş' }} — {{ $record->garanti_durumu }}</dd></div>
                <div><dt class="text-gray-500">Son bakım</dt><dd>{{ $record->son_bakim_tarihi?->format('d.m.Y') ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Sonraki bakım</dt><dd>{{ $record->sonraki_bakim_tarihi?->format('d.m.Y') ?: '-' }} — {{ $record->bakim_durumu }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section class="ts-cork-card lg:col-span-2">
            <x-slot name="heading">Servis geçmişi ({{ $record->servisKayitlari->count() }})</x-slot>
            <div class="ts-cork-table-wrap overflow-x-auto">
                <table class="ts-cork-table w-full min-w-[680px] text-sm">
                    <thead><tr class="border-b text-left"><th class="p-2">Fiş no</th><th class="p-2">Tarih</th><th class="p-2">Durum</th><th class="p-2">İşlem</th></tr></thead>
                    <tbody>
                    @forelse($record->servisKayitlari as $servis)
                        <tr class="border-b"><td class="p-2">{{ $servis->fis_no ?: '#'.$servis->id }}</td><td class="p-2">{{ optional($servis->created_at)->format('d.m.Y H:i') }}</td><td class="p-2">{{ $servis->servisDurumu?->ad ?: '-' }}</td><td class="p-2"><a class="text-primary-600" href="{{ \App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi::getUrl('view-detail', ['record' => $servis]) }}">Görüntüle</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="ts-cork-empty p-4 text-gray-500">Bu cihaz için servis kaydı bulunmuyor.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section class="ts-cork-card lg:col-span-3">
            <x-slot name="heading">Değişiklik geçmişi</x-slot>
            <div class="space-y-3 text-sm">
                @forelse($record->degisiklikler as $degisiklik)
                    <div class="ts-cork-history-item rounded-lg border p-3"><strong>{{ $degisiklik->kullanici?->name ?: 'Sistem' }}</strong> · {{ optional($degisiklik->created_at)->format('d.m.Y H:i') }}<div class="mt-1 text-gray-600">{{ implode(', ', array_keys($degisiklik->yeni_degerler ?: [])) }} alanları güncellendi.</div></div>
                @empty
                    <div class="ts-cork-empty rounded-lg px-3 py-3 text-gray-500">Henüz değişiklik kaydı yok.</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
