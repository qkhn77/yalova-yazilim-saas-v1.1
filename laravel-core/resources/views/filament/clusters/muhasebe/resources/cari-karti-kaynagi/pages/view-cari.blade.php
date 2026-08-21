<x-filament-panels::page>
    <div class="muhasebe-cork-screen muhasebe-cari-detail space-y-4">
    @php
        /** @var \App\Models\Muhasebe\Cari|null $cari */
        $cari = $this->record;
    @endphp

    @if ($cari)
        @if ($this->sekreterAktifMi())
            <section class="mb-4 rounded-xl border border-primary-200 bg-primary-50/50 p-4 shadow-sm dark:border-primary-800 dark:bg-primary-950/30">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Ajanda ve Görevler</h3>
                        <p class="text-xs text-gray-600 dark:text-gray-300">Bu Cari ile ilişkili görev ve randevular.</p>
                    </div>
                    <a href="{{ \App\Filament\Clusters\Sekreter\Pages\AjandaSayfasi::getUrl() }}" class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-300">Ajandayı aç</a>
                </div>
                <div class="mt-3 grid gap-2 md:grid-cols-2">
                    @forelse ($this->sekreterGorevleri() as $gorev)
                        <a href="{{ \App\Filament\Clusters\Sekreter\Resources\GorevKaynagi::getUrl('edit', ['record' => $gorev]) }}" class="rounded-lg border border-primary-200 bg-white p-3 transition hover:border-primary-500 dark:border-primary-800 dark:bg-gray-900">
                            <div class="font-medium text-gray-950 dark:text-white">{{ $gorev->baslik }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $gorev->tarih?->format('d.m.Y') }} · Görev · {{ $gorev->durum === 'tamamlandi' ? 'Tamamlandı' : 'Bekliyor' }}</div>
                        </a>
                    @empty
                        <p class="text-xs text-gray-500">Bağlı açık görev yok.</p>
                    @endforelse
                    @foreach ($this->sekreterRandevulari() as $randevu)
                        <a href="{{ \App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi::getUrl('edit', ['record' => $randevu]) }}" class="rounded-lg border border-primary-200 bg-white p-3 transition hover:border-primary-500 dark:border-primary-800 dark:bg-gray-900">
                            <div class="font-medium text-gray-950 dark:text-white">{{ $randevu->baslik }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $randevu->baslangic_tarihi?->format('d.m.Y') }} · Randevu · {{ $randevu->baslangic_saati?->format('H:i') }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @if (! \App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi::detayModu())
            @php($bakiye = $this->cariParaBakiyeOzet)

            <div class="yk-cari-detail space-y-3">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ $cari->ad }}</h2>
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-medium text-success-700 dark:bg-success-500/15 dark:text-success-300">{{ $this->cariDurumuMetni() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Kod: {{ $cari->kod ?: '-' }} <span class="mx-1 text-gray-300">·</span> {{ $this->cariTuruMetni() }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $cari->telefon ?: 'Telefon yok' }}</span>
                            <span>{{ $cari->email ?: 'E-posta yok' }}</span>
                        </div>
                    </div>
                    <div class="yk-info-card-grid mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 dark:border-white/10 sm:grid-cols-2 md:grid-cols-4">
                    <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                            <div class="flex items-start justify-between gap-3"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Güncel bakiye</div><x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" /></div>
                            <div class="mt-1 text-xl font-semibold tracking-tight {{ $this->bakiyeYonEtiketi($bakiye['bakiye']) === 'Borç' ? 'text-red-600 dark:text-red-400' : ($this->bakiyeYonEtiketi($bakiye['bakiye']) === 'Alacak' ? 'text-green-600 dark:text-green-400' : 'text-gray-950 dark:text-white') }}">{{ $this->bakiyeYazi($bakiye['bakiye'], $cari->para_birimi) }}</div>
                            @if ($this->bakiyeYonEtiketi($bakiye['bakiye']) !== '')
                                <div class="mt-1 text-xs font-medium {{ $this->bakiyeYonEtiketi($bakiye['bakiye']) === 'Alacak' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $this->bakiyeYonEtiketi($bakiye['bakiye']) }}</div>
                            @endif
                        </div>
                        <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                            <div class="flex items-start justify-between gap-3"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Toplam borç</div><x-filament::icon icon="heroicon-m-arrow-up-circle" class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" /></div>
                            <div class="mt-1 text-xl font-semibold tracking-tight text-danger-600 dark:text-danger-400">{{ $this->bakiyeYazi($bakiye['toplam_borc'], $cari->para_birimi) }}</div>
                            <div class="mt-1 text-xs text-warning-600 dark:text-warning-400">Cari borç toplamı</div>
                        </div>
                        <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                            <div class="flex items-start justify-between gap-3"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Toplam alacak</div><x-filament::icon icon="heroicon-m-arrow-down-circle" class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" /></div>
                            <div class="mt-1 text-xl font-semibold tracking-tight text-green-600 dark:text-green-400">{{ $this->bakiyeYazi($bakiye['toplam_alacak'], $cari->para_birimi) }}</div>
                            <div class="mt-1 text-xs text-success-600 dark:text-success-400">Cari alacak toplamı</div>
                        </div>
                        <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                            <div class="flex items-start justify-between gap-3"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Para birimi</div><x-filament::icon icon="heroicon-m-currency-dollar" class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" /></div>
                            <div class="mt-1 truncate text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $this->paraBirimiMetni() }}</div>
                            <div class="mt-1 text-xs text-primary-600 dark:text-primary-400">Cari hesap para birimi</div>
                        </div>
                        @php($bazYon = $this->bakiyeYonEtiketi($this->bazBakiyeOzet['bakiye']))
                        @php($bazKartSinifi = $bazYon === 'Borç' ? 'border-red-300 bg-red-50/70 dark:border-red-800 dark:bg-red-950/30' : ($bazYon === 'Alacak' ? 'border-green-300 bg-green-50/70 dark:border-green-800 dark:bg-green-950/30' : 'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900'))
                        @php($bazKartStili = $bazYon === 'Borç' ? 'background-color:#fef2f2 !important;border-color:#fca5a5 !important' : ($bazYon === 'Alacak' ? 'background-color:#f0fdf4 !important;border-color:#86efac !important' : ''))
                        <div style="{{ $bazKartStili }}" class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border {{ $bazKartSinifi }} p-3 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-4">
                            <div class="flex items-start justify-between gap-3"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Genel bakiye (Güncel {{ $this->bazParaBirimi }} karşılığı)</div><x-filament::icon icon="heroicon-m-calculator" class="h-4 w-4 shrink-0 {{ $bazYon === 'Borç' ? 'text-danger-600 dark:text-danger-400' : ($bazYon === 'Alacak' ? 'text-success-600 dark:text-success-400' : 'text-gray-400') }}" /></div>
                            <div style="color:{{ $bazYon === 'Borç' ? '#dc2626 !important' : ($bazYon === 'Alacak' ? '#16a34a !important' : 'inherit') }}" class="mt-1 text-xl font-semibold tracking-tight">{{ $this->bakiyeYazi($this->bazBakiyeOzet['bakiye'], $this->bazParaBirimi) }}</div>
                            @if ($bazYon !== '')
                                <div class="mt-1 text-xs font-semibold {{ $bazYon === 'Borç' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ $bazYon }} bakiyesi</div>
                                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Borç: {{ $this->paraYaziPara($this->bazBakiyeOzet['borc'], $this->bazParaBirimi) }} · Alacak: {{ $this->paraYaziPara($this->bazBakiyeOzet['alacak'], $this->bazParaBirimi) }}</div>
                            @else
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Borç ve alacak yok</div>
                            @endif
                        </div>
                    </div>
                    @if ($this->cariHesapParaBakiyeSatirlari() !== [])
                        <div class="mt-3 rounded-lg border border-warning-200 bg-warning-50/50 px-3 py-2 dark:border-warning-800 dark:bg-warning-950/20">
                            <div class="text-xs font-semibold text-warning-800 dark:text-warning-200">Cari hesap para birimi bakiyeleri</div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($this->cariHesapParaBakiyeSatirlari() as $digerBakiye)
                                    @php($digerYon = $this->bakiyeYonEtiketi($digerBakiye['bakiye']))
                                    <div class="rounded-md border border-warning-200 bg-white/70 px-2 py-1.5 text-xs dark:border-warning-800 dark:bg-gray-900/60">
                                        <div style="color:{{ $digerYon === 'Borç' ? '#dc2626 !important' : ($digerYon === 'Alacak' ? '#16a34a !important' : 'inherit') }}" class="font-semibold">{{ $this->bakiyeYazi($digerBakiye['bakiye'], $digerBakiye['para_birimi']) }}@if ($digerYon !== '') ({{ $digerYon }})@endif</div>
                                        <div class="mt-0.5 text-gray-600 dark:text-gray-300">Borç: {{ $this->paraYaziPara($digerBakiye['toplam_borc'], $digerBakiye['para_birimi']) }} · Alacak: {{ $this->paraYaziPara($digerBakiye['toplam_alacak'], $digerBakiye['para_birimi']) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="p-2">
                        {{ $this->table }}
                    </div>
                </div>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-sm font-semibold text-gray-950 outline-none dark:text-white">
                        <span>İletişim</span>
                        <span class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="border-t border-gray-100 px-3 pb-3 pt-2 dark:border-white/10">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-4">
                            <div class="col-span-2"><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Adres</dt><dd class="truncate text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->adres ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Ülke</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->ulke ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">İl</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->il ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">İlçe</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->ilce ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Posta kodu</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->posta_kodu ?: '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Email</dt><dd class="truncate text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->email ?: '-' }}</dd></div>
                        </dl>
                        <div class="mt-2 border-t border-gray-100 pt-2 dark:border-white/10">
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs leading-5"><span class="font-medium text-gray-500 dark:text-gray-400">Telefonlar</span><span class="text-gray-950 dark:text-white">Telefon: {{ $cari->telefon ?: '-' }}</span><span class="text-gray-950 dark:text-white">2. Telefon: {{ $cari->gsm ?: '-' }}</span></div>
                        </div>
                    </div>
                </details>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-sm font-semibold text-gray-950 outline-none dark:text-white">
                        <span>Hesap bilgileri</span>
                        <span class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="border-t border-gray-100 px-3 pb-3 pt-2 dark:border-white/10">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-4">
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Cari türü</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ $this->cariTuruMetni() }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Durum</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ $this->cariDurumuMetni() }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Cari grubu</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ $cari->cariGrubu?->ad ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Vergi</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ $cari->vergi_no || $cari->vergi_dairesi ? 'Var' : '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Vergi dairesi</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->vergi_dairesi ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Vergi no</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->vergi_no ?: '-' }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">T.C. kimlik no</dt><dd class="text-xs leading-5 text-gray-950 dark:text-white">{{ $cari->tc_no ?: '-' }}</dd></div>
                        </dl>
                    </div>
                </details>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-sm font-semibold text-gray-950 outline-none dark:text-white">
                        <span>Finans ve koşullar</span>
                        <span class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="border-t border-gray-100 px-3 pb-3 pt-2 dark:border-white/10">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-4">
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Risk limiti</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ $this->paraYazi($cari->risk_limiti) }}</dd></div>
                            <div><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Vade (gün)</dt><dd class="text-xs font-medium leading-5 text-gray-950 dark:text-white">{{ (int) ($cari->vade_gunu ?? 0) }}</dd></div>
                            <div class="col-span-2"><dt class="text-[11px] leading-4 text-gray-500 dark:text-gray-400">Para birimi</dt><dd class="flex flex-wrap items-center gap-2 text-xs font-medium leading-5 text-gray-950 dark:text-white"><span>{{ $this->paraBirimiMetni() }}</span><a href="{{ $this->paraBirimiUrl() }}" class="text-[11px] font-medium text-primary-600 hover:underline">Para birimlerini yönet</a></dd></div>
                        </dl>
                        <div class="mt-2 grid grid-cols-3 gap-3 border-t border-gray-100 pt-2 text-xs dark:border-white/10">
                            <div><span class="text-[11px] text-gray-500 dark:text-gray-400">Güncel bakiye</span><div class="font-medium leading-5 text-gray-950 dark:text-white">{{ $this->bakiyeYazi($bakiye['bakiye'], $cari->para_birimi) }} @if ($this->bakiyeYonEtiketi($bakiye['bakiye']) !== '')<span class="text-xs">({{ $this->bakiyeYonEtiketi($bakiye['bakiye']) }})</span>@endif</div></div>
                            <div><span class="text-[11px] text-gray-500 dark:text-gray-400">Toplam borç</span><div class="leading-5 text-gray-950 dark:text-white">{{ $this->paraYazi($bakiye['toplam_borc']) }}</div></div>
                            <div><span class="text-[11px] text-gray-500 dark:text-gray-400">Toplam alacak</span><div class="leading-5 text-gray-950 dark:text-white">{{ $this->paraYazi($bakiye['toplam_alacak']) }}</div></div>
                        </div>
                    </div>
                </details>

            </div>
        @else
        <div class="space-y-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Firma</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $cari->firma?->ad ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Kod</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $cari->kod ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Cari türü</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $this->cariTuruMetni() }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Durum</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $this->cariDurumuMetni() }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Ana para birimi</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ strtoupper((string) ($cari->para_birimi ?: 'TRY')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Risk limiti</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $this->paraYazi($cari->risk_limiti) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Vade</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ (int) ($cari->vade_gunu ?? 0) }} gün</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Kısa ad</div>
                        <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $cari->kisa_ad ?: '-' }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Telefon</div>
                        <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $cari->telefon ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">2. Telefon</div>
                        <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $cari->gsm ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">E-posta</div>
                        <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $cari->email ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Yetkili</div>
                        <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $cari->yetkili_kisi ?: '-' }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Adres</div>
                        <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $cari->adres ?: '-' }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="{{ $this->tumFaturalarUrl() }}" class="font-medium text-primary-600 hover:underline">Tüm Faturalar</a>
                    <a href="{{ $this->vadeTakibiUrl() }}" class="font-medium text-primary-600 hover:underline">Vade Takibi</a>
                    @if ($this->ekstreUrl())
                        <a href="{{ $this->ekstreUrl() }}" class="font-medium text-primary-600 hover:underline">Ekstre ekranı</a>
                    @endif
                </div>
            </section>
        </div>
        @endif
    @endif
    </div>
</x-filament-panels::page>
