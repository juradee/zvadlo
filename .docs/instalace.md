# Instalace pro lokální vývoj

Na počítači stačí Docker (rootless i rootful) a `make`. PHP, Composer ani Node nejsou potřeba, vše běží
v kontejnerech.

## Doména

Dokud Zvadlo nemá vlastní skautIS AppID, používá doménu a AppID h.skautingu. Do `/etc/hosts` patří:

```
127.0.0.1   moje-zvadlo.cz
```

skautIS po přihlášení posílá POST na adresu registrovanou u AppID (`/sign/skaut-i-s`), takže při ladění
přihlášení musí být stack h.skautingu zastavený; oba používají port 80.

## První spuštění

```bash
make init      # od nuly: obrazy, smazání cache, composer, npm, assety, reset databáze (data se ztratí)
make up        # start stacku
```

| Adresa | Co |
|---|---|
| http://moje-zvadlo.cz | aplikace |
| http://mail.localhost | Mailpit, veškerá odchozí pošta ve vývoji |
| http://adminer.localhost | databáze (server `mariadb`, uživatel a heslo `zvadlo`) |
| http://traefik.localhost | Traefik dashboard |
| http://selenium.localhost | náhled prohlížeče při `make test-acceptance` |

Lokální přepisy patří do `.env.local` (mimo git). Rootless Docker zapněte v git-ignorovaném `make.local`:

```
DOCKER_ROOTLESS=1
```

## Práce

```bash
make help              # všechny cíle
make assets-watch      # Vite build při změně frontendu
make migration         # migrace z rozdílu entit
make migrate
make console c="migrations:status"
make enter             # shell v PHP kontejneru
make clean-cache       # smaže vše v temp/ (cache i sessions, odhlásí); po přidání presenteru nebo změně konfigurace
```

Testy a kontroly:

```bash
make check-cs          # php-cs-fixer, opravuje
make check-phpstan
make check-latte
make test-unit
make test-integration  # proti testovací databázi, skautIS z fixtur
make test-acceptance   # Selenium s viditelným prohlížečem
make ci                # vše, co běží v GitHub Actions
```

Jedna varianta akceptačních testů běží i na šířce telefonu (390 px): `vendor/bin/codecept run acceptance --env mobile`
uvnitř kontejneru `php-test` se spuštěným Seleniem.

`make fixtures` nejdřív smaže data ve vývojové databázi a potom nahraje ukázkové akce a přihlášky.

Do prvního nasazení se migrace neskládají přes `ALTER TABLE`: při změně schématu se stávající migrace smažou,
`make migration` vygeneruje jednu čistou a databáze se resetuje (`make db-reset`).

Účty pro ruční zkoušení proti testovacímu skautISu a scénáře k ověření jsou v [skautis-testovaci-ucty.md](skautis-testovaci-ucty.md).

Testy nikdy nevolají skutečný skautIS: v prostředí `ci` odpovídá fixture továrna rozšíření `skaut/skautis-nette`
z `vendor/skaut/skautis-nette/resources/fixtures`. Přihlášení v akceptačních testech posílá stejný POST jako
skautIS (viz `tests/Support/Helper/Acceptance.php`).

## E-maily a fronta

- Ve vývoji a v CI jde veškerá pošta do Mailpitu (`http://mail.localhost`), odesílací Gmail účet se nevyžaduje.
- Frontu zpracovává `bin/console tasks:run` (nejvýš 50 s). V provozu ji spouští plánovač: cron každou minutu volá
  `bin/console scheduler:run`, který pouští i denní upozornění na zámek úprav. Ve vývoji totéž dělá služba
  `scheduler` (startuje s `make up`, výstup v `make logs`); pozvánky a automatické e-maily tak do minuty dojdou
  do Mailpitu. Hned frontu prožene `make console c="tasks:run"`.
- Propojení Gmail účtu potřebuje OAuth klienta Googlu: JSON s přihlašovacími údaji uložte do souboru podle
  `GOOGLE_CREDENTIALS_FILE` (výchozí `app/config/google-credentials.json`, je v `.gitignore`) a v konzoli Googlu
  povolte návratovou adresu `GOOGLE_REDIRECT_URI` (výchozí `APP_BASE_URL/google/token`). Bez souboru stránka
  nastavení jednotky propojení nenabízí.

## Administrace a hlášení problémů

- `ADMIN_USER_IDS`: čárkami oddělená skautIS ID uživatelů, kteří mají roli `admin` bez ohledu na databázi
  (nouzový přístup; ve vývoji sem dejte své ID). Ostatní správce přidáte v `/admin/uzivatele`.
- `ERROR_EMAILS`: adresy, kam chodí hlášení technických chyb z patičky („Nahlásit problém“). V `dev` a `ci`
  končí v Mailpitu.
- `GITHUB_ISSUES_TOKEN`, `GITHUB_ISSUES_OWNER`, `GITHUB_ISSUES_REPOSITORY`, `GITHUB_ISSUES_LABELS`: bez tokenu
  se issue nezakládá a detail hlášení to řekne; token patří jen do `.env.local`.
- Screenshoty hlášení leží v `uploads/bug-report-screenshots/` mimo web root.

## PWA lokálně

Service worker běží jen v bezpečném kontextu a `http://moje-zvadlo.cz` jím není. Pro ruční zkoušení v Chrome
zapněte `chrome://flags/#unsafely-treat-insecure-origin-as-secure` s hodnotou `http://moje-zvadlo.cz`
(Selenium v testech má stejný přepínač v `tests/acceptance.suite.yml`). Po `make assets` je worker na `/sw.js`,
manifest na `/manifest.webmanifest`, offline stránka na `/offline`; uloženou kopii seznamu přihlášených otevřete
i online přes `?offline=1`. `make check-types` zkontroluje TypeScript aplikace i workeru.

## Provoz lokálně

- `make console c="registrations:anonymize --dry-run"` ukáže, co by retence smazala; bez `--dry-run` to udělá.
- `make console c="logs:rotate"` prohne rotaci logů nad `log/`.
- `MAINTENANCE_MODE=true` v `.env.local` ukáže stránku odstávky; svou adresu (`127.0.0.1`, případně rozsah
  dockerové sítě, např. `172.16.0.0/12`) dejte do `MAINTENANCE_ALLOWED_IPS`, abyste aplikaci dál viděli.
- `/health` vrací stav databáze a release; `SENTRY_DSN` se lokálně nenastavuje.
- `make audit` zkontroluje známé zranitelnosti závislostí (potřebuje síť).

Nasazení na servery popisuje [nasazeni.md](nasazeni.md).

## PHP 8.5

`make build PHP=8.5` sestaví obrazy s PHP 8.5; `make ci PHP=8.5` pustí totéž co CI na druhé verzi.
