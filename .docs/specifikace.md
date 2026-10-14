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

**Zásada: jednoduché jako Google Forms.** Rodič dostane odkaz, potvrdí, vyplní a odešle; žádné
registrace, účty ani kroky navíc. „Potvrdí“ znamená u pozvaného jediné pole navíc: datum narození dítěte,
u nečlena e-mail, na který pozvánka přišla (viz 3). Tento krok zůstává, protože říká, koho rodič přihlašuje,
a chrání odkaz před přeposláním; nic dalšího se před prvním vyplněním nežádá. 95 % rodičů nemá účet ve skautISu, proto je všechno kolem skautISu
(přihlášení účastníka, párování) jen **doplněk** k osobnímu odkazu a veřejnému formuláři. Chránit je třeba
jediné: **už vyplněné údaje**. Nikdo si nesmí procházet přihlášené ani je upravovat bez ověření; první
vyplnění naopak nesmí klást překážky. Jedinou výjimkou je akce v režimu „jen přes skautIS“ (4.4).

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
```

- **slug** akce je unikátní v rámci jednotky (unikátní index `unit_id + slug`). Generuje se z názvu,
  při kolizi se přidá přípona (`-2`, `-3`). Vedoucí ho může ručně upravit, dokud nebyla rozeslána pozvánka.
- **webToken** je sdílený tajný token miniwebu (jeden na akci). Kdo ho má, vidí miniweb. Vedoucí ho může
  **obnovit** (staré odkazy přestanou platit; je nutné poslat nové). Bez tokenu miniweb vrací 404, ne 403,
  aby neprozrazoval existenci akce.
- **token** přihlášky je osobní (jeden na pozvaného na akci) a **stálý**: po celou dobu života přihlášky se
  nemění, pozvánka, připomínka i potvrzení vedou na tentýž odkaz a všechny adresy jednoho pozvaného (oba
  rodiče) dostanou stejný odkaz. Rodič se dvěma dětmi tak dostane dva e-maily s různými odkazy; stránka
  po otevření **vždy uvádí, koho se přihláška týká** (jméno účastníka, u nečlena e-mail), aby zadal
  správné datum narození. Nikdy se neposílá dál, e-mail s ním je určený jen adresátovi. Otevření přihlášky
  navíc vyžaduje **druhý údaj**, který odkaz „odemkne“:
  - člen: **datum narození** účastníka (u dítěte ho zadá rodič);
  - nečlen při **prvním vyplnění**: **e-mailová adresa, na kterou pozvánka přišla** (párovací klíč; vedoucí
    u nečlena zadává právě ten e-mail). Stránka do té doby neukazuje žádné osobní údaje, proto e-mail stačí;
  - nečlen při **úpravě už vyplněné přihlášky**: jednorázový **kód (4 číslice)** zaslaný na kontaktní e-mail
    přihlášky; platí 15 minut, po pěti špatných pokusech se odkaz zablokuje jako u data narození. Přihláška
    obsahuje osobní údaje, e-mail sám by pro jejich zobrazení nestačil.
- Tokeny mají 32 bajtů náhody (base64url). Token miniwebu se ukládá v otevřené podobě, protože ho vedoucí
  potřebuje zobrazit a poslat; jeho únik odkryje jen obsah miniwebu.
- Vedoucí si osobní odkaz může v detailu přihlášky zobrazit. Aby přitom únik databáze neprozradil funkční
  odkazy, ukládá se token **šifrovaný** klíčem z `.env` (`APP_SECRET`, `sodium_crypto_secretbox`) a vedle něj
  SHA-256 hash pro vyhledání. „Vygenerovat nový odkaz“ je výslovná akce vedoucího jen pro případ, že odkaz
  unikl; starý pak přestane platit a je nutné poslat nový.
- Po pěti neúspěšných ověřeních (datum narození, e-mail nebo kód) se odkaz na 15 minut zablokuje
  (ochrana proti hádání).

Miniweb neobsahuje přísně tajné informace, ale ani sraz dětí nemá být veřejně dohledatelný.
Proto je celý web (včetně příloh) chráněný tokenem a má `noindex`.

## 4. Akce

Akce patří **jedné jednotce** (skautIS ID). Vedoucí střediska vidí akce podřízených oddílů,
vedoucí oddílu jen svého oddílu (stejná logika jako v h.skautingu, viz [oprávnění](#7-oprávnění)).

### 4.1 Vlastnosti akce

| Pole | Popis |
|---|---|
| název, slug | viz výše |
| typ a rozsah akce | číselníky ze skautISu (`EventGeneralTypeAll`, `EventGeneralScopeAll`), stejné jako při zakládání akce v h.skautingu; použijí se při propsání; rozsah nesmí být nižší než pořádající jednotka (středisko či přístav nemá akci družinovou, družina smí pořádat i akci pro celé středisko) |
| tábor | tábor **nelze založit ručně**; „Nová akce“ nabízí vedle běžné akce volbu „Tábor ze skautISu“ se seznamem táborů jednotek, které role smí spravovat (`EventCampAll`, letošní a příští rok, už propojené tábory jsou označené). Výběr předvyplní název, termín a místo (ve Zvadle jen ke čtení, obnovují se ze skautISu) a uloží `skautis_camp_id`; vedoucí doplní jen údaje Zvadla (uzávěrka, zámek, kapacita, cena, kontakt, popis) |
| termín od–do | datum; konec je nepovinný, prázdný = jednodenní akce (od = do) |
| místo | text |
| popis | text pro miniweb a e-mail (Markdown, bez HTML) |
| uzávěrka přihlášek | datum, nepovinné; **jeden limit** pro podání, změnu i zrušení přihlášky účastníkem (do tohoto dne včetně), prázdné = do konce akce; dřív lze přihlašování ukončit uzavřením akce (rozhodnutí 36) |
| kapacita | volitelná (prázdná = bez omezení); volba **čekací listina** (výchozí zapnutá): po naplnění se další přihlásí jako náhradníci, bez ní plná akce další přihlášky odmítne; viz 5.3 |
| cena | volitelná, informativní (zobrazí se v e-mailu a na webu; platby systém neřeší) |
| kontaktní osoba | jméno, e-mail, telefon vedoucího pro dotazy; výchozí z přihlášeného vedoucího; e-mail je `Reply-To` všech e-mailů akce |
| stav | koncept → otevřená → uzavřená → archivovaná |
| veřejné přihlašování | `vypnuto` (jen pozvaní přes osobní odkaz) / `veřejný odkaz` (kdokoli s odkazem, bez ověření) / `jen přes skautIS` (kdokoli s účtem ve skautISu, i z jiné jednotky; bez skautISu se přihlásit nejde); viz 4.4 a 4.6 |
| skautIS akce | ID akce ve skautISu po propsání, včetně odkazu |
| odesílací účet | Gmail účet jednotky, ze kterého odcházejí e-maily (účty jednotky a účty nadřízených jednotek, které je podřízeným povolily, viz 8.2); bez něj nelze akci otevřít |

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

Každé pole má popisek, nápovědu, příznak *povinné* a pořadí; u pole ano/ne znamená povinné, že musí být
zaškrtnuté (potvrzení), u ostatních typů, že účastník musí odpovědět. Pole se spravují z detailu akce tlačítkem
„Vlastní pole“ (`/akce/<id>/pole`): přidání, úprava, pořadí, skrytí, smazání. Pole lze upravovat, dokud nemá žádná přihláška
vyplněnou hodnotu; potom lze jen přidat nové pole nebo pole skrýt. Hodnoty se ukládají v tabulce
v normalizovaných tabulkách podle typu (`registration_text_value`, `registration_boolean_value`,
`registration_option_value`; možnosti výběru jsou řádky v `event_field_option`), aby šly exportovat a filtrovat.
Na rozdíl od SRS nejsou pole vázaná na role ani na uživatele, ale na akci a přihlášku.

### 4.3 Pozvaní

Postup je stejný jako přidání účastníků k platbám v h.skautingu: vedoucí nejdřív účastníky **do akce přidá**
a u každého člena při tom **vybere e-maily**, potom jim **pošle pozvánky**. Mezi tím může adresy upravit
v seznamu přihlášek.

**1. Přidání účastníků**

- **ze skautISu** – tabulka členů jednotky a podřízených jednotek (`PersonAll` s `OnlyDirectMember=false`),
  hledání, filtr podle jednotky, hromadný výběr zaškrtávátkem. Už přidaní jsou označení a nejdou vybrat
  podruhé. Systém uloží skautIS ID osoby, jméno a datum narození (pro ověření).
- **ručně** – nečlen: vedoucí zadá **e-mail**, na který má pozvánka přijít (jméno je nepovinné). Tento e-mail
  je zároveň párovací klíč, kterým si nečlen přihlášku odemkne (viz 3). Dokud přihlášku nevyplní, systém ho
  zobrazuje pod e-mailem. Typicky jde o jednotlivce; nečleni na akcích pro veřejnost se hlásí sami přes
  **veřejný odkaz** (viz 4.4), takže pozvánka nečlenovi je spíš výjimka.

Přidaný účastník je ve stavu **pozván** a čeká na vyplnění; dokud pozvánka neodešla, seznam ho zobrazuje
jako „pozván – neodesláno“.

**2. Adresy** (jako výběr e-mailů při hromadném přidání plateb v h.skautingu)

E-maily se vybírají **při přidání**. Stránka přidání ukáže u každého člena pod jménem všechny jeho adresy ze
skautISu (`PersonContactAll` typy `email*`, u člena do 18 let i `PersonParentAll`) jako zaškrtávátka s typem
(matka, otec, hlavní e-mail, další e-mail). Adresy se donačítají AJAX požadavky po dávkách 10 osob (viz 12)
a cachují se hodinu jako seznam členů. Výchozí výběr:

1. člen do 18 let: adresy **rodičů** (`mother`, `father`); když skautIS žádnou nevrátí (chybějící oprávnění
   nebo rodič bez e-mailu), hlavní e-mail dítěte (`main`);
2. člen nad 18 let: vlastní hlavní e-mail (`main`);
3. nečlen: e-mail zadaný vedoucím (`manual`).

Nad tabulkou jsou zaškrtávátka typů (Matka / Otec / Hlavní / Další), která zaškrtnou nebo odškrtnou daný typ
u všech členů najednou, stejně jako v h.skautingu; potom lze výběr měnit i u jednotlivých členů (jednomu otce,
druhému matku, ostatním hlavní e-mail). Člen, jehož adresy se do odeslání nestihly načíst, dostane výchozí
výběr. Každá nalezená adresa se uloží jako řádek `registration_recipient` s typem a příznakem **vybraná**,
takže ji lze později zapnout.

**Kam chodí pošta.** Členovi chodí **všechny** e-maily k akci (pozvánka, připomínka, potvrzení, změna, zpráva
účastníkům, zamítnutí) na všechny vybrané adresy, tedy klidně matce, otci i členovi zároveň, a všechny
dostanou **tentýž** osobní odkaz. Přihláška člena proto kontaktní e-mail nemá, jen ukazuje, kam pošta chodí.
Pro nečlena platí jedna adresa: pozvánka jde na e-mail zadaný vedoucím, další zprávy na kontaktní e-mail
z přihlášky. Totéž platí pro člena přihlášeného veřejným odkazem nebo přes skautIS a pro člena bez vybrané
adresy.

**Seznam přihlášek** ukazuje u každého řádku adresy, na které pošta chodí, a vedoucí je upraví přímo v řádku
(zapnout nebo vypnout nalezenou adresu, přidat další ručně), jako sloupec e-mailů u plateb v h.skautingu.
Hromadná úprava adres v seznamu není. Pozvaní bez vybrané adresy jsou zvýraznění; pozvánku jim nelze odeslat.

**3. Odeslání pozvánek**

Tlačítko „Odeslat pozvánky“ založí úlohu `send_invitations` (viz 12) pro vybrané pozvané (výchozí filtr:
pozván – neodesláno). Každému odejde šablona `invitation` na všechny jeho vybrané adresy; u přihlášky se
uloží čas odeslání (`invited_at`). Přidávat účastníky a posílat pozvánky lze opakovaně, dokud akce není
archivovaná (dodatečné pozvání).

**Připomínka**

Pozvaným, kteří pozvánku dostali a nereagovali (stav pozván s odeslanou pozvánkou), může vedoucí poslat
**připomínku**: odejde **stejný e-mail jako pozvánka** (šablona `invitation`, stejný osobní odkaz), jen předmět
má na konci „ - připomínka“. Připomínku lze poslat nejvýš jednou denně na jednu přihlášku a jen dokud je
přihlašování otevřené (jako upomínky plateb v h.skautingu). Kdo odkaz už otevřel (stav zobrazeno), připomínku
nedostává.

E-mail ze skautISu se použije jen k odeslání; systém ho ukládá spolu s pozvánkou, aby bylo dohledatelné, kam
zvadlo odešlo. Nečlen si v přihlášce může zadat jiný kontaktní e-mail, na který se pak posílají další zprávy.

### 4.4 Veřejné přihlašování

Akce pro veřejnost (den otevřených dveří, akce pro rodiče a kamarády) má zapnuté **veřejné přihlašování**:

- Vedoucí ho zapne v nastavení akce volbou `veřejný odkaz`, nebo `jen přes skautIS` (viz 4.6; veřejná stránka
  pak nabízí pouze přihlášení přes skautIS a formulář bez něj nepustí). Veřejný odkaz je
  `https://zvadlo.skauting.cz/{unitId}/{slug}/prihlasit?t={webToken}`, tedy stejný token jako miniweb, aby akce
  měla jeden sdílený odkaz na plakát i do e-mailu; miniweb na něj odkazuje tlačítkem „Přihlásit se“.
