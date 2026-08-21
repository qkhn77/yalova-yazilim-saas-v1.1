@php
    use App\Models\Muhasebe\AlacakPlanTaksiti;
    use BackedEnum;
    use DateTimeInterface;
    use UnitEnum;

    /** @var AlacakPlanTaksiti $record */
    $plan = $record->plan;
    $paraBirimi = strtoupper((string) ($plan?->para_birimi ?: 'TRY'));
    $taksitler = $plan?->taksitler ?? collect();
    $tahsilatlar = $plan?->tahsilatEslesmeleri ?? collect();
    $takipNotlari = $plan?->takipNotlari ?? collect();
    $revizyonlar = $plan?->revizyonlar ?? collect();
    $taksitTahsilatUrlMap = $taksitTahsilatUrlMap ?? [];
    $tahsilatYetkisiVarMi = (bool) ($tahsilatYetkisiVarMi ?? false);

    $para = fn (mixed $tutar): string => number_format((float) $tutar, 2, ',', '.').' '.$paraBirimi;
    $tarih = function (mixed $deger, bool $saat = false): string {
        if (! $deger) {
            return '-';
        }

        return $deger instanceof DateTimeInterface
            ? $deger->format($saat ? 'd.m.Y H:i' : 'd.m.Y')
            : (string) $deger;
    };
    $enumDegeri = function (mixed $deger): string {
        if ($deger instanceof BackedEnum) {
            return (string) $deger->value;
        }

        if ($deger instanceof UnitEnum) {
            return $deger->name;
        }

        return (string) ($deger ?? '');
    };

    $durumEtiketi = fn (mixed $durum): string => match ((string) $durum) {
        'aktif' => 'Aktif',
        'bekliyor' => 'Bekliyor',
        'kismi_odendi' => 'Kismi odendi',
        'odendi' => 'Odendi',
        'gecikti' => 'Gecikti',
        'iptal' => 'Iptal',
        default => ucfirst((string) $durum),
    };

    $durumSinifi = fn (mixed $durum): string => match ((string) $durum) {
        'odendi' => 'bg-green-50 text-green-700 ring-green-600/20',
        'kismi_odendi' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'gecikti' => 'bg-red-50 text-red-700 ring-red-600/20',
        'iptal' => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        default => 'bg-blue-50 text-blue-700 ring-blue-600/20',
    };

    $planTuru = match ((string) ($plan?->plan_turu ?? '')) {
        'taksit' => 'Taksitli',
        'veresiye' => 'Veresiye',
        default => ucfirst((string) ($plan?->plan_turu ?? '-')),
    };

    $takipTipiEtiketi = fn (mixed $tip): string => match ((string) $tip) {
        'arama' => 'Telefon',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'eposta' => 'E-posta',
        'mutabakat' => 'Mutabakat',
        default => 'Not',
    };

    $takipDurumEtiketi = fn (mixed $durum): string => match ((string) $durum) {
        'planlandi' => 'Planlandi',
        'ulasildi' => 'Ulasildi',
        'ulasilamadi' => 'Ulasilamadi',
        'odeme_sozu' => 'Odeme sozu',
        'takip_gerekli' => 'Takip gerekli',
        'tamamlandi' => 'Tamamlandi',
        default => ucfirst((string) $durum),
    };

    $odemeSozuDurumEtiketi = fn (mixed $durum): string => match ((string) $durum) {
        'bekliyor' => 'Bekliyor',
        'kismi' => 'Kismi',
        'tutuldu' => 'Tutuldu',
        'tutulmadi' => 'Tutulmadi',
        'iptal' => 'Iptal',
        default => '-',
    };

    $revizyonTuruEtiketi = fn (mixed $tur): string => match ((string) $tur) {
        'vade_ertele' => 'Vade erteleme',
        'taksit_vade_degistir' => 'Taksit vade degisimi',
        'kalan_yeniden_taksitlendir' => 'Yeniden taksitlendirme',
        'kismi_yapilandir' => 'Kismi yapilandirma',
        default => ucfirst((string) $tur),
    };

    $kaynak = match ((string) ($plan?->kaynak_turu ?? '')) {
        'barkodlu_satis' => 'Barkodlu satis',
        'teknik_servis' => 'Teknik servis',
        'fatura' => 'Fatura',
        'manuel' => 'Manuel',
        default => ucfirst((string) ($plan?->kaynak_turu ?? '-')),
    };

    if ($plan?->kaynak_id) {
        $kaynak .= ' #'.$plan->kaynak_id;
    }
@endphp

