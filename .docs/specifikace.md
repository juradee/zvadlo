# Zvadlo – specifikace

> Název projektu je **Zvadlo** (zvadlo.skauting.cz);
> rozhodnutí zadavatele jsou v kapitole [Rozhodnutí](#rozhodnutí). Dokument je živý; při změně rozhodnutí se aktualizuje.
> Technické provedení a pořadí prací popisuje [implementační plán](implementacni-plan.md).

## 1. Účel

Zvadlo je registrační systém pro **jednotky** (oddíly, střediska) Junáka – českého skauta.
Nahrazuje e-přihlášku ve skautISu pro běžné akce jednotky: výpravy, výlety, tábory.

Co dělá:

- Vedoucí založí akci a rozešle **zvadlo** (pozvánku) členům. Členy pod 18 let zastupují rodiče.
- Rodič nebo člen otevře **osobní odkaz** z e-mailu, ověří se datem narození dítěte a vyplní přihlášku.
  Přihlášku vyplní i v případě, že se dítě **nezúčastní**. Systém tak ví, kdo zvadlo viděl a odmítl.
- Akce má **miniweb** s informacemi, zvadlem v PDF/JPG a odkazy (fotoalbum), přístupný jen přes odkaz z e-mailu.
- Přihlášeným se rozesílají informace ze **šablon** e-mailů, z Gmail účtu jednotky.
- Akce a účastníci se **propíší do skautISu**, kde je vidí Skautské hospodaření (h.skauting.cz).

Co nedělá (záměrně):

- Neřeší platby, programy, ubytování ani seminářovou agendu. To je doména SRS a h.skautingu.
- Nenahrazuje skautIS jako zdroj osobních údajů členů. Údaje členů čte ze skautISu a neukládá víc, než musí.

Vztah k ostatním systémům:

| Systém | Role |
|---|---|
| skautIS | Přihlašování uživatelů, role a oprávnění, seznam členů a kontaktů, cílový systém pro akce a účastníky. |
| h.skauting | Hospodaření akce. Zvadlo mu nabízí API, aby u akce zobrazil příznak „existuje záznam ve Zvadle“. |
| SRS | Inspirace pro funkce (vlastní pole, šablony, miniweb). Zvadlo je jeho malá, specializovaná obdoba pro jednotky. |

## 2. Aktéři

| Aktér | Přihlášení | Co dělá |
|---|---|---|
| **Vedoucí** (správce akce) | skautIS | Zakládá akce, vybírá pozvané, rozesílá e-maily, spravuje přihlášky a miniweb. Oprávnění odvozené ze skautISu. |
| **Rodič** | bez přihlášení, osobní odkaz + datum narození dítěte | Vyplní přihlášku za dítě, upraví ji nebo zruší účast. Vidí miniweb. |
| **Člen (dospělý)** | bez přihlášení, osobní odkaz + vlastní datum narození | Totéž za sebe. |
| **Nečlen** | bez přihlášení, osobní odkaz | Vedoucí ho přidá ručně (jméno, e-mail). Vyplní i osobní údaje. |
| **Správce systému** | skautIS + systémová role | Dohled nad celým systémem: uživatelé, jednotky, hlášení problémů, statistiky. Stejné jako v h.skautingu. |

## 3. Domény, adresy, tokeny

Jedna centrální instance pro všechny jednotky. Adresy se nesmí křížit, proto je každá akce
prefixovaná **skautIS ID jednotky** (číselné, stabilní; ne registrační číslo 622.01.xxx).

```
https://zvadlo.skauting.cz/                                  úvod, přihlášení vedoucího
https://zvadlo.skauting.cz/akce/…                            administrace (vyžaduje skautIS login)
https://zvadlo.skauting.cz/{unitId}/{slug}?t={webToken}      miniweb akce
https://zvadlo.skauting.cz/{unitId}/{slug}/prihlaska/{token} osobní přihláška
https://zvadlo.skauting.cz/api/v1/…                          API pro h.skauting
```

- **slug** akce je unikátní v rámci jednotky (unikátní index `unit_id + slug`). Generuje se z názvu,
  při kolizi se přidá přípona (`-2`, `-3`). Vedoucí ho může ručně upravit, dokud nebyla rozeslána pozvánka.
- **webToken** je sdílený tajný token miniwebu (jeden na akci). Kdo ho má, vidí miniweb. Vedoucí ho může
  **obnovit** (staré odkazy přestanou platit; je nutné poslat nové). Bez tokenu miniweb vrací 404, ne 403,
  aby neprozrazoval existenci akce.
- **token** přihlášky je osobní (jeden na pozvaného na akci). Nikdy se neposílá dál, e-mail s ním je
  určený jen adresátovi. Otevření přihlášky navíc vyžaduje **datum narození** účastníka (u dítěte rodič
  zadá datum narození dítěte). Nečlen datum narození nezadává; jeho token je jediné ověření.
- Tokeny mají 32 bajtů náhody (base64url). Osobní token přihlášky se v databázi ukládá jen jako SHA-256 hash
  a ověřuje se přes hash, takže únik databáze neprozradí funkční osobní odkazy. Token miniwebu se ukládá
  v otevřené podobě, protože ho vedoucí potřebuje zobrazit a poslat; jeho únik odkryje jen obsah miniwebu.
- Po pěti neúspěšných ověřeních data narození se odkaz na 15 minut zablokuje (ochrana proti hádání).

Miniweb neobsahuje přísně tajné informace, ale ani sraz dětí nemá být veřejně dohledatelný.
Proto je celý web (včetně příloh) chráněný tokenem a má `noindex`.

## 4. Akce

Akce patří **jedné jednotce** (skautIS ID). Vedoucí střediska vidí akce podřízených oddílů,
vedoucí oddílu jen svého oddílu (stejná logika jako v h.skautingu, viz [oprávnění](#7-oprávnění)).

### 4.1 Vlastnosti akce

| Pole | Popis |
|---|---|
| název, slug | viz výše |
| typ a rozsah akce | číselníky ze skautISu (`EventGeneralTypeAll`, `EventGeneralScopeAll`), stejné jako při zakládání akce v h.skautingu; použijí se při propsání |
| tábor | příznak; tábor se založí ve skautISu (přes API to nejde), vedoucí ho ve Zvadle vybere ze seznamu táborů jednotky |
| termín od–do | datum; u jednodenních akcí od = do |
| místo | text |
| popis | text pro miniweb a e-mail (Markdown, bez HTML) |
| uzávěrka přihlášek | datum; po uzávěrce nelze podat novou přihlášku |
| zámek úprav | **počet dní před akcí**, po kterém účastník nemůže přihlášku měnit ani rušit (nastavení akce, výchozí 3) |
| kapacita | volitelná (prázdná = bez omezení); po naplnění jdou další přihlášky na čekací listinu |
| cena | volitelná, informativní (zobrazí se v e-mailu a na webu; platby systém neřeší) |
| kontaktní osoba | jméno, e-mail, telefon vedoucího pro dotazy; výchozí z přihlášeného vedoucího; e-mail je `Reply-To` všech e-mailů akce |
| stav | koncept → otevřená → uzavřená → archivovaná |
| skautIS akce | ID akce ve skautISu po propsání, včetně odkazu |
| odesílací účet | Gmail účet jednotky, ze kterého odcházejí e-maily (výběr z účtů jednotky nebo nadřízeného střediska); bez něj nelze akci otevřít |

Stavy:

- **koncept** – vidí ji jen vedoucí; lze upravovat vše, nic se nerozesílá.
- **otevřená** – rozeslané pozvánky, přihlášky se přijímají, miniweb funguje.
- **uzavřená** – po uzávěrce nebo ručně; přihlášky nelze podávat ani měnit, miniweb funguje, e-maily lze posílat.
- **archivovaná** – akce skončila; jen ke čtení. Po uplynutí retenční lhůty se osobní údaje nečlenů anonymizují.

Více akcí ve stejném termínu je běžné (družiny, oddíly). Systém to nijak neomezuje, jen v přehledu upozorní,
že člen je pozvaný na dvě akce se stejným termínem.

### 4.2 Vlastní pole formuláře

Ke každé akci lze přidat libovolný počet vlastních polí:

| Typ | Uložení | Příklad |
|---|---|---|
| text | řetězec | „Alergie a léky“ |
| ano/ne | boolean | „Souhlasím s focením“ |
| výběr jedné možnosti (radio) | jedna hodnota ze seznamu | „Doprava: autobus / vlastní“ |
| výběr více možností (checklist) | seznam hodnot | „Přijede na: pátek / sobota / neděle“ |

Každé pole má popisek, nápovědu, příznak *povinné* a pořadí. Pole lze upravovat, dokud nemá žádná přihláška
vyplněnou hodnotu; potom lze jen přidat nové pole nebo pole skrýt. Hodnoty se ukládají v tabulce
v normalizovaných tabulkách podle typu (`registration_text_value`, `registration_boolean_value`,
`registration_option_value`; možnosti výběru jsou řádky v `event_field_option`), aby šly exportovat a filtrovat.
Na rozdíl od SRS nejsou pole vázaná na role ani na uživatele, ale na akci a přihlášku.

### 4.3 Pozvaní

Vedoucí sestaví seznam pozvaných:

- **ze skautISu** – členové jednotky a podřízených jednotek (`PersonAll` s `OnlyDirectMember=false`), filtr podle
  oddílu/družiny a věku, hromadný výběr. Systém uloží skautIS ID osoby, jméno, datum narození (pro ověření)
  a e-mailové adresy, na které pošle zvadlo.
- **ručně** – nečlen: jméno, příjmení, e-mail. Datum narození a další údaje vyplní sám v přihlášce.

Adresát e-mailu:

1. Člen do 18 let: e-maily **rodičů** ze skautISu (`PersonParentAll`, stejně jako h.skauting); pokud aplikace
   nemá oprávnění nebo rodič e-mail nemá, hlavní e-mail dítěte (`PersonContactAll`, typ `email_hlavni`);
   pokud ani ten, vedoucí doplní ručně.
2. Člen nad 18 let: vlastní hlavní e-mail ze skautISu.
3. Nečlen: e-mail zadaný vedoucím.

Kontakty se ze skautISu donačítají hned po přidání pozvaných AJAX požadavky po dávkách (viz 12); stránka
mezitím ukazuje průběh. Bez e-mailu nelze pozvánku odeslat; systém takové pozvané zvýrazní a nabídne ruční doplnění.
E-mail ze skautISu se použije jen k odeslání; systém ho ukládá spolu s pozvánkou, aby bylo dohledatelné, kam
zvadlo odešlo. Účastník si v přihlášce může zadat jiný kontaktní e-mail, na který se pak posílají další zprávy.

## 5. Přihláška

### 5.1 Stavy

```
pozván ──(otevře odkaz, ověří se)──▶ zobrazeno ──▶ přihlášen ──▶ zrušeno
                                           └──────▶ odmítl
                                           přihlášen ──(kapacita plná)──▶ čekací listina ──▶ přihlášen
```

| Stav | Význam |
|---|---|
| pozván | pozvánka odeslána, odkaz zatím neotevřen |
| zobrazeno | odkaz otevřen a ověřen, přihláška neodeslána |
| přihlášen | účast potvrzena, formulář vyplněn |
| odmítl | rodič/člen zvadlo viděl a účast odmítl (může uvést důvod) |
| čekací listina | přihlášen po naplnění kapacity; vedoucí může přesunout mezi přihlášené |
| zrušeno | účast zrušena po přihlášení (účastníkem před zámkem, nebo vedoucím kdykoli) |

Vedoucí vidí u každého pozvaného stav, čas otevření odkazu a čas odpovědi. Přehled akce ukazuje počty
podle stavů a seznam těch, kdo **nereagovali**, s tlačítkem „poslat připomínku“.

### 5.2 Formulář

1. **Ověření** – datum narození účastníka (u nečlena se přeskočí).
2. **Účast** – „Zúčastní se“ / „Nezúčastní se“. Při odmítnutí volitelný důvod a konec.
3. **Údaje** – u člena jméno a příjmení jen ke čtení (ze skautISu), předvyplněný e-mail a telefon k úpravě.
   U nečlena vše, co potřebuje skautIS pro založení osoby: jméno, příjmení, přezdívka (nepovinná),
   **pohlaví (povinné**, i když dokumentace skautISu tvrdí opak), datum narození, ulice, město, PSČ,
   e-mail, telefon. Poznámka pro vedoucí.
4. **Vlastní pole** akce.
5. **Souhlas** – potvrzení, že údaje jsou pravdivé a že zákonný zástupce souhlasí s účastí (text je nastavitelný na úrovni jednotky).

Po odeslání přijde potvrzovací e-mail s odkazem na úpravu přihlášky (stejný osobní odkaz) a odkazem na
miniweb. Přihlášku lze měnit a rušit až do **zámku úprav**; potom formulář zobrazí jen souhrn a kontakt na vedoucího.

### 5.3 Správa přihlášek vedoucím

- Seznam pozvaných s filtrem podle stavu, hledáním, hromadným výběrem pro e-mail.
- Detail přihlášky včetně historie změn (kdo, kdy, co) a odeslaných e-mailů.
- Ruční změna stavu (zrušit, přesunout z čekací listiny, označit odmítnutí po telefonu).
- Export do XLSX/CSV (standardní pole + vlastní pole).
- Přidání pozvaného i po rozeslání (dodatečná pozvánka).

## 6. Miniweb akce

Miniweb je jednoduchá stránka akce dostupná přes `webToken`. Obsah se skládá z bloků, které vedoucí řadí:

| Blok | Obsah |
|---|---|
| text | Markdown (nadpisy, odstavce, seznamy, odkazy) |
| soubor | příloha PDF nebo JPG/PNG (zvadlo, seznam věcí); do 10 MB, uložená mimo web root, servírovaná přes presenter s kontrolou tokenu |
| odkaz | externí odkaz s popiskem (fotoalbum Immich / Google Photos, mapa srazu). Data zůstávají mimo systém. |

Hlavička webu automaticky zobrazuje název, termín, místo, kontaktní osobu a tlačítko „Přihláška“ (vede na
obecnou stránku s pokynem použít osobní odkaz z e-mailu; osobní tokeny se na webu nezobrazují).

Oproti SRS (17 typů bloků, stránky, role) je miniweb záměrně jednostránkový a bez rolí.

## 7. Oprávnění

Systém **respektuje oprávnění ve skautISu** a přebírá model z h.skautingu ve třech vrstvách.

### 7.1 Přihlášení

- Výhradně přes skautIS (jádro `skaut/skautis` 3.1 a balíček `skautis-nette`, viz 9.1). Uživatel je přesměrován na `{skautIS}/Login/?appid=…`, skautIS pošle
  POST na registrovanou návratovou adresu s poli `skautIS_Token`, `skautIS_IDRole`, `skautIS_IDUnit`,
  `skautIS_DateLogout`. Identita v Nette má ID = skautIS ID uživatele (`UserDetail`), role = `UserRoleAll`,
  data = aktuální role a přístupové jednotky.
- Token platí 30 minut; každý požadavek přihlášení prodlužuje (`LoginUpdateRefresh`), jako v h.skautingu.
  Při vypršení systém uživatele odhlásí, uloží rozpracovaný požadavek a po opětovném přihlášení ho obnoví.
- Přepínání role a jednotky v horní liště (`LoginUpdate`), s možností zapamatovat si poslední roli.
- Odhlášení proběhne i ve skautISu (`Login/LogOut.aspx`), jinak by se další návštěva přihlásila sama.

### 7.2 Přístup k jednotkám (podle klíče role)

Role ze skautISu se klasifikuje podle klíče (`Key`) stejně jako v h.skautingu:

| Klíč role | Význam | Čtení | Úpravy |
|---|---|---|---|
| začíná `vedouci` | vedoucí / zástupce | jednotka role a podřízené | ano |
| začíná `hospodar` | hospodář | jednotka role a podřízené | ano |
| začíná `spravceAkci` | správce akcí | jednotka role a podřízené | ano |
| začíná `cinovnik` | činovník | jednotka role a podřízené | ne |
| ostatní | – | nic | ne |

Podřízené jednotky se načítají rekurzivně (`UnitAll` s `ID_UnitParent`) pro role končící `Stredisko` nebo
`Oddil`; strom jednotek se cachuje jeden den. Výsledek je dvojice seznamů `read` a `edit` uložená v identitě.
Akce jednotky smí spravovat jen uživatel, který má jednotku v `edit`; ostatní ji vidí jen ke čtení.

### 7.3 Ověření ve skautISu (`ActionVerify`)

Operace, které se dotýkají skautISu, se před provedením ověřují přímo v něm (`UserManagement.ActionVerify`
s `ID_Table` a `ID`), přesně jako v h.skautingu:

| Operace ve Zvadle | Akce ve skautISu |
|---|---|
| propsat akci do skautISu | `EV_EventGeneral_INSERT` (tabulka `EV_EventGeneral`, bez ID) |
| propsat účastníky | `EV_ParticipantGeneral_INSERT_EventGeneral` nad akcí |
| odebrat účastníka | `EV_ParticipantGeneral_DELETE_EventGeneral` nad akcí |
| propsat účastníky tábora | `EV_ParticipantCamp_INSERT_EventCamp` nad táborem |

Výsledek se cachuje po dobu požadavku. Chybějící oprávnění se zobrazí v UI jako vysvětlení, proč tlačítko
není dostupné (obdoba dialogu oprávnění v h.skautingu).

### 7.4 Systémové role

- Tabulka `system_user_role` (`user_id` = skautIS ID uživatele, `role` ∈ `admin`, `support`) a seznam
  `adminAllowedUserIds` v konfiguraci pro nouzový přístup. Shodné s h.skautingem.
- `admin`: vše v administraci. `support`: jen hlášení problémů.
- Správci se spravují v administraci (`/admin/uzivatele`): zadání skautIS ID uživatele a zaškrtnutí rolí.

### 7.5 Implementace

Jeden `Authorizator` s metodou `isAllowed(resource, action, ?id)`. Zdroje: `Event` (naše akce – kontrola
proti `edit`/`read` jednotkám), `Unit`, `SkautisEvent` (přes `ActionVerify`), `Admin`, `BugReports`.
Presentery se nikdy neptají skautISu přímo.

## 8. E-maily

### 8.1 Šablony

Šablony fungují jako u platebních skupin v h.skautingu:

- Každá akce má **vlastní sadu e-mailů** (`event_email`): typ, zapnuto/vypnuto, předmět, tělo.
  Při založení akce se naplní z **výchozích šablon jednotky** (`unit_email_template`); pokud jednotka
  žádné nemá, ze systémových výchozích textů dodaných v repozitáři.
- Jednotka si výchozí šablony upraví v nastavení jednotky; změna se projeví u nově zakládaných akcí.
- Zástupné symboly jsou ve tvaru `%nazev%` a nahrazují se prostým dosazením (bez HTML). Vedle editoru je
  nápověda se seznamem symbolů, jako v h.skautingu.

| Typ | Kdy se posílá | Komu |
|---|---|---|
| `invitation` (zvadlo) | ručně vedoucím při otevření akce a pro dodatečně pozvané | pozvaní |
| `reminder` | ručně, těm, kdo nereagovali | pozvaní bez odpovědi |
| `confirmation` | automaticky po odeslání přihlášky | účastník |
| `declined` | automaticky po odmítnutí (lze vypnout) | účastník |
| `changed` | automaticky při změně nebo zrušení přihlášky | účastník |
| `lock_notice` | automaticky N dní před zámkem úprav (nastavitelné, lze vypnout) | přihlášení |
| `info` | ručně, libovolný text | vybraní podle stavu |

Symboly: `%jmeno%`, `%prijmeni%`, `%akce%`, `%termin%`, `%misto%`, `%cena%`, `%odkaz-prihlaska%`,
`%odkaz-web%`, `%uzaverka%`, `%zamek%`, `%kontakt%`, `%jednotka%`, `%poznamka%` (text, který vedoucí
napíše při ručním odeslání).

### 8.2 Odesílání

- Jednotka si připojí **Gmail účet** přes OAuth (`google/apiclient`, rozsah `gmail.send` + e-mail účtu),
  přesně jako h.skauting: tlačítko v nastavení jednotky → Google → návrat na `/google/token` → uloží se
  jen **refresh token** do `google_oauth` (jednotka, e-mail). Účet je vázaný na jednotku aktivní role.
  Refresh token je v databázi šifrovaný klíčem z `.env` (`APP_SECRET`).
- Akce má vybraný odesílací účet; nabízejí se účty její jednotky a nadřízeného střediska (pravidlo z h.skautingu).
- Odeslání přes Gmail API (`users.messages.send`). Neplatný nebo odvolaný token skončí srozumitelnou chybou
  „Je potřeba odebrat a znovu propojit e-mailový účet.“
- Jiný odesílatel pro e-maily akce **není**, stejně jako v h.skautingu. Bez připojeného účtu nelze akci otevřít
  ani nic rozeslat; UI vysvětlí, že je třeba v nastavení jednotky propojit Gmail účet.
- Každý e-mail akce má `Reply-To` nastavené na **kontaktní osobu z nastavení akce** (jméno a e-mail).
  Rodič tak odpovídá vedoucímu, ne na adresu účtu jednotky.
- Systémové e-maily (hlášení chyb, odpovědi správců) odcházejí sendmailem z `noreply@<doména instance>`,
  přesně jako v h.skautingu. S e-maily akcí nemají nic společného.
- V prostředí `dev` a `ci` (`SEND_EMAIL=false`) jde **veškerá** pošta, e-maily akcí i systémové, přes SMTP do
  mailcatcheru v Dockeru (Mailpit, webové UI na `mail.localhost`). Ven nikdy nic neodejde. V `dev` se navíc
  poslední zprávy zobrazují v Tracy liště (`nextras/mail-panel`), aby šly šablony ladit bez přepínání oken.
- Každý odchozí e-mail odesílá **worker fronty úloh** (viz 12): vedoucí založí úlohu „rozeslat zvadlo“,
  automatické e-maily zakládá systém jako úlohu s jedním adresátem. Worker šablonu vyrenderuje, odešle
  a zapíše řádek do logu `email_message` (adresát, předmět, čas, výsledek, chyba, úloha a kdo ji založil).

## 9. Propojení se skautISem a h.skautingem

### 9.1 Čtení ze skautISu

| Účel | Služba a metoda | Parametry |
|---|---|---|
| detail přihlášeného | `UserManagement.UserDetail` | – |
| role uživatele | `UserManagement.UserRoleAll` | `ID_User`, `IsActive=true` |
| přepnutí role | `UserManagement.LoginUpdate` | `ID` (token), `ID_UserRole` |
| prodloužení přihlášení | `UserManagement.LoginUpdateRefresh` | `ID` (token) |
| ověření oprávnění | `UserManagement.ActionVerify` | `ID_Table`, `ID` |
| detail jednotky | `OrganizationUnit.UnitDetail` | `ID` |
| podřízené jednotky | `OrganizationUnit.UnitAll` | `ID_UnitParent` |
| členové jednotky | `OrganizationUnit.PersonAll` | `ID_Unit`, `OnlyDirectMember` |
| detail osoby | `OrganizationUnit.PersonDetail` | `ID` |
| kontakty osoby | `OrganizationUnit.PersonContactAll` | `ID_Person` |
| kontakty rodičů | `OrganizationUnit.PersonParentAll` | `ID_Person` |
| členství (kategorie) | `OrganizationUnit.MembershipAllPerson` | `ID_Person`, `IsValid=true` |
| číselníky akcí | `Events.EventGeneralTypeAll`, `EventGeneralScopeAll` | – |
| tábory jednotky | `Events.EventCampAll` | `Year`, `ID_EventCampState` |
| stav tábora | `Events.EventCampDetail` | `ID` (čte se `ID_EventCampState`) |

Vše přes vlastní rozhraní `App\Model\Skautis\SkautisGateway` (jedna metoda na účel, typované DTO) s jedinou
implementací nad jádrem `skaut/skautis` 3.1 (`Skaut\Skautis\Skautis`, webové služby přes `WsdlManager`,
`User` se session adaptérem). Napojení na Nette (DI extension, session adaptér, dispatcher, Tracy panel)
dodává interní balíček `packages/skautis-nette`. V `ci` a testech extension zapne **fixture továrnu**, která
místo SOAP vrací odpovědi z JSON souborů (`{Služba}/{Metoda}.json`); testy tak procházejí skutečným mapováním
a nikdy nevolají skutečný skautIS. Názvy vstupních polí se vždy ověřují na
`…/JunakWebservice/<Služba>.asmx?op=<Metoda>`, protože `SoapClient` neznámé klíče tiše zahodí.
Jednotky a členové se cachují (jednotky 1 den, členové 1 hodinu, s ručním obnovením).

### 9.2 Zápis do skautISu

| Účel | Metoda | Parametry (ověřit na ws.skautis.cz) |
|---|---|---|
| založení akce | `Events.EventGeneralInsert` | `ID=1`, `Location`, `Note=' '`, `ID_EventGeneralScope`, `ID_EventGeneralType`, `ID_Unit`, `DisplayName`, `StartDate`, `EndDate`, `IsStatisticAutoComputed=false`; z odpovědi se čte `ID` |
| účastník člen | `Events.ParticipantGeneralInsert` | `ID_EventGeneral`, `ID_Person`; duplicita `Participant_PersonIsAllreadyParticipantGeneral` se ignoruje |
| účastník nečlen | `Events.ParticipantGeneralInsert` + `OrganizationUnit.PersonUpdateBasic` | `Person{FirstName, LastName, NickName, Note}` → `ID_Person`; potom `ID`, `FirstName`, `LastName`, `NickName`, `ID_Sex`, `Birthday`, `Street`, `City`, `Postcode` |
| odebrání účastníka | `Events.ParticipantGeneralDelete` | `ID`, `DeletePerson=false` |
| účastník tábora | `Events.ParticipantCampInsert` | `ID_EventCamp`, `ID_Person` nebo `Person{…}` |

Pravidla:

- Akce se do skautISu propíše tlačítkem „Propsat do skautISu“ (u koncept → otevřená se nabídne rovnou).
  Uložené ID se zobrazí s odkazem do skautISu a do h.skautingu.
- Propsání účastníků spouští vedoucí; systém nejdřív ukáže rozdíl (kdo ve skautISu chybí, kdo je navíc)
  a po potvrzení ho provede. Výsledek každé položky se uloží (`skautis_participant_id`, chyba).
- Zrušení přihlášky po propsání nabídne odebrání účastníka ze skautISu.
- Tábory se přes API **nezakládají** (metoda `EventCampInsert` neexistuje, ověří se před fází 5). Postup je
  obrácený: vedoucí založí tábor ve skautISu, ve Zvadle ho vybere a Zvadlo do něj propisuje účastníky
  (`ParticipantCampInsert`), jakmile je tábor **odevzdaný ke schválení**. Propsání se povolí ve stavech
  `approvedLeader` a `approvedParent` (schválení střediskem není podmínkou; přesný stav se ještě potvrdí),
  ve stavu `draft` Zvadlo vysvětlí, co je třeba udělat ve skautISu.
- Nečlen se propisuje s kompletními údaji (viz 5.2), aby záznam ve skautISu nebyl torzo.
- Změny ve skautISu (přejmenování, termín) se do Zvadla nepropisují; skautIS je cíl, ne zdroj.

### 9.3 Oprávnění externí aplikace (AppID)

Při registraci aplikace na ws.skautis.cz je třeba požádat o tyto akce (názvy podle číselníku skautISu,
seznam se před podáním ověří):

- přihlášení: `UM_Login_DETAIL`, `UM_Login_UPDATE`, `UM_User_DETAIL`, `UM_UserRole_ALL_User`, `UM_Role_ALL`
- jednotky a osoby: `OU_Unit_DETAIL`, `OU_Unit_ALL`, `OU_Person_ALL` (členové jednotky), `OU_Person_DETAIL`,
  `OU_PersonContact_ALL_Person`, oprávnění pro `PersonParentAll` (kontakty rodičů), `OU_Membership_ALL_Person`
- akce: `EV_EventGeneral_INSERT`, `EV_EventGeneral_DETAIL`, `EV_EventGeneralType_ALL`, `EV_EventGeneralScope_ALL`,
  `EV_ParticipantGeneral_ALL_EventGeneral`, `EV_ParticipantGeneral_INSERT_EventGeneral`,
  `EV_ParticipantGeneral_DELETE_EventGeneral`
- tábory: `EV_EventCamp_ALL`, `EV_EventCamp_DETAIL`, `EV_ParticipantCamp_ALL_EventCamp`,
  `EV_ParticipantCamp_INSERT_EventCamp`, `EV_ParticipantCamp_DELETE`
- zápis osoby (nečlen): `OU_Person_UPDATE` (pro `PersonUpdateBasic`)

Kontakty rodičů jsou známé slabé místo (aplikace skautIS mobil je nemá). Bez nich systém posílá zvadlo
na e-mail dítěte a upozorní vedoucího.

### 9.4 API pro h.skauting

h.skauting zobrazí u akce příznak, že existuje záznam ve Zvadle. Oba systémy přihlašují uživatele přes skautIS;
jeho token má omezenou platnost (30 minut, prodlužuje se) a je vázaný na AppID, takže token h.skautingu nejde
použít ve Zvadle ani naopak. Výměna dat proto stojí na čtyřech zásadách:

1. **Systém–systém, ne uživatel–systém.** h.skauting volá Zvadlo mimo scope přihlášeného uživatele, se
   sdíleným tokenem konzumenta (`API_TOKEN_HSKAUTING` ve Zvadle, protějšek v konfiguraci h.skautingu), jen
   přes HTTPS. Token se porovnává konstantním časem; při rotaci Zvadlo přechodně přijímá dva tokeny. Bez
   platného tokenu vrací 401 bez rozlišení, zda akce existuje.
2. **API nikdy nevrací osobní údaje.** Vrací jen fakta o akci, která smí vidět každý, kdo akci vidí ve
   skautISu: existence záznamu, odkaz, souhrnné počty. Kdo je smí vidět, rozhoduje konzument: h.skauting
   u své akce už ověřil oprávnění uživatele přes skautIS. Zvadlo mu důvěřuje jen v rozsahu těchto neosobních dat.
3. **Osobní data tečou přes skautIS, nebo přes prohlížeč uživatele.** Účastníky Zvadlo propisuje do skautISu
   a h.skauting je odtud čte se svým vlastním oprávněním; přes API nikdy. Cokoli, co má vidět jen konkrétní
   uživatel, se řeší odkazem: odznak vede na detail akce ve Zvadle, kde se uživatel přihlásí skautISem sám,
   a Zvadlo stejně odkazuje na h.skauting.
4. **Rozšíření API** toto pravidlo dodrží. Kdyby někdy bylo nutné předat uživatelsky vázaná data (např.
   „moje přihlášky“ v jiném systému), použije se krátkodobý jednorázový token předaný přes prohlížeč uživatele,
   nikdy sdílený systémový token. V první verzi nic takového není.

```
GET /api/v1/skautis-events/{skautisEventId}
GET /api/v1/skautis-events?ids=123,124,125          pro seznam akcí, jeden dotaz místo N
Authorization: Bearer <token pro h.skauting>

200 { "exists": true, "url": "https://zvadlo.skauting.cz/akce/123", "registered": 18, "declined": 4, "pending": 3 }
404 { "exists": false }
```

- Odpovědi nesou `Cache-Control: max-age=300`; h.skauting je cachuje 5 minut a výpadek Zvadla nesmí rozbít
  jeho stránku (odznak se prostě nezobrazí).
- Změna v h.skautingu je samostatný pull request: odznak v hlavičce detailu akce
  (`app/Presentation/Events/Event/default.latte`) a sloupec v seznamu akcí, s odkazem do Zvadla.
- Ve Zvadle je u propsané akce odkaz na akci v h.skautingu.
- Přístupy k API se logují (konzument, akce, čas), aby šlo dohledat, kdo se na co ptal.

## 10. Administrace systému

Stejná jako v h.skautingu; funkce hlášení problémů se přebírá **beze změny chování**.

### 10.1 Uživatelé a role

`/admin/uzivatele`: seznam systémových uživatelů (skautIS ID + jméno ze skautISu), formulář se skautIS ID
uživatele a zaškrtávacími poli rolí `admin` / `support`, odebrání. Správci z konfigurace jsou vypsaní zvlášť.

### 10.2 Hlášení problémů

Odkaz „Nahlásit problém“ v patičce (`/nahlasit-problem`, s předvyplněnou URL aktuální stránky). Jen pro
přihlášené. Formulář:

| Pole | Pravidla |
|---|---|
| Popis chybového chování | povinné, max. 10 000 znaků, textarea 10 řádků, nápověda „Uveďte, co jste dělali, co jste očekávali a co se místo toho stalo.“ |
| URL stránky s chybou | nepovinné, platná URL, max. 2 048 znaků, předvyplněné |
| Váš e-mail | jen pokud se e-mail nepodařilo zjistit ze skautISu; povinné, max. 255 |
| Screenshot | nepovinný obrázek do 5 MB, ověření `getImageSize` |
| diagnostika klienta | skryté pole plněné JavaScriptem: URL, referrer, user agent, jazyk, obrazovka, viewport, časové pásmo, připojení |

Zpracování (`BugReportService::submit`):

1. Sestaví diagnostiku: uživatel (ID, detail, aktivní role a jednotka, všechny role, jednotky read/edit),
   požadavek (URL, metoda, IP, vybrané hlavičky), aplikace (release, PHP verze), diagnostika prohlížeče.
2. Uloží screenshot do `uploads/bug-report-screenshots/`.
3. Uloží hlášení do `technical_error_report`.
4. Pošle e-mail správcům (`ERROR_EMAILS`) s předmětem `[Zvadlo] Hlášení technické chyby #id`, `Reply-To`
   na hlasitele, screenshot v příloze; výsledek uloží k hlášení.
5. Uživateli zobrazí „Hlášení technické chyby bylo uloženo a odesláno správcům.“, případně varování,
   že e-mail se nepodařilo odeslat.

Administrace `/admin/hlaseni-chyb`: tabulka nevyřešených hlášení; detail s diagnostikou, screenshotem a
třemi akcemi: **odpovědět** (e-mail hlasiteli + komentář k issue), **založit GitHub issue** (ručně, POST na
`repos/{owner}/{repo}/issues` s tokenem z `.env`, štítky z konfigurace), **vyřešit / zamítnout** (e-mail
hlasiteli, označení stavu). Přístup mají role `admin` a `support`.

### 10.3 Statistiky a jednotky

- `/admin/statistiky`: počty jednotek, akcí, přihlášek, odeslaných e-mailů a chyb odesílání po měsících.
- `/admin/jednotky`: jednotky, které systém používají, jejich odesílací účty a poslední aktivita.

## 11. Prostředí

Prostředí se přepíná proměnnou `APP_ENV`, stejně jako v h.skautingu:

| `APP_ENV` | Kde | skautIS | Banner v liště |
|---|---|---|---|
| `dev` | lokální docker; do vyřízení vlastního AppID doména `moje-hospodareni.cz` a AppID h.skautingu | testovací (`test-is.skaut.cz`) | „Vývojový server“ |
| `ci` | GitHub Actions, testy | falešný klient z fixtur | – |
| `beta` | beta.zvadlo.skauting.cz, větve `beta/**` | testovací | „Beta server“ (modrý) |
| `test` | test.zvadlo.skauting.cz, `master` | testovací | „Testovací server“ (žlutý) |
| `prod` | zvadlo.skauting.cz, `master` po schválení | produkční | – |

Banner: mimo produkci se logo v liště nahradí ikonou s popiskem prostředí a lišta má barvu prostředí
(`TEST_BACKGROUND`, `ENVIRONMENT_LABEL`, `ENVIRONMENT_COLOR` jako v h.skautingu).

Konfigurace přes `.env` (výchozí hodnoty v repozitáři), `.env.local` (lokální přepis, mimo git),
`.env.{APP_ENV}` a `.env.{APP_ENV}.local`; načítá je `App\Environment`. Proměnné z okolí kontejneru mají
přednost. Seznam proměnných je v [implementačním plánu](implementacni-plan.md#proměnné-prostředí).

Režim údržby (`MAINTENANCE_MODE`, povolené IP) se přebírá z h.skautingu.

## 12. Dlouhé operace a fronta

Dva druhy práce, dvě pravidla.

**Čtení ze skautISu je vždy synchronní.** Jedno volání (členové jednotky, strom jednotek, seznam táborů,
účastníci akce ve skautISu) proběhne přímo v požadavku a výsledek se cachuje. Kde je volání víc (kontakty
pro každou pozvanou osobu: `PersonParentAll` a `PersonContactAll`), stránka se zobrazí hned a data se
**donačítají AJAX požadavky** po dávkách (10 osob na požadavek, každý pod několik sekund) s viditelným
průběhem; naja obnovuje snippet se seznamem. Když vedoucí stránku opustí, donačtení se dokončí při jejím
příštím otevření (přihlášky bez `recipients_resolved_at`). Čtení ze skautISu nikdy nejde do fronty.

**Úlohy zadané uživatelem jdou do fronty v databázi.** Úloha je akce vedoucího (nebo reakce na akci
účastníka), která něco odesílá nebo zapisuje mimo systém a může trvat déle než 30 s: rozeslání 100 pozvánek
přes Gmail API nebo 100 zápisů do skautISu ten limit překročí. HTTP požadavek jen úlohu založí a UI
ukazuje průběh.

| Úloha | Kdo zakládá | Co dělá | Dávka na běh |
|---|---|---|---|
| `send_invitations` | vedoucí | vyrenderuje a odešle zvadlo vybraným pozvaným | 25 e-mailů |
| `send_reminder` | vedoucí | připomínka těm, kdo nereagovali | 25 e-mailů |
| `send_info` | vedoucí | libovolná zpráva vybraným podle stavu | 25 e-mailů |
| `send_email` | systém po akci účastníka | jeden automatický e-mail (potvrzení, odmítnutí, změna) | 1 e-mail |
| `send_lock_notice` | plánovač | upozornění na zámek úprav | 25 e-mailů |
| `sync_participants` | vedoucí | propsání účastníků do skautISu | 20 účastníků |

Každý odchozí e-mail tak odesílá worker fronty; je jen jedna cesta pro Gmail OAuth, opakování a log.
Anonymizace není úloha uživatele a běží jako denní konzolový příkaz z plánovače.

Fronta:

- Tabulka `task`: typ, akce, parametry (které přihlášky, jaká šablona, text zprávy), stav
  `queued → running → done | failed`, počet pokusů, průběh (`progress_done` / `progress_total`), chyba,
  kdo a kdy založil, kdy začala a skončila.
- Zpracování: `bin/console tasks:run` spouští plánovač (`contributte/scheduler`, cron každou minutu volá
  `scheduler:run`, stejně jako v h.skautingu). Worker bere úlohy FIFO; převzetí je atomické
  (`UPDATE task SET state = 'running' … WHERE id = ? AND state = 'queued'`), takže souběh dvou workerů nevadí.
- Worker běží nejvýš 50 s a potom skončí. Úloha zpracovává **dávky** a ukládá průběh; další minuta pokračuje,
  kde předchozí skončila. Zpracované položky se neopakují: u e-mailů existuje řádek `email_message`
  pro danou úlohu a přihlášku, u účastníků je vyplněné `skautis_participant_id`.
- Selhání: chyba jedné položky se zapíše k ní (`email_message.error`, `registration.skautis_error`) a pokračuje
  se dál; úloha končí `failed` až po třech neúspěšných pokusech o celou dávku (typicky neplatný OAuth token
  nebo výpadek skautISu). Chyba je vidět v UI a vedoucí může úlohu spustit znovu.
- UI: po založení úlohy stránka zobrazí panel s průběhem (naja, obnovení každé 3 s) a po dokončení výsledek
  (počty, seznam chyb). Do dokončení úlohy nejde stejnou akci pro stejnou akci spustit podruhé.
- Žádný Messenger, RabbitMQ ani Redis. Jedna tabulka a jeden konzolový příkaz.
- Hosting musí umožnit cron každou minutu (plánovač h.skautingu na stejném hostingu funguje). Ověří se ve fázi 7.

## 13. Datový model

Schéma je ve **třetí normální formě**: každá tabulka popisuje jednu věc, žádné opakující se skupiny ani
seznamy v jednom sloupci, žádné odvozené sloupce (např. „je člen“ plyne z vyplněného `skautis_person_id`).
Jediná výjimka jsou **neprůhledné dokumenty**, které se nikdy nefiltrují ani nespojují: parametry úlohy,
diff v logu přihlášky a diagnostika hlášení chyby (stejně jako v h.skautingu). Ty jsou v JSON sloupci.

Tabulky (Doctrine entity s atributy, migrace v `migrations/`, cizí klíče všude, kde je vazba):

| Tabulka | Sloupce |
|---|---|
| `event` | `id`, `unit_id`, `name`, `slug`, `skautis_type_id`, `skautis_scope_id`, `is_camp`, `date_from`, `date_to`, `place`, `description`, `registration_deadline`, `edit_lock_days`, `capacity`, `price`, `contact_name`, `contact_email`, `contact_phone`, `state`, `skautis_event_id`, `skautis_camp_id`, `web_token_hash`, `oauth_id` → `google_oauth`, `created_by_user_id`, `created_at`, `updated_at`; unikátní `(unit_id, slug)` |
| `event_field` | `id`, `event_id`, `type` (`text` / `boolean` / `choice` / `multichoice`), `label`, `help`, `required`, `position`, `hidden` |
| `event_field_option` | `id`, `field_id`, `label`, `position`; unikátní `(field_id, position)`; jen pro `choice` a `multichoice` |
| `event_content` | `id`, `event_id`, `type` (`text` / `file` / `link`), `title`, `position` |
| `event_content_text` | `content_id` (PK → `event_content`), `body` |
| `event_content_file` | `content_id` (PK), `path`, `original_name`, `mime_type`, `size` |
| `event_content_link` | `content_id` (PK), `url` |
| `event_email` | `id`, `event_id`, `type`, `enabled`, `subject`, `body`; unikátní `(event_id, type)` |
| `unit_email_template` | `id`, `unit_id`, `type`, `subject`, `body`; unikátní `(unit_id, type)` |
| `unit_settings` | `unit_id` (PK), `agreement_text`, `default_oauth_id` → `google_oauth`, `default_edit_lock_days` |
| `registration` | `id`, `event_id`, `skautis_person_id` (prázdné = nečlen), `first_name`, `last_name`, `nick_name`, `sex`, `birth_date`, `street`, `city`, `postcode`, `contact_email`, `phone`, `note`, `state`, `decline_reason`, `token_hash`, `recipients_resolved_at`, `seen_at`, `responded_at`, `cancelled_at`, `failed_verifications`, `blocked_until`, `skautis_participant_id`, `skautis_error`, `anonymized_at`, `created_at`, `updated_at`; unikátní `token_hash`, unikátní `(event_id, skautis_person_id)` |
| `registration_recipient` | `id`, `registration_id`, `email`, `name`, `source` (`parent` / `person` / `manual`); unikátní `(registration_id, email)`; kam odešla pozvánka |
| `registration_text_value` | `registration_id`, `field_id`, `value`; PK `(registration_id, field_id)` |
| `registration_boolean_value` | `registration_id`, `field_id`, `value`; PK `(registration_id, field_id)` |
| `registration_option_value` | `registration_id`, `option_id` → `event_field_option`; PK `(registration_id, option_id)`; radio 1 řádek, checklist N řádků |
| `registration_log` | `id`, `registration_id`, `at`, `actor_type` (`participant` / `user` / `system`), `actor_user_id`, `message`, `changes` (JSON, neprůhledný) |
| `email_message` | `id`, `task_id`, `event_id`, `registration_id`, `type`, `to_email`, `to_name`, `subject`, `body`, `oauth_id`, `state` (`sent` / `failed`), `sent_at`, `error`; log každého odchozího e-mailu, píše ho worker |
| `task` | `id`, `type`, `event_id`, `state` (`queued` / `running` / `done` / `failed`), `payload` (JSON, neprůhledný), `attempts`, `progress_done`, `progress_total`, `error`, `created_by_user_id`, `created_at`, `started_at`, `finished_at` |
| `task_registration` | `task_id`, `registration_id`; PK `(task_id, registration_id)`; které přihlášky úloha zpracovává |
| `google_oauth` | `id` (UUID), `unit_id`, `email`, `refresh_token` (šifrovaný), `updated_at`; unikátní `(unit_id, email)` |
| `system_user_role` | `id`, `user_id`, `role`, `created_at`; unikátní `(user_id, role)` |
| `technical_error_report`, `technical_error_report_reply` | shodné s h.skautingem |
| `user_preference` | `user_id` (PK), `remember_role`, `remembered_role_id` |

Sloupce s osobními údaji nečlenů (`first_name`, `last_name`, `nick_name`, `sex`, `birth_date`, `street`, `city`,
`postcode`, `contact_email`, `phone`, `note`), řádky `registration_recipient` a hodnoty polí se při anonymizaci
přepíší nebo smažou, záznam `registration` zůstane kvůli statistikám.

## 14. Osobní údaje

- Členové: systém ukládá skautIS ID, jméno, datum narození (jen pro ověření), e-maily použité k odeslání,
  hodnoty z přihlášky. Zdrojem pravdy zůstává skautIS.
- Nečlenové: jméno, pohlaví, datum narození, adresa, e-mail, telefon, hodnoty z přihlášky.
- Retence po konci akce:
  - hodnoty vlastních polí všech přihlášek a poznámky se mažou po **12 měsících**;
  - osobní údaje nečlena **propsaného do skautISu** se anonymizují po **12 měsících** (údaje žijí ve skautISu);
  - osobní údaje nečlena **nepropsaného** do skautISu se anonymizují po **24 měsících**.

  Zůstávají jen souhrnná čísla. Lhůty jsou konfigurovatelné systémem (`RETENTION_MONTHS_SYNCED`,
  `RETENTION_MONTHS_UNSYNCED`), ne jednotkou. Anonymizaci provádí konzolový příkaz spouštěný plánovačem denně.
- Přístup vedoucích k přihláškám se zapisuje do aplikačního logu s kontextem uživatele (jako v h.skautingu).
- E-maily s osobními odkazy se posílají jen na adresy z pozvánky; systém nikdy nezobrazuje osobní token
  jiného účastníka.

## 15. Mobil a PWA

Rodiče otevírají zvadlo z e-mailu na telefonu a vedoucí kontroluje přihlášené na srazu bez notebooku.
Proto:

- Každá obrazovka se navrhuje **nejdřív pro telefon** (šířka 390 px), potom pro desktop. Veřejné stránky
  (přihláška, miniweb) jsou jednosloupcové, s velkými ovládacími prvky a bez tabulek; seznamy v administraci
  se na telefonu skládají do karet.
- Akceptační testy veřejných stránek běží i v mobilním viewportu.
- **PWA** pro vedoucí přijde jako samostatná fáze po MVP: manifest, ikona, instalace na plochu, service worker
  se strategií *network first* (data jsou osobní údaje a mění se, nikdy se neservírují z cache online),
  cache jen pro skořápku aplikace, statické assety a **poslední zobrazený seznam přihlášených** akce, aby byl
  k dispozici na srazu bez signálu. Vzorem je skautIS mobil (`PwaController`, `public/sw.js`).
- Veřejné stránky pro rodiče PWA nepotřebují; zůstávají obyčejným webem.

## 16. Co se přebírá z SRS a co ne

| Přebírá se (v jednodušší podobě) | Nepřebírá se |
|---|---|
| vlastní pole formuláře (text, ano/ne, výběr, vícenásobný výběr) | pole typu soubor, datum; vazba polí na role |
| šablony e-mailů se zástupnými symboly, fronta, historie | schvalování účastníků, role účastníků, podakce |
| souhlas s podmínkami jako nastavitelný text | programy, bloky, místnosti, lektoři, vstupenky |
| export přihlášených do XLSX | platby, variabilní symboly, Fio, slevy |
| propsání účastníků do akce ve skautISu (`ParticipantGeneralInsert`) | vzdělávací akce a kurzy |
| miniweb (text, soubory, odkazy) | CMS se stránkami, viditelnost podle rolí, FAQ, novinky |

## 17. Mimo rozsah první verze

- Platby a párování plateb (h.skauting).
- Přihlášení účastníka přes skautIS účet (přehled „moje přihlášky“); v první verzi stačí osobní odkazy.
- Veřejná otevřená registrace bez pozvánky (kdokoli s odkazem se přihlásí jako nečlen).
- Oznámení správců na nástěnce (h.skauting je má; přidají se, až bude co oznamovat).
- Vícejazyčnost; UI je česky.
- SMS.

## Rozhodnutí

Rozhodnuto zadavatelem 29. 9. 2026. Otevřené body jsou označené *(ověřit)*.

| # | Téma | Rozhodnutí |
|---|---|---|
| 1 | Název | **Zvadlo**, zvadlo.skauting.cz |
| 2 | Hosting a nasazení | stejné jako h.skauting: sdílený hosting www.skauting.cz, Deployer, migrace přes HTTP `db_fix.php`, plánovač přes cron |
| 3 | skautIS AppID a dev doména | registraci a doménu zařídí zadavatel; do té doby vývoj na `moje-hospodareni.cz` s AppID h.skautingu (`rs/.env.local`) |
| 4 | Odesílání e-mailů akce | jen z Gmail účtu jednotky jako v h.skautingu, bez náhradního odesílatele; `Reply-To` je kontaktní osoba z nastavení akce; systémová pošta sendmailem (viz 8.2) |
| 5 | Tábory | zakládají se ve skautISu; Zvadlo propisuje účastníky, jakmile je tábor odevzdaný ke schválení; schválení střediskem není podmínkou *(ověřit přesný stav)* |
| 6 | Kapacita | ano, včetně čekací listiny; kapacita je nepovinná, bez ní je akce bez omezení |
| 7 | Retence | nečlen propsaný do skautISu 12 měsíců, nepropsaný 24 měsíců; nečlen vyplňuje kompletní údaje včetně pohlaví (viz 5.2, 14) |
| 8 | Licence | **AGPL-3.0-or-later** (soubor `LICENSE`) |
| 9 | GitHub | zatím `github.com/juradee/zvadlo`, včetně issues z hlášení problémů; přesun pod organizaci později (jen změna proměnných `GITHUB_ISSUES_*`) |
| 10 | Vzhled | čistý Bootstrap 5 s malým vlastním tématem, bez Tableru |
| 11 | Knihovna skautIS | jádro **`skaut/skautis` 3.1** z forku `webwingscz/Skautis` (název z větve 3.x, plně PHP 8.4+); Nette napojení jako **interní balíček `packages/skautis-nette`** (`webwingscz/skautis-nette`, MIT, bez závislostí na aplikaci), který půjde vydat jako nový balíček nebo z něj udělat SkautisNette 3.1 (viz níže a plán) |
| 12 | Frontend nástroje | **npm** (součást Node, `package-lock.json`, `npm ci`); Node **24 LTS**, přechod na 26 po 28. 10. 2026, kdy se stane Active LTS |
| 13 | Verze PHP | minimum **8.4**, kód a všechny knihovny musí běžet i na **8.5**; CI testuje obě verze; na 8.5 se přejde, jakmile to hosting dovolí |
| 14 | Mobil a PWA | všechny obrazovky mobile-first (rodiče vyplňují na telefonu); PWA pro vedoucí jako samostatná fáze po MVP (viz 15) |
| 15 | Dlouhé operace | čtení ze skautISu vždy synchronně, u většího objemu donačtení AJAXem po dávkách; do fronty v databázi jdou jen úlohy zadané uživatelem (rozeslání pozvánek, propsání účastníků), které mohou trvat déle než 30 s (viz 12) |
| 16 | Návrh databáze | třetí normální forma; JSON jen pro neprůhledné dokumenty, které se nefiltrují (viz 13) |
| 17 | API mezi systémy | systém–systém se sdíleným tokenem, mimo scope uživatele; API nikdy nevrací osobní údaje, ty tečou přes skautIS nebo přes prohlížeč uživatele, který se v druhém systému přihlásí sám (viz 9.4) |

K bodu 11: vydané verze `skautis/nette` (2.2.0 z roku 2020 i 3.0.0-alpha.3 z roku 2022) vyžadují `nette/utils`
^3.0 a `nette/utils` 3.2.x má omezení `php <8.4`, takže s nimi projekt na PHP 8.4 nejde ani nainstalovat; právě
proto je h.skauting pevně na PHP 8.3 a Nette 3.1. Jádro (`skautis/skautis`, od větve 3.x `skaut/skautis`; SOAP klient, `User`, `Config`,
`WsdlManager`, události) zadavatel forkl do `webwingscz/Skautis` a vznikne verze **3.1** pro PHP 8.4+ se
zachovaným API a namespace `Skaut\Skautis` (používá ho už SRS); Zvadlo ji bere přes composer `vcs` repozitář,
dokud nevyjde upstream. Rozšíření `skautis/nette` se nepoužije: nad jádrem přidává jen DI extension, session
adaptér, PSR-16 adaptér a Tracy panel, a jeho jediný slot pro PSR-14 dispatcher by Zvadlu bránil logovat SOAP
chyby v produkci. Napojení na Nette je proto vlastní, od začátku ve tvaru samostatného balíčku
`packages/skautis-nette` (vlastní `composer.json`, namespace `Webwings\SkautisNette`, žádná závislost na
`App\`, fixture továrna pro testy), takže půjde vydat jako nový balíček nebo z něj udělat SkautisNette 3.1.
Analýza a návrh jsou v [knihovny skautIS](skautis-knihovny-3.1.md). Rozhraní `SkautisGateway` zůstává jako
jediné místo, kde aplikace s jádrem mluví; díky fixturám na úrovni webové služby má jedinou implementaci.