- Kdokoli s odkazem vyplní přihlášku **bez pozvánky a bez ověření**, ve dvou krocích: nejdřív jen jméno,
  příjmení a datum narození účastníka (podle nich systém pozná člena, viz 4.5), potom u **člena jen kontakt,
  doplňující otázky a souhlas** (pohlaví, adresu a další údaje má skautIS, vedoucí je vidí v detailu
  a exportu) a u nečlena navíc údaje nutné pro evidenci ve skautISu (5.2). Kontakt, adresa a příjmení se
  po odeslání pamatují v prohlížeči, takže rodič dalšího dítěte vyplní jen jméno a datum narození
  („Přihlásit další dítě“). Vznikne přihláška ve stavu přihlášen (nebo čekací listina) s původem `public`;
  potvrzovací e-mail obsahuje osobní odkaz. Pozdější úprava jde přes osobní odkaz a kód (viz 3).
- Platí uzávěrka, kapacita i zámek úprav; po uzávěrce nebo u neotevřené akce odkaz vysvětlí, že přihlašování
  neběží. Vypnutí veřejného přihlašování odkaz zavře, hotové přihlášky zůstanou.
- Ochrana proti robotům: skryté pole (honeypot) a nejvýš 10 přihlášek z jedné IP adresy za hodinu. Duplicitu
  (stejný e-mail a jméno v jedné akci) formulář odmítne s odkazem na kontakt vedoucího. Nesmyslné přihlášky
  vedoucí smaže.
- Člen, který se přihlásí veřejným odkazem, se má do systému dostat už **spárovaný** s osobou ve skautISu
  (viz 4.5), aby se při propsání nezaložila duplicitní osoba.

### 4.5 Párování člena při veřejném přihlášení

Schváleno zadavatelem 30. 9. 2026 (rozhodnutí 25).

Omezení: veřejnou stránku otevírá rodič bez přihlášení, takže Zvadlo v tu chvíli **nemá token do skautISu**
a nemůže se ho na nic ptát. Párovat lze jen proti údajům, které si Zvadlo uložilo dřív, když měl přístup vedoucí.

Řešení (otisky členů + potvrzení vedoucím; přihlášení přes skautIS viz 4.6):

1. **Otisky členů.** Když vedoucí založí akci s veřejným přihlašováním nebo ho u akce zapne, Zvadlo z
   seznamu členů jednotky a podjednotek (`PersonAll`, jedno volání) uloží do tabulky `unit_member_fingerprint`
   pro každou osobu jen `person_id` a dva otisky: HMAC-SHA256 (klíč z `APP_SECRET`) z normalizovaného
   „příjmení|jméno|datum narození“ a z „příjmení|datum narození“. Jména ani data narození nečlenů akce se tedy
   v čitelné podobě neukládají a z uniklé tabulky se bez klíče nedají hádat. Členové se mění zřídka, proto
   se otisky jinak automaticky neobnovují; vedoucí je může kdykoli obnovit tlačítkem v detailu akce. Smažou
   se, když jednotka nemá žádnou akci s veřejným přihlašováním. Otisky patří jednotce, ne akci: všechny akce
   jednotky sdílejí jednu sadu. Člen oddílu je uložený dvakrát, když má veřejnou akci oddíl i středisko
   (každá jednotka má svůj strom); páruje se vždy jen proti jednotce akce, takže to výsledek neovlivní.
   Normalizace: malá písmena, bez diakritiky, spojovník a apostrof jako mezera, více mezer jako jedna.
2. **Vyplnění.** Účastník členství neuvádí, shodu určí systém. Po odeslání kroku 1 Zvadlo spočítá otisk ze zadaného jména a data narození (bez diakritiky, malými písmeny, zkusí i prohozené jméno
   a příjmení) a porovná ho s otisky jednotky akce:
   - **přesná shoda a v akci pro tuto osobu ještě žádná přihláška není** → přihláška vznikne rovnou spárovaná
     (`skautis_person_id`), účastník je člen; propsání do skautISu použije `ID_Person`;
   - **přesná shoda, ale přihláška pro tuto osobu už existuje** (byl pozván osobně) → formulář odmítne
     neutrálně: „Přihlášku pro tuto osobu už evidujeme, použijte odkaz z e-mailu nebo napište vedoucímu.“
     Duplicita ani obcházení osobního odkazu tak nevzniknou;
   - **shoda jen příjmení a data narození** (překlep, přezdívka místo jména) nebo **více členů se stejným jménem
     a datem narození** → přihláška vznikne jako nečlen s příznakem „možná shoda“ a vedoucí ji potvrdí jedním klikem;
   - **žádná shoda** → nečlen; vyplní i údaje, které skautIS potřebuje k založení osoby.
   Formulář nikdy neprozrazuje, zda je zadaná osoba členem; liší se jen odmítnutí duplicity.
3. **Potvrzení vedoucím.** V seznamu přihlášek má nespárovaný nečlen z veřejného odkazu tlačítko „Spárovat“:
   Zvadlo (teď už s tokenem vedoucího) nabídne osoby jednotky se stejným datem narození a podobným jménem,
   vedoucí vybere a potvrdí. Tím se řeší překlepy i členové, které systém nepoznal.
4. Veřejná stránka i miniweb členy vedou k osobnímu odkazu: „Členové jednotky dostali osobní odkaz e-mailem,
   použijte ho.“

Proč jméno a datum narození: je to totéž, čím se ověřuje osobní odkaz, rodič to zná a v rámci jedné jednotky
je dvojice prakticky jednoznačná. Riziko falešné přihlášky za cizí dítě je stejné jako u každého veřejného
formuláře; před propsáním do skautISu vedoucí přihlášky z veřejného odkazu vidí a potvrzuje rozdíl (9.2).

Zvažované alternativy:

- **Jen ruční párování vedoucím** (bod 3 samostatně): nejjednodušší, ale člen se do systému dostane jako nečlen
  a spárování závisí na pozornosti vedoucího.
