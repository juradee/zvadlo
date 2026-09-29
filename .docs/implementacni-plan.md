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
| skautIS | jádro `skaut/skautis` ^3.1 z forku `webwingscz/Skautis` (composer `vcs`) + interní balíček `packages/skautis-nette` (`webwingscz/skautis-nette`, path repository), za rozhraním `SkautisGateway` | podprojekt níže; vydané verze blokují PHP ≥ 8.4, viz rozhodnutí 11 ve specifikaci |
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
│   │   └── Auth/        Authorizator, Resources
│   ├── Presentation/
│   │   ├── Auth/        přihlášení, odhlášení, přepínání role
│   │   ├── Dashboard/   přehled akcí
│   │   ├── Events/      Event, Invitations, Registrations, Emails, Web, Fields, Skautis
│   │   ├── Public/      miniweb a přihláška (bez loginu)
│   │   ├── Settings/    nastavení jednotky, Google
│   │   ├── Admin/       Users, BugReports, Statistics, Units
│   │   ├── Api/         SkautisEventsPresenter
│   │   └── @layout.latte, Error/
│   └── Router/RouterFactory.php
├── bin/console
├── packages/skautis-nette/   interní balíček webwingscz/skautis-nette (DI extension, session adaptér, dispatcher, Tracy panel, fixtury); path repository
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
| `APP_BASE_URL` | veřejná adresa instance | `http://moje-hospodareni.cz` do vyřízení vlastního AppID, potom vlastní dev doména |
| `APP_SECRET` | klíč pro šifrování refresh tokenů (32 B, base64) | povinné mimo `dev` |
| `APP_RELEASE_HASH` | verze pro Sentry a hlášení chyb | `dev` |
| `APPLICATION_ID` | skautIS AppID | povinné |
| `SKAUTIS_TEST_MODE` | testovací skautIS | `true` mimo `prod` |
| `SKAUTIS_FIXTURES` | cesta k JSON fixturám místo SOAP (fixture továrna balíčku) | `tests/fixtures/skautis` v `ci`, jinak prázdné |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | databáze | dockerové hodnoty |
| `SEND_EMAIL` | `true` = Gmail API a sendmail; `false` = vše přes SMTP do mailcatcheru | `false` v `dev`/`ci` |
| `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT` | mailcatcher pro `SEND_EMAIL=false` | `mailpit`, `1025` |
| `SYSTEM_EMAIL` | odesílatel systémové pošty přes sendmail (hlášení chyb, odpovědi správců) | `noreply@<host APP_BASE_URL>` |
| `ERROR_EMAILS` | příjemci chyb serveru a hlášení problémů | – |
| `GOOGLE_CREDENTIALS_FILE`, `GOOGLE_REDIRECT_URI` | Gmail OAuth | `app/config/google-credentials.json`, `{APP_BASE_URL}/google/token` |
| `GITHUB_ISSUES_TOKEN`, `GITHUB_ISSUES_OWNER`, `GITHUB_ISSUES_REPOSITORY`, `GITHUB_ISSUES_LABELS` | hlášení chyb → GitHub | `juradee` / `zvadlo` / `bug,user-report` |
| `API_TOKEN_HSKAUTING` | token pro API h.skautingu | – |
| `TEST_BACKGROUND`, `ENVIRONMENT_LABEL`, `ENVIRONMENT_COLOR` | banner prostředí | dle `APP_ENV` |
| `TRACY_SHOW_BAR` | Tracy lišta | `true` v `dev` |
| `SENTRY_DSN` | Sentry | – |
| `MAINTENANCE_MODE`, `MAINTENANCE_ALLOWED_IPS`, `MAINTENANCE_*` | údržba | `false` |
| `RETENTION_MONTHS_SYNCED` | anonymizace nečlenů propsaných do skautISu a mazání hodnot polí | `12` |
| `RETENTION_MONTHS_UNSYNCED` | anonymizace nečlenů nepropsaných do skautISu | `24` |

### Docker a Makefile

Služby: `traefik` (dev doména z proměnné `DEV_HOST`, zatím `moje-hospodareni.cz` → nginx), `php` (oficiální
`php:8.4-fpm` + Composer), `php-test` (`APP_ENV=ci`, stejný obraz; proměnná `PHP_VERSION` přepne na 8.5),
`node` (`node:24`, jen pro `npm` a Vite; na hostiteli Node není), `nginx`, `mariadb`, `mariadb-test` (tmpfs),
`mailpit` (UI `mail.localhost`), `selenium`, `adminer`.
Dokud Zvadlo sdílí doménu a AppID s h.skautingem, nemohou oba stacky běžet na portu 80 zároveň; skautIS
posílá POST na `moje-hospodareni.cz`, takže při ladění přihlášení musí být stack h.skautingu zastavený
(`TRAEFIK_HOST_PORT` jiný port nepomůže, skautIS o něm neví). Makefile s cíli
`build`, `up`, `down`, `init`, `enter`, `composer-install`, `fixtures`, `assets`, `assets-watch`,
`test-unit`, `test-integration`, `test-acceptance`, `test-packages`, `ci-acceptance`, `check-cs`, `check-cs-check`,
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
Rozhodnutí: jádro dostane **verzi 3.1** ve forku `webwingscz/Skautis` (složka `Skautis` vedle `rs`,
navazuje na větev `3.x`, API a namespace `Skaut\Skautis` beze změny, aby šla poslat upstream). Nette napojení
se nepřebírá ze `skautis/nette`, ale vzniká jako **interní balíček `packages/skautis-nette`**
(`webwingscz/skautis-nette`, namespace `Webwings\SkautisNette`, MIT, composer path repository) po vzoru
`packages/skautis-symfony` ve skautIS mobilu: DI extension se stejnými klíči jako `skautis/nette`,
session adaptér nad Nette Session, jeden PSR-14 dispatcher pro panel i logování, Tracy panel a fixture
továrna, díky které má Zvadlo jedinou implementaci `SkautisGateway` a testy jedou bez skautISu.
Zadavatel později rozhodne, zda balíček vydat jako nový, nebo z něj udělat SkautisNette 3.1.
Jádro předchází fázi 0, balíček vzniká ve fázi 0.

