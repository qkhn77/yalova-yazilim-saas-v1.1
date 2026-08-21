@php
    /** @var \App\Models\Firma|null $firma */
    $firma = $firma ?? null;
    $aktifModuller = $aktifModuller ?? [];
    $saltOkunurModuller = $saltOkunurModuller ?? [];
    $kullaniciSayisi = $kullaniciSayisi ?? 0;
    $abonelik = $abonelik ?? null;
    $guncellendiAt = $guncellendiAt ?? null;
    $mesajKpiKarti = $mesajKpiKarti ?? null;
    $gunlukOzetKartlari = $gunlukOzetKartlari ?? [];
    $oncelikKartlari = $oncelikKartlari ?? [];
    $servisAkisKartlari = $servisAkisKartlari ?? [];
    $aksiyonUyarilari = $aksiyonUyarilari ?? [];
    $modulKpiKartlari = $modulKpiKartlari ?? [];
    $hizliIslemGruplari = $hizliIslemGruplari ?? [];
    $altListeler = $altListeler ?? [];
    $abonelikBitisTarihi = $abonelik?->bitis_tarihi;
    $abonelikKalanGun = $abonelikBitisTarihi ? now()->startOfDay()->diffInDays($abonelikBitisTarihi->copy()->startOfDay(), false) : null;
@endphp