- **Párování podle e-mailu**: otisky by musely obsahovat e-maily rodičů a členů (`PersonParentAll`,
  `PersonContactAll` pro každého člena, stovky volání) a rodinný e-mail neurčí, o které dítě jde. Nevhodné.
- **Přihlášení rodiče přes skautIS** na veřejné stránce: nejprůkaznější, ale mnoho rodičů účet nemá a vazbu
  rodič → dítě by skautIS musel vracet; zůstává mimo první verzi (17).
- **Členové jen přes osobní odkaz, veřejný odkaz jen pro nečleny**: jednoduché pravidlo, ale nevymahatelné;
  lidé kliknou na odkaz, který mají. Zůstává jako doporučení v textu stránky (bod 4), ne jako řešení.
- **Člen mimo strom jednotky akce** (dítě z jiného střediska) spárovat nelze; při propsání vznikne v skautISu
  nová osoba stejně jako u e-přihlášky. *(ověřit, zda skautIS při zápisu duplicitní osoby nabízí spojení)*

### 4.6 Přihlášení účastníka přes skautIS

Starší členové mají vlastní účet ve skautISu a rodiče, kteří jsou ve skautISu vedení jako zákonní zástupci,
často také. Veřejná stránka přihlášky a miniweb proto nabízejí tlačítko **„Přihlásit se přes skautIS“**;
přihlášení nahrazuje otisky i ověření a je nejprůkaznější způsob párování.

Proveditelnost ověřena 30. 9. 2026 (rozhodnutí 27); implementováno 30. 9. 2026 proti fixturám, ověření na
testovacím skautISu je na zadavateli (postup a účty v [skautis-testovaci-ucty.md](skautis-testovaci-ucty.md)):

- **Návrat na správnou stránku.** Přihlašovací adresa skautISu nese parametr `ReturnUrl` (jádro
  `Skaut\Skautis\Skautis::getLoginUrl($backlink)`), skautIS ho po přihlášení připojí k návratové adrese
  registrované u AppID a Zvadlo ho dostane zpět v `Auth:skautIS` jako `?ReturnUrl=`. Stejně to v produkci
  používá h.skauting pro návrat na původní stránku. Zvadlo do něj vloží krátký, ověřitelný klíč
  (`p:{unitId}:{slug}:{webToken}`; token miniwebu musí být v klíči, protože skautIS posílá POST z cizí domény
  a session cookie se `SameSite=Lax` s ním nedorazí), po návratu ho zkontroluje proti pevnému vzoru a pokračuje
  na `/{unitId}/{slug}/prihlasit/skautis`; nikdy nepřesměruje na cizí adresu. Jedna registrovaná návratová
  adresa AppID stačí pro vedoucí i účastníky.
- **Koho účet přihlašuje.** Po návratu Zvadlo zavolá `UserManagement.UserDetail` (osoba účtu) a
  `OrganizationUnit.PersonParentAllPersonChildren` s `ID_Person` účtu; skautIS vrací **děti, u kterých je
  přihlášený vedený jako zákonný zástupce** (`ID_Person`, jméno, datum narození, pohlaví, e-mail, telefon).
  Metoda existuje ve WSDL testovacího skautISu (`test-is.skaut.cz`, služba OrganizationUnit). Stránka
  „Koho přihlašujete?“ nabídne osobu účtu a její děti **bez ohledu na jednotku**: na akci se tak korektně
  přihlásí i člen jiné jednotky (okresní nebo krajská akce, kamarád z jiného střediska). U přihlášky se uloží
  jen to, co skautIS o vybrané osobě vrátí a co účastník vyplní; vedoucí vidí štítek „mimo jednotku“.
  Rodič bez vazby na dítě ve skautISu žádné dítě neuvidí a použije běžný formulář; totéž předpokládá už
  rozesílání pozvánek rodičům (`PersonParentAll`).
- **Zápis účastníka z jiné jednotky do skautISu.** `Events.ParticipantGeneralInsert` bere `ID_Person` bez
  vazby na jednotku a `ParticipantGeneralAll` u účastníka vrací jeho `ID_Unit` a `Unit`, takže skautIS
  s účastníky z cizích jednotek počítá (h.skauting v UI nabízí jen členy vlastní jednotky, ale volá tutéž
  metodu). Zda role vedoucího smí zapsat osobu, kterou nesmí číst, se musí vyzkoušet na testovacím skautISu
  *(ověřit ve fázi 5)*. Když skautIS zápis odmítne, Zvadlo chybu zobrazí u přihlášky a vedoucí účastníka přidá
  ve skautISu ručně; nikdy se pro známou osobu ze skautISu nezakládá nová (duplicitní) osoba.
- **Režim „jen přes skautIS“** (4.1): akce pro členy napříč jednotkami. Veřejná stránka nabízí pouze tlačítko
  „Přihlásit se přes skautIS“; po přihlášení se kdokoli s účtem zapíše sám nebo zapíše své dítě. Osobní pozvánky
  členům fungují i v tomto režimu.
- **Vyplnění.** Přihláška vznikne spárovaná (`skautis_person_id`, původ `skautis`) s předvyplněným jménem,
  datem narození, pohlavím a kontakty ze skautISu; účastník doplní jen e-mail, telefon, vlastní pole a souhlas
  a uložením je přihlášen. Když pro vybranou osobu už přihláška existuje (byl pozván osobně), vyplní se
  **ta** – tady je to bezpečné, identitu prokázal skautIS.
- **Oddělení od administrace.** Token ze skautISu se použije jen pro tato volání v rámci jednoho požadavku
  a nevzniká identita vedoucího (`SkautisParticipantLogin`: `UserDetail`, `PersonDetail`,
  `PersonParentAllPersonChildren`, potom `resetLoginData`); účastník dostane jen krátkou session (osoby,
  1 hodina) pro dokončení a případnou úpravu přihlášky. Členská role ve skautISu tak nikdy neotevře
  administraci Zvadla. Vedoucí, který si v témže prohlížeči vyzkouší přihlášení jako účastník, se musí do
  administrace přihlásit znovu (jeho token do skautISu se tím zahodí).
- **Podmínky k ověření na testovacím skautISu** *(ověřit)*: AppID musí mít oprávnění k `UserDetail`,
  `PersonDetail` a `PersonParentAllPersonChildren` i pro role běžného člena a rodiče (v UI skautISu tato data
  člen i rodič vidí, takže by mělo být udělitelné); přihlašovací stránka skautISu si sama vyřídí heslo,
  dvoufázové ověření (`LoginUpdateTwoFactor`) i volbu role. Zadavatel dodá testovací účty: člen 15+ a rodič
  s vazbou na dítě.
- Přihlášení přes skautIS může později nahradit kód pro úpravu přihlášky (3) a datum narození u členů, kteří
  účet mají. Mimo režim „jen přes skautIS“ je to vždy jen doplněk: většina rodičů účet nemá a osobní odkaz
  s veřejným formulářem musí stačit (zásada v kapitole 1).

## 5. Přihláška

### 5.1 Stavy

```
pozván (neodesláno) ──(odeslání pozvánky)──▶ pozván ──(otevře odkaz)──▶ zobrazeno ──(ověří se, vyplní)──▶ přihlášen ──▶ zrušeno
                                                                             └──────▶ odmítl
                                                                             přihlášen ──(kapacita plná)──▶ čekací listina ──▶ přihlášen
veřejný odkaz (4.4) ──(vyplní)──▶ přihlášen | čekací listina
kterýkoli stav ──(vedoucí: Zamítnout)──▶ zamítnuto
```

| Stav | Význam |
|---|---|
| pozván | přidán do akce a čeká na vyplnění; podle `invited_at` se rozlišuje „neodesláno“ a „odesláno“ (odkaz zatím neotevřen) |
| zobrazeno | osobní odkaz byl otevřen (i bez úspěšného ověření), přihláška neodeslána |
| přihlášen | účast potvrzena, formulář vyplněn |
| odmítl | rodič/člen zvadlo viděl a účast odmítl (může uvést důvod) |
| čekací listina | přihlášen po naplnění kapacity (jen s povolenou čekací listinou); pořadí podle času zařazení (`waitlisted_at`) |
| zrušeno | účast zrušena po přihlášení (účastníkem do uzávěrky, nebo vedoucím kdykoli) |
| zamítnuto | vedoucí přihlášku odmítl (na akci nepatří, typicky z veřejného odkazu); osobní údaje se **ihned anonymizují**, přihláška zmizí z počtů, exportů, e-mailů, propsání do skautISu i z API pro h.skauting a zůstává jen ve filtru „zamítnuté“ jako záznam. Zůstane jen `skautis_person_id` nebo otisk e-mailu, aby se totéž nemohlo přihlásit veřejným odkazem znovu (odmítne se neutrálně jako duplicita). Účastníkovi automaticky odejde e-mail `rejected` (8.1); vyrenderuje se a zařadí do fronty **před** anonymizací, adresu si nese záznam `email_message`. Pokud už byl účastník propsaný, nabídne se jeho odebrání z akce ve skautISu. |

### Kapacita a čekací listina

- Bez kapacity se přijme každý. S kapacitou se přihláška nad počet přihlášených zařadí na **čekací listinu**,
  je-li u akce povolená; jinak ji Zvadlo odmítne hláškou, že je akce plná (odmítnout účast jde vždy).
- **Účastník se odhlásí sám** (zrušení nebo změna na „nezúčastní se“): uvolněné místo automaticky dostane
  první náhradník podle pořadí na čekací listině a přijde mu e-mail `promoted` (upravitelná šablona akce).
- **Vedoucí zruší, odmítne nebo zamítne účastníka:** nikdo se neposouvá sám. Vedoucí dostane upozornění, že se
  uvolnilo místo a na čekací listině někdo je; v přehledu přihlášek ho jedním tlačítkem přijme, jinak místo
  zůstane volné a může ho obsadit nová přihláška.
