# Zvadlo – implementační plán

Plán vychází ze [specifikace](specifikace.md). Řadí práci do fází tak, aby po každé fázi existovala
funkční, otestovaná a nasaditelná aplikace. Fáze na sebe navazují; uvnitř fáze lze úkoly dělat paralelně.

## Zásady

- **Keep it simple.** Žádné command/query busy, žádné agregáty s doménovými událostmi, žádné vlastní
  PHPStan rozšíření. Entity + repozitáře + služby + presentery. h.skauting je architektonicky bohatší,
  protože řeší účetnictví; registrace to nepotřebuje.
- **Best practice má přednost před kopírováním.** Kde h.skauting nese historickou zátěž (ručně sestavený
  EntityManager, anotace vedle atributů, webpack, `db_fix.php`), Zvadlo použije standardní cestu.
  Kde specifikace říká „stejně jako v h.skautingu“, přebírá se **chování**, ne nutně kód.
- **skautIS za rozhraním.** Jediná třída mluví se SOAP klientem. Vše ostatní pracuje s DTO. Testy běží
  na falešné implementaci z JSON fixtur.
- **Každá fáze končí zelenou CI** (cs, phpstan, latte, unit, integration, acceptance) a aktualizovanou
  dokumentací v `.docs/`.
- **Mobile-first.** Každá obrazovka se dělá nejdřív pro telefon (390 px), pak pro desktop. Veřejné stránky
  mají akceptační test i v mobilním viewportu. PWA je samostatná fáze 8, ne podmínka MVP.
- **PHP 8.5 ready od prvního commitu.** Žádná závislost s horní hranicí pod 8.5, CI matice 8.4 + 8.5.
- **Čtení ze skautISu synchronně, úlohy uživatele do fronty.** Čtení ze skautISu běží v požadavku, u většího
  objemu se donačítá AJAXem po dávkách. Úlohy zadané uživatelem, které mohou trvat déle než 30 s (rozeslání
  pozvánek, propsání účastníků), jdou do fronty v databázi (`task`) zpracovávané konzolovým příkazem
  z plánovače; HTTP požadavek jen úlohu založí (specifikace, kapitola 12). Žádný Messenger ani externí broker.
- **Databáze ve 3NF.** Žádné seznamy v jednom sloupci, žádné odvozené sloupce; JSON jen pro neprůhledné
  dokumenty (parametry úlohy, diff v logu, diagnostika hlášení). Každá migrace se proti tomu kontroluje v review.

## Technický základ

| Oblast | Volba | Poznámka |
|---|---|---|
| PHP | **8.4** v Dockeru a na hostingu, kód i knihovny musí běžet na **8.5** | `composer.json`: `"php": "^8.4"`; CI matice 8.4 + 8.5; oficiální obrazy `php:8.4-fpm` / `php:8.5-fpm` s `install-php-extensions`, ne `thecodingmachine` |
| Framework | Nette Application **3.3** (PHP 8.3–8.5) s `nette/utils` 4, `forms`, `security`, `http`, `mail`, `caching`, `di`, `bootstrap`; Latte **3.1**; Tracy 2.10+ | žádná závislost nesmí stahovat `nette/utils` 3 |
| ORM | `nettrine/orm` ^0.10, `nettrine/dbal`, `nettrine/migrations`, `nettrine/fixtures`; Doctrine ORM 3 + DBAL 4, mapování atributy | bez ručně sestaveného EntityManageru |
| DB | MariaDB 11 LTS, `utf8mb4_czech_ci` | h.skauting má MySQL 8; MariaDB je požadavek zadání |
| skautIS | jádro `skaut/skautis` ^3.1 a rozšíření `skaut/skautis-nette` ^3.1 (obojí z GitHubu `skaut/*` přes composer `vcs`, na Packagistu zatím nejsou), za rozhraním `SkautisGateway` | podprojekt níže; starší vydané verze blokují PHP ≥ 8.4, viz rozhodnutí 11 ve specifikaci |
| Licence | AGPL-3.0-or-later | `LICENSE` v kořeni, hlavička v `composer.json` |
| Konzole | `contributte/console`, `contributte/scheduler` | `bin/console`, cron `scheduler:run` každou minutu |
| Fronta | vlastní tabulka `task` (+ `task_registration`, log `email_message`), příkaz `tasks:run` z plánovače, atomické převzetí úlohy, dávky po 20–25 položkách, limit 50 s na běh | jen úlohy uživatele; bez Messengeru, RabbitMQ a Redisu |
| Google | `google/apiclient` jen se službami `Gmail`, `Oauth2` | shodné s h.skautingem |
| Frontend | Vite 7, TypeScript, Bootstrap 5.3, Bootstrap Icons, naja; **npm** (`npm ci`, `package-lock.json`); Node **24 LTS** (26 po 28. 10. 2026) | zdroje `frontend/`, výstup `www/assets/` + `manifest.json`, Latte helper `{asset 'app.ts'}`; mobile-first |
| Export | `phpoffice/phpspreadsheet` | XLSX seznamu přihlášených |
| Markdown | `league/commonmark` s bezpečným režimem (bez HTML) | popis akce, bloky miniwebu, těla e-mailů |
| Logování | Tracy + Monolog (rotující soubor), volitelně Sentry | jako h.skauting |
| Pošta ve vývoji | Mailpit (docker, SMTP 1025, UI a REST API) + `nextras/mail-panel` v Tracy liště | v `dev`/`ci` jde všechna pošta sem; `SmtpMailer` z `nette/mail` |
| Kvalita | `php-cs-fixer` (`@Symfony`, stejná pravidla jako h.skauting), PHPStan **2** level 8 s `phpstan-nette`, `phpstan-doctrine`, `latte-lint` | |
| Testy | Codeception **5.3+** (má opravy pro PHP 8.5): `unit`, `integration` (NetteDIModule + DB), `acceptance` (WebDriver, Selenium Chrome, desktop i mobilní viewport) | |
| CI/CD | GitHub Actions; Deployer 7 (pokud zůstává hosting h.skautingu) | |

### Struktura repozitáře

```
rs/
├── .agents/            pokyny pro agenty (AGENTS.md)
├── .docs/              dokumentace pro lidi (specifikace, plán, instalace, nasazení)
├── .github/workflows/  main.yml (kontroly, testy, deploy), git.yml (blokace fixup commitů)
├── app/
│   ├── Bootstrap.php, Environment.php
│   ├── config/         config.neon, config.{dev,ci,beta,test,prod}.neon, services.neon, doctrine.neon, skautis.neon, mail.neon
│   ├── Model/
│   │   ├── Event/       Event, EventField, EventContent, EventEmail, EventRepository, EventService, SlugGenerator
│   │   ├── Registration/ Registration, FieldValue, RegistrationLog, RegistrationService, TokenService, Anonymizer
│   │   ├── Mailing/     EmailMessage, TemplateRenderer (%symboly%), MailQueue, MailerFactory, GoogleOAuth, OAuthMailer
│   │   ├── Task/        Task, TaskRepository, TaskRunner, TaskHandler (interface), Handlers/ (SendInvitations, SendReminder, SendInfo, SendEmail, SendLockNotice, SyncParticipants)
│   │   ├── Skautis/     SkautisGateway (interface), SoapSkautisGateway (jediná implementace nad jádrem), DTO/, Authorizator/
│   │   ├── User/        SkautisRole, UserAccess, SystemUserRole, UserPreference
│   │   ├── BugReport/   TechnicalErrorReport, BugReportService, GitHubIssueService (port z h.skautingu)
│   │   ├── Admin/       AdminAccessChecker, Statistics
│   │   ├── Hskauting/   HskautingNotifier (push propsaných akcí do h.skautingu), WebhookSignature
│   │   └── Auth/        Authorizator, Resources
│   ├── Presentation/
│   │   ├── Auth/        přihlášení, odhlášení, přepínání role
│   │   ├── Dashboard/   přehled akcí
│   │   ├── Events/      Event, Invitations, Registrations, Emails, Web, Fields, Skautis
│   │   ├── Public/      miniweb a přihláška (bez loginu)
│   │   ├── Settings/    nastavení jednotky, Google
│   │   ├── Admin/       Users, BugReports, Statistics, Units
│   │   └── @layout.latte, Error/
│   └── Router/RouterFactory.php
├── bin/console
├── docker/             docker-compose.yml, docker-compose.ci.yml, containers/{php,nginx,mariadb,traefik}
├── frontend/           app.ts, scss/, ts/
├── migrations/
├── tests/              unit/, integration/, acceptance/, _support/, _data/, fixtures/skautis/*.json
├── www/                index.php, assets/ (build), .htaccess
├── Makefile, composer.json, package.json, vite.config.ts, tsconfig.json
├── phpstan.neon, .php-cs-fixer.dist.php, codeception.yml
└── .env, .env.ci, .env.local.example, .env.test.local.example
```

