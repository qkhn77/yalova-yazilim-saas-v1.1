<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\RecaptchaAyarlari;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Iletisim formu gonderimi.
     * Mesaj, Mail Ayarlari ekranindaki alici e-posta adresine iletilir.
     * reCAPTCHA anahtarlari girildiyse Google dogrulamasi yapilir.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ];

        $messages = [
            'name.required' => 'Ad Soyad gerekli.',
            'email.required' => 'E-posta adresi gerekli.',
            'email.email' => 'Gecerli bir e-posta adresi girin.',
            'message.required' => 'Mesaj gerekli.',
        ];

        $recaptchaSecret = RecaptchaAyarlari::secretKey();

        if (RecaptchaAyarlari::etkinMi()) {
            $rules['g-recaptcha-response'] = 'required';
            $messages['g-recaptcha-response.required'] = 'Lutfen robot dogrulamasini tamamlayin.';
        }

        $validated = $request->validate($rules, $messages);
        $mailPayload = $validated;
        $mailPayload['form_message'] = $validated['message'];
        unset($mailPayload['message']);

        if (RecaptchaAyarlari::etkinMi()) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            $body = $response->json();

            if (empty($body['success'])) {
                return back()
                    ->with('error', 'Robot dogrulamasi basarisiz. Lutfen tekrar deneyin.')
                    ->withInput($request->except('g-recaptcha-response'));
            }
        }

        $to = Setting::get('mail_recipient');

        if (empty($to)) {
            return back()->with('error', 'Mail alici adresi tanimli degil. Lutfen yonetici ile iletisime gecin.');
        }

        try {
            Mail::send('emails.contact', $mailPayload, function ($message) use ($to, $validated) {
                $message->to($to)
                    ->replyTo($validated['email'], $validated['name'])
                    ->subject('Iletisim formu: '.$validated['name']);
            });
        } catch (\Throwable $e) {
            Log::error('Iletisim formu mail gonderimi basarisiz.', [
                'message' => $e->getMessage(),
                'mail_host' => Setting::get('mail_host'),
                'mail_port' => Setting::get('mail_port'),
                'mail_encryption' => Setting::get('mail_encryption'),
                'mail_username' => Setting::get('mail_username'),
                'mail_recipient' => $to,
            ]);

            return back()->with('error', 'Mesaj gonderilemedi. Lutfen daha sonra tekrar deneyin.');
        }

        return back()->with('success', 'Mesajiniz alindi, en kisa surede size donus yapacagiz.');
    }
}