- **Rozhoduje odeslání formuláře.** Volná místa se počítají v okamžiku odeslání pod zámkem řádku akce
  (`SELECT … FOR UPDATE` v transakci), ne při otevření ani vyplňování. Souběžná odeslání se seřadí: kdo odešle
  dřív, dostane místo, další se podle volby akce zapíše jako náhradník (a vidí to hned po odeslání), nebo dostane
  ve formuláři hlášku, že je akce plně obsazená, a vyplněné údaje mu zůstanou.
- **Přijetí náhradníka vedoucím** jde, dokud je volné místo; u plné akce jen tlačítkem „Přijmout nad kapacitu“
  s potvrzovacím dotazem (overbooking), v historii přihlášky se zapíše, že šlo o přijetí nad kapacitu.

Vedoucí vidí u každého pozvaného stav, čas odeslání pozvánky, čas otevření odkazu a čas odpovědi. Přehled akce
ukazuje počty podle stavů a seznam těch, kdo **nereagovali**, s tlačítky „Odeslat pozvánky“ (neodeslané)
a „Poslat připomínku“ (odeslané bez odpovědi).

### 5.2 Formulář

1. **Ověření** – stránka uvádí název akce a **koho se přihláška týká** (jméno účastníka, u nečlena e-mail),
   aby rodič se dvěma pozvanými dětmi věděl, koho přihlašuje. Člen: datum narození účastníka. Nečlen: při prvním
   vyplnění e-mail, na který pozvánka přišla; při úpravě už vyplněné přihlášky kód zaslaný na kontaktní e-mail
   (viz 3). Veřejné přihlášení (4.4) tento krok nemá.
2. **Účast** – „Zúčastní se“ / „Nezúčastní se“. Při odmítnutí volitelný důvod a konec.
3. **Údaje** – u člena jméno a příjmení jen ke čtení (ze skautISu), předvyplněný e-mail a telefon k úpravě.
   U nečlena vše, co potřebuje skautIS pro založení osoby: jméno, příjmení, přezdívka (nepovinná),
   **pohlaví (povinné**, i když dokumentace skautISu tvrdí opak), datum narození, ulice, město, PSČ,
   e-mail, telefon. Poznámka pro vedoucí.
4. **Vlastní pole** akce.
5. **Souhlas** – potvrzení, že údaje jsou pravdivé a že zákonný zástupce souhlasí s účastí (text je nastavitelný na úrovni jednotky).

Po odeslání přijde potvrzovací e-mail s odkazem na úpravu přihlášky (stejný osobní odkaz) a odkazem na
miniweb. Přihlášku lze podat, měnit a rušit do **uzávěrky** (bez ní do konce akce); potom formulář zobrazí jen souhrn a kontakt na vedoucího.

### 5.3 Správa přihlášek vedoucím

- Seznam pozvaných s filtrem podle stavu, hledáním, hromadným výběrem pro e-mail.
- Detail přihlášky včetně historie změn (kdo, kdy, co) a odeslaných e-mailů.
- Ruční změna stavu (zrušit, přesunout z čekací listiny, označit odmítnutí po telefonu, **zamítnout**
  přihlášku někoho, kdo na akci nepatří).
- Spárování nečlena z veřejného odkazu s osobou ve skautISu (viz 4.5).
- Export do XLSX/CSV (standardní pole + vlastní pole).
- Přidání pozvaného i po rozeslání (dodatečná pozvánka), odeslání pozvánek neodeslaným a připomínka
  odeslaným bez odpovědi (viz 4.3).
- Zobrazení osobního odkazu pozvaného (pro předání jinak než e-mailem) a jeho výslovná obnova, když unikl.

## 6. Miniweb akce

Miniweb je jednoduchá stránka akce dostupná přes `webToken`. Obsah se skládá z bloků, které vedoucí řadí:

| Blok | Obsah |
|---|---|
| text | Markdown (nadpisy, odstavce, seznamy, odkazy) |
| soubor | příloha PDF nebo JPG/PNG (zvadlo, seznam věcí); do 10 MB, uložená mimo web root, servírovaná přes presenter s kontrolou tokenu |
| odkaz | externí odkaz s popiskem (fotoalbum Immich / Google Photos, mapa srazu). Data zůstávají mimo systém. |

Hlavička webu automaticky zobrazuje název, termín, místo, cenu, kontaktní osobu a popis akce (Markdown).
Pod ní je rámeček přihlášky: u akce s veřejným přihlašováním tlačítko „Přihlásit se“ na veřejný formulář
(stejný token), jinak pokyn použít osobní odkaz z e-mailu; osobní tokeny se na webu nezobrazují. Web
konceptu (akce ve stavu koncept) se nezobrazuje. Soubory se servírují přes presenter s kontrolou tokenu
(`/{unitId}/{slug}/soubor/{id}?t=…`), bez tokenu 404. Obnovení tokenu zneplatní odkaz na web, soubory
i veřejnou přihlášku; proto je to výslovná akce s potvrzením.

Oproti SRS (17 typů bloků, stránky, role) je miniweb záměrně jednostránkový a bez rolí.

## 7. Oprávnění

Systém **respektuje oprávnění ve skautISu** a přebírá model z h.skautingu ve třech vrstvách.

### 7.1 Přihlášení

- Výhradně přes skautIS (jádro `skaut/skautis` 3.1 a rozšíření `skaut/skautis-nette` 3.1, viz 9.1). Uživatel je přesměrován na `{skautIS}/Login/?appid=…`, skautIS pošle
  POST na registrovanou návratovou adresu s poli `skautIS_Token`, `skautIS_IDRole`, `skautIS_IDUnit`,
  `skautIS_DateLogout`. Identita v Nette má ID = skautIS ID uživatele (`UserDetail`), role = `UserRoleAll`,
  data = aktuální role a přístupové jednotky.
- Token platí 30 minut; každé otevření stránky přihlášení prodlužuje (`LoginUpdateRefresh`), jako v h.skautingu.
  Při vypršení systém uživatele odhlásí, uloží rozpracovaný požadavek a po opětovném přihlášení ho obnoví.
- Přepínání role a jednotky v horní liště (`LoginUpdate`), s možností zapamatovat si poslední roli.
- Odhlášení proběhne i ve skautISu (`Login/LogOut.aspx`), jinak by se další návštěva přihlásila sama.

**Prodlužování přihlášení a role Zvadla** (převzato z h.skautingu): každá stránka, kterou přihlášený vedoucí
otevře, prodlouží přihlášení do skautISu (`LoginUpdateRefresh`) a otevřená záložka posílá každých 20 minut ping na
`/session/keep-alive`, koordinovaně mezi záložkami, takže rozdělaná práce nevyprší. AJAX požadavky na pozadí
(sledování průběhu úloh, dávky, načítání adres) přihlášení neprodlužují, jen ověří, že platí, a po vypršení
dostanou 401; otevřené okno tak drží přihlášení jen ping, a jen když ho vedoucí nevypnul; lišta ukazuje zbývající
minuty. Ping lze v přehledu vypnout. Role (jednotka) je volba Zvadla: při přihlášení se použije role, se kterou
uživatel ve Zvadle pracoval naposledy (`user_preference.remembered_role_id`, lze vypnout), ne role, kterou má
zrovna aktivní skautIS nebo h.skauting. Přístup k jednotkám je uložený v identitě a mění se jen přepnutím role
ve Zvadle. **Token do skautISu má vždy jen přihlášený uživatel; CLI, fronta ani plánovač ho nemají.**

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
  skautIS ID uživatelů v proměnné prostředí `ADMIN_USER_IDS` (čárkami oddělený) pro nouzový přístup s rolí
  `admin` bez ohledu na databázi. Shodné s h.skautingem (`adminAllowedUserIds`).
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
- Jednotka si výchozí šablony upraví v nastavení jednotky; změna se projeví u nově zakládaných akcí. Vlastní text
  je označený a tlačítkem „Obnovit výchozí text“ se vrátí k systémovému (řádek `unit_email_template` se smaže);
  text uložený beze změny proti systémovému se jako vlastní neukládá.
- Zástupné symboly jsou ve tvaru `%nazev%` a nahrazují se prostým dosazením (bez HTML). Vedle editoru je
  nápověda se seznamem symbolů, jako v h.skautingu.

| Typ | Kdy se posílá | Komu |
|---|---|---|
| `invitation` (zvadlo) | ručně vedoucím po přidání účastníků a pro dodatečně pozvané; jako **připomínka** týmž pozvaným bez odpovědi se stejným tělem a předmětem doplněným o „ - připomínka“ | pozvaní |
| `confirmation` | automaticky po odeslání přihlášky | účastník |
| `declined` | automaticky po odmítnutí (lze vypnout) | účastník |
| `changed` | automaticky při změně nebo zrušení přihlášky | účastník |
| `lock_notice` | automaticky N dní před uzávěrkou (nastavitelné, lze vypnout; bez uzávěrky se neposílá) | přihlášení |
| `edit_code` | automaticky, když si nečlen otevře vyplněnou přihlášku k úpravě; text pevný, jen symbol `%kod%` | účastník |
| `rejected` | automaticky při zamítnutí přihlášky vedoucím (5.1); šablona je **společná pro všechny akce jednotky** (jen `unit_email_template`, do akce se nekopíruje), důvod vedoucího jde do `%poznamka%` | účastník |
| `promoted` | automaticky náhradníkovi, který se dostal mezi účastníky: na místo uvolněné odhlášením jiného účastníka, nebo rozhodnutím vedoucího (5.3) | účastník |
| `info` | ručně, libovolný text | vybraní podle stavu |

Symboly: `%jmeno%`, `%prijmeni%`, `%akce%`, `%termin%`, `%misto%`, `%cena%`, `%odkaz-prihlaska%`,
`%odkaz-web%`, `%uzaverka%` (poslední den pro podání, změnu i zrušení: uzávěrka, jinak konec akce), `%kontakt%`, `%jednotka%`, `%poznamka%` (text, který vedoucí
napíše při ručním odeslání), `%kod%` (jen `edit_code`).

