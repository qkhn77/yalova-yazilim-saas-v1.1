<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="grid gap-3 sm:grid-cols-4">
            <div>
                <div class="text-xs text-gray-500">Senet</div>
                <div class="font-medium">{{ $senet->senet_no }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Tutar</div>
                <div class="font-medium">{{ number_format((float) $senet->tutar, 2, ',', '.') }} {{ strtoupper((string) ($senet->para_birimi ?: 'TRY')) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Durum</div>
                <div class="font-medium">
                    {{ match ($senet->durum?->value ?? $senet->durum) {
                        'portfoyde' => 'Portföyde',
                        'verildi' => 'Verildi',
                        'odendi' => 'Ödendi',
                        'iade_edildi' => 'İade edildi',
                        'imha_edildi' => 'İmha edildi',
                        'iptal' => 'İptal',
                        default => '—',
                    } }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Vade</div>
                <div class="font-medium">{{ optional($senet->vade_tarihi)->format('d.m.Y') ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[720px] divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Tarih</th>
                    <th class="px-4 py-3 text-left font-medium">İşlem</th>
                    <th class="px-4 py-3 text-left font-medium">Cari</th>
                    <th class="px-4 py-3 text-right font-medium">Tutar</th>
                    <th class="px-4 py-3 text-left font-medium">Finans hareketi</th>
                    <th class="px-4 py-3 text-left font-medium">Kanal / hesap</th>
                    <th class="px-4 py-3 text-left font-medium">İşlemi yapan</th>
                    <th class="px-4 py-3 text-left font-medium">Açıklama</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                @forelse ($senet->hareketleri->sortBy('islem_tarihi') as $hareket)
                    @php
                        $islem = $hareket->islem_turu?->value ?? $hareket->islem_turu;
                        $finans = $hareket->finansHareketi;
                        $kasa = $finans?->kasaHareketleri?->first();
                        $banka = $finans?->bankaHareketleri?->first();
                        $pos = $finans?->posHareketleri?->first();
                        $kanalHesap = $kasa?->kasaHesabi?->ad
                            ? 'Kasa — '.$kasa->kasaHesabi->ad
                            : ($banka?->bankaHesabi?->ad
                                ? 'Banka — '.$banka->bankaHesabi->ad
                                : ($pos?->posHesabi?->ad ? 'POS — '.$pos->posHesabi->ad : '—'));
                        $islemEtiketi = match ($islem) {
                            'giris' => 'Senet girişi',
                            'cikis' => 'Senet çıkışı',
                            'tahsilat' => 'Senet tahsilatı',
                            'odeme' => 'Senet ödemesi',
                            'kapatma' => 'İade / imha',
                            default => 'Senet hareketi',
                        };
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3">{{ optional($hareket->islem_tarihi)->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $islemEtiketi }}</td>
                        <td class="px-4 py-3">{{ $hareket->cari?->ad ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ number_format((float) $hareket->tutar, 2, ',', '.') }} {{ strtoupper((string) ($hareket->para_birimi ?: 'TRY')) }}</td>
                        <td class="px-4 py-3">{{ $hareket->finansHareketi?->id ? '#'.$hareket->finansHareketi->id : '—' }}</td>
                        <td class="px-4 py-3">{{ $kanalHesap }}</td>
                        <td class="px-4 py-3">{{ $hareket->islemYapanKullanici?->name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $hareket->aciklama ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Senet hareketi bulunmuyor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
