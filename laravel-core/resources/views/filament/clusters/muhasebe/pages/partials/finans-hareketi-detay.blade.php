@php
    $enumDegeri = static fn ($deger) => $deger instanceof \BackedEnum ? $deger->value : $deger;
    $para = static fn ($deger, $birim = null) => $deger === null
        ? '—'
        : number_format((float) $deger, 2, ',', '.').($birim ? ' '.strtoupper((string) $birim) : '');
    $deger = static function ($deger): string {
        if ($deger instanceof \BackedEnum) {
            $deger = $deger->value;
        }

        if ($deger instanceof \DateTimeInterface) {
            return $deger->format('d.m.Y H:i:s');
        }

        return filled($deger) || $deger === 0 || $deger === '0' ? (string) $deger : '—';
    };
    $satirlar = [
        'Hareket no' => '#'.$hareket->id,
        'Modül' => $hareket->modul_etiketi,
        'Tür' => $enumDegeri($hareket->tur),
        'Durum' => $enumDegeri($hareket->durum),
        'Tarih' => $hareket->tarih,
        'Vade tarihi' => $hareket->vade_tarihi?->format('d.m.Y'),
        'Tutar' => $para($hareket->tutar, $hareket->para_birimi),
        'Baz tutar' => $para($hareket->baz_tutar, $hareket->baz_para_birimi),
        'Brüt tutar' => $para($hareket->brut_tutar, $hareket->para_birimi),
        'POS komisyon tutarı' => $para($hareket->pos_komisyon_tutari, $hareket->para_birimi),
        'POS komisyon oranı' => $hareket->pos_komisyon_orani_yuzde === null ? '—' : '% '.number_format((float) $hareket->pos_komisyon_orani_yuzde, 4, ',', '.'),
        'Kur' => $hareket->kur,
        'Kullanılan tutar' => $para($hareket->kullanilan_tutar, $hareket->para_birimi),
        'Avans tutarı' => $para($hareket->avans_tutar, $hareket->para_birimi),
        'Cari' => $hareket->cari ? trim(($hareket->cari->kod ? $hareket->cari->kod.' — ' : '').$hareket->cari->ad) : null,
        'Firma' => $hareket->firma?->ad,
        'İşletme projesi' => $hareket->isletmeProjesi?->ad,
        'İşlem yapan kullanıcı' => $hareket->islemYapanKullanici?->name,
        'İşlem kaynağı' => $hareket->islem_kaynagi,
        'Referans türü' => $hareket->referans_turu,
        'Referans no' => $hareket->referans_id ? '#'.$hareket->referans_id : null,
        'Referans faturası' => $hareket->referansFaturasi?->fatura_no,
        'İptal edilen hareket' => $hareket->iptal_edilen_hareket_id ? '#'.$hareket->iptal_edilen_hareket_id : null,
        'Açıklama' => $hareket->aciklama,
        'Ek açıklama' => $hareket->ek_aciklama,
        'Kayıt tarihi' => $hareket->created_at,
        'Son güncelleme' => $hareket->updated_at,
    ];
    $hesapHareketleri = collect()
        ->concat($hareket->kasaHareketleri->map(fn ($item) => ['kanal' => 'Kasa', 'hesap' => $item->kasaHesabi?->ad ?: '#'.$item->kasa_hesap_id, 'hareket' => $item]))
        ->concat($hareket->bankaHareketleri->map(fn ($item) => ['kanal' => 'Banka', 'hesap' => $item->bankaHesabi?->ad ?: '#'.$item->banka_hesap_id, 'hareket' => $item]))
        ->concat($hareket->posHareketleri->map(fn ($item) => ['kanal' => 'POS', 'hesap' => $item->posHesabi?->ad ?: '#'.$item->pos_hesap_id, 'hareket' => $item]));
@endphp

<div class="space-y-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($satirlar as $etiket => $icerik)
                    <tr>
                        <th class="w-1/3 bg-gray-50 px-4 py-2.5 text-left font-medium text-gray-700 dark:bg-white/5 dark:text-gray-200">{{ $etiket }}</th>
                        <td class="px-4 py-2.5 text-gray-950 dark:text-white whitespace-pre-wrap break-words">{{ $deger($icerik) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Hesap hareketleri</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-gray-200">Kanal</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-gray-200">Hesap</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-gray-200">Tutar</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-gray-200">Durum</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-gray-200">Alt hareket no</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($hesapHareketleri as $hesapHareketi)
                        <tr>
                            <td class="px-4 py-2.5">{{ $hesapHareketi['kanal'] }}</td>
                            <td class="px-4 py-2.5">{{ $hesapHareketi['hesap'] }}</td>
                            <td class="px-4 py-2.5">{{ $para($hesapHareketi['hareket']->tutar, $hesapHareketi['hareket']->para_birimi) }}</td>
                            <td class="px-4 py-2.5">{{ $deger($hesapHareketi['hareket']->durum) }}</td>
                            <td class="px-4 py-2.5">#{{ $hesapHareketi['hareket']->id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Bağlı hesap hareketi bulunmuyor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($hareket->faturaKapatmalari->isNotEmpty())
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Fatura kapamaları</h3>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5"><tr><th class="px-4 py-2.5 text-left">Fatura</th><th class="px-4 py-2.5 text-left">Uygulanan tutar</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($hareket->faturaKapatmalari as $kapama)
                            <tr><td class="px-4 py-2.5">{{ $kapama->fatura?->fatura_no ?: '#'.$kapama->fatura_id }}</td><td class="px-4 py-2.5">{{ $para($kapama->uygulanan_tutar, $hareket->para_birimi) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