### 8.2 Odesílání

- Jednotka si připojí **Gmail účet** přes OAuth (`google/apiclient`, rozsah `gmail.send` + e-mail účtu),
  přesně jako h.skauting: tlačítko v nastavení jednotky → Google → návrat na `/google/token` → uloží se
  jen **refresh token** do `google_oauth` (jednotka, e-mail). Účet je vázaný na jednotku aktivní role.
  Refresh token je v databázi šifrovaný klíčem z `.env` (`APP_SECRET`).
- **Povolené domény** (rozhodnutí 37): účet lze propojit jen z domény `skaut.cz`, `skautka.cz`, z domén skautského
  Google Workspace, které jednotka vlastní (`GoogleApps.DomainAllUnit`) nebo které jí přidělila nadřízená
  (`GoogleApps.DomainUnitAll`; doména nadřízené platí dolů jen s `IncludeChildUnit`), a z domén přihlášeného
  vedoucího (`GoogleApps.DomainAllPerson`). Vše se čte ze skautISu při každém zobrazení a propojení, nic se
  necachuje. Účet mimo povolené domény se neuloží a souhlas u Googlu se odvolá. Účet propojuje oddíl nebo vyšší
  jednotka, družina ne. h.skauting tohle pravidlo jen popisuje textem, nevynucuje ho.
- **Sdílení dolů** jako bankovní účet v h.skautingu: účet patří jednotce, která ho propojila; podřízené jednotky ho
  mohou použít, jen když to jednotka zapne („Povolit podřízeným“, výchozí vypnuto). Vypnutí odebere účet akcím
  a výchozímu nastavení podřízených jednotek. Akce už nastavený účet podrží, i když ho její jednotka nově vybrat nesmí.
- Akce má vybraný odesílací účet; nabízejí se účty její jednotky a sdílené účty nadřízených jednotek.
- Odeslání přes Gmail API (`users.messages.send`). Neplatný nebo odvolaný token skončí srozumitelnou chybou
  „Je potřeba odebrat a znovu propojit e-mailový účet.“
- Jiný odesílatel pro e-maily akce **není**, stejně jako v h.skautingu. Bez připojeného účtu nelze akci otevřít
  ani nic rozeslat; UI vysvětlí, že je třeba v nastavení jednotky propojit Gmail účet. Výjimkou jsou prostředí
  se `SEND_EMAIL=false` (`dev`, `ci`): tam jde všechno do Mailpitu a účet se nevyžaduje.
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
| děti přihlášeného rodiče | `OrganizationUnit.PersonParentAllPersonChildren` | `ID_Person` (rodič); vrací `ID_Person`, jméno, datum narození, pohlaví, e-mail a telefon dítěte (viz 4.6) |
| členství (kategorie) | `OrganizationUnit.MembershipAllPerson` | `ID_Person`, `IsValid=true` |
| číselníky akcí | `Events.EventGeneralTypeAll`, `EventGeneralScopeAll` | – |
| tábory jednotky | `Events.EventCampAll` | `Year`, `ID_EventCampState` |
| stav tábora | `Events.EventCampDetail` | `ID` (čte se `ID_EventCampState`) |

Vše přes vlastní rozhraní `App\Model\Skautis\SkautisGateway` (jedna metoda na účel, typované DTO) s jedinou
implementací nad jádrem `skaut/skautis` 3.1 (`Skaut\Skautis\Skautis`, webové služby přes `WsdlManager`,
`User` se session adaptérem). Napojení na Nette (DI extension, session adaptér, dispatcher, Tracy panel)
dodává rozšíření `skaut/skautis-nette` 3.1 (SkautisNette, vzniklo z interního balíčku Zvadla). V `ci` a testech
extension zapne **fixture továrnu**, která
místo SOAP vrací odpovědi z JSON souborů (`{Služba}/{Metoda}.json`); testy tak procházejí skutečným mapováním
a nikdy nevolají skutečný skautIS. Názvy vstupních polí se vždy ověřují na
`…/JunakWebservice/<Služba>.asmx?op=<Metoda>`, protože `SoapClient` neznámé klíče tiše zahodí.
Jednotky a členové se cachují (jednotky 1 den, členové 1 hodinu, s ručním obnovením).

### 9.2 Zápis do skautISu

| Účel | Metoda | Parametry (ověřit na ws.skautis.cz) |
|---|---|---|
| založení akce | `Events.EventGeneralInsert` | `ID=1`, `Location`, `Note=' '`, `ID_EventGeneralScope`, `ID_EventGeneralType`, `ID_Unit`, `DisplayName`, `StartDate`, `EndDate`, `IsStatisticAutoComputed=false`; z odpovědi se čte `ID` |
| účastník člen | `Events.ParticipantGeneralInsert` | `ID_EventGeneral`, `ID_Person`; duplicita `Participant_PersonIsAllreadyParticipantGeneral` se ignoruje |
| účastník nečlen | `Events.ParticipantGeneralInsert` + `OrganizationUnit.PersonUpdateBasic` | `Person{FirstName, LastName, NickName, Note}` → `ID_Person`; potom `ID`, `FirstName`, `LastName`, `NickName`, `ID_Sex`, `Birthday`, `Street`, `City`, `Postcode` **jen u osoby, kterou Zvadlo právě založilo** (postup h.skautingu) |
| účastník z jiné jednotky | `Events.ParticipantGeneralInsert` | `ID_EventGeneral`, `ID_Person` (osoba mimo strom jednotky, přihlášená přes skautIS, 4.6) *(ověřit oprávnění)* |
| odebrání účastníka z akce | `Events.ParticipantGeneralDelete` | `ID`, `DeletePerson=false`; odebere jen účast, osoba ve skautISu zůstává beze změny |
| účastník tábora | `Events.ParticipantCampInsert` | `ID_EventCamp`, `ID_Person` nebo `Person{…}` |

Pravidla:

- Akce se do skautISu propíše tlačítkem „Propsat do skautISu“ (u koncept → otevřená se nabídne rovnou).
  Uložené ID se zobrazí s odkazem do skautISu a do h.skautingu.
- Propsání účastníků spouští vedoucí; systém nejdřív ukáže rozdíl (kdo ve skautISu chybí, kdo už nejede,
  ale ve skautISu je, kdo je tam jen ručně) a po potvrzení ho provede **po dávkách 10 AJAX požadavky
  v prohlížeči vedoucího** s průběhem, stejně jako donačítání kontaktů. Do fronty úloh zápis nejde: worker
  nemá token do skautISu, ten má jen přihlášený vedoucí (rozhodnutí 33). Výsledek každé položky se uloží
  (`skautis_participant_id`, chyba). Osoba, kterou skautIS už u akce eviduje (přidaná ručně), se převezme,
  nezakládá se podruhé; účastníky přidané ve skautISu ručně Zvadlo nikdy nemaže. Nečlen po založení osoby
  dostane její `skautis_person_id`, takže se dál chová jako osoba ze skautISu.
- Zrušení nebo zamítnutí přihlášky po propsání nabídne **odebrání účastníka z akce** ve skautISu
  (`ParticipantGeneralDelete`, `DeletePerson=false`). „Odebrat ze skautISu“ vždy znamená jen tohle.
- Zvadlo **nikdy neupravuje osobní údaje člena ve skautISu**. Jediný zápis do osoby je `PersonUpdateBasic`
  bezprostředně po založení nové osoby nečlena přes `ParticipantGeneralInsert`, aby nový záznam nebyl torzo.
- Tábory se přes API **nezakládají** (metoda `EventCampInsert` neexistuje, ověří se před fází 5). Postup je
  obrácený: vedoucí založí tábor ve skautISu, ve Zvadle ho vybere a Zvadlo do něj propisuje účastníky
  (`ParticipantCampInsert`), jakmile je tábor **odevzdaný ke schválení**. Propsání se povolí ve stavech
  `approvedLeader` a `approvedParent` (schválení střediskem není podmínkou; přesný stav se ještě potvrdí),
  ve stavu `draft` Zvadlo vysvětlí, co je třeba udělat ve skautISu.
- Nečlen se propisuje s kompletními údaji (viz 5.2), aby záznam ve skautISu nebyl torzo.
- Změny ve skautISu (přejmenování, termín) se do Zvadla nepropisují; skautIS je cíl, ne zdroj.

### 9.3 Oprávnění externí aplikace (AppID)

Úplný soupis volaných metod, návratových adres a potřebných oprávnění podle kódu je
v [skautis-opravneni.md](skautis-opravneni.md); při každém novém volání skautISu se doplní tam.

Kontakty rodičů jsou známé slabé místo (aplikace skautIS mobil je nemá). Bez nich systém posílá zvadlo
na e-mail dítěte a upozorní vedoucího.

### 9.4 Odznak v h.skautingu: push ze Zvadla

h.skauting zobrazí u akce příznak, že existuje záznam ve Zvadle, s odkazem a souhrnnými počty. Data tečou
**jedním směrem**: Zvadlo posílá, h.skauting ukládá a odznak kreslí ze své databáze. Stránky h.skautingu
Zvadlo nikdy nevolají, takže jednotky, které Zvadlo nepoužívají, nevyvolají žádný požadavek a výpadek Zvadla
se h.skautingu nedotkne (rozhodnutí 35; původní pull API z fáze 5 bylo odstraněno). Oba systémy přihlašují
uživatele přes skautIS a jeho token je vázaný na AppID, proto výměna stojí na těchto zásadách:

1. **Systém–systém, ne uživatel–systém.** Zvadlo volá h.skauting mimo scope přihlášeného uživatele, jen přes
   HTTPS, a každé tělo podepisuje sdíleným tajemstvím (`HSKAUTING_WEBHOOK_SECRET` ve Zvadle, protějšek
   v konfiguraci h.skautingu). Bez platného podpisu h.skauting dávku odmítne.
