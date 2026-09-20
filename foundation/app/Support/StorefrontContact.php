<?php

namespace App\Support;

class StorefrontContact
{
    private const COUNTRY_CODE = '506';

    /**
     * Public contact channels, ready for the browser. The configured number may be written in
     * any local format (8342-1619, 8342 1619, +506 8342-1619): the display text and the wa.me
     * link are both derived here so the page never has to guess.
     */
    public static function public(): array
    {
        $digits = preg_replace('/\D/', '', (string) config('storefront.contact.whatsapp'));
        $international = match (true) {
            $digits === '' => '',
            // Costa Rican numbers are eight digits; wa.me always needs the country code.
            strlen($digits) === 8 => self::COUNTRY_CODE.$digits,
            default => $digits,
        };

        return [
            'whatsapp' => $international === '' ? '' : self::display($international),
            'whatsapp_url' => $international === '' ? '' : 'https://wa.me/'.$international,
            'email' => (string) config('storefront.contact.email'),
        ];
    }

    /** 50683421619 becomes "8342-1619"; anything else is shown as "+<digits>". */
    private static function display(string $international): string
    {
        if (str_starts_with($international, self::COUNTRY_CODE) && strlen($international) === 11) {
            $local = substr($international, 3);

            return substr($local, 0, 4).'-'.substr($local, 4);
        }

        return '+'.$international;
    }
}