<div class="space-y-5 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        @if($tahsilatYetkisiVarMi && (float) ($record->kalan_tutar ?? 0) > 0 && filled($tahsilatUrl ?? null))
            <a href="{{ $tahsilatUrl }}" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                Secili taksiti tahsil et
            </a>
        @endif
        @if($tahsilatYetkisiVarMi && (float) ($plan?->kalan_tutar ?? 0) > 0 && filled($planTahsilatUrl ?? null))
            <a href="{{ $planTahsilatUrl }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Plan kalanini tahsil et
            </a>
        @endif
        @if(filled($ekstreUrl ?? null))
            <a href="{{ $ekstreUrl }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Cari ekstreyi ac
            </a>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">İşlem No</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $plan?->islem_no ?: '-' }}</div>
            <div class="mt-1 text-xs text-gray-500">Ortak plan kodu</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Cari</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $plan?->cari?->ad ?? $record->cari?->ad ?? '-' }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $plan?->cari?->kod ?: '-' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Kaynak</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $kaynak }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $planTuru }} / {{ $paraBirimi }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Plan durumu</div>
            <div class="mt-2">
                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $durumSinifi($plan?->durum) }}">
                    {{ $durumEtiketi($plan?->durum) }}
                </span>
            </div>
            <div class="mt-2 text-xs text-gray-500">{{ $tarih($plan?->baslangic_tarihi) }} - {{ $tarih($plan?->son_vade_tarihi) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Kalan</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $para($plan?->kalan_tutar ?? 0) }}</div>
            <div class="mt-1 text-xs text-gray-500">Odenen: {{ $para($plan?->odenen_tutar ?? 0) }}</div>
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="text-xs text-gray-500">Toplam</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $para($plan?->toplam_tutar ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="text-xs text-gray-500">Pesinat</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $para($plan?->pesinat_tutari ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="text-xs text-gray-500">Planlanan</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $para($plan?->planlanan_tutar ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="text-xs text-gray-500">Taksit</div>
            <div class="mt-1 font-semibold text-gray-950">{{ $taksitler->count() }}</div>
        </div>
    </div>

    @if(filled($plan?->aciklama))
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Aciklama</div>
            <div class="mt-1 whitespace-pre-line text-gray-800">{{ $plan->aciklama }}</div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-950">Taksitler</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Sira</th>
                        <th class="px-3 py-2">Vade</th>
                        <th class="px-3 py-2">Durum</th>
                        <th class="px-3 py-2 text-right">Tutar</th>
                        <th class="px-3 py-2 text-right">Odenen</th>
                        <th class="px-3 py-2 text-right">Kalan</th>
                        <th class="px-3 py-2">Son tahsilat</th>
                        <th class="px-3 py-2 text-right">Islem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($taksitler as $taksit)
                        <tr @class(['bg-blue-50/60' => $taksit->is($record)])>
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950">#{{ $taksit->sira_no }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($taksit->vade_tarihi) }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $durumSinifi($taksit->durum) }}">
                                    {{ $durumEtiketi($taksit->durum) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700">{{ $para($taksit->tutar) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700">{{ $para($taksit->odenen_tutar) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-950">{{ $para($taksit->kalan_tutar) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($taksit->son_tahsilat_tarihi, true) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                @if($tahsilatYetkisiVarMi && filled($taksitTahsilatUrlMap[(int) $taksit->getKey()] ?? null))
                                    <a href="{{ $taksitTahsilatUrlMap[(int) $taksit->getKey()] }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500">
                                        Tahsilat
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500">Taksit kaydi bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-950">Tahsilat gecmisi</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Tarih</th>
                        <th class="px-3 py-2">Taksit</th>
                        <th class="px-3 py-2">Finans hareketi</th>
                        <th class="px-3 py-2">Tur</th>
                        <th class="px-3 py-2">Durum</th>
                        <th class="px-3 py-2 text-right">Tutar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($tahsilatlar as $tahsilat)
                        @php
                            $finans = $tahsilat->finansHareketi;
                            $finansTuru = str_replace('_', ' ', $enumDegeri($finans?->tur));
                            $finansDurumu = str_replace('_', ' ', $enumDegeri($finans?->durum));
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($tahsilat->tarih, true) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">#{{ $tahsilat->taksit?->sira_no ?? '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950">#{{ $finans?->id ?? '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $finansTuru !== '' ? ucfirst($finansTuru) : '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $finansDurumu !== '' ? ucfirst($finansDurumu) : '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-950">{{ $para($tahsilat->tutar) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">Tahsilat kaydi bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-950">Takip notlari</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Tarih</th>
                        <th class="px-3 py-2">Tip</th>
                        <th class="px-3 py-2">Durum</th>
                        <th class="px-3 py-2">Taksit</th>
                        <th class="px-3 py-2">Sonraki takip</th>
                        <th class="px-3 py-2">Odeme sozu</th>
                        <th class="px-3 py-2">Not</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($takipNotlari as $takipNotu)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($takipNotu->takip_tarihi, true) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $takipTipiEtiketi($takipNotu->takip_tipi) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $takipDurumEtiketi($takipNotu->durum) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">#{{ $takipNotu->taksit?->sira_no ?? '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($takipNotu->sonraki_takip_tarihi, true) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">
                                @if((string) $takipNotu->durum === 'odeme_sozu')
                                    {{ $tarih($takipNotu->odeme_sozu_tarihi, true) }}
                                    / {{ $para($takipNotu->odeme_sozu_tutari ?? $takipNotu->beklenen_tutar ?? 0) }}
                                    / {{ $odemeSozuDurumEtiketi($takipNotu->odeme_sozu_durumu) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="min-w-72 px-3 py-2 text-gray-700">{{ $takipNotu->not ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-gray-500">Takip notu bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-950">Plan revizyonlari</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Tarih</th>
                        <th class="px-3 py-2">Tur</th>
                        <th class="px-3 py-2">Kullanici</th>
                        <th class="px-3 py-2">Not</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($revizyonlar as $revizyon)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $tarih($revizyon->created_at, true) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $revizyonTuruEtiketi($revizyon->revizyon_turu) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $revizyon->olusturan?->name ?? '-' }}</td>
                            <td class="min-w-72 px-3 py-2 text-gray-700">{{ $revizyon->aciklama ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500">Plan revizyonu bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
