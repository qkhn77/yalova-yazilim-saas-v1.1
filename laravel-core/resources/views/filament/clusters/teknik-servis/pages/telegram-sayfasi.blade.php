@php
    $sonTestDurumu = (string) ($sonTest['durum'] ?? '');
    $sonTestBasarili = $sonTestDurumu === 'basarili';
    $sonTestBasarisiz = $sonTestDurumu === 'basarisiz';
    $botToken = (string) ($data['telegram_bot_token'] ?? '');
    $chatId = (string) ($data['telegram_chat_id'] ?? '');
@endphp

<x-filament-panels::page>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Eklenti</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ ($data['teknik_servis_telegram_aktif_mi'] ?? false) ? 'Aktif' : 'Pasif' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Bot token</div>
            <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white" title="{{ $botToken }}">
                {{ $botToken !== '' ? 'Tanımlı' : 'Eksik' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Chat ID</div>
            <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white" title="{{ $chatId }}">
                {{ $chatId !== '' ? $chatId : 'Eksik' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Son test</div>
            <div @class([
                'mt-1 text-sm font-semibold',
                'text-success-700 dark:text-success-300' => $sonTestBasarili,
                'text-danger-700 dark:text-danger-300' => $sonTestBasarisiz,
                'text-gray-950 dark:text-white' => ! $sonTestBasarili && ! $sonTestBasarisiz,
            ])>
                {{ $sonTestBasarili ? 'Başarılı' : ($sonTestBasarisiz ? 'Başarısız' : 'Test yok') }}
            </div>
        </div>
    </div>

    <form wire:submit="kaydet" class="space-y-5">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Ortak bağlantı bilgileri</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Bot token ve Chat ID tüm modüller için <a href="{{ url('/admin/firma-ayarlari') }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">Firma Ayarları</a> sayfasındaki Telegram alanından yönetilir.
            </p>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Bildirim olayları</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Teknik servis akışında Telegram'a gönderilecek olayları seçin.
                </p>
            </div>

            <div class="grid gap-3">
                <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="data.teknik_servis_telegram_aktif_mi" class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <span>Telegram eklentisi aktif</span>
                </label>

                <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="data.teknik_servis_telegram_yeni_servis_aktif_mi" class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <span>Yeni Servis Ekleme Bildirimi</span>
                </label>

                <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="data.teknik_servis_telegram_teslim_edildi_aktif_mi" class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <span>Teslim Edildi Bildirimi</span>
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Test ve önizleme</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Ayarları kaydedip Telegram bağlantısını kontrol edin.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="kaydet" class="rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-60">
                        Kaydet
                    </button>

                    <button type="button" wire:click="testGonder" wire:loading.attr="disabled" wire:target="testGonder" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        Telegram test mesajı gönder
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Son test</span>
                        <span @class([
                            'rounded px-2 py-1 text-xs font-semibold',
                            'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300' => $sonTestBasarili,
                            'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300' => $sonTestBasarisiz,
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! $sonTestBasarili && ! $sonTestBasarisiz,
                        ])>
                            {{ $sonTestBasarili ? 'Başarılı' : ($sonTestBasarisiz ? 'Başarısız' : 'Test yok') }}
                        </span>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tarih</div>
                            <div class="mt-0.5 text-gray-900 dark:text-gray-100">{{ $sonTest['tarih'] ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Sonuç</div>
                            <div class="mt-0.5 break-words text-gray-900 dark:text-gray-100">{{ $sonTest['mesaj'] ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Mesaj önizlemesi</span>
                    <pre class="mt-1 max-h-52 overflow-auto whitespace-pre-wrap rounded-md border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">{{ $mesajOnizleme }}</pre>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Son Telegram bildirimleri</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">Son 10 kayıt</span>
            </div>

            <div class="overflow-hidden rounded-md border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[680px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tarih</th>
                            <th class="px-3 py-2">Bildirim</th>
                            <th class="px-3 py-2">Durum</th>
                            <th class="px-3 py-2">Hata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($bildirimGecmisi as $log)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2">{{ $log['tarih'] }}</td>
                                <td class="px-3 py-2 font-medium">{{ $log['konu'] }}</td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'rounded px-2 py-1 text-xs font-semibold',
                                        'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300' => $log['durum'] === 'gonderildi',
                                        'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300' => $log['durum'] === 'hata',
                                        'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300' => $log['durum'] === 'atlanan',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! in_array($log['durum'], ['gonderildi', 'hata', 'atlanan'], true),
                                    ])>
                                        {{ match ($log['durum']) {
                                            'gonderildi' => 'Gönderildi',
                                            'hata' => 'Hata',
                                            'atlanan' => 'Atlandı',
                                            default => $log['durum'],
                                        } }}
                                    </span>
                                </td>
                                <td class="max-w-md break-words px-3 py-2 text-gray-600 dark:text-gray-300">{{ $log['hata'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-5 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Telegram bildirimi yok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </form>
</x-filament-panels::page>