### Proměnné prostředí

| Proměnná | Význam | Výchozí |
|---|---|---|
| `APP_ENV` | `dev` / `ci` / `beta` / `test` / `prod` | `dev` |
| `APP_BASE_URL` | veřejná adresa instance | `http://moje-zvadlo.cz` do vyřízení vlastního AppID, potom vlastní dev doména |
| `APP_SECRET` | klíč pro šifrování refresh tokenů (32 B, base64) | povinné mimo `dev` |
| `APP_RELEASE_HASH` | verze pro Sentry a hlášení chyb | `dev` |
| `APPLICATION_ID` | skautIS AppID (veřejný identifikátor, parametr `appid` v URL přihlášení) | povinné |
| `SKAUTIS_TEST_MODE` | testovací skautIS | `true` mimo `prod` |
| `SKAUTIS_FIXTURES` | cesta k JSON fixturám místo SOAP (fixture továrna balíčku) | `tests/fixtures/skautis` v `ci`, jinak prázdné |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | databáze | dockerové hodnoty |
| `DB_SERVER_VERSION` | verze databázového serveru pro Doctrine; prázdné = zjistí se připojením | prázdné |
| `SEND_EMAIL` | `true` = Gmail API a sendmail; `false` = vše přes SMTP do mailcatcheru | `false` v `dev`/`ci` |
| `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT` | mailcatcher pro `SEND_EMAIL=false` | `mailpit`, `1025` |
| `SYSTEM_EMAIL` | odesílatel systémové pošty přes sendmail (hlášení chyb, odpovědi správců) | `noreply@<host APP_BASE_URL>` |
| `ERROR_EMAILS` | příjemci chyb serveru a hlášení problémů | – |
| `GOOGLE_CREDENTIALS_FILE`, `GOOGLE_REDIRECT_URI` | Gmail OAuth | `app/config/google-credentials.json`, `{APP_BASE_URL}/google/token` |
| `GITHUB_ISSUES_TOKEN`, `GITHUB_ISSUES_OWNER`, `GITHUB_ISSUES_REPOSITORY`, `GITHUB_ISSUES_LABELS` | hlášení chyb → GitHub | `juradee` / `zvadlo` / `bug,user-report` |
| `HSKAUTING_WEBHOOK_URL`, `HSKAUTING_WEBHOOK_SECRET` | push propsaných akcí do h.skautingu (9.4); prázdné = vypnuto | – |
| `TEST_BACKGROUND`, `ENVIRONMENT_LABEL`, `ENVIRONMENT_COLOR` | banner prostředí | dle `APP_ENV` |
| `TRACY_SHOW_BAR` | Tracy lišta | `true` v `dev` |
| `SENTRY_DSN` | Sentry | – |
| `DB_FIX_TOKEN_SHA256` | GitHub secret pro jednorázový HTTP endpoint migrací při deployi | `openssl rand -hex 32` |
| `MAINTENANCE_MODE`, `MAINTENANCE_ALLOWED_IPS`, `MAINTENANCE_STARTED_AT`, `MAINTENANCE_ENDS_AT` | údržba: stránka odstávky, povolené IP/CIDR, ISO časy pro stránku | `false` |
| `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_PREPARE_PORT`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_PHP_BIN`, `DEPLOY_WEB_ROOT`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_PREPARE_KNOWN_HOSTS` | jen v GitHub environmentu pro `deploy.php`; přípravný port nejprve zpřístupní finální SSH port | – |
| `RETENTION_MONTHS_SYNCED` | anonymizace nečlenů propsaných do skautISu a mazání hodnot polí | `12` |
| `RETENTION_MONTHS_UNSYNCED` | anonymizace nečlenů nepropsaných do skautISu | `24` |

### Docker a Makefile

Služby: `traefik` (dev doména z proměnné `DEV_HOST`, zatím `moje-zvadlo.cz` → nginx), `php` (oficiální
`php:8.4-fpm` + Composer), `php-test` (`APP_ENV=ci`, stejný obraz; proměnná `PHP_VERSION` přepne na 8.5),
`node` (`node:24`, jen pro `npm` a Vite; na hostiteli Node není), `nginx`, `mariadb`, `mariadb-test` (tmpfs),
`mailpit` (UI `mail.localhost`), `selenium`, `adminer`.
Dokud Zvadlo sdílí doménu a AppID s h.skautingem, nemohou oba stacky běžet na portu 80 zároveň; skautIS
posílá POST na `moje-zvadlo.cz`, takže při ladění přihlášení musí být stack h.skautingu zastavený
(`TRAEFIK_HOST_PORT` jiný port nepomůže, skautIS o něm neví). Makefile s cíli
`build`, `up`, `down`, `init`, `enter`, `composer-install`, `fixtures`, `assets`, `assets-watch`,
`test-unit`, `test-integration`, `test-acceptance`, `ci-acceptance`, `check-cs`, `check-cs-check`,
`check-phpstan`, `check-latte`, `test-mapping`, `fix`, `ci`, `help`. Konvence a názvy jako v h.skautingu,
aby vývojář nepřepínal hlavu.

### CI (GitHub Actions)

`main.yml`: na PR a push do `master` a `beta/**`.

1. `workdir`: composer install, `yarn build`, tarball.
2. `quick-checks`: cs (dry-run), phpstan, latte-lint, validace mapování migrací; `composer install` a phpstan
   běží v matici **PHP 8.4 a 8.5**, aby se každá závislost bez podpory 8.5 objevila hned.
3. `tests`: matice `unit`, `integration` (obě na PHP 8.4 i 8.5), `acceptance` (8.4, headless, falešný skautIS,
   bez tajemství, desktop i mobilní viewport).
4. `deploy-beta` (větve `beta/**`), `deploy-test` (`master`), `deploy-prod` (`master`, GitHub environment se schválením).

`git.yml`: blokace fixup commitů. Dependabot pro composer, npm a actions.

## Podprojekt: jádro skautIS 3.1 a Nette napojení

Vydané verze jádra `skautis/skautis` (2.3.0, 3.0.0-alpha.5) i rozšíření `skautis/nette` blokují PHP ≥ 8.4.
Rozhodnutí: jádro dostalo **verzi 3.1** ve forku `webwingscz/Skautis` (složka `Skautis` vedle `rs`,
navazuje na větev `3.x`, API a namespace `Skaut\Skautis` beze změny) a Nette napojení vzniklo ve fázi 0 jako
interní balíček `packages/skautis-nette`: DI extension se stejnými klíči jako `skautis/nette`, session adaptér
nad Nette Session, jeden PSR-14 dispatcher pro panel i logování, Tracy panel a fixture továrna, díky které má
Zvadlo jedinou implementaci `SkautisGateway` a testy jedou bez skautISu. Obojí šlo upstream a 7. 10. 2026 vyšlo
jako `skaut/skautis` v3.1.0 a `skaut/skautis-nette` v3.1.0 (SkautisNette 3.1, namespace `Skaut\SkautisNette`,
přejmenovaný z `Skautis\Nette`). Zvadlo od té doby používá vydané verze z GitHubu (`vcs` repozitáře, na Packagistu
zatím nejsou) a interní balíček je smazaný.

Analýza, srovnání větví 2.x a 3.x, seznam změn jádra, návrh balíčku a pořadí prací jsou
v [knihovny skautIS](skautis-knihovny-3.1.md). Klíčové body:

- větev 3.x jádra není za 2.x pozadu (všechny opravy z 2.x tam jsou nebo se jí netýkají);
- název balíčku zůstává `skaut/skautis`, jak ho větev 3.x přejmenovala (Packagist zná zatím jen starší `skautis/skautis`); Zvadlo vyžaduje `skaut/skautis ^3.1`;
- opravy pro PHP 8.4: implicitně nullable parametry, `Serializable` na událostech, `isMaintenance` pro HTTP/2,
  širší rozpoznání SOAP faultů, typované vlastnosti, PHPStan 2 bez výjimek, PHPUnit 11, CI na 8.4 + 8.5;
- Zvadlo bere jádro i rozšíření přes composer `repositories` typu `vcs` z GitHubu (`skaut/Skautis`,
  `skaut/SkautisNette`), dokud nevyjdou na Packagistu; do 7. 10. 2026 bralo napojení jako interní balíček
  přes `path` se symlinkem.

Hotovo, když: jádro projde CI na PHP 8.4 a 8.5, balíček má vlastní testy zelené, Zvadlo obojí nainstaluje
přes composer a přihlášení funguje přes testovací skautIS (`dev`) i přes fixtury (`ci`).

## Fáze

### Fáze 0 – Kostra projektu

Cíl: prázdná Nette aplikace, která běží v Dockeru, má CI a přihlašuje přes skautIS.

- Repozitář: `composer.json`, `package.json`, `Makefile`, `docker/`, `.env*`, `.gitignore`, `README.md`,
  `.docs/instalace.md`, licence.
- `App\Environment` (port z h.skautingu, zjednodušený), `Bootstrap.php`, `config.neon` + per-env neony.
- Doctrine (nettrine), první migrace (prázdná), `make test-mapping`.
- Pošta: `MailerFactory` (`SEND_EMAIL=false` → `SmtpMailer` na Mailpit, jinak Gmail/sendmail), Mailpit
  v compose, `nextras/mail-panel` v Tracy liště v `dev`.
- Vite (npm, Node 24): `frontend/app.ts` (Bootstrap, naja, netteForms), `manifest.json`, Latte filtr/tag pro assety.
  Layout s lištou, bannerem prostředí, patičkou s „Nahlásit problém“ (zatím odkaz na placeholder); responzivní
  od začátku, navigace na telefonu sbalená.
- skautIS: rozšíření `skautis-nette` (ve fázi 0 interní balíček, dnes `skaut/skautis-nette` 3.1; extension
  v `config.neon`, `applicationId`, `testMode` a `fixtures` z `.env`), jádro 3.1 přes `vcs`; nad tím
  `SkautisGateway` rozhraní a `SoapSkautisGateway` (obaluje `Skaut\Skautis\Skautis`); fixtury pro `ci`
  z `resources/fixtures` rozšíření
  (`UserDetail`, `UserRoleAll`, `UnitDetail`, `UnitAll`, `PersonAll`, `PersonContactAll`, `PersonParentAll`).
  Přihlášení, odhlášení, přepínání role, prodlužování tokenu, identita s `read`/`edit` jednotkami.
- Autorizátor s `Event`/`Unit`/`Admin`/`BugReports` zdroji.
- Codeception: tři suity, `_support`, spuštění v CI; první acceptance test „přihlášení falešným skautISem“.
- CI workflow bez deploy jobů.

Hotovo, když: `make init && make up` zobrazí přihlašovací stránku, `make ci` projde, přihlášení přes
testovací skautIS funguje v `dev` a přes fixtury v `ci`.

### Fáze 1 – Akce

Cíl: vedoucí založí, upraví a vidí akce své jednotky.

- Entity `Event`, `EventField`, migrace, fixtury pro vývoj.
- Dashboard: seznam akcí jednotek v `read`, filtr rok/stav, upozornění na kolizi termínů.
- Formulář akce (Bootstrap 5 renderer), číselníky typ/rozsah ze skautISu (cache), generátor slugu,
  stavy akce a přechody, zámek úprav v nastavení.
- Vlastní pole: správa (přidat, upravit, skrýt, seřadit), validace „nelze měnit po vyplnění“.
- Oprávnění: `edit` jednotky pro zápis, `read` pro čtení, jinak 403 s vysvětlením.
- Testy: unit (slug, přechody stavů, zámek), integration (repozitář), acceptance (založení akce).

### Fáze 2 – Pozvání a přihláška

Cíl: kompletní tok pozvánka → osobní odkaz → přihláška → správa.

- Entity `Registration`, `RegistrationRecipient`, hodnoty polí (`RegistrationTextValue`, `RegistrationBooleanValue`,
  `RegistrationOptionValue`), `RegistrationLog`, migrace.
- Výběr pozvaných: členové jednotky a podřízených jednotek (`PersonAll`, synchronně, cache), filtr, hromadný
  výběr, ruční nečlen. Uložení pozvánek s tokeny; kontakty (`PersonParentAll` → `PersonContactAll` → ručně)
  se donačítají AJAXem po dávkách 10 osob se snippetem průběhu, nedokončené se doplní při příštím otevření
  stránky; přehled pozvaných bez e-mailu.
- Veřejný modul: ověření datem narození, blokace po pokusech, formulář přihlášky (účast, údaje, vlastní pole,
  souhlas), souhrn, úprava a zrušení do zámku, stránky pro uzavřenou akci a neplatný odkaz (404).
- Kapacita a čekací listina.
- Správa přihlášek: seznam s filtry, detail s historií, ruční změny stavu, dodatečné pozvání, export XLSX.
- Testy: unit (stavový automat, tokeny, zámek, kapacita), integration (repozitáře, anonymizace),
  acceptance (rodič vyplní přihlášku, odmítne, zruší; vedoucí vidí stavy).

Po fázi 2 lze systém používat bez e-mailů (odkazy si vedoucí zkopíruje). To je první interní milník.

### Doplnění fáze 2 (zadáno 30. 9. 2026)

Zadavatel upřesnil postup přidávání účastníků podle plateb v h.skautingu (specifikace 4.1, 4.3, 5.1, Rozhodnutí
18–21). Oproti hotové fázi 2 je třeba:

- **Tábor ze skautISu**: v „Nová akce“ nahradit příznak „Jde o tábor“ výběrem ze seznamu `EventCampAll` pro
  jednotky, které role spravuje; název, termín a místo převzít ze skautISu (jen ke čtení, obnova na vyžádání),
  uložit `skautis_camp_id`. Fixtura `Events/EventCampAll.json` už existuje.
- **Adresy pozvánky**: `registration_recipient` rozšířit o typ (`mother`, `father`, `main`, `other`, `manual`)
  a příznak `selected`; ukládat všechny nalezené adresy, výchozí výběr podle věku; v seznamu přihlášek výběr
  po pozvaném a hromadné přepínače typů jako v `MassAddForm` h.skautingu.
- **Nečlen e-mailem**: přidání jen e-mailem (jméno nepovinné, hromadně po řádcích), ve veřejné části ověření
  zadáním e-mailu místo data narození, blokace po 5 pokusech; seznam zobrazuje e-mail, dokud není jméno.
- **`invited_at`** u přihlášky a rozlišení „pozván – neodesláno / odesláno“ v seznamu, počtech a filtrech.
- **Stav zobrazeno** při otevření odkazu (před ověřením), ne až po ověření.
- **Token** (rozhodnutí 18): šifrované uložení (`sodium_crypto_secretbox`, klíč z `APP_SECRET`) + hash;
  detail přihlášky odkaz zobrazuje bez obnovy, „Vygenerovat nový odkaz“ jen jako výslovná obnova s varováním.
- **Veřejné přihlašování** (4.4): příznak akce, stránka `/{unitId}/{slug}/prihlasit?t=…`, honeypot a limit na IP,
  původ `public`, duplicitní kontrola.
- **Párování člena z veřejného odkazu** (4.5, schváleno): tabulka otisků `unit_member_fingerprint`
  obnovovaná při otevření přihlášek vedoucím, porovnání při odeslání veřejného formuláře, příznaky „možná shoda“
  (příznak „uvádí členství“ a zaškrtávátko členství později odstraněny), tlačítko „Spárovat“ s návrhy podle data narození v seznamu přihlášek.
- **Stav zamítnuto** (5.1): akce vedoucího „Zamítnout“ s důvodem, okamžitá anonymizace, `email_hash`, blokace
  opakovaného veřejného přihlášení, vyloučení z počtů, exportu, e-mailů, propsání a API; e-mail `rejected`
  ze společné šablony jednotky se renderuje před anonymizací (odeslání ve fázi 3).