2. **Payload nikdy nenese osobní údaje.** Jen fakta o akci, která smí vidět každý, kdo akci vidí ve skautISu:
   odkaz, stav, souhrnné počty. Komu odznak ukázat, rozhoduje h.skauting: u své akce už ověřil oprávnění
   uživatele přes skautIS.
3. **Osobní data tečou přes skautIS, nebo přes prohlížeč uživatele.** Účastníky Zvadlo propisuje do skautISu
   a h.skauting je odtud čte se svým vlastním oprávněním. Odznak vede na detail akce ve Zvadle, kde se
   uživatel přihlásí skautISem sám; Zvadlo stejně odkazuje na h.skauting.
4. **Rozšíření** toto pravidlo dodrží. Uživatelsky vázaná data by se předávala krátkodobým jednorázovým
   tokenem přes prohlížeč uživatele, nikdy sdíleným tajemstvím. V první verzi nic takového není.

**Co a kdy Zvadlo posílá** (`App\Model\Hskauting\HskautingNotifier`):

- Jen akce propsané do skautISu (`skautis_event_id` nebo `skautis_camp_id`); ostatní h.skauting nezajímají.
- Plánovač jednou za minutu vybere akce, u kterých se od posledního odeslání (`event.hskauting_pushed_at`)
  změnila akce nebo některá její přihláška, a pošle **jeden** `POST` se všemi najednou. Deset přihlášek za
  minutu je jeden požadavek, klidná hodina žádný.
- Každá položka je plný stav akce, ne rozdíl; opakované doručení je neškodné a h.skauting přepisuje jen
  novějším `sentAt`. Archivace nebo zrušení akce pošle stav, po kterém odznak zmizí.
- Když h.skauting neodpoví 2xx, akce zůstanou neodeslané a další pokusy se odsouvají (2, 4, 8, 16, 32,
  nejvýš 60 minut); první úspěch odklad zruší. Konzolový příkaz `hskauting:push --all` pošle všechny propsané
  akce znovu (první nasazení, obnova po výpadku). Průběh je v `log/hskauting.log`.

```
POST {HSKAUTING_WEBHOOK_URL}                       (např. https://h.skauting.cz/api/zvadlo/events)
Content-Type: application/json
X-Zvadlo-Signature: t=1790000000,v1=<hex HMAC-SHA256("<t>.<tělo>", tajemství)>

{
  "sentAt": "2026-10-01T10:00:00+02:00",
  "events": [
    { "skautisEventId": 5001, "skautisCampId": null, "url": "https://zvadlo.skauting.cz/akce/123",
      "state": "open", "registered": 18, "waitlisted": 2, "declined": 4, "pending": 3 }
  ]
}
```

**Co má udělat h.skauting** (samostatný pull request): endpoint, který ověří podpis (`t` nejvýš 5 minut od
svého času, `hash_equals`), uloží položky do tabulky záznamů Zvadla (ID akce nebo tábora ve skautISu, odkaz,
stav, počty, `sentAt`) s přepisem jen novějším `sentAt`, a odpoví 2xx. Odznak v hlavičce detailu akce
(`app/Presentation/Events/Event/default.latte`), sloupec v seznamu akcí a totéž u táborů se čtou z této
tabulky; žádný HTTP klient, cache ani timeouty. Konfigurace: tajemství podpisu pro každé prostředí.

## 10. Administrace systému

Stejná jako v h.skautingu; funkce hlášení problémů se přebírá **beze změny chování**.

### 10.1 Uživatelé a role

`/admin/uzivatele`: seznam systémových uživatelů (skautIS ID a role), formulář se skautIS ID uživatele a
zaškrtávacími poli rolí `admin` / `support`, úprava a odebrání. Správci z konfigurace jsou vypsaní zvlášť.
Jméno ze skautISu se u cizího uživatele nenačítá (vyžadovalo by dotaz `UserDetail` na cizí účet pod tokenem
přihlášeného správce); ID uživatele je vidět v detailu jeho hlášení chyby.

### 10.2 Hlášení problémů

Odkaz „Nahlásit problém“ v patičce (`/nahlasit-problem`, s předvyplněnou URL aktuální stránky). Jen pro
přihlášené. Formulář:

| Pole | Pravidla |
|---|---|
| Popis chybového chování | povinné, max. 10 000 znaků, textarea 10 řádků, nápověda „Uveďte, co jste dělali, co jste očekávali a co se místo toho stalo.“ |
| URL stránky s chybou | nepovinné, platná URL, max. 2 048 znaků, předvyplněné |
| Váš e-mail | jen pokud se e-mail nepodařilo zjistit ze skautISu; povinné, max. 255 |
| Screenshot | nepovinný obrázek do 5 MB; jen PNG, JPEG, GIF nebo WebP podle MIME typu (SVG by správci spustilo skript; PHP obraz nemá GD, `getImageSize` nelze použít), správci se servíruje s `X-Content-Type-Options: nosniff` a `Content-Security-Policy: sandbox` |
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

Administrace `/admin/hlaseni-chyb`: tabulka nevyřešených hlášení a posledních 20 vyřízených; detail
(`/admin/hlaseni-chyb/<id>`) s diagnostikou (JSON), screenshotem (`…/screenshot`) a třemi akcemi: **odpovědět** (e-mail hlasiteli + komentář k issue), **založit GitHub issue** (ručně, POST na
`repos/{owner}/{repo}/issues` s tokenem z `.env`, štítky z konfigurace), **vyřešit / zamítnout** (e-mail
hlasiteli, označení stavu). Přístup mají role `admin` a `support`.

### 10.3 Statistiky a jednotky

- `/admin/statistiky`: počty jednotek, akcí, přihlášek, odeslaných e-mailů a chyb odesílání po měsících.
- `/admin/jednotky`: jednotky, které systém používají, jejich odesílací účty a poslední aktivita.

## 11. Prostředí

Prostředí se přepíná proměnnou `APP_ENV`, stejně jako v h.skautingu:

| `APP_ENV` | Kde | skautIS | Banner v liště |
|---|---|---|---|
| `dev` | lokální docker; do vyřízení vlastního AppID doména `moje-zvadlo.cz` a AppID h.skautingu | testovací (`test-is.skaut.cz`) | „Vývojový server“ |
| `ci` | GitHub Actions, testy | falešný klient z fixtur | – |
| `beta` | beta.zvadlo.skauting.cz, větve `beta/**` | testovací | „Beta server“ (modrý) |
| `test` | test.zvadlo.skauting.cz, `master` | testovací | „Testovací server“ (žlutý) |
| `prod` | zvadlo.skauting.cz, `master` po schválení | produkční | – |

Banner: mimo produkci se logo v liště nahradí ikonou s popiskem prostředí a lišta má barvu prostředí
(`TEST_BACKGROUND`, `ENVIRONMENT_LABEL`, `ENVIRONMENT_COLOR` jako v h.skautingu).

