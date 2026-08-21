@php
    $konular = $aktifSekme === 'gelen' ? $this->konular() : collect();
    $seciliKonu = $aktifSekme === 'gelen' ? $this->seciliKonu() : null;
    $bildirimler = $aktifSekme === 'bildirimler' ? $this->bildirimler() : collect();
    $kullaniciSecenekleri = $aktifSekme === 'yeni' ? $this->kullaniciSecenekleri() : [];
    $aliciSecenekToplamSayisi = $aktifSekme === 'yeni' ? $this->aliciSecenekToplamSayisi() : 0;
    $aliciListeLimiti = $aktifSekme === 'yeni' ? $this->aliciListeLimiti() : 0;
    $aliciAramaYapiliyor = trim($this->aliciArama) !== '';
    $konuLimit = max(1, (int) ($this->konuLimit ?? 24));
    $bildirimLimit = max(1, (int) ($this->bildirimLimit ?? 30));
    $konuDevamiVar = $konular->count() > $konuLimit;
    $bildirimDevamiVar = $bildirimler->count() > $bildirimLimit;
    $konular = $konular->take($konuLimit);
    $bildirimler = $bildirimler->take($bildirimLimit);
    $sayaclar = $this->sayaclar();
    $okunmamisMesajSayisi = (int) ($sayaclar['okunmamis_mesaj'] ?? 0);
    $okunmamisBildirimSayisi = (int) ($sayaclar['okunmamis_bildirim'] ?? 0);
    $oncelikEtiketleri = [
        'dusuk' => 'Düşük',
        'normal' => 'Normal',
        'yuksek' => 'Yüksek',
        'acil' => 'Acil',
    ];
    $listeModuEtiketleri = [
        'aktif' => 'Aktif',
        'favori' => 'Favoriler',
        'arsiv' => 'Arşiv',
    ];
    $oncelikRenkleri = [
        'dusuk' => 'yk-message-priority--low',
        'normal' => 'yk-message-priority--normal',
        'yuksek' => 'yk-message-priority--high',
        'acil' => 'yk-message-priority--urgent',
    ];
@endphp

