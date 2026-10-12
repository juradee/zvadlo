<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * Replaces %symbol% placeholders the way h.skauting does: plain substitution, no HTML in the values.
 */
final class TemplateRenderer
{
    public const array SYMBOLS = [
        'jmeno' => 'jméno účastníka',
        'prijmeni' => 'příjmení účastníka',
        'akce' => 'název akce',
        'termin' => 'termín akce',
        'misto' => 'místo akce',
        'cena' => 'cena akce',
        'odkaz-prihlaska' => 'osobní odkaz na přihlášku',
        'odkaz-web' => 'odkaz na miniweb akce',
        'uzaverka' => 'poslední den, kdy lze přihlášku podat, změnit i zrušit (uzávěrka, jinak konec akce)',
        'kontakt' => 'kontaktní osoba akce (jméno, e-mail, telefon)',
        'jednotka' => 'název jednotky',
        'poznamka' => 'text, který vedoucí napíše při odeslání (zpráva, důvod zamítnutí)',
        'kod' => 'kód pro úpravu přihlášky (jen v e-mailu s kódem)',
    ];

    /**
     * @param array<string, string> $values symbol name => value, without the percent signs
     */
    public function render(string $template, array $values): string
    {
        $search = [];
        $replace = [];
        foreach (self::SYMBOLS as $symbol => $description) {
            $search[] = '%'.$symbol.'%';
            $replace[] = $values[$symbol] ?? '';
        }

        return trim(str_replace($search, $replace, $template));
    }
}
