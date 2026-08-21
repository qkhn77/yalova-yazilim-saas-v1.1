<x-filament-panels::page>
    @php
        $detayRaporlariYuklendi = $this->detayRaporlariYuklendi;
        $hatirlatmaOzetiYuklendi = $this->hatirlatmaOzetiYuklendi;
        $hatirlatma = $hatirlatmaOzetiYuklendi ? $this->hatirlatmaOzeti() : [];
        $takipAjandasi = $detayRaporlariYuklendi ? $this->takipAjandasiSatirlari() : [];
        $tahsilatOncelikleri = $detayRaporlariYuklendi ? $this->tahsilatOncelikSatirlari() : [];
        $tahsilatPerformansi = $detayRaporlariYuklendi ? $this->tahsilatPerformansiSatirlari() : [];
        $riskSkorlari = $detayRaporlariYuklendi ? $this->riskSkoruSatirlari() : [];
        $hatirlatmaLoglari = $detayRaporlariYuklendi ? $this->hatirlatmaGonderimLoglari() : [];
        $onayBekleyenTalepler = $detayRaporlariYuklendi ? $this->onayBekleyenTalepler() : [];
        $yaslandirma = $detayRaporlariYuklendi ? $this->yaslandirmaSatirlari() : [];
        $cariOzetleri = $detayRaporlariYuklendi ? $this->cariOzetSatirlari() : [];
        $kaynakOzetleri = $detayRaporlariYuklendi ? $this->kaynakOzetSatirlari() : [];
        $planOzetleri = $detayRaporlariYuklendi ? $this->planOzetSatirlari() : [];
        $paraBirimi = 'TRY';
        $ajandaDurumu = 'plansiz';
        $oncelik = 'normal';
    @endphp

    <div class="muhasebe-cork-screen muhasebe-vade-takibi space-y-5">
        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Gunluk Vade Hatirlatmalari</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        @if(! $hatirlatmaOzetiYuklendi)
                            Ozet ve WhatsApp satirlari istenince yuklenir.
                        @elseif(filled($hatirlatma['cache_olusturulma'] ?? null))
                            Son zamanlanmis kontrol: {{ $hatirlatma['cache_olusturulma'] }}
                        @else
                            Canli hesaplama
                        @endif
                    </p>
                </div>
                @if($hatirlatmaOzetiYuklendi)
                    <div class="text-xs text-gray-500">Yaklasan aralik: {{ $hatirlatma['yaklasan_gun'] ?? 7 }} gun</div>
                @else
                    <button type="button" wire:click="hatirlatmaOzetiniYukle" wire:loading.attr="disabled" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="hatirlatmaOzetiniYukle">Hatirlatmalari yukle</span>
                        <span wire:loading wire:target="hatirlatmaOzetiniYukle">Yukleniyor...</span>
                    </button>
                @endif
            </div>
            @if($hatirlatmaOzetiYuklendi)
            <div class="grid gap-3 border-b border-gray-200 p-4 md:grid-cols-3">
                <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                    <div class="text-xs font-medium uppercase tracking-wide text-red-700">Geciken</div>
                    <div class="mt-1 text-lg font-semibold text-red-900">{{ $this->hatirlatmaToplamMetni($hatirlatma['geciken'] ?? []) }}</div>
                    <div class="mt-1 text-xs text-red-700">{{ $hatirlatma['geciken']['adet'] ?? 0 }} vade</div>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <div class="text-xs font-medium uppercase tracking-wide text-amber-700">Bugun</div>
                    <div class="mt-1 text-lg font-semibold text-amber-900">{{ $this->hatirlatmaToplamMetni($hatirlatma['bugun'] ?? []) }}</div>
                    <div class="mt-1 text-xs text-amber-700">{{ $hatirlatma['bugun']['adet'] ?? 0 }} vade</div>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <div class="text-xs font-medium uppercase tracking-wide text-blue-700">Yaklasan</div>
                    <div class="mt-1 text-lg font-semibold text-blue-900">{{ $this->hatirlatmaToplamMetni($hatirlatma['yaklasan'] ?? []) }}</div>
                    <div class="mt-1 text-xs text-blue-700">{{ $hatirlatma['yaklasan']['adet'] ?? 0 }} vade</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2 text-right">Kalan</th>
                            <th class="px-4 py-2 text-right">Geciken</th>
                            <th class="px-4 py-2 text-right">Bugun</th>
                            <th class="px-4 py-2">Ilk vade</th>
                            <th class="px-4 py-2 text-right">Mesaj</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse(($hatirlatma['satirlar'] ?? []) as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ ($satir['cari_kod'] ?? '') !== '' ? $satir['cari_kod'] : '-' }} / {{ $satir['vade_adedi'] ?? 0 }} vade</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['kalan_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-red-700">{{ $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-amber-700">{{ $this->raporPara($satir['bugun_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporTarih($satir['ilk_vade_tarihi'] ?? null) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    @if($url = $this->hatirlatmaWhatsappUrl($satir))
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100">
                                            WhatsApp
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">Hedef yok</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Hatirlatilacak acik vade bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        @if(! $detayRaporlariYuklendi)
            <section class="rounded-lg border border-gray-200 bg-white px-4 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950">Detay raporlar</h2>
                        <p class="mt-1 text-xs text-gray-500">Risk skoru, takip ajandasi, tahsilat onceligi, yaslandirma ve plan ozetleri.</p>
                    </div>
                    <button type="button" wire:click="detayRaporlariniYukle" wire:loading.attr="disabled" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="detayRaporlariniYukle">Detay raporlari yukle</span>
                        <span wire:loading wire:target="detayRaporlariniYukle">Yukleniyor...</span>
                    </button>
                </div>
            </section>
        @else
        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Alacak Risk Skoru</h2>
                    <p class="mt-1 text-xs text-gray-500">Gecikme, acik tutar, vade adedi ve odeme sozu ihlallerine gore cari onceligi.</p>
                </div>
                <div class="text-xs text-gray-500">{{ count($riskSkorlari) }} cari</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Risk</th>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2 text-right">Acik</th>
                            <th class="px-4 py-2 text-right">Geciken</th>
                            <th class="px-4 py-2 text-right">Gecikme</th>
                            <th class="px-4 py-2">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($riskSkorlari as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                                $riskSeviyesi = (string) ($satir['risk_seviyesi'] ?? 'normal');
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->riskSeviyesiSinifi($riskSeviyesi) }}">
                                        {{ $this->riskSeviyesiEtiketi($riskSeviyesi) }} / {{ (int) ($satir['risk_skoru'] ?? 0) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ ($satir['cari_kod'] ?? '') !== '' ? $satir['cari_kod'] : '-' }}
                                        / {{ $satir['acik_vade_adedi'] ?? 0 }} vade
                                        @if((int) ($satir['odeme_sozu_ihlali_adedi'] ?? 0) > 0)
                                            / {{ (int) $satir['odeme_sozu_ihlali_adedi'] }} soz ihlali
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-red-700">{{ $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ (int) ($satir['gecikme_gunu'] ?? 0) }} gun</td>
                                <td class="px-4 py-2 text-gray-700">{{ $satir['onerilen_aksiyon'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Risk skoru hesaplanacak acik alacak bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Hatirlatma Gonderim Loglari</h2>
                    <p class="mt-1 text-xs text-gray-500">Son olusturulan SMS, WhatsApp ve e-posta hatirlatmalari.</p>
                </div>
                <div class="text-xs text-gray-500">{{ count($hatirlatmaLoglari) }} kayit</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Durum</th>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2">Kanal</th>
                            <th class="px-4 py-2">Hedef</th>
                            <th class="px-4 py-2">Tarih</th>
                            <th class="px-4 py-2">Hata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($hatirlatmaLoglari as $log)
                            @php
                                $durum = (string) ($log['durum'] ?? 'kuyrukta');
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->hatirlatmaLogDurumSinifi($durum) }}">
                                        {{ $this->hatirlatmaLogDurumEtiketi($durum) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $log['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ ($log['cari_kod'] ?? '') !== '' ? $log['cari_kod'] : '-' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ strtoupper((string) ($log['kanal'] ?? '')) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ ($log['hedef'] ?? '') !== '' ? $log['hedef'] : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $log['gonderildi_at'] ?: ($log['created_at'] ?? '-') }}</td>
                                <td class="max-w-sm px-4 py-2 text-gray-500">
                                    <div class="line-clamp-2">{{ ($log['hata'] ?? '') !== '' ? $log['hata'] : '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Henuz hatirlatma gonderim kaydi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if(count($onayBekleyenTalepler) > 0)
            <section class="rounded-lg border border-amber-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950">Finans Onayi Bekleyen Islemler</h2>
                        <p class="mt-1 text-xs text-gray-500">Limit ustu vade iptal ve revizyon talepleri.</p>
                    </div>
                    <div class="text-xs text-amber-700">{{ count($onayBekleyenTalepler) }} talep</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-amber-50 text-left text-xs font-medium uppercase tracking-wide text-amber-700">
                            <tr>
                                <th class="px-4 py-2">Talep</th>
                                <th class="px-4 py-2">Cari</th>
                                <th class="px-4 py-2 text-right">Risk</th>
                                <th class="px-4 py-2">Gerekce</th>
                                <th class="px-4 py-2">Talep eden</th>
                                <th class="px-4 py-2 text-right">Islem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($onayBekleyenTalepler as $talep)
                                @php
                                    $paraBirimi = strtoupper((string) ($talep['para_birimi'] ?? 'TRY'));
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <div class="font-medium text-gray-950">{{ $talep['talep_turu_etiketi'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">Plan #{{ $talep['plan_id'] ?? '-' }} / {{ $talep['created_at'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-950">{{ $talep['cari_ad'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ ($talep['cari_kod'] ?? '') !== '' ? $talep['cari_kod'] : '-' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($talep['risk_tutari'] ?? 0, $paraBirimi) }}</td>
                                    <td class="max-w-md px-4 py-2 text-gray-700">
                                        <div class="line-clamp-2">{{ $talep['gerekce'] ?? '-' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $talep['talep_eden'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <button type="button" wire:click="onayTalebiniOnayla({{ (int) ($talep['id'] ?? 0) }})" class="inline-flex items-center rounded-md bg-green-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-green-500">
                                            Onayla
                                        </button>
                                        <button type="button" wire:click="onayTalebiniReddet({{ (int) ($talep['id'] ?? 0) }})" class="ml-2 inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            Reddet
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Takip Ajandasi</h2>
                    <p class="mt-1 text-xs text-gray-500">Geciken, bugun ve 7 gun icinde planlanan tahsilat takipleri.</p>
                </div>
                <div class="text-xs text-gray-500">{{ count($takipAjandasi) }} takip</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Durum</th>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2">Takip</th>
                            <th class="px-4 py-2">Kaynak</th>
                            <th class="px-4 py-2 text-right">Beklenen</th>
                            <th class="px-4 py-2">Sonraki takip</th>
                            <th class="px-4 py-2 text-right">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($takipAjandasi as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                                $ajandaDurumu = (string) ($satir['ajanda_durumu'] ?? 'plansiz');
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->takipAjandaSinifi($ajandaDurumu) }}">
                                        {{ $this->takipAjandaEtiketi($ajandaDurumu) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ ($satir['cari_kod'] ?? '') !== '' ? $satir['cari_kod'] : '-' }}</div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $this->takipTipiEtiketi($satir['takip_tipi'] ?? '') }}</div>
                                    <div class="text-xs text-gray-500">{{ $this->takipDurumEtiketi($satir['takip_durumu'] ?? '') }}</div>
                                    @if((string) ($satir['takip_durumu'] ?? '') === 'odeme_sozu')
                                        <div class="mt-1 text-xs text-amber-700">
                                            Soz: {{ $this->raporTarihSaat($satir['odeme_sozu_tarihi'] ?? null) }}
                                            / {{ $this->odemeSozuDurumEtiketi($satir['odeme_sozu_durumu'] ?? null) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    <div>{{ $this->raporKaynakMetni($satir['kaynak_turu'] ?? '', $satir['kaynak_id'] ?? null) }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $this->raporPlanTuru($satir['plan_turu'] ?? '') }}
                                        @if(filled($satir['sira_no'] ?? null))
                                            / #{{ (int) $satir['sira_no'] }}
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['beklenen_tutar'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">
                                    {{ $this->raporTarihSaat($satir['sonraki_takip_tarihi'] ?? null) }}
                                    @if((int) ($satir['takip_gecikme_gunu'] ?? 0) > 0)
                                        <span class="ml-1 text-xs text-red-600">+{{ (int) $satir['takip_gecikme_gunu'] }} gun</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button type="button" wire:click="takipNotunuKapat({{ (int) ($satir['takip_notu_id'] ?? 0) }})" class="mr-3 text-sm font-semibold text-gray-600 hover:text-gray-900">
                                        Tamamla
                                    </button>
                                    <button type="button" wire:click="takipNotunuYarinaErtele({{ (int) ($satir['takip_notu_id'] ?? 0) }})" class="mr-3 text-sm font-semibold text-amber-700 hover:text-amber-600">
                                        Yarına
                                    </button>
                                    <a href="{{ $this->cariEkstreUrl((int) ($satir['cari_id'] ?? 0), $paraBirimi) }}" class="mr-3 text-sm font-semibold text-gray-600 hover:text-gray-900">
                                        Ekstre
                                    </a>
                                    <a href="{{ $this->cariTahsilatUrl((int) ($satir['cari_id'] ?? 0), $paraBirimi, $satir['beklenen_tutar'] ?? 0) }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500">
                                        Tahsilat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">Yaklasan takip bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Tahsilat Oncelik Listesi</h2>
                    <p class="mt-1 text-xs text-gray-500">Aktif filtrelere gore cari bazli acik vade siralamasi.</p>
                </div>
                <div class="text-xs text-gray-500">{{ count($tahsilatOncelikleri) }} cari</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Oncelik</th>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2 text-right">Acik</th>
                            <th class="px-4 py-2 text-right">Geciken</th>
                            <th class="px-4 py-2 text-right">Bugun</th>
                            <th class="px-4 py-2">Ilk vade</th>
                            <th class="px-4 py-2 text-right">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($tahsilatOncelikleri as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                                $oncelik = (string) ($satir['oncelik'] ?? 'normal');
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->tahsilatOncelikSinifi($oncelik) }}">
                                        {{ $this->tahsilatOncelikEtiketi($oncelik) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ ($satir['cari_kod'] ?? '') !== '' ? $satir['cari_kod'] : '-' }}
                                        / {{ $satir['plan_adedi'] ?? 0 }} plan
                                        / {{ $satir['acik_vade_adedi'] ?? 0 }} vade
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-red-700">{{ $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-amber-700">{{ $this->raporPara($satir['bugun_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">
                                    {{ $this->raporTarih($satir['ilk_vade_tarihi'] ?? null) }}
                                    @if((int) ($satir['gecikme_gunu'] ?? 0) > 0)
                                        <span class="ml-1 text-xs text-red-600">+{{ (int) $satir['gecikme_gunu'] }} gun</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <a href="{{ $this->cariTahsilatUrl((int) ($satir['cari_id'] ?? 0), $paraBirimi, $satir['acik_toplam'] ?? 0) }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500">
                                        Tahsilat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">Tahsilat onceligi bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Tahsilat Performansi</h2>
                    <p class="mt-1 text-xs text-gray-500">Son 30 gunde vade planlarina dagitilan tahsilatlar.</p>
                </div>
                <div class="text-xs text-gray-500">{{ count($tahsilatPerformansi) }} para birimi</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Para</th>
                            <th class="px-4 py-2 text-right">Tahsil edilen</th>
                            <th class="px-4 py-2 text-right">Finans hareketi</th>
                            <th class="px-4 py-2 text-right">Plan</th>
                            <th class="px-4 py-2 text-right">Cari</th>
                            <th class="px-4 py-2">Son tahsilat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($tahsilatPerformansi as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-950">{{ $paraBirimi }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['tahsil_edilen_tutar'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $satir['finans_hareket_adedi'] ?? 0 }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $satir['plan_adedi'] ?? 0 }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $satir['cari_adedi'] ?? 0 }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporTarihSaat($satir['son_tahsilat_tarihi'] ?? null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Son 30 gunde dagitilmis tahsilat bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Alacak Yaslandirma</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Para</th>
                            <th class="px-4 py-2 text-right">Vadesi gelmemis</th>
                            <th class="px-4 py-2 text-right">Bugun</th>
                            <th class="px-4 py-2 text-right">1-30 gun</th>
                            <th class="px-4 py-2 text-right">31-60 gun</th>
                            <th class="px-4 py-2 text-right">61-90 gun</th>
                            <th class="px-4 py-2 text-right">90+ gun</th>
                            <th class="px-4 py-2 text-right">Toplam</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($yaslandirma as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-950">{{ $paraBirimi }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['vadesi_gelmemis'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['bugun'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_1_30'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_31_60'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_61_90'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_90_plus'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['toplam'] ?? 0, $paraBirimi) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">Acik alacak bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-950">Cari Bazli Ozet</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Cari</th>
                                <th class="px-4 py-2 text-right">Acik</th>
                                <th class="px-4 py-2 text-right">Geciken</th>
                                <th class="px-4 py-2">Ilk vade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($cariOzetleri as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                                <tr>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $satir['cari_kod'] ?? '-' }} / {{ $satir['acik_vade_adedi'] ?? 0 }} vade</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporTarih($satir['ilk_vade_tarihi'] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Cari ozeti bulunmuyor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-950">Kaynak Dagilimi</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Kaynak</th>
                                <th class="px-4 py-2">Plan</th>
                                <th class="px-4 py-2 text-right">Adet</th>
                                <th class="px-4 py-2 text-right">Acik</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($kaynakOzetleri as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-950">{{ $this->raporKaynakMetni($satir['kaynak_turu'] ?? '') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporPlanTuru($satir['plan_turu'] ?? '') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $satir['plan_adedi'] ?? 0 }} plan / {{ $satir['acik_vade_adedi'] ?? 0 }} vade</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['acik_toplam'] ?? 0, $paraBirimi) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Kaynak ozeti bulunmuyor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Plan Ozeti</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Plan</th>
                            <th class="px-4 py-2">Cari</th>
                            <th class="px-4 py-2">Kaynak</th>
                            <th class="px-4 py-2 text-right">Kalan</th>
                            <th class="px-4 py-2 text-right">Geciken</th>
                            <th class="px-4 py-2">Ilk acik vade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($planOzetleri as $satir)
                            @php
                                $paraBirimi = strtoupper((string) ($satir['para_birimi'] ?? 'TRY'));
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <div class="font-medium text-gray-950">#{{ $satir['plan_id'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $this->raporPlanTuru($satir['plan_turu'] ?? '') }} / {{ $satir['acik_vade_adedi'] ?? 0 }} vade</div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-950">{{ $satir['cari_ad'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $satir['cari_kod'] ?? '-' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporKaynakMetni($satir['kaynak_turu'] ?? '', $satir['kaynak_id'] ?? null) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-950">{{ $this->raporPara($satir['kalan_tutar'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-gray-700">{{ $this->raporPara($satir['geciken_toplam'] ?? 0, $paraBirimi) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $this->raporTarih($satir['ilk_acik_vade_tarihi'] ?? null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Plan ozeti bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