Analýza, srovnání větví 2.x a 3.x, seznam změn jádra, návrh balíčku a pořadí prací jsou
v [knihovny skautIS](skautis-knihovny-3.1.md). Klíčové body:

- větev 3.x jádra není za 2.x pozadu (všechny opravy z 2.x tam jsou nebo se jí netýkají);
- název balíčku zůstává `skaut/skautis`, jak ho větev 3.x přejmenovala (Packagist zná zatím jen starší `skautis/skautis`); Zvadlo vyžaduje `skaut/skautis ^3.1`;
- opravy pro PHP 8.4: implicitně nullable parametry, `Serializable` na událostech, `isMaintenance` pro HTTP/2,
  širší rozpoznání SOAP faultů, typované vlastnosti, PHPStan 2 bez výjimek, PHPUnit 11, CI na 8.4 + 8.5;
- Zvadlo bere jádro přes composer `repositories` typu `vcs`, dokud nevyjde na Packagistu; balíček přes `path`
  se symlinkem, produkční build s `COMPOSER_MIRROR_PATH_REPOS=1`.

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
- skautIS: balíček `packages/skautis-nette` (extension v `config.neon`, `applicationId`, `testMode`
  a `fixtures` z `.env`), jádro 3.1 přes `vcs`; nad tím `SkautisGateway` rozhraní a `SoapSkautisGateway`
  (obaluje `Skaut\Skautis\Skautis`); fixtury pro `ci` v `tests/fixtures/skautis`
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
- API `/api/v1/skautis-events/{id}` a `?ids=` s Bearer tokenem konzumenta (dva platné tokeny pro rotaci),
  jen neosobní data, `Cache-Control`, log přístupů; žádný uživatelský kontext se nepředává (specifikace 9.4).
- Pull request do h.skautingu: odznak a odkaz na detailu akce a v seznamu (volání API s cache 5 minut,
  chyba API nesmí rozbít stránku; oprávnění uživatele k akci ověřuje h.skauting sám jako dosud).
- Testy: unit (diff účastníků, mapování DTO), integration (falešný gateway zaznamenává volání),
  acceptance (API vrací čísla).

### Fáze 6 – Administrace a hlášení problémů

- Port z h.skautingu: `SystemUserRole`, `AdminAccessChecker`, `/admin`, `/admin/uzivatele`.
- Hlášení problémů 1:1: formulář, diagnostika klienta (TypeScript), služba, entity, e-mail správcům,
  detail v administraci, odpověď, GitHub issue, vyřešení/zamítnutí, e-maily hlasiteli.
- Statistiky, přehled jednotek.
- Testy: unit (přístup), integration (uložení hlášení, GitHub klient s falešným HTTP), acceptance (odeslání hlášení).

### Fáze 7 – Provoz

- Deployer (`deploy.php`) a deploy joby; GitHub environments `beta`, `test`, `production` s proměnnými.
  Produkční `composer install` s `COMPOSER_MIRROR_PATH_REPOS=1`, aby `vendor/webwingscz/skautis-nette`
  byla kopie, ne symlink na `packages/`.
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

## Průběh

| Datum | Stav |
|---|---|
| 29. 9. 2026 | Jádro `skaut/skautis` 3.1 hotové (v3.1.0 na GitHubu). Fáze 0 hotová: docker stack, Makefile, prostředí, Nette kostra s Vite a Bootstrapem, Doctrine přes nettrine, balíček `packages/skautis-nette` s fixturami, přihlášení přes skautIS s přepínáním role a výpočtem přístupu k jednotkám, Codeception (unit, integration, acceptance) a GitHub Actions. |
| 29. 9. 2026 | Fáze 1 hotová: entity `Event`, `EventField`, `EventFieldOption` s migrací, stavy akce a zámek úprav, generátor slugu, číselníky typů a rozsahů ze skautISu s cache, přehled akcí s filtrem a upozorněním na kolizi termínů, formulář akce s Bootstrap rendererem, správa vlastních polí (přidání, úprava, skrytí, pořadí, smazání), oprávnění podle jednotek (403), fixtury pro vývoj, unit, integrační a akceptační testy. |
