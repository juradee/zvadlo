# Instalace pro lokální vývoj

Na počítači stačí Docker (rootless i rootful) a `make`. PHP, Composer ani Node nejsou potřeba, vše běží
v kontejnerech.

## Doména

Dokud Zvadlo nemá vlastní skautIS AppID, používá doménu a AppID h.skautingu. Do `/etc/hosts` patří:

```
127.0.0.1   moje-hospodareni.cz
```

skautIS po přihlášení posílá POST na adresu registrovanou u AppID (`/sign/skaut-i-s`), takže při ladění
přihlášení musí být stack h.skautingu zastavený; oba používají port 80.

## První spuštění

```bash
make init      # obrazy, composer, npm, assety, migrace, fixtury
make up        # start stacku
```

| Adresa | Co |
|---|---|
| http://moje-hospodareni.cz | aplikace |
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
make clean-cache       # po přidání presenteru nebo změně konfigurace, když Nette hlásí chybějící třídu
```

Testy a kontroly:

```bash
make check-cs          # php-cs-fixer, opravuje
make check-phpstan
make check-latte
make test-packages     # balíček packages/skautis-nette
make test-unit
make test-integration  # proti testovací databázi, skautIS z fixtur
make test-acceptance   # Selenium s viditelným prohlížečem
make ci                # vše, co běží v GitHub Actions
```

Testy nikdy nevolají skutečný skautIS: v prostředí `ci` odpovídá fixture továrna balíčku
z `packages/skautis-nette/resources/fixtures`. Přihlášení v akceptačních testech posílá stejný POST jako
skautIS (viz `tests/Support/Helper/Acceptance.php`).

## PHP 8.5

`make build PHP=8.5` sestaví obrazy s PHP 8.5; `make ci PHP=8.5` pustí totéž co CI na druhé verzi.