Konfigurace přes `.env` (výchozí hodnoty v repozitáři), `.env.local` (lokální přepis, mimo git),
`.env.{APP_ENV}` a `.env.{APP_ENV}.local`; načítá je `App\Environment`. Proměnné z okolí kontejneru mají
přednost. Seznam proměnných je v [implementačním plánu](implementacni-plan.md#proměnné-prostředí).

Režim údržby se přebírá z h.skautingu: `MAINTENANCE_MODE` přepne aplikaci ještě před startem Nette na stránku
odstávky (503), adresy a rozsahy v `MAINTENANCE_ALLOWED_IPS` aplikaci vidí dál (se štítkem „Údržba“ v liště),
`MAINTENANCE_STARTED_AT` a `MAINTENANCE_ENDS_AT` (ISO) se na stránce ukážou jako `dd.mm.rrrr HH:mm`.
Nasazení, cron, logy a Sentry popisuje [nasazení](nasazeni.md).

## 12. Dlouhé operace a fronta

Dva druhy práce, dvě pravidla.

**Čtení ze skautISu je vždy synchronní.** Jedno volání (členové jednotky, strom jednotek, seznam táborů,
účastníci akce ve skautISu) proběhne přímo v požadavku a výsledek se cachuje. Kde je volání víc (kontakty
pro každou pozvanou osobu: `PersonParentAll` a `PersonContactAll`), stránka se zobrazí hned a data se
**donačítají AJAX požadavky** po dávkách (10 osob na požadavek, každý pod několik sekund) s viditelným
průběhem a cachují se. Čtení ze skautISu nikdy nejde do fronty.

**Úlohy zadané uživatelem jdou do fronty v databázi.** Úloha je akce vedoucího (nebo reakce na akci
účastníka), která něco odesílá nebo zapisuje mimo systém a může trvat déle než 30 s: rozeslání 100 pozvánek
přes Gmail API nebo 100 zápisů do skautISu ten limit překročí. HTTP požadavek jen úlohu založí a UI
ukazuje průběh.

| Úloha | Kdo zakládá | Co dělá | Dávka na běh |
|---|---|---|---|
| `send_invitations` | vedoucí | vyrenderuje a odešle zvadlo vybraným pozvaným | 25 e-mailů |
| `send_reminder` | vedoucí | připomínka těm, kdo pozvánku dostali a nereagovali: šablona `invitation` s předmětem + „ - připomínka“, nejvýš jednou denně | 25 e-mailů |
| `send_info` | vedoucí | libovolná zpráva vybraným podle stavu | 25 e-mailů |
| `send_email` | systém po akci účastníka | jeden automatický e-mail (potvrzení, odmítnutí, změna) | 1 e-mail |
| `send_lock_notice` | plánovač | upozornění na uzávěrku | 25 e-mailů |

Každý odchozí e-mail tak odesílá worker fronty; je jen jedna cesta pro Gmail OAuth, opakování a log.
Jediná výjimka je **kód pro úpravu přihlášky** (`edit_code`): účastník na něj čeká u obrazovky, proto se
jediný e-mail odešle hned v požadavku stejnou službou a zapíše do `email_message` jako ostatní; do fronty
se nezařazuje, aby nečekal až minutu na plánovač.
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
| `event` | `id`, `unit_id`, `name`, `slug`, `skautis_type_id`, `skautis_scope_id`, `is_camp`, `date_from`, `date_to`, `place`, `description`, `registration_deadline`, `capacity`, `waitlist_enabled`, `price`, `contact_name`, `contact_email`, `contact_phone`, `state`, `public_registration` (`none` / `open` / `skautis`), `lock_notice_days`, `answers_forgotten_at` (retence, 14) (kolik dní před uzávěrkou odejde upozornění, 0 = nikdy), `skautis_event_id`, `skautis_camp_id`, `hskauting_pushed_at` (push do h.skautingu, 9.4), `web_token`, `oauth_id` → `google_oauth`, `created_by_user_id`, `created_at`, `updated_at`; unikátní `(unit_id, slug)` |
| `event_field` | `id`, `event_id`, `type` (`text` / `boolean` / `choice` / `multichoice`), `label`, `help`, `required`, `position`, `hidden` |
| `event_field_option` | `id`, `field_id`, `label`, `position`; unikátní `(field_id, position)`; jen pro `choice` a `multichoice` |
| `event_content` | `id`, `event_id`, `type` (`text` / `file` / `link`), `title`, `position` |
| `event_content_text` | `id` (PK → `event_content`, dědičnost JOINED), `body` |
| `event_content_file` | `id` (PK → `event_content`), `path` (relativně k `uploads/`), `original_name`, `mime_type`, `size` |
| `event_content_link` | `id` (PK → `event_content`), `url` |
| `event_email` | `id`, `event_id`, `type`, `enabled`, `subject`, `body`; unikátní `(event_id, type)` |
| `unit_email_template` | `id`, `unit_id`, `type`, `subject`, `body`; unikátní `(unit_id, type)`; typ `rejected` existuje jen tady a platí pro všechny akce jednotky |
| `unit_settings` | `unit_id` (PK), `agreement_text`, `default_oauth_id` → `google_oauth` |
| `registration` | `id`, `event_id`, `edit_code_hash`, `edit_code_expires_at` (jednorázový kód pro úpravu, 3), `skautis_person_id` (prázdné = nečlen), `first_name`, `last_name` (u nečlena prázdné do vyplnění), `nick_name`, `sex`, `birth_date`, `street`, `city`, `postcode`, `contact_email`, `phone`, `note`, `state`, `decline_reason`, `origin` (`invited` / `public` / `skautis`), `pairing_hint` (`possible` / prázdné; jen u veřejných přihlášek), `email_hash` (HMAC kontaktního e-mailu, přežije anonymizaci), `token_hash`, `token_encrypted`, `invited_at`, `seen_at`, `responded_at`, `waitlisted_at`, `cancelled_at`, `rejected_at`, `failed_verifications`, `blocked_until`, `edit_code_hash`, `edit_code_expires_at`, `skautis_participant_id`, `skautis_error`, `anonymized_at`, `skautis_person_created_at` (Zvadlo založilo osobu nečlena ve skautISu; retence, 14), `created_at`, `updated_at`; unikátní `token_hash`, unikátní `(event_id, skautis_person_id)` |
| `registration_recipient` | `id`, `registration_id`, `email`, `name`, `source` (`mother` / `father` / `main` / `other` / `manual`), `selected`; unikátní `(registration_id, email)`; nalezené adresy a které z nich dostávají pozvánku |
| `registration_text_value` | `registration_id`, `field_id`, `value`; PK `(registration_id, field_id)` |
| `registration_boolean_value` | `registration_id`, `field_id`, `value`; PK `(registration_id, field_id)` |
| `registration_option_value` | `registration_id`, `option_id` → `event_field_option`; PK `(registration_id, option_id)`; radio 1 řádek, checklist N řádků |
| `registration_log` | `id`, `registration_id`, `at`, `actor_type` (`participant` / `user` / `system`), `actor_user_id`, `message`, `changes` (JSON, neprůhledný) |
| `unit_member_fingerprint` | `unit_id`, `person_id`, `fingerprint_full` (HMAC „příjmení|jméno|datum narození“), `fingerprint_surname` (HMAC „příjmení|datum narození“), `refreshed_at`; PK `(unit_id, person_id)`; jen pro jednotky s akcí s veřejným přihlašováním (viz 4.5) |
| `email_message` | `id`, `task_id`, `event_id`, `registration_id`, `type` (typ e-mailu včetně `reminder`), `to_email`, `to_name`, `subject`, `body`, `oauth_id`, `state` (`sent` / `failed`), `sent_at`, `error`; log každého odchozího e-mailu, píše ho worker (kód pro úpravu a zkušební e-mail se zapisují hned) |
| `task` | `id`, `type`, `event_id`, `state` (`queued` / `running` / `done` / `failed`), `payload` (JSON, neprůhledný), `attempts`, `progress_done`, `progress_total`, `error`, `created_by_user_id`, `created_at`, `started_at`, `finished_at` |
| `task_registration` | `task_id`, `registration_id`; PK `(task_id, registration_id)`; které přihlášky úloha zpracovává |
| `google_oauth` | `id` (UUID), `unit_id`, `email`, `refresh_token_encrypted` (šifrovaný klíčem `APP_SECRET`), `updated_at`; unikátní `(unit_id, email)` |
| `system_user_role` | `id`, `user_id`, `role`, `created_at`; unikátní `(user_id, role)` |
| `technical_error_report`, `technical_error_report_reply` | shodné s h.skautingem |
| `user_preference` | `user_id` (PK), `extend_login`, `remember_role`, `remembered_role_id`, `updated_at` |

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
  `RETENTION_MONTHS_UNSYNCED`), ne jednotkou. Anonymizaci provádí konzolový příkaz `registrations:anonymize`
  spouštěný plánovačem denně; hotové kroky si značí `event.answers_forgotten_at` a `registration.anonymized_at`,
  nečlena s osobou založenou Zvadlem pozná podle `registration.skautis_person_created_at`. Členové si jméno
  a skautIS ID nechávají.
- Zamítnutá přihláška (5.1) se anonymizuje **ihned**, ne až po retenční lhůtě; zůstane `skautis_person_id`
  nebo otisk e-mailu kvůli blokaci opakovaného veřejného přihlášení.
- Otisky členů (4.5) jsou HMAC s klíčem z `APP_SECRET`; čitelná jména a data narození členů mimo akci se
  neukládají a otisky se mažou, když jednotka nemá akci s veřejným přihlašováním.
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
- **PWA** pro vedoucí (fáze 8, hotová): manifest `/manifest.webmanifest` a service worker `/sw.js` servíruje
  presenter (ikony a názvy cache nesou hash release, nový release začíná s prázdnou cache), ikona se stanem,
  výzva „Přidat na plochu“ jen v přihlášené aplikaci. Worker je *network first* (data jsou osobní údaje a mění
  se, nikdy se neservírují z cache online); cachuje jen offline stránku `/offline`, assety s digestem
  a **poslední zobrazený seznam přihlášených** každé akce (`/akce/<id>/prihlasky`, jen odpověď 200 bez
  přesměrování na přihlášení). Bez připojení worker vrátí uloženou kopii označenou žlutým pruhem „Zobrazena
  uložená kopie z dd.mm.rrrr HH:mm“, jinak offline stránku se seznamem uložených kopií; kopii lze otevřít
  i záměrně přes `?offline=1`. Odhlášení (`/sign/out`, `/sign/skautis-logout`) uložené seznamy smaže.
  Vzorem byl skautIS mobil (`PwaController`, `public/sw.js`).
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
- Oznámení správců na nástěnce (h.skauting je má; přidají se, až bude co oznamovat).
- Vícejazyčnost; UI je česky.
- SMS.

## Rozhodnutí

Rozhodnuto zadavatelem 29. 9. 2026 (1–17) a 30. 9. 2026 (18–31: přidání účastníků, nečlen, připomínka, tábory,
token, veřejné přihlašování, párování, zamítnutí, přihlášení přes skautIS, zásada jednoduchosti, režim jen přes
skautIS). Otevřené body jsou označené *(ověřit)*.

