<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Support\RecaptchaAyarlari;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsletterSubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'newsletter_email' => 'required|email|max:255',
        ];

        $messages = [
            'newsletter_email.required' => 'Lutfen e-posta adresinizi girin.',
            'newsletter_email.email' => 'Gecerli bir e-posta adresi girin.',
        ];

        $recaptchaSecret = RecaptchaAyarlari::secretKey();

        if (RecaptchaAyarlari::etkinMi()) {
            $rules['g-recaptcha-response'] = 'required';
            $messages['g-recaptcha-response.required'] = 'Lutfen robot dogrulamasini tamamlayin.';
        }

        $validated = $request->validateWithBag('newsletter', $rules, $messages);

        if (RecaptchaAyarlari::etkinMi()) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            if (empty(($response->json() ?? [])['success'])) {
                return back()
                    ->with('newsletter_error', 'Robot dogrulamasi basarisiz. Lutfen tekrar deneyin.')
                    ->withInput();
            }
        }

        $subscriber = NewsletterSubscriber::query()->firstOrNew([
            'email' => mb_strtolower(trim($validated['newsletter_email'])),
        ]);

        $wasExistingActive = $subscriber->exists && $subscriber->is_active;

        $subscriber->fill([
            'ip_address' => $request->ip(),
            'source' => 'footer',
            'is_active' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
        $subscriber->save();

        if ($wasExistingActive) {
            return back()->with('newsletter_success', 'Bu e-posta adresi zaten aboneler listesinde kayitli.');
        }

        return back()->with('newsletter_success', 'Aboneliginiz alindi. Tesekkur ederiz.');
    }
}