- **Režim veřejného přihlašování** `none` / `open` / `skautis` místo příznaku; v režimu `skautis` veřejná stránka
  jen s tlačítkem přihlášení přes skautIS; osoby mimo strom jednotky povolené, štítek „mimo jednotku“.
- **Přihlášení účastníka přes skautIS** (4.6): backlink `p:{unitId}:{slug}` přes `ReturnUrl`, větev v
  `Auth:skautIS` bez identity vedoucího, volání `UserDetail` + `PersonParentAllPersonChildren`, stránka „Koho
  přihlašujete?“, účastnická session na hodinu, předvyplněný formulář. Nejdřív ověřit oprávnění na testovacím
  skautISu s účty od zadavatele (člen 15+, rodič s dítětem); fixtury `PersonParentAllPersonChildren` do balíčku.
- **Kód pro úpravu přihlášky nečlena** (3, 24) a **odeslání pozvánek s připomínkou** patří do fáze 3, protože
  potřebují odesílání e-mailů; do té doby nečlen vyplněnou přihlášku upravovat nemůže (stránka to vysvětlí).

#### Problémy postupu registrace pozvaných (nález 30. 9. 2026)

1. Hash tokenu bez možnosti ho zobrazit se neslučuje s připomínkou „stejný e-mail“: každé odeslání by
   zneplatnilo odkaz z předchozího e-mailu (druhý rodič, záložka, tlačítko vedoucího). Rozhodnuto: token je
   stálý, ukládá se šifrovaný (rozhodnutí 18).
2. Stav „pozván“ dnes vzniká při přidání, ještě před odesláním; nejde rozlišit „nikdy nedostal pozvánku“ od
   „dostal a neotevřel“, přitom připomínka smí jít jen druhé skupině. Řešení: `invited_at`.
3. Adresy se dnes doplňují až po přidání a vedoucí nevybírá, kam pozvánka půjde. Rozhodnuto: vedoucí vybírá
   u každého člena e-mail (otec, matka, hlavní), oba rodiče dostanou stejný odkaz (rozhodnutí 19).
4. Ověření nečlena e-mailem je slabší než datum narození (přeposlaný e-mail nese odkaz i adresu). Rozhodnuto:
   pro první vyplnění stačí, protože se neukazují osobní údaje; úprava vyplněné přihlášky vyžaduje kód
   na e-mail (rozhodnutí 21, 24).
5. Připomínka jen pro „pozván“ mine ty, kdo odkaz otevřeli a nevyplnili. Rozhodnuto: zůstává jen pro pozván
   s odeslanou pozvánkou (rozhodnutí 20).
6. Sourozenci: rodič dostane N e-mailů s N odkazy a při ověření musí trefit datum narození správného dítěte.
   Rozhodnuto: stránka i e-mail vždy uvádějí jméno dítěte (rozhodnutí 18). Jeden e-mail na adresu se seznamem
   dětí zůstává jako možné pozdější zlepšení.
7. Nečlen přidaný jen e-mailem bez jména. Rozhodnuto: pozvánka nečlenovi je výjimka pro jednotlivce; nečleni
   na akcích pro veřejnost se hlásí sami veřejným odkazem (rozhodnutí 23).
8. Připomínka ani pozvánka nemají smysl po uzávěrce nebo u uzavřené akce; odeslání se musí odmítnout s vysvětlením
   (dnes by úloha vznikla).
9. Tábor založený ručně (dnešní příznak) by neměl `skautis_camp_id` a účastníci by se nikam nepropsali;
   ruční založení tábora se ruší (rozhodnutí 19).
10. Osobní odkaz je v URL veřejných stránek; jakmile na nich bude odkaz na miniweb nebo externí odkazy, uniká
    přes `Referer`. Nastavit `Referrer-Policy: same-origin` (drobnost, fáze 4).

### Fáze 3 – E-maily

Cíl: pozvánky a potvrzení odcházejí z Gmail účtu jednotky.

- `unit_email_template`, `event_email`, výchozí texty v repozitáři, editor s nápovědou symbolů,
  náhled s dosazenými hodnotami, testovací e-mail sobě.
- `TemplateRenderer` (`%symbol%` → hodnota, Markdown → HTML + textová alternativa).
- Gmail OAuth: nastavení jednotky, `/google/oauth`, `/google/token`, uložení šifrovaného refresh tokenu,
  odebrání účtu, `OAuthMailer`, výběr účtu u akce (bez účtu nelze akci otevřít), `Reply-To` na kontaktní
  osobu akce. Systémová pošta (hlášení chyb) sendmailem přes `SystemMailer` jako v h.skautingu.
- Fronta úloh: entita `Task`, `TaskRunner` s atomickým převzetím, dávkami a limitem 50 s, příkaz `tasks:run`
  v plánovači, panel průběhu úlohy v UI (naja polling), log `email_message` u akce a u přihlášky, opakování
  a chyby v UI.
- Úlohy `send_invitations`, `send_reminder`, `send_info` (vedoucí), `send_email` (automatické potvrzení,
  odmítnutí, změna), `send_lock_notice` (plánovač).
- Testy: unit (renderer, výběr adresáta), integration (fronta, mailer s falešným transportem),
  acceptance (odeslání pozvánky se projeví v tabulce `email_message` a v Mailpitu, ověřeno přes jeho REST API).

Po fázi 3 je systém použitelný pro první jednotku (MVP).

### Fáze 4 – Miniweb

- `EventContent`, bloky text/soubor/odkaz, řazení, nahrávání do `uploads/` mimo web root, servírování
  přes presenter s tokenem, obnovení tokenu, `noindex`.
- Veřejná stránka: hlavička z akce, bloky, odkaz na přihlášku.
- Testy: integration (úložiště souborů), acceptance (web s tokenem a bez něj).

### Fáze 5 – skautIS zápis a h.skauting

- `ActionVerify` v autorizátoru, dialog oprávnění.
- Propsání akce (`EventGeneralInsert`), uložení ID, odkazy do skautISu a h.skautingu.
- Propsání účastníků jako úloha `sync_participants`: náhled rozdílu (synchronní `ParticipantGeneralAll`), `ParticipantGeneralInsert` (člen, nečlen
  + `PersonUpdateBasic` s pohlavím, datem narození a adresou), `ParticipantGeneralDelete`, dávky po 20,
  uložení výsledků a chyb k přihláškám, průběh v UI.
- Tábory: výběr tábora jednotky (`EventCampAll`), kontrola stavu (`EventCampDetail`), `ParticipantCampInsert`
  jen ve stavech `approvedLeader` / `approvedParent`; před implementací ověřit na ws.skautis.cz, že
  `EventCampInsert` opravdu neexistuje, a potvrdit se zadavatelem přesný stav „odevzdaný ke schválení“.
- Push do h.skautingu (`HskautingNotifier`): jednou za minutu jedna podepsaná dávka propsaných akcí, které se
  změnily, jen neosobní data, odklad po chybě, `hskauting:push --all` (specifikace 9.4, rozhodnutí 35; nahradilo
  pull API).
- Pull request do h.skautingu: endpoint s ověřením podpisu a tabulkou záznamů Zvadla, odznak a sloupec ze
  své databáze (oprávnění uživatele k akci ověřuje h.skauting sám jako dosud).
- Testy: unit (diff účastníků, mapování DTO, podpis), integration (falešný gateway zaznamenává volání, push
  s falešným HTTP), acceptance (založení akce ve skautISu).

### Fáze 6 – Administrace a hlášení problémů

- Port z h.skautingu: `SystemUserRole`, `AdminAccessChecker`, `/admin`, `/admin/uzivatele`.
- Hlášení problémů 1:1: formulář, diagnostika klienta (TypeScript), služba, entity, e-mail správcům,
  detail v administraci, odpověď, GitHub issue, vyřešení/zamítnutí, e-maily hlasiteli.
- Statistiky, přehled jednotek.
- Testy: unit (přístup), integration (uložení hlášení, GitHub klient s falešným HTTP), acceptance (odeslání hlášení).

### Fáze 7 – Provoz

