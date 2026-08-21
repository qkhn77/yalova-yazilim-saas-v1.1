<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Support\RecaptchaAyarlari;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait HandlesLoginRecaptcha
{
    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function recaptchaKurallariniEkle(array $rules, array $messages): array
    {
        if ($this->recaptchaEtkinMi()) {
            $rules['g-recaptcha-response'] = ['required'];
            $messages['g-recaptcha-response.required'] = 'Lutfen Google dogrulamasini tamamlayin.';
        }

        return [$rules, $messages];
    }

    protected function recaptchaDogrulamasiniKontrolEt(Request $request): ?RedirectResponse
    {
        if (! $this->recaptchaEtkinMi()) {
            return null;
        }

        $secret = $this->recaptchaSecretKey();
        if ($secret === '') {
            return null;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        if (! empty(($response->json() ?? [])['success'])) {
            return null;
        }

        return back()
            ->withErrors(['g-recaptcha-response' => 'Google dogrulamasi basarisiz. Lutfen tekrar deneyin.'])
            ->withInput($request->except('sifre', 'g-recaptcha-response'));
    }

    protected function recaptchaEtkinMi(): bool
    {
        return RecaptchaAyarlari::etkinMi();
    }

    protected function recaptchaSecretKey(): string
    {
        return RecaptchaAyarlari::secretKey();
    }
}
