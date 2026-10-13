# Nasazení a provoz

Zvadlo se nasazuje stejně jako h.skauting: GitHub Actions sestaví release, Deployer 7 ho nahraje přes SSH
a rsync na hosting a přepne web root. Tři prostředí odpovídají GitHub environments `beta`, `test`
a `production`; proměnné a tajemství žijí tam, nikdy v repozitáři.

## Před prvním nasazením

1. **Hosting**: PHP 8.4 (nebo 8.5) pro web i CLI s rozšířeními `intl`, `soap`, `pdo_mysql`, `json`; MariaDB
   (vývoj a CI běží na 11.4, jinou verzi je třeba vyzkoušet); SSH přístup pro deploy účet s `rsync`; možnost
   nasměrovat document root symlinkem na `current/www`; cron po minutě; funkční `sendmail` pro systémovou poštu
   z `SYSTEM_EMAIL` (výchozí `noreply@<doména>`); HTTPS na doméně z `APP_BASE_URL`.
2. **Databáze**: prázdná databáze a uživatel s právy na vytváření tabulek; schéma založí první deploy migrací.
3. **skautIS**: aplikace zaregistrovaná zvlášť na testovacím a produkčním skautISu s návratovými adresami
   `{APP_BASE_URL}/login` a `{APP_BASE_URL}/sign/skautis-logout` a s oprávněními podle
   [skautis-opravneni.md](skautis-opravneni.md). Prostředí `beta` a `test` běží proti testovacímu skautISu
   (`SKAUTIS_TEST_MODE=true`, testovací AppID), `production` proti produkčnímu (`false`, produkční AppID).
4. **Google**: OAuth klient pro odesílání pošty z Gmail účtů jednotek; v konzoli Googlu povolit návratovou
   adresu `{APP_BASE_URL}/google/token` a JSON klienta uložit do tajemství `GOOGLE_CREDENTIALS`.
5. **GitHub**: environments `beta`, `test` a `production` s proměnnými a tajemstvími níže; u `production`
   zapnout schválení nasazení. `APP_SECRET` vygenerovat jednou a už neměnit. Vše založí `bin/github-setup.sh`
   s přihlášeným `gh` (bez argumentů všechna prostředí, `bin/github-setup.sh production` jen vybraná): první
   spuštění vytvoří šablony v git-ignorované složce `.deployment/`, druhé po doplnění hodnot environmenty založí
   a hodnoty nahraje. Beta a test navíc dostanou repozitářovou proměnnou `DEPLOY_BETA` / `DEPLOY_TEST`, bez
   které workflow na daný server nenasazuje; dokud servery neexistují, jede jen produkce.
6. **Po prvním deployi**: nastavit cron (níže), do `ADMIN_USER_IDS` dát skautIS ID prvního správce, ověřit
   `/health`, přihlášení přes skautIS a odeslání zkušebního e-mailu; jakmile je nastavený
   `HSKAUTING_WEBHOOK_URL`, jednou spustit `php bin/console hskauting:push --all`.

## Průběh nasazení

1. `push` do `master` nebo do větve `beta/**` spustí workflow `main.yml`: kontroly (PHP 8.4 a 8.5), akceptační
   testy a job `build`, který udělá `composer install --no-dev --classmap-authoritative`,
   sestaví assety Vite a zabalí release bez testů, dockeru a dokumentace.
2. `deploy-beta` (větve `beta/**`, jen s `DEPLOY_BETA=true`), `deploy-test` (`master`, jen s `DEPLOY_TEST=true`)
   a `deploy-production` (`master`, environment se schválením v GitHubu; běží po `deploy-test`, nebo rovnou po
   buildu, když je test vypnutý) stáhnou release a spustí `dep deploy <cíl> -f deploy.php`. Když je nastavený
   `DEPLOY_PREPARE_PORT`, workflow nejprve otevře a nechá zavřít SSH relaci na tomto portu a počká, až je
   dostupný finální `DEPLOY_PORT`.