- Deployer (`deploy.php`) a deploy joby; GitHub environments `beta`, `test`, `production` s proměnnými.
- Režim údržby, Sentry, rotace logů, `RETENTION_MONTHS_*` a příkaz `registrations:anonymize`.
- `.docs/nasazeni.md`, `.docs/instalace.md` dokončené, nápověda pro vedoucí (krátká stránka v aplikaci).
- Zátěžová kontrola fronty e-mailů (200 adresátů), audit závislostí (`composer audit`, Dependabot).

### Fáze 8 – PWA pro vedoucí

- Manifest (název, ikony, `display: standalone`, barva), generovaný presenterem kvůli URL ikon s digestem.
- Service worker: *network first* pro stránky, cache jen pro skořápku a assety s digestem, offline stránka,
  a poslední zobrazený seznam přihlášených každé akce (jen pro přihlášeného vedoucího, mazaný při odhlášení).
- Výzva k instalaci v administraci, nikdy na veřejných stránkách.
- Testy: acceptance (manifest a SW se servírují, seznam přihlášených se zobrazí offline).

## Rizika a závislosti

| Riziko | Dopad | Opatření |
|---|---|---|
| AppID bez oprávnění na kontakty rodičů | zvadlo jde jen na e-mail dítěte | fallback v kódu; požádat o oprávnění hned na začátku (fáze 0) |
| Přesné názvy oprávnění a parametrů skautISu | chyby až při propsání | ověřit na ws.skautis.cz před fází 5; fixtury z reálných odpovědí testovacího skautISu |
| Gmail limity a odvolané tokeny | nedoručené pozvánky | fronta s opakováním, chyby viditelné v UI, výzva k opětovnému propojení účtu |
| Sdílený hosting bez SSH migrací | složitější deploy | převzít osvědčený postup h.skautingu (`db_fix.php`, ověření release přes HTTP) beze změn |
| Hádání osobních odkazů | únik údajů dítěte | 256bitové tokeny, hash v DB, datum narození, blokace, 404 |
| Jádro skautIS 3.1 a balíček napojení | fáze 0 nemá přihlášení | podprojekt před fází 0, CI jádra na PHP 8.4 + 8.5, testy balíčku, kouřový test proti testovacímu skautISu |
| Knihovna bez podpory PHP 8.5 | zablokovaná migrace | CI matice 8.4 + 8.5 od fáze 0; při výběru knihovny se kontroluje horní hranice `php` |
| Hosting bez cronu po minutě | fronta se nezpracuje | plánovač h.skautingu na stejném hostingu běží; záložně HTTP endpoint `scheduler:run` s tokenem volaný externím cronem |

## Co je potřeba od zadavatele

Před fází 0 nic nechybí: rozhodnutí jsou ve specifikaci, vývoj jede dočasně na doméně a AppID h.skautingu.

Průběžně:

1. Registrace vlastního AppID ve skautISu s oprávněními z kapitoly 9.3 specifikace a dev doména (do fáze 2,
   kdy se poprvé čtou kontakty rodičů).
2. Google OAuth klient (credentials JSON) pro `dev` a produkci (do fáze 3); postup jako u h.skautingu.
3. Potvrzení stavu tábora, ve kterém lze přidávat účastníky (do fáze 5).
4. Repozitář `github.com/juradee/zvadlo`, jemnozrnný token s právem Issues pro hlášení problémů (do fáze 6),
   GitHub environments `beta`, `test`, `production` s proměnnými a tajemstvími pro deploy (do fáze 7).
5. Potvrzení, jakou verzi PHP hosting nabízí (8.4, nebo už 8.5), do fáze 7; Deployer nastaví `bin/php` podle toho.
6. Fork jádra `webwingscz/Skautis` je naklonovaný ve složce `Skautis` (checkout `2.x`, práce jde z větve `3.x`);
   fork `SkautisNette` se zatím nepoužije. Po dokončení balíčku rozhodnutí, zda ho vydat jako nový, nebo
   z něj udělat SkautisNette 3.1.
7. Testovací účty na `test-is.skaut.cz` pro přihlášení účastníka přes skautIS (4.6): běžný člen 15+ a rodič
   vedený jako zákonný zástupce dítěte v jednotce; u AppID oprávnění k `UserDetail`, `PersonDetail`
   a `PersonParentAllPersonChildren` pro tyto role (do „Doplnění fáze 2“). Navíc člen **jiné jednotky** pro
   ověření, že `ParticipantGeneralInsert` s jeho `ID_Person` projde (do fáze 5).

## Průběh