<x-filament-widgets::widget>
    @if (! $firma)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Firma bilgisi yüklenemedi.</p>
        </x-filament::section>
    @else
        <div class="yk-dashboard-shell space-y-3">
            <div class="yk-dashboard-summary flex flex-wrap gap-2">
                <div class="yk-dashboard-summary-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Firma</div>
                    <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $firma->ad }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ \App\Models\Firma::durumSecenekleri()[$firma->durum] ?? $firma->durum }}
                    </div>
                </div>

                <div class="yk-dashboard-summary-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Abonelik</div>
                    <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $abonelik?->plan?->ad ?? 'Aktif plan yok' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $abonelik?->bitis_tarihi ? 'Bitiş: '.$abonelik->bitis_tarihi->format('d.m.Y') : 'Süre tanımsız' }}
                    </div>
                </div>

                <div class="yk-dashboard-summary-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kullanıcı</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $kullaniciSayisi }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aktif firma kullanıcısı</div>
                </div>

                <div class="yk-dashboard-summary-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Modüller</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ count($aktifModuller) }} aktif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ count($saltOkunurModuller) }} salt okunur
                    </div>
                </div>

                <div class="yk-dashboard-summary-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Veri</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $guncellendiAt ?? '-' }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Son hesaplama</div>
                </div>
            </div>

            @if (! $abonelik || ($abonelikKalanGun !== null && $abonelikKalanGun <= 30))
                <div class="rounded-md border px-3 py-2 shadow-sm {{ ! $abonelik || ($abonelikKalanGun !== null && $abonelikKalanGun < 0) ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold">
                                {{ ! $abonelik ? 'Aktif abonelik bulunamadı' : ($abonelikKalanGun < 0 ? 'Abonelik süresi doldu' : 'Abonelik bitişi yaklaşıyor') }}
                            </div>
                            <div class="mt-0.5 truncate text-xs">
                                {{ ! $abonelik ? 'Plan ve modül erişimleri kontrol edilmeli.' : 'Bitiş: '.$abonelikBitisTarihi->format('d.m.Y').' / '.$abonelikKalanGun.' gün' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="yk-dashboard-panel rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Modül erişimi</h3>
                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">Aktif firma için kullanılabilir modül durumu.</p>
                    </div>
                </div>

                <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
                    <div class="rounded-md border border-emerald-100 bg-emerald-50 px-2 py-1.5 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">
                        <div class="text-[11px] font-medium">Aktif</div>
                        <div class="mt-0.5 truncate text-xs">{{ $aktifModuller !== [] ? implode(', ', array_slice($aktifModuller, 0, 6)) : 'Aktif modül yok' }}</div>
                    </div>
                    <div class="rounded-md border border-amber-100 bg-amber-50 px-2 py-1.5 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                        <div class="text-[11px] font-medium">Salt okunur</div>
                        <div class="mt-0.5 truncate text-xs">{{ $saltOkunurModuller !== [] ? implode(', ', array_slice($saltOkunurModuller, 0, 6)) : 'Yok' }}</div>
                    </div>
                </div>
            </div>

            @if ($gunlukOzetKartlari !== [] || $oncelikKartlari !== [])
                <div class="yk-dashboard-top-grid grid gap-3">
                    @if ($gunlukOzetKartlari !== [])
                        <div class="yk-dashboard-panel rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-center justify-between gap-3 px-1">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Bugün</h3>
                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">Günlük operasyon nabzı.</p>
                                </div>
                            </div>

                            <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr));">
                                @foreach ($gunlukOzetKartlari as $ozet)
                                    <a
                                        href="{{ $ozet['url'] }}"
                                        class="rounded-md border border-gray-100 bg-gray-50 px-2 py-1.5 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-primary-700 dark:hover:bg-primary-950/20"
                                    >
                                        <div class="truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $ozet['baslik'] }}</div>
                                        <div class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $ozet['deger'] }}</div>
                                        <div class="mt-0.5 truncate text-[10px] text-gray-500 dark:text-gray-400">{{ $ozet['aciklama'] }}</div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($oncelikKartlari !== [])
                        <div class="yk-dashboard-panel rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-center justify-between gap-3 px-1">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Bugünün öncelikleri</h3>
                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">Acil takip gerektiren servis, tahsilat, stok ve mesaj başlıkları.</p>
                                </div>
                            </div>

                            <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));">
                                @foreach ($oncelikKartlari as $oncelik)
                                    @php
                                        $renk = $oncelik['renk'] ?? 'gray';
                                        $renkSinifi = match ($renk) {
                                            'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
                                            'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
                                            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
                                            'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300',
                                            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-300',
                                        };
                                    @endphp
                                    <a
                                        href="{{ $oncelik['url'] }}"
                                        class="rounded-md border px-2 py-1.5 transition hover:border-primary-300 hover:bg-primary-50 dark:hover:border-primary-700 dark:hover:bg-primary-950/20 {{ $renkSinifi }}"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-[11px] font-medium">{{ $oncelik['baslik'] }}</span>
                                            <span class="shrink-0 text-[10px]">&rarr;</span>
                                        </div>
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="truncate text-sm font-semibold">{{ $oncelik['deger'] }}</span>
                                            <span class="truncate text-[10px]">{{ $oncelik['aciklama'] }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($servisAkisKartlari !== [])
                <div class="yk-dashboard-panel yk-dashboard-flow-panel rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 px-1">
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Servis iş akışı</h3>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">Teknik servis yoğunluğu ve bekleyen darboğazlar.</p>
                        </div>
                    </div>

                    <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(8.75rem, 1fr));">
                        @foreach ($servisAkisKartlari as $akis)
                            @php
                                $renk = $akis['renk'] ?? 'gray';
                                $renkSinifi = match ($renk) {
                                    'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
                                    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
                                    'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300',
                                    default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-300',
                                };
                            @endphp
                            <a
                                href="{{ $akis['url'] }}"
                                class="rounded-md border px-2 py-1.5 transition hover:border-primary-300 hover:bg-primary-50 dark:hover:border-primary-700 dark:hover:bg-primary-950/20 {{ $renkSinifi }}"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-[11px] font-medium">{{ $akis['baslik'] }}</span>
                                    <span class="shrink-0 text-[10px]">&rarr;</span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-2">
                                    <span class="truncate text-base font-semibold leading-5">{{ $akis['deger'] }}</span>
                                    <span class="truncate text-[10px]">{{ $akis['aciklama'] }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="yk-dashboard-panel rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Aksiyon uyarıları</h3>
                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">Öncelikli müdahale gerektiren işler.</p>
                    </div>
                </div>

                @if ($aksiyonUyarilari !== [])
                    <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
                        @foreach ($aksiyonUyarilari as $uyari)
                            @php
                                $seviye = $uyari['seviye'] ?? 'gray';
                                $seviyeSinifi = match ($seviye) {
                                    'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
                                    'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
                                    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
                                    'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300',
                                    default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-300',
                                };
                            @endphp
                            <a
                                href="{{ $uyari['url'] }}"
                                class="rounded-md border px-2 py-1.5 transition hover:border-primary-300 hover:bg-primary-50 dark:hover:border-primary-700 dark:hover:bg-primary-950/20 {{ $seviyeSinifi }}"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-xs font-semibold">{{ $uyari['baslik'] }}</div>
                                        <div class="mt-0.5 truncate text-[10px]">{{ $uyari['aciklama'] }}</div>
                                    </div>
                                    <div class="shrink-0 text-sm font-semibold">{{ $uyari['deger'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-xs font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">
                        Kritik bekleyen iş görünmüyor.
                    </div>
                @endif
            </div>

            @if ($mesajKpiKarti)
                <div class="yk-dashboard-panel rounded-md border border-sky-200 bg-white p-2 shadow-sm dark:border-sky-900 dark:bg-gray-900">
                    <a
                        href="{{ $mesajKpiKarti['url'] }}"
                        class="flex items-center justify-between gap-3 rounded-md px-1 py-0.5 transition hover:bg-sky-50 dark:hover:bg-sky-950/20"
                    >
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $mesajKpiKarti['baslik'] }}</h3>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $mesajKpiKarti['aciklama'] }}</p>
                        </div>
                        <span class="shrink-0 text-sm text-sky-600 dark:text-sky-300">&rarr;</span>
                    </a>

                    <div class="mt-2 grid gap-1.5 sm:grid-cols-3">
                        @foreach ($mesajKpiKarti['bilgiler'] as $bilgi)
                            <a
                                href="{{ $bilgi['url'] }}"
                                class="rounded-md border border-gray-100 bg-gray-50 px-2 py-1.5 transition hover:border-sky-300 hover:bg-sky-50 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-sky-800 dark:hover:bg-sky-950/20"
                            >
                                <div class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ $bilgi['etiket'] }}</div>
                                <div class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $bilgi['deger'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($modulKpiKartlari !== [])
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Modül KPI'ları</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Ana modül sağlığı</span>
                </div>
            @endif

            <div class="yk-dashboard-module-kpi-grid grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));">
                @foreach ($modulKpiKartlari as $kart)
                    <div class="yk-dashboard-module-kpi-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <a
                            href="{{ $kart['url'] }}"
                            class="-mx-1 -mt-1 flex items-center justify-between gap-2 rounded-md px-1 py-1 transition hover:bg-primary-50 dark:hover:bg-primary-950/20"
                        >
                            <div class="truncate text-[11px] font-semibold leading-4 text-gray-950 dark:text-white">{{ $kart['baslik'] }}</div>
                            <span class="shrink-0 text-[10px] text-gray-400">&rarr;</span>
                        </a>

                        <div class="mt-1 grid gap-1" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            @foreach (($kart['bilgiler'] ?? []) as $bilgi)
                                <a
                                    href="{{ $bilgi['url'] ?? $kart['url'] }}"
                                    class="min-w-0 rounded border border-gray-100 bg-gray-50 px-1.5 py-1 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-primary-700 dark:hover:bg-primary-950/20"
                                >
                                    <div class="truncate text-[9px] leading-3 text-gray-500 dark:text-gray-400">{{ $bilgi['etiket'] }}</div>
                                    <div class="mt-0.5 truncate text-[11px] font-semibold leading-4 text-gray-950 dark:text-white">{{ $bilgi['deger'] }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($hizliIslemGruplari !== [])
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Hızlı işlemler</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Sık kullanılan ekranlar</span>
                </div>
            @endif

            <div class="yk-dashboard-quick-grid grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(8.75rem, 1fr));">
                @foreach ($hizliIslemGruplari as $grup)
                    <div class="yk-dashboard-quick-card rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <a
                            href="{{ $grup['url'] }}"
                            class="-m-1 block rounded-md p-1 transition hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="truncate text-[10px] font-semibold leading-3 text-gray-950 dark:text-white">{{ $grup['baslik'] }}</h3>
                                    <p class="mt-0.5 truncate text-[9px] leading-[11px] text-gray-500 dark:text-gray-400">{{ $grup['aciklama'] }}</p>
                                </div>
                                <span class="mt-0.5 shrink-0 text-[10px] text-gray-400">→</span>
                            </div>
                        </a>
                        <div class="mt-1.5 grid gap-1">
                            @foreach ($grup['aksiyonlar'] as $aksiyon)
                                <a
                                    href="{{ $aksiyon['url'] }}"
                                    class="flex items-center justify-between rounded-md border px-1.5 py-1 text-[10px] font-medium leading-3 transition {{ ($aksiyon['vurgu'] ?? false) ? 'border-primary-300 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300' : 'border-gray-200 text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-800 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300' }}"
                                >
                                    <span class="truncate">{{ $aksiyon['etiket'] }}</span>
                                    <span class="ml-1 text-[10px]">→</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($altListeler !== [])
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Operasyon listeleri</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Son ve bekleyen kayıtlar</span>
                </div>
            @endif

            <div class="yk-dashboard-grid grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($altListeler as $liste)
                    @php
                        $listeVurgu = $liste['vurgu'] ?? null;
                        $listeSinifi = match ($listeVurgu) {
                            'danger' => 'border-red-200 dark:border-red-900',
                            'warning' => 'border-amber-200 dark:border-amber-900',
                            default => 'border-gray-200 dark:border-gray-800',
                        };
                    @endphp
                    <div class="yk-dashboard-list-card rounded-md border bg-white p-3 shadow-sm dark:bg-gray-900 {{ $listeSinifi }}">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $liste['baslik'] }}</h3>
                        <div class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($liste['kayitlar'] as $kayit)
                                <a
                                    @if ($kayit['url']) href="{{ $kayit['url'] }}" @endif
                                    class="block py-1.5 first:pt-0 last:pb-0"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-xs font-medium text-gray-900 dark:text-white">{{ $kayit['baslik'] }}</div>
                                            <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $kayit['alt'] }}</div>
                                        </div>
                                        <div class="shrink-0 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $kayit['deger'] }}</div>
                                    </div>
                                </a>
                            @empty
                                <div class="py-2 text-sm text-gray-500 dark:text-gray-400">{{ $liste['bos'] }}</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