<x-filament-panels::page>
    <div class="yk-message-center">
        <div class="yk-message-hero">
            <div>
                <div class="yk-message-eyebrow">Firma içi sohbetler</div>
                <h2>Ekip Mesajları</h2>
                <p>Konu başlığı açın, alıcıları seçin ve yalnızca konuşmaya katılan firma kullanıcılarıyla yazışın.</p>
            </div>
            <div class="yk-message-hero__side">
                <div class="yk-message-hero__stats">
                    <div>
                        <span>{{ $okunmamisMesajSayisi }}</span>
                        <small>Okunmamış mesaj</small>
                    </div>
                    <div>
                        <span>{{ $okunmamisBildirimSayisi }}</span>
                        <small>Yeni bildirim</small>
                    </div>
                </div>
                <x-filament::button type="button" icon="heroicon-m-plus" wire:click="sekmeDegistir('yeni')">
                    Yeni sohbet başlat
                </x-filament::button>
            </div>
        </div>

        <div class="yk-message-tabs">
            <button type="button" wire:click="sekmeDegistir('gelen')" class="yk-message-tab {{ $aktifSekme === 'gelen' ? 'is-active' : '' }}">
                <span>Konuşmalar</span>
                <strong>Konu geçmişi</strong>
                <small>{{ $okunmamisMesajSayisi }} okunmamış mesaj</small>
            </button>

            <button type="button" wire:click="sekmeDegistir('yeni')" class="yk-message-tab {{ $aktifSekme === 'yeni' ? 'is-active' : '' }}">
                <span>Yeni sohbet</span>
                <strong>Alıcı seç ve gönder</strong>
                <small>Sadece seçilen katılımcılar görür</small>
            </button>

            <button type="button" wire:click="sekmeDegistir('bildirimler')" class="yk-message-tab {{ $aktifSekme === 'bildirimler' ? 'is-active' : '' }}">
                <span>Bildirimler</span>
                <strong>Sistem uyarıları</strong>
                <small>{{ $okunmamisBildirimSayisi }} yeni bildirim</small>
            </button>
        </div>

        @if ($aktifSekme === 'yeni')
            <form wire:submit="konuOlustur" class="yk-message-card">
                <div class="yk-message-card__header">
                    <div>
                        <h3>Yeni sohbet başlat</h3>
                        <p>Her sohbet bir konu başlığı altında saklanır. Alıcı olarak seçmediğiniz kişiler bu konuşmayı göremez.</p>
                    </div>
                </div>

                <div class="yk-message-form-grid">
                    <div class="yk-message-step yk-message-field--wide">
                        <div class="yk-message-step__number">1</div>
                        <div>
                            <strong>Kime göndereceksin?</strong>
                            <p>Seçtiğiniz kişiler konuşmanın geçmişini ve yanıtlarını görür.</p>
                        </div>
                    </div>

                    <div class="yk-message-field yk-message-field--wide">
                        <span>Alıcı seç</span>
                        <div class="yk-message-recipient-search">
                            <input type="search" wire:model.live.debounce.650ms="aliciArama" placeholder="Ad, kullanıcı adı veya e-posta ile ara">
                            @if ($aliciArama !== '')
                                <button type="button" wire:click="aliciAramaTemizle">Temizle</button>
                            @endif
                        </div>
                        <div class="yk-message-recipient-grid">
                            @forelse ($kullaniciSecenekleri as $id => $etiket)
                                <label>
                                    <input type="checkbox" wire:model="aliciIds" value="{{ $id }}">
                                    <span>{{ $etiket }}</span>
                                </label>
                            @empty
                                <div class="yk-message-empty-inline">Mesaj gönderilebilecek başka kullanıcı bulunamadı.</div>
                            @endforelse
                        </div>
                        <small class="yk-message-recipient-hint">
                            @if ($aliciAramaYapiliyor)
                                Arama sonucunda ilk {{ min($aliciListeLimiti, count($kullaniciSecenekleri)) }} kayıt gösteriliyor.
                            @else
                                İlk {{ count($kullaniciSecenekleri) }} kullanıcı gösteriliyor; kişi bulmak için arama yapın.
                            @endif
                            Toplam {{ $aliciSecenekToplamSayisi }} uygun kullanıcı var. {{ count($aliciIds) }} alıcı seçildi.
                        </small>
                        @error('aliciIds') <em>{{ $message }}</em> @enderror
                    </div>

                    <div class="yk-message-step yk-message-field--wide">
                        <div class="yk-message-step__number">2</div>
                        <div>
                            <strong>Konu başlığını yaz</strong>
                            <p>Sohbet geçmişinde bu başlıkla görünecek.</p>
                        </div>
                    </div>

                    <label class="yk-message-field">
                        <span>Konu başlığı</span>
                        <input type="text" wire:model="baslik" maxlength="160" placeholder="Örn. ABC Ltd. fatura kontrolü">
                        @error('baslik') <em>{{ $message }}</em> @enderror
                    </label>

                    <label class="yk-message-field">
                        <span>Öncelik</span>
                        <select wire:model="oncelik">
                            <option value="dusuk">Düşük</option>
                            <option value="normal">Normal</option>
                            <option value="yuksek">Yüksek</option>
                            <option value="acil">Acil</option>
                        </select>
                    </label>

                    <div class="yk-message-step yk-message-field--wide">
                        <div class="yk-message-step__number">3</div>
                        <div>
                            <strong>İlk mesajı gönder</strong>
                            <p>Yanıtlar aynı konu başlığı altında sohbet geçmişine eklenir.</p>
                        </div>
                    </div>

                    <label class="yk-message-field yk-message-field--wide">
                        <span>İlk mesaj</span>
                        <textarea wire:model="mesaj" rows="7" maxlength="5000" placeholder="Mesajınızı yazın..."></textarea>
                        <small>{{ mb_strlen($mesaj, 'UTF-8') }}/5000 karakter</small>
                        @error('mesaj') <em>{{ $message }}</em> @enderror
                    </label>
                </div>

                <div class="yk-message-card__footer">
                    <x-filament::button type="button" color="gray" wire:click="sekmeDegistir('gelen')">Vazgeç</x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">Sohbeti başlat</x-filament::button>
                </div>
            </form>
        @elseif ($aktifSekme === 'bildirimler')
            <div class="yk-message-card">
                <div class="yk-message-card__header yk-message-card__header--split">
                    <div>
                        <h3>Bildirimler</h3>
                        <p>Mesaj merkezi ve sistem içi kullanıcı bildirimleri.</p>
                    </div>
                    <x-filament::button color="gray" size="sm" wire:click="tumBildirimleriOku">Tümünü okundu yap</x-filament::button>
                </div>

                <div class="yk-message-toolbar">
                    <label>
                        <span>Bildirim ara</span>
                        <input type="search" wire:model.live.debounce.650ms="bildirimArama" placeholder="Başlık veya mesaj ara">
                    </label>
                    <label class="yk-message-toggle">
                        <input type="checkbox" wire:model.live="sadeceOkunmamisBildirimler">
                        <span>Sadece okunmamış</span>
                    </label>
                    <button type="button" wire:click="bildirimFiltreleriTemizle">Temizle</button>
                </div>

                <div class="yk-message-notification-list">
                    @forelse ($bildirimler as $bildirim)
                        <div class="yk-message-notification {{ $bildirim->okundu_at ? 'is-read' : 'is-unread' }}" wire:key="bildirim-{{ $bildirim->id }}">
                            <div class="yk-message-notification__dot"></div>
                            <div class="yk-message-notification__body">
                                <div class="yk-message-notification__title">
                                    <strong>{{ $bildirim->baslik }}</strong>
                                    <span>{{ $bildirim->created_at?->diffForHumans() }}</span>
                                </div>
                                @if ($bildirim->mesaj)
                                    <p>{{ $bildirim->mesaj }}</p>
                                @endif
                                <div class="yk-message-notification__actions">
                                    @if ($bildirim->aksiyon_url)
                                        <a href="{{ $bildirim->aksiyon_url }}">Aç</a>
                                    @endif
                                    @unless ($bildirim->okundu_at)
                                        <button type="button" wire:click="bildirimOku({{ $bildirim->id }})">Okundu yap</button>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="yk-message-empty">
                            <strong>Bildirim bulunamadı</strong>
                            <span>Filtreleri temizleyerek tekrar deneyebilirsiniz.</span>
                        </div>
                    @endforelse

                    @if ($bildirimDevamiVar)
                        <div class="yk-message-load-more">
                            <button type="button" wire:click="dahaFazlaBildirim" wire:loading.attr="disabled">
                                Daha fazla bildirim göster
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="yk-message-inbox">
                <div class="yk-message-card yk-message-thread-list">
                    <div class="yk-message-card__header">
                        <div>
                            <h3>Konuşmalar</h3>
                            <p>{{ $listeModuEtiketleri[$konuListeModu] ?? 'Aktif' }} konu başlıkları, en son yanıt sırasına göre listelenir.</p>
                        </div>
                    </div>

                    <div class="yk-message-toolbar yk-message-toolbar--stack">
                        <div class="yk-message-segment">
                            <button type="button" wire:click="$set('konuListeModu', 'aktif')" class="{{ $konuListeModu === 'aktif' ? 'is-active' : '' }}">Aktif</button>
                            <button type="button" wire:click="$set('konuListeModu', 'favori')" class="{{ $konuListeModu === 'favori' ? 'is-active' : '' }}">Favoriler</button>
                            <button type="button" wire:click="$set('konuListeModu', 'arsiv')" class="{{ $konuListeModu === 'arsiv' ? 'is-active' : '' }}">Arşiv</button>
                        </div>
                        <label>
                            <span>Konuşma ara</span>
                            <input type="search" wire:model.live.debounce.650ms="konuArama" placeholder="Başlık veya son mesaj">
                        </label>
                        <div class="yk-message-toolbar__row">
                            <label>
                                <span>Öncelik</span>
                                <select wire:model.live="oncelikFiltresi">
                                    <option value="tum">Tümü</option>
                                    <option value="dusuk">Düşük</option>
                                    <option value="normal">Normal</option>
                                    <option value="yuksek">Yüksek</option>
                                    <option value="acil">Acil</option>
                                </select>
                            </label>
                            <label class="yk-message-toggle">
                                <input type="checkbox" wire:model.live="sadeceOkunmamis">
                                <span>Okunmamış</span>
                            </label>
                        </div>
                        <div class="yk-message-toolbar__actions">
                            <button type="button" wire:click="filtreleriTemizle">Filtreleri temizle</button>
                            <button type="button" wire:click="tumKonusmalariOku">Tümünü okundu yap</button>
                        </div>
                    </div>

                    <div class="yk-message-thread-items">
                        @forelse ($konular as $konu)
                            @php
                                $okunmamis = (bool) ((int) ($konu->okunmamis_mi ?? 0));
                                $favoriMi = (bool) ((int) ($konu->katilimci_favori_mi ?? 0));
                                $sessizdeMi = (bool) ((int) ($konu->katilimci_sessize_alindi_mi ?? 0));
                                $arsivdeMi = (bool) ((int) ($konu->katilimci_arsivlendi_mi ?? 0));
                                $oncelikClass = $oncelikRenkleri[$konu->oncelik] ?? $oncelikRenkleri['normal'];
                                $oncelikEtiket = $oncelikEtiketleri[$konu->oncelik] ?? 'Normal';
                            @endphp
                            <div class="yk-message-thread {{ $seciliKonuId === $konu->id ? 'is-selected' : '' }} {{ $okunmamis ? 'is-unread' : '' }}" wire:key="konu-{{ $konu->id }}">
                                <button type="button" wire:click="konuSec({{ $konu->id }})">
                                    <div class="yk-message-thread__top">
                                        <span class="yk-message-thread__title">{{ $konu->baslik }}</span>
                                        @if ($okunmamis)
                                            <i></i>
                                        @endif
                                    </div>
                                    <p>{{ \Illuminate\Support\Str::limit((string) ($konu->son_mesaj_metin ?? ''), 92) ?: 'Henüz mesaj yok' }}</p>
                                    <div class="yk-message-thread__meta">
                                        <span class="yk-message-thread__badges">
                                            <span class="yk-message-priority {{ $oncelikClass }}">{{ $oncelikEtiket }}</span>
                                            @if ($favoriMi)
                                                <span class="yk-message-mini-badge">Favori</span>
                                            @endif
                                            @if ($sessizdeMi)
                                                <span class="yk-message-mini-badge">Sessiz</span>
                                            @endif
                                        </span>
                                        <span>{{ $konu->son_mesaj_at?->diffForHumans() ?: 'Yeni' }}</span>
                                    </div>
                                </button>
                                <div class="yk-message-thread__actions">
                                    <details>
                                        <summary>İşlemler</summary>
                                        <div>
                                            <button type="button" wire:click="favoriDegistir({{ $konu->id }})" title="Favori durumunu değiştir">
                                                {{ $favoriMi ? 'Favoriden çıkar' : 'Favoriye al' }}
                                            </button>
                                            <button type="button" wire:click="sessizDegistir({{ $konu->id }})" title="Bildirim sessizliğini değiştir">
                                                {{ $sessizdeMi ? 'Sesi aç' : 'Sessize al' }}
                                            </button>
                                            @if ($arsivdeMi)
                                                <button type="button" wire:click="konuArsivdenCikar({{ $konu->id }})" title="Konuşmayı arşivden çıkar">Arşivden çıkar</button>
                                            @else
                                                <button type="button" wire:click="konuArsivle({{ $konu->id }})" title="Konuşmayı arşivle">Arşivle</button>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </div>
                        @empty
                            <div class="yk-message-empty">
                                <strong>Henüz sohbet yok</strong>
                                <span>Ekip arkadaşınıza mesaj göndermek için yeni bir konu başlatın.</span>
                                <button type="button" wire:click="sekmeDegistir('yeni')">Yeni sohbet başlat</button>
                            </div>
                        @endforelse

                        @if ($konuDevamiVar)
                            <div class="yk-message-load-more">
                                <button type="button" wire:click="dahaFazlaKonusma" wire:loading.attr="disabled">
                                    Daha fazla konuşma göster
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="yk-message-card yk-message-conversation">
                    @if ($seciliKonu)
                        @php
                            $seciliOncelikClass = $oncelikRenkleri[$seciliKonu->oncelik] ?? $oncelikRenkleri['normal'];
                            $seciliOncelikEtiket = $oncelikEtiketleri[$seciliKonu->oncelik] ?? 'Normal';
                            $mesajLimit = max(1, (int) ($this->mesajLimit ?? 50));
                            $mesajKayitlari = $seciliKonu->mesajlar;
                            $mesajDevamiVar = $mesajKayitlari->count() > $mesajLimit;
                            $mesajKayitlari = $mesajKayitlari->take($mesajLimit)->sortBy('created_at');
                        @endphp
                        <div class="yk-message-card__header yk-message-card__header--split">
                            @php
                                $katilimciAdlari = $seciliKonu->katilimcilar
                                    ->map(fn ($katilimci) => $katilimci->kullanici?->ad_soyad ?: $katilimci->kullanici?->name ?: $katilimci->kullanici?->email)
                                    ->filter()
                                    ->values();
                                $katilimciOzeti = $katilimciAdlari->implode(', ');
                                $katilimciToplami = (int) ($seciliKonu->katilimcilar_count ?? $katilimciAdlari->count());
                                $fazlaKatilimciSayisi = max(0, $katilimciToplami - $katilimciAdlari->count());
                            @endphp
                            <div>
                                <h3>{{ $seciliKonu->baslik }}</h3>
                                <p>
                                    Bu sohbeti görebilenler:
                                    {{ $katilimciOzeti }}
                                    @if ($fazlaKatilimciSayisi > 0)
                                        +{{ $fazlaKatilimciSayisi }} kişi
                                    @endif
                                </p>
                            </div>
                            <span class="yk-message-priority {{ $seciliOncelikClass }}">{{ $seciliOncelikEtiket }}</span>
                        </div>

                        <div class="yk-message-bubbles">
                            @if ($mesajDevamiVar)
                                <div class="yk-message-load-more yk-message-load-more--inside">
                                    <button type="button" wire:click="dahaEskiMesajlariYukle" wire:loading.attr="disabled">
                                        Daha eski mesajları göster
                                    </button>
                                </div>
                            @endif

                            @foreach ($mesajKayitlari as $mesajKaydi)
                                @php
                                    $benimMesajim = (int) $mesajKaydi->gonderen_id === (int) auth()->id();
                                @endphp
                                <div class="yk-message-bubble-row {{ $benimMesajim ? 'is-mine' : '' }}" wire:key="mesaj-{{ $mesajKaydi->id }}">
                                    <div class="yk-message-bubble">
                                        <div>
                                            <strong>{{ $mesajKaydi->gonderen?->ad_soyad ?: $mesajKaydi->gonderen?->name ?: $mesajKaydi->gonderen?->email }}</strong>
                                            <span>{{ $mesajKaydi->created_at?->format('d.m.Y H:i') }}</span>
                                        </div>
                                        <p>{{ $mesajKaydi->mesaj }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <form wire:submit="yanitGonder" class="yk-message-reply">
                            <label>
                                <span>Yanıt yaz</span>
                                <textarea wire:model="yanitMesaji" rows="4" maxlength="5000" placeholder="Yanıtınızı yazın..."></textarea>
                                <small>{{ mb_strlen($yanitMesaji, 'UTF-8') }}/5000 karakter</small>
                                @error('yanitMesaji') <em>{{ $message }}</em> @enderror
                            </label>
                            <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">Yanıt gönder</x-filament::button>
                        </form>
                    @else
                        <div class="yk-message-empty yk-message-empty--large">
                            <strong>Bir konuşma seçin</strong>
                            <span>Mesajları okumak veya yanıtlamak için soldan bir konu seçin ya da yeni sohbet başlatın.</span>
                            <button type="button" wire:click="sekmeDegistir('yeni')">Yeni sohbet başlat</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