| Datum | Stav |
|---|---|
| 29. 9. 2026 | Jádro `skaut/skautis` 3.1 hotové (v3.1.0 na GitHubu). Fáze 0 hotová: docker stack, Makefile, prostředí, Nette kostra s Vite a Bootstrapem, Doctrine přes nettrine, balíček `packages/skautis-nette` s fixturami, přihlášení přes skautIS s přepínáním role a výpočtem přístupu k jednotkám, Codeception (unit, integration, acceptance) a GitHub Actions. |
| 1. 10. 2026 | Nastavení jednotky: šablonu e-mailu lze vrátit k systémovému textu („Obnovit výchozí text“, `UnitEmailTemplateRepository::reset`); uložení textu shodného se systémovým se jako vlastní neukládá, takže odznak „vlastní text“ mají jen skutečně upravené šablony (dřív se uložením formuláře staly vlastními všechny). Test: akceptační. `make init` maže všechny cache a resetuje databázi (`make db-reset`); v textech UI je vždy „účastník“, nikdy „dítě“. |
| 1. 10. 2026 | Fronta ve vývoji: služba `scheduler` v docker compose spouští každou minutu `scheduler:run` (startuje s `make up`). AJAX požadavky na pozadí (polling úloh, dávky, načítání adres) přihlášení do skautISu neprodlužují, jen ověří (`SecuredPresenter`), po vypršení vracejí 401 s JSON; prodlužuje otevření stránky a ping keep-alive. Test: akceptační (AJAX po odhlášení dostane 401). |
| 1. 10. 2026 | Výběr e-mailů přesunut do přidání účastníků (zadavatel, jako hromadné přidání plateb v h.skautingu): stránka přidání načte adresy členů AJAXem po 10 (`MemberAddresses`, cache 1 h, nahrazuje `ContactResolver`), u každého člena je nabídne jako zaškrtávátka s výchozím výběrem podle věku a zaškrtávátka typů nad tabulkou je mění u všech najednou. Všechny e-maily členovi jdou na vybrané adresy (`Registration::getMailAddresses`), přihláška člena kontaktní e-mail nemá; nečlen má dál jeden. Seznam přihlášek ukazuje adresy a upravuje je v řádku; hromadný řádek „Adresy pozvánky u všech najednou“, donačítání kontaktů v seznamu a sloupec `recipients_resolved_at` odstraněny, migrace přegenerována. Testy: unit (`MemberAddresses`, adresy pošty), integrační (výběr při přidání, úprava v řádku, zamítnutí oběma rodičům), akceptační (výběr typem i jednotlivě, úprava v řádku). |
| 1. 10. 2026 | Opravy po zkoušce se skautIS_TEST: číselníky typu a rozsahu akce mají číselná ID (fixtury, `ScopeLevels` a testy převedeny, výchozí rozsah podle úrovně jednotky a přepnutí při změně jednotky); testy (`APP_ENV=ci`) mají vlastní `temp/ci`, takže fixturové číselníky už nepřepíšou cache vývojové aplikace; odkazy do skautISu vedou podle `testMode` na test-is nebo is (`SkautisLinks`, cesta `Junak/Events/General/Detail.aspx`); automatické e-maily mají v seznamu přihlášek jeden souhrnný panel místo panelu za každou přihlášku; volba účasti je na přihlášce první. |
| 1. 10. 2026 | Rozsah akce respektuje pořádající jednotku (`ScopeLevels`): rozsah nesmí být nižší než úroveň jednotky (středisko nebo přístav nemá akci družinovou, družina smí pořádat i střediskovou); výběr se filtruje podle zvolené jednotky v prohlížeči i na serveru, neznámé ID rozsahu nebo typu jednotky se neomezuje. Kontaktní e-mail nové akce se předvyplní z hlavního e-mailu přihlášeného vedoucího (`CurrentUserEmail`, sjednocuje dosavadní dvě kopie téhož načítání v hlášení chyb a zkušebním e-mailu). ID úrovní je třeba ověřit na testovacím skautISu. |
| 1. 10. 2026 | Oprava ručně vykreslených formulářů (`n:name`): Bootstrap třídy se dávají v `onRender` (`BootstrapFormRenderer::decorate`, spouští ho i Latte), takže tlačítka hromadného výběru adres a odesílací panel mají styl; prázdné `<label n:name>` dostaly popisky (role a dvě zaškrtávátka na přehledu, text zprávy, šablony jednotky). Hromadný výběr adres ukazuje jen typy, které pozvaní mají, s počtem, a hláška řekne, u kolika pozvaných se adresa změnila. |
| 1. 10. 2026 | Oprava: přehled akcí bez zvoleného roku ukazoval jen aktuální rok, takže akce z dalšího roku (např. 01.01.2027) byla na úvodní stránce, ale v přehledu ne. Výchozí zobrazení je teď „nadcházející“ (probíhající a budoucí akce bez ohledu na rok, od nejbližší), konkrétní rok slouží jako historie. |
| 1. 10. 2026 | Pole data mají zpět kalendář: tlačítko vedle pole `dd.mm.rrrr` otevře nativní výběr data prohlížeče (`showPicker()` na skrytém `<input type="date">`, na telefonu systémový kalendář) a vybraný den zapíše jako `dd.mm.rrrr`; zrušen zbytečný `autocomplete="off"`. |
| 1. 10. 2026 | Souběžné přihlášky: rozhodnutí o místě (přihlášen / náhradník / plná akce) se dělá při odeslání pod zámkem řádku akce (`RegistrationRepository::underCapacityLock`, `SELECT … FOR UPDATE` v transakci na spojení, ne `wrapInTransaction`, aby odmítnutí nezavřelo EntityManager), stejně u posunu náhradníka a přijetí vedoucím. Plná akce bez čekací listiny vrátí hlášku do formuláře s vyplněnými údaji (dosud chyběl `catch`), zápis na čekací listinu ohlásí varování. Integrační test zámku z druhého spojení (`FOR UPDATE NOWAIT`). |
| 1. 10. 2026 | Uzávěrka a zámek sloučeny (rozhodnutí 36): jeden limit `registration_deadline` pro podání, změnu i zrušení, prázdný = do konce akce; zámek úprav (`edit_lock_days`, zaškrtávátko, výchozí zámek jednotky, symbol `%zamek%` a jeho varování) odstraněn, `%uzaverka%` dosazuje uzávěrku nebo konec akce, upozornění na zámek je teď „Upozornění na uzávěrku“ (jen s uzávěrkou). Kapacita: volba čekací listiny (`waitlist_enabled`), bez ní plná akce odmítne (`EventFullException`); pořadí náhradníků `waitlisted_at`; odhlášení účastníkem posune prvního náhradníka a pošle nový e-mail `promoted` (upravitelná šablona); zrušení vedoucím místo nechá volné a nabídne náhradníka (panel v přehledu přihlášek); přijetí nad kapacitu jen tlačítkem s potvrzením. Toggly formulářů se přepočítávají i při psaní. Migrace přegenerována. Předchozí dva záznamy o zámku tím pozbyly platnost. |
| 1. 10. 2026 | Zámek úprav se vypíná zaškrtávátkem „Přihlášky bez časového omezení“ místo prázdného pole: skryje pole zámku a upozornění (Nette toggle), jejich hodnoty zůstávají, při zrušení zaškrtnutí se vrátí výchozí počet dní jednotky. |
| 1. 10. 2026 | Zámek úprav lze vypnout (prázdné pole, `event.edit_lock_days` nullable): úpravy a veřejné přihlašování do konce akce, bez upozornění na zámek, `%zamek%` prázdný; editor e-mailů symbol u takové akce zašedí a při uložení textu, který ho obsahuje, upozorní, uložení akce upozorní na zapnuté e-maily s `%zamek%`. Migrace přegenerována. |
| 1. 10. 2026 | Pole data ve formulářích (začátek, konec, uzávěrka, datum narození) jsou textová ve tvaru `dd.mm.rrrr` (`DateInput`; nativní pole ukazovalo formát prohlížeče s lomítky), přijmou i jednociferný den a měsíc a čárku z číselné klávesnice telefonu, neexistující datum hlásí zvlášť. Konec akce je nepovinný: prázdný znamená jednodenní akci (nápověda pod polem). |
| 1. 10. 2026 | Párování na veřejné přihlášce zjednodušeno (zadavatel): zaškrtávátko „Účastník je členem“, potvrzení neshody a příznak „uvádí členství“ odstraněny, jasně identifikovaný člen (přesně jeden otisk jména a data narození) se bere za člena, více shod nebo shoda jen příjmení dává „možnou shodu“. Otisky se staví jen při založení akce s veřejným přihlašováním nebo jeho zapnutí (a tlačítkem v detailu akce), denní automatická obnova na stránkách vedoucího odstraněna. Normalizace otisku navíc bere spojovník a apostrof jako mezeru. |
| 1. 10. 2026 | Druhé code review (10 nálezů): veřejná přihláška i přihláška přes skautIS řadí potvrzení s osobním odkazem; veřejné přihlašování končí i zámkem úprav (akce bez uzávěrky těsně před začátkem nebo po konci); `createParticipant` při chybě `PersonUpdateBasic` vrátí ID založené osoby a účasti a chyba se zapíše k přihlášce, takže se osoba nezaloží znovu; nečlen, kterému Zvadlo založilo osobu, se dál ověřuje kódem, ne datem narození; přihlášení účastníka přes skautIS obnoví přihlášení vedoucího ve stejném prohlížeči (`withTemporaryLogin`); zamítnutý e-mail se nevrátí ani přes jiný skautIS účet; kód pro úpravu se počítá do limitu jen při skutečném odeslání, jinak hláška; krok 1 veřejné přihlášky má limit 30 pokusů za hodinu na IP; automatická obnova otisků na stránkách vedoucího při chybě skautISu stránku nerozbije (varování, zůstanou staré otisky). Odeslání kódu v požadavku je výjimka podle rozhodnutí 24, zapsáno v AGENTS.md. |
| 1. 10. 2026 | Code review (nálezy 7 až 11 opraveny): propsání do skautISu po dávkách s kurzorem `next`, takže každý řádek se zkusí jednou za běh a trvale selhávající řádky neblokují ostatní (dříve nekonečná smyčka), `batchRunner.ts` navíc končí, když se zbývající počet nesníží; úlohy fronty mají `heartbeat_at` a `tasks:run` vrací úlohy po mrtvém workeru (bez heartbeatu 5 minut) do fronty, po vyčerpání pokusů je označí jako selhané s možností opakovat; odklad pushe do h.skautingu si počítadlo selhání pamatuje den, takže skutečně roste 2 → 60 minut; obnova otisků členů nejdřív načte skautIS a teprve pak v transakci nahradí otisky; kód pro úpravu přihlášky nejvýš jednou za minutu a pětkrát za hodinu, starý kód zůstává platný. Migrace přegenerována. Testy: unit (heartbeat), integrační (kurzor dávek s trvale selhávajícím řádkem, uvolnění úloh, omezení kódu, rostoucí odklad). |
| 1. 10. 2026 | Code review (nálezy 1 až 6 opraveny): formuláře osobní přihlášky, zrušení a ověření se zpracují jen v odpovídajícím stavu ověření (signál POST na neověřený odkaz je přesměrován), blokace po pěti pokusech odmítá i správné odpovědi a ověřovací formulář nejde použít mimo svou metodu (datum narození u nečlena s kódem); screenshoty hlášení chyb jen PNG/JPEG/GIF/WebP, servírované s `nosniff` a `CSP sandbox` (SVG = XSS na správce); Google OAuth s parametrem `state` v session; CSV export neutralizuje buňky začínající `=`, `+`, `-`, `@`; veřejná stránka už nevolá skautIS: spárovaný člen se bere z cache členů, a když vypršela, z údajů zadaných rodičem (otisk je ověřil). Testy: unit (blokace, typy screenshotu), integrační (párování bez cache), akceptační (odmítnuté signály, CSV). |
| 1. 10. 2026 | Vlastní pole přihlášky prověřena proti zadání (4.2): čtyři typy, povinnost, nápověda, pořadí, skrytí, zámek úprav, zobrazení v detailu i souhrnu a export byly hotové; doplněno vynucení povinného pole ano/ne (musí být zaškrtnuté), schování „Možností“ u typů bez voleb, ukázková pole všech typů s odpověďmi ve fixturách a kryt v akceptačních testech. |
| 1. 10. 2026 | Oprava: tlačítka „Kopírovat“ (veřejný odkaz, odkaz miniwebu, osobní odkaz) nefungovala mimo bezpečný kontext (`http://` dev doména nemá `navigator.clipboard`); doplněn záložní výběr textu s `execCommand('copy')` a hláška „Zkopírujte ručně“, akceptační test vypíná `navigator.clipboard` a ověřuje záložní cestu. |
| 30. 9. 2026 | Odznak v h.skautingu předělán na push (rozhodnutí 35, zadavatel: minimum požadavků, jednotky bez Zvadla nic nevolají): `HskautingNotifier` každou minutu pošle jednu dávku propsaných akcí změněných od `event.hskauting_pushed_at` (akce i přihlášky), podpis `X-Zvadlo-Signature` HMAC-SHA256 s časem, odklad 2 až 60 minut po chybě, `hskauting:push --all`, log `hskauting.log`, proměnné `HSKAUTING_WEBHOOK_URL` a `HSKAUTING_WEBHOOK_SECRET`. Pull API (`/api/v1/...`, `API_TOKEN_HSKAUTING`, `api.log`, `ApiCest`) odstraněno včetně nevyužité metody repozitáře; akceptační test založení akce ve skautISu zůstal jako `SkautisSyncCest`. Testy: unit (podpis), integrační (dávka, jen změny, jen propsané, odklad, `--all`, vypnuto). Obsah PR do h.skautingu popisuje specifikace 9.4. |
| 30. 9. 2026 | Přihlášení účastníka přes skautIS (4.6) implementováno proti fixturám: režim akce „jen přes skautIS“ ve formuláři akce, tlačítko na veřejné stránce i miniwebu (v režimu veřejného odkazu jako doplněk), backlink `p:{unitId}:{slug}:{webToken}` přes `ReturnUrl`, větev v `Auth:skautIS` bez identity vedoucího (`SkautisParticipantLogin`: `UserDetail` → `PersonDetail` → `PersonParentAllPersonChildren` → `resetLoginData`), stránka „Koho přihlašujete?“ (osoba účtu a děti), existující přihláška se otevře ověřená skautISem, nová vznikne s původem `skautis` a předvyplněnými kontakty (`registerViaSkautis`). Fixtury: dítě Tomáš Novák (106) účtu Jan Novák. Testy: unit (backlink), integrační (párování, duplicita, formulář v režimu skautIS zavřený), akceptační `SkautisParticipantCest`. Ruční ověření oprávnění AppID na testovacím skautISu je na zadavateli: `.docs/skautis-testovaci-ucty.md` (účty dodány 30. 9. 2026). |
| 30. 9. 2026 | Fáze 8 hotová: PWA pro vedoucí. `PwaPresenter` servíruje manifest (ikony s `?v=release`), service worker sestavený Vite z `frontend/sw.ts` (druhý vstup, vlastní `tsconfig.sw.json`) s dosazeným hashem release a offline stránku; worker je network first, cache-first jen pro `/assets/`, uloží poslední zobrazený seznam přihlášených každé akce (hlavička `X-Zvadlo-Cached-At`, titulek), offline vrátí kopii s pruhem „uložená kopie z dd.mm.rrrr HH:mm“ nebo `/offline` se seznamem kopií, `?offline=1` otevře kopii záměrně, odhlášení cache smaže. Výzva k instalaci (`beforeinstallprompt`) jen v přihlášeném layoutu, ikony v `www/pwa/`. Selenium má `--unsafely-treat-insecure-origin-as-secure` pro dev doménu (worker potřebuje bezpečný kontext). `make check-types` (i v CI) kontroluje TypeScript obou vstupů. Akceptační test `PwaCest`. |
| 30. 9. 2026 | Fáze 7 hotová: `deploy.php` (Deployer 7, rsync release z CI, `.env.local` a Google credentials z GitHub environmentu, migrace, prohřátí kontejneru, přepnutí web rootu, ověření přes `/health`), workflow s jobem `build` (`composer --no-dev`, `COMPOSER_MIRROR_PATH_REPOS=1`, Vite) a joby `deploy-beta`, `deploy-test`, `deploy-production`; režim údržby před startem Nette (`MaintenanceMode`, stránka 503, povolené IP/CIDR, štítek „Údržba“); Sentry přes Tracy logger (`SentryLogger`, jen s `SENTRY_DSN`, kontext uživatele bez osobních údajů); rotace logů (`logs:rotate`, denně 3:30); retence (`RetentionService`, `registrations:anonymize --dry-run`, denně 4:00, sloupce `event.answers_forgotten_at` a `registration.skautis_person_created_at`, mazání otisků jednotek bez veřejné akce); `/health`; nápověda pro vedoucí (`/napoveda`); `make audit` + krok v CI, Dependabot (composer, npm, actions); zátěžový test fronty (200 adresátů projde jedním během workeru, cca 15 s). Dokumentace `.docs/nasazeni.md`. Nasazení na skutečný server je na zadavateli (SSH klíč, environmenty v GitHubu). |
| 30. 9. 2026 | Fáze 6 hotová: systémové role (`system_user_role`, `AdminAccess`, nouzový seznam `ADMIN_USER_IDS`), administrace `/admin` (uživatelé s rolemi admin/support, hlášení chyb, statistiky po měsících, jednotky s odesílacími účty a poslední aktivitou), odkaz „Administrace“ v liště jen pro role. Hlášení problémů 1:1 s h.skautingem: odkaz v patičce s URL aktuální stránky, formulář (popis, URL, e-mail jen když ho skautIS nezná, screenshot do 5 MB podle MIME typu, skrytá diagnostika prohlížeče z TypeScriptu), diagnostika serveru (uživatel, role, jednotky, požadavek, release), e-mail správcům (`ERROR_EMAILS`) se screenshotem a `Reply-To`, detail s odpovědí hlasiteli, ručním založením GitHub issue (`GITHUB_ISSUES_*`, komentář při další odpovědi) a vyřízením / zamítnutím s e-mailem. Tabulky `technical_error_report`, `technical_error_report_reply`; migrace přegenerována do jedné počáteční. Testy: integrační (GitHub klient s falešným HTTP přes Guzzle MockHandler, role, statistiky), akceptační (hlášení → Mailpit → administrace → odpověď → vyřízení; správa rolí, statistiky, jednotky). Jméno cizího uživatele ze skautISu se v seznamu rolí nenačítá (viz specifikace 10.1). |
| 30. 9. 2026 | Převzato z h.skautingu: prodlužování přihlášení do skautISu na každém požadavku a pingem z prohlížeče každých 20 minut (`/session/keep-alive`, koordinace záložek přes localStorage, odpočet v liště) a zapamatovaná role (`user_preference`: `remembered_role_id`, `remember_role`, `extend_login`), která se při přihlášení obnoví bez ohledu na roli aktivní ve skautISu nebo h.skautingu. Rozhodnutí 34. |
| 30. 9. 2026 | Fáze 5 hotová (bez pull requestu do h.skautingu, ten je poslední fáze): `ActionVerify` v autorizátoru s cache na požadavek a vysvětlením chybějícího oprávnění, založení akce ve skautISu (`EventGeneralInsert` se stejným payloadem jako h.skauting), stránka „skautIS“ u akce s rozdílem (chybí, už nejedou, propsaní, jen ve skautISu), propsání účastníků po dávkách 10 AJAXem v prohlížeči vedoucího (člen přes `ID_Person`, nečlen založením osoby a `PersonUpdateBasic`, převzetí ručně přidaného účastníka, odebrání zrušených), tábory přes `ParticipantCampInsert` jen mimo stav `draft` (`EventCampDetail`), API `/api/v1/skautis-events/{id}`, `?ids=`, `/api/v1/skautis-camps/{id}` s Bearer tokeny, `Cache-Control` a logem `api.log`. Fronta zápisů do skautISu zrušena (rozhodnutí 33). Fixtury skautISu doplněny o odpovědi zápisových metod. |
| 30. 9. 2026 | Veřejná přihláška rozdělena na dva kroky (zadání zadavatele): krok 1 jméno, příjmení, datum narození a členství, krok 2 u rozpoznaného člena jen kontakt, otázky a souhlas, u nečlena navíc pohlaví a adresa; příjmení, kontakt a adresa se pamatují pro další dítě („Přihlásit další dítě“). Hláška veřejné stránky rozlišuje koncept, uzavřenou akci, prošlou uzávěrku a vypnuté veřejné přihlašování. |
| 30. 9. 2026 | Fáze 4 hotová: miniweb akce. Bloky text (Markdown), soubor (PDF, JPG, PNG do 10 MB v `uploads/` mimo web root, servírované presenterem s tokenem) a odkaz, řazení a mazání, editor u akce s odkazem ke kopírování a tlačítkem „Zobrazit“, obnovení tokenu s potvrzením. Veřejná stránka s hlavičkou akce, popisem a rámečkem přihlášky (veřejný formulář, nebo pokyn použít osobní odkaz), bez tokenu i pro koncept 404, `Referrer-Policy: same-origin`. Fixtura `WebFixture`, integrační test úložiště a řazení, akceptační test s nahráním PDF přes Selenium. |
| 30. 9. 2026 | Fixtura `MailFixture` pro vývoj (Gmail účet s falešným tokenem pro každou fixturovou jednotku, nastavení jednotky s výchozím účtem a textem souhlasu, účet u všech fixturových akcí, jedna vlastní šablona) a sjednocení formátu dat na `dd.mm.rrrr` (rozhodnutí 32). |
| 30. 9. 2026 | Fáze 3 hotová: šablony e-mailů (`unit_email_template`, `event_email` kopírované při založení akce, výchozí české texty v kódu, editor s náhledem a zkušebním e-mailem sobě, symboly `%…%`, Markdown → HTML s textovou alternativou), Gmail OAuth podle h.skautingu (`GoogleService`, `OAuthMailer`, propojení a odebrání účtu v nastavení jednotky, šifrovaný refresh token, výběr účtu u akce, bez účtu nelze akci otevřít, když je `SEND_EMAIL=true`), `Reply-To` na kontakt akce, fronta úloh (`task`, `task_registration`, atomické převzetí, dávky, limit 50 s, tři pokusy, `tasks:run`, plánovač `scheduler:run` každou minutu), úlohy pozvánky, připomínka (jednou denně, jen pozvaným s odeslanou pozvánkou, předmět „ - připomínka“), zpráva účastníkům, automatické potvrzení, odmítnutí, změna, zamítnutí (vyrenderované před anonymizací) a upozornění na zámek (denní job), log `email_message` u akce i přihlášky, panel průběhu úloh s pollingem, `invited_at` při odeslání, kód pro úpravu přihlášky nečlena poslaný hned v požadavku, nastavení jednotky (text souhlasu, výchozí účet, výchozí zámek). Testy: unit, integrační s Mailpitem přes REST API, akceptační (odeslání pozvánky a připomínky, editor, nastavení jednotky). V `dev` a `ci` jde všechno do Mailpitu a odesílací účet není potřeba. Zbývá ověřit propojení Gmailu s reálnými přihlašovacími údaji Googlu. |
| 30. 9. 2026 | „Doplnění fáze 2“ hotové: stálý šifrovaný token (`token_encrypted` + hash, odkaz v detailu bez obnovy, „Vygenerovat nový odkaz“ s potvrzením), stav zobrazeno při otevření odkazu, `invited_at` a štítek „pozvánka neodeslána“, adresy pozvánky s typem (matka, otec, hlavní, další, ručně) a výběrem po pozvaném i hromadně, tábor jen ze seznamu skautISu (`EventCampAll`, název, termín a místo ke čtení s obnovou), nečlen přidaný e-mailem a ověřený e-mailem při prvním vyplnění, veřejné přihlašování (režim akce, veřejný odkaz s tokenem miniwebu, honeypot, limit na IP, duplicity), párování otisky členů (`unit_member_fingerprint`, HMAC) s příznaky „možná shoda“ a „uvádí členství“ a tlačítkem „Spárovat“, stav zamítnuto s okamžitou anonymizací a blokací. Migrace sloučené do jedné počáteční (pokyn zadavatele: do nasazení žádné `ALTER TABLE`, databáze se resetuje). Přihlášení účastníka přes skautIS (4.6) a kód pro úpravu přihlášky nečlena čekají na testovací účty a fázi 3; nečlen zatím po prvním vyplnění vidí jen potvrzení, že přihláška je uložená. |
| 30. 9. 2026 | Upřesnění: e-mail o zamítnutí ze společné šablony jednotky, „odebrat ze skautISu“ = odebrat z akce, Zvadlo nikdy nemění osobní údaje člena ve skautISu, zásada „jednoduché jako Google Forms“, nový režim akce „jen přes skautIS“ s účastníky z jiných jednotek (schéma skautISu to dovoluje, oprávnění se ověří ve fázi 5). Rozhodnutí 28–31. |
| 30. 9. 2026 | Schváleno párování člena z veřejného odkazu (otisky + potvrzení vedoucím), přidán stav zamítnuto s okamžitou anonymizací a ověřena proveditelnost přihlášení účastníka přes skautIS (návrat přes `ReturnUrl`, děti rodiče přes `PersonParentAllPersonChildren` ve WSDL testovacího skautISu). Rozhodnutí 25–27. |
| 30. 9. 2026 | Specifikace doplněna o postup přidání účastníků a pozvánek podle plateb v h.skautingu, nečlena e-mailem, připomínku a tábory ze skautISu; sepsány problémy postupu registrace pozvaných. Zadavatel rozhodl (Rozhodnutí 18–24): stálý šifrovaný token, stav zobrazeno při otevření odkazu, kód pro úpravu přihlášky nečlena, veřejné přihlašování v první verzi. Implementace v „Doplnění fáze 2“. |
| 30. 9. 2026 | Fáze 2 hotová: entity `Registration`, `RegistrationRecipient`, hodnoty polí ve třech tabulkách a `RegistrationLog` s migrací; pozvání členů jednotky a podjednotek (`PersonAll` s cache 1 h, filtr, hromadný výběr) i nečlena; kontakty (rodiče → vlastní e-mail → ručně) se donačítají AJAXem po 10 osobách s průběhem; veřejná přihláška na osobní odkaz s ověřením datem narození a blokací po 5 pokusech, formulář (účast, údaje, vlastní pole, souhlas), souhrn, úprava a zrušení do zámku, stránky pro uzavřenou akci a neplatný odkaz; kapacita s čekací listinou; správa přihlášek (seznam s filtry a počty, detail s historií a adresami, ruční změny stavu, nový osobní odkaz, export CSV); pole s odpověďmi nelze smazat ani měnit jeho možnosti; fixtury pro vývoj; unit, integrační a akceptační testy (desktop i mobil). Export je zatím CSV (UTF-8 s BOM, středník); XLSX se přidá, až bude třeba. |
| 29. 9. 2026 | Fáze 1 hotová: entity `Event`, `EventField`, `EventFieldOption` s migrací, stavy akce a zámek úprav, generátor slugu, číselníky typů a rozsahů ze skautISu s cache, přehled akcí s filtrem a upozorněním na kolizi termínů, formulář akce s Bootstrap rendererem, správa vlastních polí (přidání, úprava, skrytí, pořadí, smazání), oprávnění podle jednotek (403), fixtury pro vývoj, unit, integrační a akceptační testy. |
| 7. 10. 2026 | Knihovny skautIS vydané upstream: jádro `skaut/skautis` v3.1.0 (`skaut/Skautis`) a rozšíření `skaut/skautis-nette` v3.1.0 (`skaut/SkautisNette`, obsah interního balíčku s namespace `Skaut\SkautisNette`). Zvadlo bere obojí přes `vcs` repozitáře z GitHubu (`^3.1`), extension je `Skaut\SkautisNette\SkautisExtension`, fixtury pro `ci` z `vendor/skaut/skautis-nette/resources/fixtures`; interní balíček `packages/skautis-nette`, `make test-packages` a `COMPOSER_MIRROR_PATH_REPOS` zrušené. Sekce session se přejmenovala, po nasazení se uživatelé jednou odhlásí. |