| # | Téma | Rozhodnutí |
|---|---|---|
| 1 | Název | **Zvadlo**, zvadlo.skauting.cz |
| 2 | Hosting a nasazení | stejné jako h.skauting: sdílený hosting www.skauting.cz, Deployer, migrace přes HTTP `db_fix.php`, plánovač přes cron |
| 3 | skautIS AppID a dev doména | registraci a doménu zařídí zadavatel; do té doby vývoj na `moje-zvadlo.cz` s AppID h.skautingu (`rs/.env.local`) |
| 4 | Odesílání e-mailů akce | jen z Gmail účtu jednotky jako v h.skautingu, bez náhradního odesílatele; `Reply-To` je kontaktní osoba z nastavení akce; systémová pošta sendmailem (viz 8.2) |
| 5 | Tábory | zakládají se ve skautISu; Zvadlo propisuje účastníky, jakmile je tábor odevzdaný ke schválení; schválení střediskem není podmínkou *(ověřit přesný stav)* |
| 6 | Kapacita | ano, včetně volitelné čekací listiny; kapacita je nepovinná, bez ní je akce bez omezení (podrobnosti 5.3, rozhodnutí 36) |
| 7 | Retence | nečlen propsaný do skautISu 12 měsíců, nepropsaný 24 měsíců; nečlen vyplňuje kompletní údaje včetně pohlaví (viz 5.2, 14) |
| 8 | Licence | **AGPL-3.0-or-later** (soubor `LICENSE`) |
| 9 | GitHub | zatím `github.com/juradee/zvadlo`, včetně issues z hlášení problémů; přesun pod organizaci později (jen změna proměnných `GITHUB_ISSUES_*`) |
| 10 | Vzhled | čistý Bootstrap 5 s malým vlastním tématem, bez Tableru |
| 11 | Knihovna skautIS | jádro **`skaut/skautis` 3.1** (vzniklo ve forku `webwingscz/Skautis`, název z větve 3.x, plně PHP 8.4+; od 7. 10. 2026 vydané upstream jako v3.1.0) a Nette napojení **`skaut/skautis-nette` 3.1** (SkautisNette 3.1, vzniklo jako interní balíček Zvadla a šlo upstream; namespace `Skaut\SkautisNette`); obojí přes composer `vcs` z GitHubu, dokud nejsou na Packagistu (viz níže a plán) |
| 12 | Frontend nástroje | **npm** (součást Node, `package-lock.json`, `npm ci`); Node **24 LTS**, přechod na 26 po 28. 10. 2026, kdy se stane Active LTS |
| 13 | Verze PHP | minimum **8.4**, kód a všechny knihovny musí běžet i na **8.5**; CI testuje obě verze; na 8.5 se přejde, jakmile to hosting dovolí |
| 14 | Mobil a PWA | všechny obrazovky mobile-first (rodiče vyplňují na telefonu); PWA pro vedoucí jako samostatná fáze po MVP (viz 15) |
| 15 | Dlouhé operace | čtení ze skautISu vždy synchronně, u většího objemu donačtení AJAXem po dávkách; do fronty v databázi jdou jen úlohy zadané uživatelem (rozeslání pozvánek, propsání účastníků), které mohou trvat déle než 30 s (viz 12) |
| 16 | Návrh databáze | třetí normální forma; JSON jen pro neprůhledné dokumenty, které se nefiltrují (viz 13) |
| 17 | API mezi systémy | systém–systém se sdíleným tokenem, mimo scope uživatele; API nikdy nevrací osobní údaje, ty tečou přes skautIS nebo přes prohlížeč uživatele, který se v druhém systému přihlásí sám (viz 9.4) Nahrazeno rozhodnutím 35 (push ze Zvadla). |
| 18 | Osobní token | stálý po celou dobu života přihlášky, odesláním se nemění; oba rodiče dostanou pro dané dítě stejný odkaz; ukládá se šifrovaný + hash; stránka vždy uvádí, koho se přihláška týká (sourozenci) (viz 3) |
| 19 | Přidání účastníků a pozvánky | dvoukrokově jako platby v h.skautingu: přidat účastníky a při tom vybrat u každého člena e-maily (skautIS jasně rozlišuje otce, matku a hlavní e-mail; hromadně zaškrtávátkem typu, jednotlivě u člena) → odeslat pozvánky; všechny e-maily členovi jdou na vybrané adresy, nečlen má jednu; seznam přihlášek adresy ukazuje a upravuje v řádku, bez hromadné úpravy; tábory **vždy** jen výběrem ze seznamu skautISu, ručně založit nejde (viz 4.1, 4.3) |
| 20 | Připomínka | stejný e-mail jako pozvánka, předmět + „ - připomínka“, nejvýš jednou denně, pro stav pozván s odeslanou pozvánkou |
| 21 | Ověření nečlena | první vyplnění: e-mail, na který pozvánka přišla (stránka neukazuje osobní údaje, stačí); úprava vyplněné přihlášky: jednorázový čtyřmístný kód na kontaktní e-mail (viz 3) |
| 22 | Stav zobrazeno | nastaví se otevřením osobního odkazu, ještě před ověřením (viz 5.1) |
| 23 | Veřejné přihlašování | nečleni se na akce pro veřejnost hlásí sami veřejným odkazem bez pozvánky a bez ověření; je součástí první verze (viz 4.4), pozvánka nečlenovi zůstává jako výjimka |
| 24 | Kód pro úpravu | jediný e-mail, na který účastník čeká, se posílá hned v požadavku mimo frontu, ale stejnou službou a se záznamem v logu (viz 12) |
| 25 | Párování člena z veřejného odkazu | schváleno řešení otisky členů + potvrzení vedoucím (viz 4.5) |
| 26 | Zamítnutí přihlášky | nový stav zamítnuto: vedoucí odmítne přihlášku někoho, kdo na akci nepatří; osobní údaje se ihned anonymizují, přihláška se dál neeviduje a nepropisuje (viz 5.1) |
| 27 | Přihlášení účastníka přes skautIS | proveditelné: návrat na konkrétní akci přes `ReturnUrl`, děti rodiče přes `PersonParentAllPersonChildren`; přidává se na veřejnou stránku a miniweb (viz 4.6); *(ověřit)* oprávnění AppID pro role člen a rodič na testovacím skautISu |
| 28 | E-mail o zamítnutí | zamítnuté přihlášce odejde e-mail `rejected`; šablona je společná pro všechny akce jednotky a nekopíruje se do akce (viz 5.1, 8.1) |
| 29 | Zápisy do skautISu | „odebrat ze skautISu“ znamená odebrat účastníka z akce; Zvadlo nikdy neupravuje osobní údaje člena ve skautISu, `PersonUpdateBasic` jen pro osobu nečlena, kterou právě založilo (viz 9.2) |
| 30 | Zásada jednoduchosti | jako Google Forms: odkaz, potvrzení, vyplnění, odeslání; chrání se jen už vyplněné údaje; vše kolem skautISu je doplněk (viz 1) |
| 34 | Přihlášení | automatické prodlužování přihlášení do skautISu (každá otevřená stránka + ping z prohlížeče každých 20 minut; AJAX na pozadí přihlášení neprodlužuje) a role uložená ve Zvadle podle h.skautingu; token má jen přihlášený uživatel (viz 7.1) |
| 33 | Zápis do skautISu | běží po dávkách v prohlížeči vedoucího (AJAX), ne ve frontě: worker nemá token do skautISu; push do h.skautingu zná i tábory (`skautisCampId`) a posílá i `waitlisted` a `state` (pull API nahrazeno, rozhodnutí 35) |
| 32 | Formát dat | všude v UI, e-mailech a exportech `dd.mm.rrrr` (např. 05.02.2027), s časem `dd.mm.rrrr HH:MM`; i pole data ve formulářích jsou textová ve tvaru `dd.mm.rrrr` (nativní pole data ukazuje formát podle jazyka prohlížeče, např. mm/dd/yyyy) |
| 31 | Režim jen přes skautIS | volitelný režim akce: přihlásit se může kdokoli s účtem ve skautISu, i z jiné jednotky, bez skautISu to nejde; zápis osoby z jiné jednotky do akce metodou `ParticipantGeneralInsert` s `ID_Person` schéma dovoluje, oprávnění role *(ověřit ve fázi 5)* (viz 4.1, 4.6) |
| 35 | Odznak v h.skautingu | push ze Zvadla místo pull API: dávka nejvýš jednou za minutu jen s propsanými akcemi, které se změnily, podpis HMAC-SHA256, odklad po chybě; h.skauting drží kopii neosobních čísel a jeho stránky Zvadlo nevolají; pull API odstraněno (viz 9.4) |
| 36 | Uzávěrka a kapacita | jeden limit pro podání, změnu i zrušení přihlášky (uzávěrka, prázdná = do konce akce), zámek úprav zrušen; čekací listina je volba akce; odhlásí-li se účastník sám, místo automaticky dostane první náhradník a e-mail `promoted`; zruší-li účast vedoucí, místo zůstane volné a vedoucí rozhodne, zda náhradníka přijme; přijetí nad kapacitu jen s výslovným potvrzením vedoucího (viz 5.3) |
| 37 | Domény odesílacích účtů | jen `skaut.cz`, `skautka.cz` a domény skautského Google Workspace jednotky, přidělené nadřízenou (s podřízenými) nebo dostupné přihlášenému vedoucímu, vše živě ze skautISu bez cache; účet propojuje oddíl a výš; sdílení s podřízenými jednotkami zapíná vlastník účtu jako u bankovních účtů h.skautingu (14. 10. 2026); výjimky pro jiné domény později |

K bodu 11: vydané verze `skautis/nette` (2.2.0 z roku 2020 i 3.0.0-alpha.3 z roku 2022) vyžadují `nette/utils`
^3.0 a `nette/utils` 3.2.x má omezení `php <8.4`, takže s nimi projekt na PHP 8.4 nejde ani nainstalovat; právě
proto je h.skauting pevně na PHP 8.3 a Nette 3.1. Jádro (`skautis/skautis`, od větve 3.x `skaut/skautis`; SOAP klient, `User`, `Config`,
`WsdlManager`, události) zadavatel forkl do `webwingscz/Skautis` a vznikla verze **3.1** pro PHP 8.4+ se
zachovaným API a namespace `Skaut\Skautis` (používá ho už SRS), 7. 10. 2026 vydaná upstream jako `skaut/skautis`
v3.1.0. Původní rozšíření `skautis/nette` 2.x nešlo použít: jeho jediný slot pro PSR-14 dispatcher by Zvadlu
bránil logovat SOAP chyby v produkci. Napojení na Nette proto vzniklo jako interní balíček Zvadla
(`packages/skautis-nette`: DI extension, session adaptér, fan-out dispatcher, Tracy panel, fixture továrna pro
testy, žádná závislost na `App\`) a šlo upstream jako **SkautisNette 3.1** (`skaut/skautis-nette` v3.1.0,
namespace `Skaut\SkautisNette`). Zvadlo bere obě knihovny přes composer `vcs` repozitáře z GitHubu, dokud
nevyjdou na Packagistu. Analýza a návrh jsou v [knihovny skautIS](skautis-knihovny-3.1.md). Rozhraní
`SkautisGateway` zůstává jako jediné místo, kde aplikace s jádrem mluví; díky fixturám na úrovni webové služby
má jedinou implementaci.
