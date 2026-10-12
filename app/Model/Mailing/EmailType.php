<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * Kinds of e-mail an event sends. Most have an editable template per event; the reminder reuses the
 * invitation, the edit code has a fixed text and the rejection template belongs to the unit.
 */
enum EmailType: string
{
    case Invitation = 'invitation';
    case Reminder = 'reminder';
    case Confirmation = 'confirmation';
    case Declined = 'declined';
    case Changed = 'changed';
    case LockNotice = 'lock_notice';
    case Info = 'info';
    case EditCode = 'edit_code';
    case Rejected = 'rejected';
    case Promoted = 'promoted';

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Pozvánka (zvadlo)',
            self::Reminder => 'Připomínka pozvánky',
            self::Confirmation => 'Potvrzení přihlášky',
            self::Declined => 'Potvrzení odmítnutí',
            self::Changed => 'Změna nebo zrušení přihlášky',
            self::LockNotice => 'Upozornění na uzávěrku',
            self::Info => 'Zpráva účastníkům',
            self::EditCode => 'Kód pro úpravu přihlášky',
            self::Rejected => 'Zamítnutí přihlášky',
            self::Promoted => 'Přesun z čekací listiny',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Invitation => 'Posílá vedoucí přidaným účastníkům; stejný text odchází i jako připomínka s předmětem doplněným o „ - připomínka“.',
            self::Reminder => 'Stejný text jako pozvánka.',
            self::Confirmation => 'Odchází automaticky po odeslání přihlášky.',
            self::Declined => 'Odchází automaticky, když účastník účast odmítne.',
            self::Changed => 'Odchází automaticky při změně nebo zrušení přihlášky.',
            self::LockNotice => 'Odchází automaticky přihlášeným několik dní před uzávěrkou (jen u akce s uzávěrkou).',
            self::Info => 'Libovolná zpráva, kterou vedoucí pošle vybraným účastníkům; text píše při odeslání do %poznamka%.',
            self::EditCode => 'Pevný text s kódem, kterým si nečlen odemkne úpravu přihlášky.',
            self::Rejected => 'Odchází automaticky po zamítnutí přihlášky; text platí pro všechny akce jednotky.',
            self::Promoted => 'Odchází automaticky náhradníkovi, který se z čekací listiny dostal mezi účastníky.',
        };
    }

    /**
     * Templates a leader edits for one event and a unit keeps as defaults.
     *
     * @return list<self>
     */
    public static function eventTemplates(): array
    {
        return [self::Invitation, self::Confirmation, self::Declined, self::Changed, self::Promoted, self::LockNotice, self::Info];
    }

    /**
     * Templates edited in the unit settings: the event defaults plus the unit-wide rejection mail.
     *
     * @return list<self>
     */
    public static function unitTemplates(): array
    {
        return [...self::eventTemplates(), self::Rejected];
    }

    /** The template this kind of e-mail is rendered from. */
    public function templateType(): self
    {
        return $this === self::Reminder ? self::Invitation : $this;
    }

    /** Whether a leader may switch the e-mail off for an event. */
    public function isOptional(): bool
    {
        return \in_array($this, [self::Declined, self::Changed, self::LockNotice], true);
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::Invitation, self::Reminder => 'Zvadlo: %akce% (%termin%)',
            self::Confirmation => 'Přihláška na akci %akce% přijata',
            self::Declined => 'Odmítnutí účasti na akci %akce%',
            self::Changed => 'Změna přihlášky na akci %akce%',
            self::LockNotice => 'Poslední možnost upravit přihlášku na akci %akce%',
            self::Promoted => 'Uvolnilo se místo: %jmeno% jede na akci %akce%',
            self::Info => 'Informace k akci %akce%',
            self::EditCode => 'Kód pro úpravu přihlášky na akci %akce%',
            self::Rejected => 'Přihláška na akci %akce% nebyla přijata',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::Invitation, self::Reminder => <<<'TXT'
                Ahoj,

                zveme %jmeno% %prijmeni% na akci **%akce%** (%termin%, %misto%).

                Přihlášku vyplňte prosím na osobním odkazu, i když se akce nezúčastníte:
                %odkaz-prihlaska%

                Přihlášku lze podat, změnit i zrušit do %uzaverka%.

                %poznamka%

                Díky a těšíme se,
                %kontakt%
                %jednotka%
                TXT,
            self::Confirmation => <<<'TXT'
                Ahoj,

                přihláška %jmeno% %prijmeni% na akci **%akce%** (%termin%, %misto%) je přijatá.

                Přihlášku můžete do %uzaverka% upravit nebo zrušit na stejném odkazu:
                %odkaz-prihlaska%

                Informace k akci: %odkaz-web%

                %kontakt%
                %jednotka%
                TXT,
            self::Declined => <<<'TXT'
                Ahoj,

                zaznamenali jsme, že se %jmeno% %prijmeni% akce **%akce%** (%termin%) nezúčastní. Děkujeme za odpověď.

                Kdyby se něco změnilo, přihlášku lze do %uzaverka% vyplnit znovu: %odkaz-prihlaska%

                %kontakt%
                %jednotka%
                TXT,
            self::Changed => <<<'TXT'
                Ahoj,

                přihláška %jmeno% %prijmeni% na akci **%akce%** (%termin%) byla změněna. Aktuální stav najdete na odkazu:
                %odkaz-prihlaska%

                %kontakt%
                %jednotka%
                TXT,
            self::LockNotice => <<<'TXT'
                Ahoj,

                blíží se uzávěrka akce **%akce%** (%termin%). Přihlášku %jmeno% %prijmeni% lze upravit nebo zrušit jen do %uzaverka%:
                %odkaz-prihlaska%

                Potom už změny domluvte přímo s vedoucím.

                %kontakt%
                %jednotka%
                TXT,
            self::Promoted => <<<'TXT'
                Ahoj,

                na akci **%akce%** (%termin%, %misto%) se uvolnilo místo: %jmeno% %prijmeni% je teď z čekací listiny
                mezi účastníky.

                Pokud už nemůžete, zrušte prosím účast do %uzaverka% na stejném odkazu, ať místo dostane další náhradník:
                %odkaz-prihlaska%

                Informace k akci: %odkaz-web%

                %kontakt%
                %jednotka%
                TXT,
            self::Info => <<<'TXT'
                Ahoj,

                k akci **%akce%** (%termin%) posíláme zprávu:

                %poznamka%

                Přihláška %jmeno% %prijmeni%: %odkaz-prihlaska%

                %kontakt%
                %jednotka%
                TXT,
            self::EditCode => <<<'TXT'
                Ahoj,

                pro úpravu přihlášky na akci **%akce%** zadejte kód **%kod%**. Kód platí 15 minut.

                Pokud jste o úpravu nežádali, tento e-mail ignorujte.

                %jednotka%
                TXT,
            self::Rejected => <<<'TXT'
                Ahoj,

                přihlášku na akci **%akce%** (%termin%) jsme bohužel nemohli přijmout.

                %poznamka%

                Případné dotazy: %kontakt%
                %jednotka%
                TXT,
        };
    }
}
