<?php

namespace App\Services\Front;

class FrontTercihServisi
{
    public const SESSION_LOCALE = 'front_locale';
    public const SESSION_CURRENCY = 'front_currency';

    /**
     * @return array<string, string>
     */
    public function desteklenenDiller(): array
    {
        return [
            'tr' => 'TR',
            'en' => 'EN',
            'de' => 'DE',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function desteklenenParaBirimleri(): array
    {
        return [
            'TRY' => 'TRY',
            'USD' => 'USD',
            'EUR' => 'EUR',
        ];
    }

    public function varsayilanDil(): string
    {
        return 'tr';
    }

    public function varsayilanParaBirimi(): string
    {
        return 'TRY';
    }

    public function aktifDil(): string
    {
        return $this->dilNormalize((string) session(self::SESSION_LOCALE, $this->varsayilanDil()));
    }

    public function aktifParaBirimi(): string
    {
        return $this->paraBirimiNormalize((string) session(self::SESSION_CURRENCY, $this->varsayilanParaBirimi()));
    }

    public function dilNormalize(string $dil): string
    {
        $dil = mb_strtolower(trim($dil));

        return array_key_exists($dil, $this->desteklenenDiller()) ? $dil : $this->varsayilanDil();
    }

    public function paraBirimiNormalize(string $paraBirimi): string
    {
        $paraBirimi = strtoupper(trim($paraBirimi));

        return array_key_exists($paraBirimi, $this->desteklenenParaBirimleri()) ? $paraBirimi : $this->varsayilanParaBirimi();
    }
}

