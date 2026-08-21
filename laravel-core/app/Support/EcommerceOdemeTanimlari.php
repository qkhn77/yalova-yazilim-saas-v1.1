<?php

namespace App\Support;

class EcommerceOdemeTanimlari
{
    public const SAGLAYICI_HAVALE_EFT = 'havale_eft';

    /**
     * @return array<string, string>
     */
    public static function saglayicilar(): array
    {
        return [
            self::SAGLAYICI_HAVALE_EFT => 'Havale / EFT',
            'stripe' => 'Stripe',
            'iyzico' => 'iyzico',
            'paytr' => 'PayTR',
            'payoneer' => 'Payoneer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paraBirimleri(): array
    {
        return [
            'TRY' => 'TRY',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function varsayilanYontemler(): array
    {
        return [
            ['kod' => 'havale_eft', 'ad' => 'Havale / EFT', 'saglayici' => self::SAGLAYICI_HAVALE_EFT],
            ['kod' => 'stripe', 'ad' => 'Stripe Kart', 'saglayici' => 'stripe'],
            ['kod' => 'iyzico', 'ad' => 'iyzico Kart', 'saglayici' => 'iyzico'],
            ['kod' => 'paytr', 'ad' => 'PayTR Kart', 'saglayici' => 'paytr'],
            ['kod' => 'payoneer', 'ad' => 'Payoneer', 'saglayici' => 'payoneer'],
        ];
    }
}