3. `deploy.php` vytvoří z proměnných environmentu soubor `.env.local` a `google-credentials.json`
   (`zvadlo:build_runtime_files`), přepne aplikaci do údržby, rsyncem nakopíruje release do `releases/<hash>`,
   nasdílí `log/`, `uploads/`, `temp/sessions/` a přepne `current` i web root hostingu
   (`ln -sfn {{release_path}}/www {{web_root}}`). GitHub runner pak přes zabezpečený dočasný endpoint
   `/db_fix.php` spustí migrace, obnoví běžnou konfiguraci, prohřeje DI kontejner a odstraní endpoint. Nakonec
   ověří přes `/health`, že běží nový release (`APP_RELEASE_HASH` = název release).
4. Drží se tři poslední release; návrat zpět je `dep rollback` nebo ruční přepnutí symlinku `current`.

## Co nastavit v GitHub environmentu

Repozitářové proměnné (mimo environmenty): `DEPLOY_BETA` a `DEPLOY_TEST` s hodnotou `true` zapínají nasazení na beta
a test; produkce se nasazuje vždy.

Proměnné environmentu (`vars`):

| Proměnná | Význam |
|---|---|
| `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PATH` | SSH cíl a kořen nasazení (`releases/`, `shared/`, `current`) |
| `DEPLOY_PREPARE_PORT` | volitelný port pro krátké SSH spojení, které zpřístupní `DEPLOY_PORT` |
| `DEPLOY_PHP_BIN` | cesta k PHP CLI na hostingu (např. `/usr/bin/php8.4`) |
| `DEPLOY_WEB_ROOT` | kam hosting ukazuje jako document root; symlink na `current/www` (výchozí `DEPLOY_PATH/www`) |
| `DEPLOY_KNOWN_HOSTS` | řádek `known_hosts` pro `DEPLOY_PORT` (`ssh-keyscan -p <port> <host>`), aby se ověřoval otisk |
| `DEPLOY_PREPARE_KNOWN_HOSTS` | řádek `known_hosts` pro `DEPLOY_PREPARE_PORT` (`ssh-keyscan -p <port> <host>`); povinný, když je přípravný port nastavený |
| `APP_ENV`, `APP_BASE_URL`, `SKAUTIS_TEST_MODE`, `SEND_EMAIL`, `SYSTEM_EMAIL`, `ERROR_EMAILS` | viz [proměnné prostředí](implementacni-plan.md#proměnné-prostředí) |
| `APPLICATION_ID` | skautIS AppID daného prostředí; není tajné, skautIS ho posílá jako parametr `appid` v URL přihlášení |
| `TEST_BACKGROUND`, `ENVIRONMENT_LABEL`, `ENVIRONMENT_COLOR` | banner prostředí (na produkci `TEST_BACKGROUND=false`) |
| `DB_HOST`, `DB_NAME`, `DB_USER` | databáze |
| `DB_SERVER_VERSION` | verze databázového serveru pro Doctrine (`SELECT VERSION()`, např. `8.0.39`); bez ní se Doctrine při startu připojuje k serveru, což na hostingu z příkazové řádky nemusí jít |
| `GOOGLE_REDIRECT_URI`, `ADMIN_USER_IDS`, `RETENTION_MONTHS_SYNCED`, `RETENTION_MONTHS_UNSYNCED` | volitelné |
| `HSKAUTING_WEBHOOK_URL` | endpoint h.skautingu daného prostředí pro push propsaných akcí; prázdné = push vypnutý |
| `MAINTENANCE_MODE`, `MAINTENANCE_ALLOWED_IPS`, `MAINTENANCE_STARTED_AT`, `MAINTENANCE_ENDS_AT` | režim údržby (níže) |

Tajemství (`secrets`): `DEPLOY_SSH_KEY` (privátní klíč deploy účtu), `APP_SECRET` (32 B base64, `openssl rand -base64 32`;
jeho změna zneplatní uložené Gmail tokeny a osobní odkazy), `DB_PASSWORD`, `GOOGLE_CREDENTIALS`
(obsah JSON OAuth klienta), `SENTRY_DSN`, `GH_ISSUES_TOKEN`, `HSKAUTING_WEBHOOK_SECRET` (tajemství podpisu
dávek pro h.skauting, stejné na obou stranách) a `DB_FIX_TOKEN_SHA256` (jednorázový 256bitový hexadecimální
token pro HTTP migraci, vygenerovat `openssl rand -hex 32`).

## Na serveru

- Cron každou minutu v aktuálním release: `* * * * * cd /cesta/current && php bin/console scheduler:run`.
  Plánovač pouští frontu e-mailů (`tasks:run`), push změněných akcí do h.skautingu (`hskauting:push`, každou
  minutu, po prvním nasazení jednou ručně s `--all`), denní upozornění na zámek úprav (7:00), retenci
  (`registrations:anonymize`, 4:00) a rotaci logů (`logs:rotate`, 3:30).
- PWA: `/sw.js` a `/manifest.webmanifest` servíruje aplikace s hlavičkami `Cache-Control: no-cache` a hashem
  release, takže po nasazení prohlížeče vedoucích worker obnoví a staré cache smažou; ikony v `www/pwa/` se
  cachují dlouho (URL nese `?v=release`).
- `/health` vrací `{"status":"ok","release":"…"}` a 503, když neodpovídá databáze; hodí se pro uptime monitoring.
- Logy jsou v `shared/log/` (Tracy: `exception.log`, `error.log`, dumpy `exception--*.html`, `hskauting.log`,
  `retention.log`). `logs:rotate` přejmenuje soubory nad 10 MB, smaže dumpy a rotované soubory starší 30 dnů a
  jednou denně odstraní značku `email-sent`, aby Tracy dál posílala chyby na `ERROR_EMAILS`.
- Chyby a výjimky jdou zároveň do Sentry, když je nastavené `SENTRY_DSN` (bez osobních údajů; k události se přidá
  ID uživatele, jednotka a klíč role). Tracy zůstává zdrojem pravdy v logu.
- Nahrané soubory (miniweb, screenshoty hlášení) jsou v `shared/uploads/` mimo web root; zálohujte je spolu
  s databází.

## Režim údržby

`MAINTENANCE_MODE=true` v environmentu a nový deploy (nebo ruční úprava `shared/.env.local`) přepne aplikaci na
stránku odstávky (503, `Retry-After`) ještě před startem Nette, takže funguje i při rozbité databázi. Adresy
v `MAINTENANCE_ALLOWED_IPS` (IPv4/IPv6, i CIDR, oddělené čárkou) vidí aplikaci normálně a v liště mají červený
štítek „Údržba“. `MAINTENANCE_STARTED_AT` a `MAINTENANCE_ENDS_AT` jsou ISO datumy (`2026-07-02T20:00:00+02:00`),
na stránce se zobrazí jako `dd.mm.rrrr HH:mm`. Fronta a plánovač běží dál; při delší odstávce vypněte cron.

## Retence osobních údajů

Denní příkaz `registrations:anonymize` (ručně `bin/console registrations:anonymize --dry-run` ukáže počty)
provádí kapitolu 14 specifikace: po `RETENTION_MONTHS_SYNCED` měsících od konce akce smaže odpovědi a poznámky
všech přihlášek (`event.answers_forgotten_at`) a anonymizuje nečleny, kterým Zvadlo založilo osobu ve skautISu
(`registration.skautis_person_created_at`); po `RETENTION_MONTHS_UNSYNCED` měsících anonymizuje nečleny bez osoby
ve skautISu. Členové si nechávají skautIS ID a jméno. Otisky členů jednotek bez akce s veřejným přihlašováním se
mažou při každém běhu.

## Kontrola závislostí

`make audit` (a krok „Dependency audit“ v CI) spouští `composer audit` a `npm audit --omit=dev`. Dependabot
otevírá týdně pull requesty pro Composer, npm i GitHub Actions (menší verze sloučené do jednoho PR).
