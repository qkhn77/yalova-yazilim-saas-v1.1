<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Üyelik Kaydı</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; }
        .kart { max-width: 480px; margin: 32px auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .alan { margin-bottom: 14px; }
        .telefon-satir { display: flex; gap: 8px; align-items: center; }
        .telefon-kod { width: 120px; height: 42px; border: 1px solid #d6dbe5; border-radius: 8px; padding: 0 10px; box-sizing: border-box; background: #fff; }
        .telefon-girdi { flex: 1; }
        label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 600; }
        input { width: 100%; height: 42px; border: 1px solid #d6dbe5; border-radius: 8px; padding: 0 12px; box-sizing: border-box; }
        button { width: 100%; height: 44px; border: 0; border-radius: 8px; background: #155efd; color: #fff; font-weight: 700; cursor: pointer; }
        .hata { color: #b00020; font-size: 13px; margin-top: 4px; }
        .bilgi { background: #eef5ff; color: #11449b; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 14px; }
        .satir { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
        a { color: #155efd; text-decoration: none; }
        .yardim { font-size: 12px; color: #5b6474; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="kart">
        <h2>Üyelik Kaydı</h2>
        <p>E-ticaret hesabınızı oluşturun.</p>

        @if (session('status'))
            <div class="bilgi">{{ session('status') }}</div>
        @endif

        <div class="satir">
            <a href="{{ \App\Support\UygulamaUrl::rota('tenant.login', [], request()) }}">Giriş sayfası</a>
            <a href="{{ \App\Support\UygulamaUrl::rota('tenant.firma-kodu-bul.form', [], request()) }}">Firma kodumu bul</a>
        </div>

        <form method="POST" action="{{ \App\Support\UygulamaUrl::rota('tenant.register.attempt', [], request()) }}">
            @csrf

            <div class="alan">
                <label for="ad_soyad">Ad Soyad</label>
                <input id="ad_soyad" name="ad_soyad" value="{{ old('ad_soyad') }}" required>
                @error('ad_soyad') <div class="hata">{{ $message }}</div> @enderror
            </div>

            <div class="alan">
                <label for="email">E-posta</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <div class="hata">{{ $message }}</div> @enderror
            </div>

            <div class="alan">
                <label for="telefon">Telefon</label>
                <div class="telefon-satir">
                    <select id="telefon_ulke_kodu" name="telefon_ulke_kodu" class="telefon-kod" required>
                        <option value="+90" {{ old('telefon_ulke_kodu', '+90') === '+90' ? 'selected' : '' }}>+90</option>
                        <option value="+1" {{ old('telefon_ulke_kodu') === '+1' ? 'selected' : '' }}>+1</option>
                        <option value="+49" {{ old('telefon_ulke_kodu') === '+49' ? 'selected' : '' }}>+49</option>
                        <option value="+44" {{ old('telefon_ulke_kodu') === '+44' ? 'selected' : '' }}>+44</option>
                        <option value="+31" {{ old('telefon_ulke_kodu') === '+31' ? 'selected' : '' }}>+31</option>
                    </select>
                    <input
                        id="telefon"
                        type="tel"
                        name="telefon"
                        class="telefon-girdi"
                        inputmode="tel"
                        maxlength="18"
                        placeholder="(555) 000 11 22"
                        value="{{ old('telefon') }}"
                        required
                    >
                </div>
                @error('telefon') <div class="hata">{{ $message }}</div> @enderror
                @error('telefon_ulke_kodu') <div class="hata">{{ $message }}</div> @enderror
            </div>

            <div class="alan">
                <label for="sifre">Şifre</label>
                <input id="sifre" type="password" name="sifre" required>
                @error('sifre') <div class="hata">{{ $message }}</div> @enderror
            </div>

            <div class="alan">
                <label for="sifre_confirmation">Şifre Tekrar</label>
                <input id="sifre_confirmation" type="password" name="sifre_confirmation" required>
            </div>

            <button type="submit">Kayıt Ol</button>
        </form>
    </div>
    <script>
        (function () {
            const telefon = document.getElementById('telefon');
            const ulkeKodu = document.getElementById('telefon_ulke_kodu');

            if (!telefon || !ulkeKodu) {
                return;
            }

            const formatla = () => {
                let rakamlar = (telefon.value || '').replace(/\D+/g, '');

                if (ulkeKodu.value === '+90') {
                    if (rakamlar.startsWith('90') && rakamlar.length >= 12) {
                        rakamlar = rakamlar.slice(2);
                    }
                    if (rakamlar.startsWith('0')) {
                        rakamlar = rakamlar.slice(1);
                    }
                    rakamlar = rakamlar.slice(0, 10);

                    const a = rakamlar.slice(0, 3);
                    const b = rakamlar.slice(3, 6);
                    const c = rakamlar.slice(6, 8);
                    const d = rakamlar.slice(8, 10);

                    if (rakamlar.length <= 3) {
                        telefon.value = a ? `(${a}` : '';
                    } else if (rakamlar.length <= 6) {
                        telefon.value = `(${a}) ${b}`;
                    } else if (rakamlar.length <= 8) {
                        telefon.value = `(${a}) ${b} ${c}`;
                    } else {
                        telefon.value = `(${a}) ${b} ${c} ${d}`;
                    }

                    return;
                }

                rakamlar = rakamlar.slice(0, 15);
                telefon.value = rakamlar;
            };

            telefon.addEventListener('input', formatla);
            ulkeKodu.addEventListener('change', formatla);
            formatla();
        })();
    </script>
</body>
</html>

