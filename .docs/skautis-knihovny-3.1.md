# Knihovny skautIS: jádro 3.1 a Nette napojení

Podprojekt Zvadla. Stav analýzy a rozhodnutí k 29. 9. 2026.

| Balíček | Upstream | Fork | Složka ve workspace | Rozhodnutí |
|---|---|---|---|---|
| `skaut/skautis` (na Packagistu zatím `skautis/skautis`; jádro: SOAP klient, `User`, `Config`, `WsdlManager`, cache dekorátor, PSR-14 události) | `skaut/Skautis` | `webwingscz/Skautis` | `Skautis` (klon má checkout `2.x`, práce jde z `3.x`) | **verze 3.1** pro PHP 8.4+, viz kapitola 3 |
| `skautis/nette` (DI extension, session adaptér, PSR-16 adaptér, Tracy panel) | `skaut/SkautisNette` | `webwingscz/SkautisNette` | `SkautisNette` | **zatím se nepoužije**; Nette napojení je interní balíček Zvadla (kapitola 4), z něj lze později udělat SkautisNette 3.1 nebo nový balíček |

## 1. Proč navázat na 3.x jádra, ne na 2.x

Vydané verze jádra: 2.3.0 (duben 2023) a 3.0.0-alpha.5 (září 2022). Řada 3.x je moderní základ:
`declare(strict_types)`, skalární typy, immutable `Config`, PSR-14 události s trace, PSR-16 cache,
`DateTimeImmutable`, hierarchie výjimek, prázdná odpověď jako `null` / `[]` místo prázdného `stdClass`.
Řada 2.x je pro PHP 5.6 s vlastním event dispatcherem a `Serializable`. Verze 3.1 = 3.x + PHP 8.4+
+ současné nástroje + opravy z kapitoly 3.

## 2. Srovnání větví 2.x a 3.x jádra

Společný předek je `45949a0` (2019). Ve 2.x je od té doby 7 commitů, ve 3.x přes 40.

| Commit jen ve 2.x | Co mění | Ve 3.x |
|---|---|---|
| `a9e72f9` (#74) `isMaintenance`: `get_headers` může vrátit `false` | `!$headers \|\| …` | ano, `c721b26` (přeneseno) |
| `634ac3a` (#73) nové služby `DocumentStorage`, `Grants`, `Insurance` | seznam služeb | ano, `3c0bff9`, v `WebServiceName`; **chybí jen v `@property-read` docbloku třídy `Skautis`** → doplnit |
| `ae490a5` (#103) chybějící import `WebServiceInterface` | docblok | ano, import existuje |
| `4273576` (#105) `SkautisQuery::__serialize/__unserialize` | PHP 8.1 serializace | netýká se: `SkautisQuery` ve 3.x jádru není, události mají `__serialize/__unserialize` od `89bd4b5` |
| `e311a87`, `3b88802`, `e770502` | dev závislosti, překlepy v docs | bez významu |

Závěr: **3.x není za 2.x pozadu.** Naopak 3.x má navíc PSR-14 události včetně zásobníku volání (`449faf0`),
opravu `parseOutput` (#90) a nový formát prázdné odpovědi, `DateTimeImmutable`, vlastní výjimky
(`ParsingFailedException`, `MaintenanceErrorException`, `WebServiceNotFoundException`), TLS přes
`stream_context` místo deprecated `ssl_method` (#104), PHP 8.1 serializaci a GitHub Actions.

Rozšíření SkautisNette: větev `3.x` nevznikla z konce `2.x`, `git log` proto ukazuje 28 commitů „jen ve 2.x“
(Nette 3.0, `SmartObject`, PHP 7.2, CI z let 2018–2019). Obsahový diff ukazuje, že 3.x je nadmnožina.
Z 2.x není co přenášet; pro Zvadlo je to ale bezpředmětné, viz kapitola 4.

## 3. Co 3.1 v jádru opraví nebo změní

Nálezy z kódu větve `3.x` (`f90c8e4`):

1. **Název balíčku.** Tagy 3.x mají v `composer.json` `"name": "skaut/skautis"` (přejmenování `995ead1`),
   zatímco Packagist i SRS znají starší `skautis/skautis`. Rozhodnutí zadavatele: 3.1 **zachová `skaut/skautis`**
   a řídí se přejmenováním z 3.x. Zvadlo i balíček napojení proto vyžadují `skaut/skautis ^3.1`; přes `vcs`
   repozitář se název bere z `composer.json` forku, takže se spáruje. Upstream vydání na Packagistu bude
   pod novým názvem; `suggest` v SkautisNette a v dokumentaci se pak upraví. Do `authors` přidán
   Jiří Dorazil (https://github.com/juradee).
2. **Implicitně nullable parametry** (deprecated od PHP 8.4): `User::__construct(…, AdapterInterface $session = null)`,
   `WebServiceNotFoundException` a `WebServiceAliasNotFoundException` (`Throwable $previous = null`) → `?Typ`.
3. **`Serializable`** na `RequestPreEvent`, `RequestPostEvent`, `RequestFailEvent`: rozhraní je od 8.1 zastaralé
   a v PHP 9 zmizí. Ponechat jen `__serialize` / `__unserialize`, smazat `serialize()` / `unserialize()`.
   `RequestPostEvent::__unserialize` přetypovává `result` na pole, i když může být `stdClass` nebo `null` → zachovat typ.
4. **`isMaintenance`** porovnává řetězec `HTTP/1.1 200 OK`; při HTTP/2 vrátí falešnou údržbu → kontrola stavu
   regulárním výrazem na kód 200 bez ohledu na verzi protokolu.
5. **`convertToSkautisException`** zná jen „Uživatel byl odhlášen“ a „Nemáte oprávnění“. Rozšířit podle
   ověřeného překladače z `webwingscz/skautis-symfony`: vypršelé přihlášení, neexistující nebo nepřihlášený
   uživatel → `AuthenticationException`; „nedostatečná práva“, „není povoleno“ → `PermissionException`.
6. **`WebServiceFactory::setEventDispatcher`** porovnává `!= null` → `!== null`.
7. **`@property-read`** v `Skautis` doplnit o `DocumentStorage`, `Grants`, `Insurance`.
8. **Typy**: typované vlastnosti místo `@var`, konstruktorová promoce, `readonly` u `Config` a událostí,
   `final` kde dává smysl; PHPStan level max **bez** `ignoreErrors` (magické volání `LoginUpdateRefresh`
   v `User` nahradit `->call('LoginUpdateRefresh', …)`).
9. **Závislosti**: `php ^8.4`, `ext-soap`, `psr/simple-cache ^1.0 || ^2.0 || ^3.0` (jádro rozhraní jen
   konzumuje), `psr/event-dispatcher ^1.0`. Dev: `phpunit/phpunit ^11`, `mockery/mockery ^1.6`,
   `phpstan/phpstan ^2.1`, `friendsofphp/php-cs-fixer ^3` (pravidla `@Symfony` jako Zvadlo; kód má dnes
   smíšené odsazení 2 a 4 mezery), `php-parallel-lint`. Pryč `post-install-cmd` se symlinkem pre-commit hooku,
   `fix_syle.sh`, `.scrutinizer.yml`, `.travis.yml`.
10. **Testy**: `phpunit.xml.dist` v novém schématu (`<source>`), `assertObjectHasAttribute` →
    `assertObjectHasProperty`, `@dataProvider` → atribut `#[DataProvider]`; PSR-16 v testech přes
    `symfony/cache ^7` (`Psr16Cache` + `ArrayAdapter`) nebo desetiřádková in-memory cache v `tests/`.
    Testů je 15 souborů, 13× Mockery; rozsah úprav malý.
11. **CI**: GitHub Actions na `ubuntu-latest`, `actions/checkout@v4`, `shivammathur/setup-php@v2`, matice
    PHP **8.4 a 8.5**: lint, cs, PHPStan, PHPUnit. Dependabot pro composer a actions.
12. **Dokumentace**: `CHANGELOG.md` s položkou 3.1.0, README s požadavky, záměr poslat pull request do `skaut/Skautis`.

Co se **nemění**: namespace `Skaut\Skautis`, veřejné API (`Skautis`, `User`, `Config`, `WsdlManager`,
`WebServiceInterface`, `WebServiceFactoryInterface`, `CacheDecorator`, události), konstanty `WebServiceName`
(žádný přechod na `enum`, aby zůstal zpětně kompatibilní řetězcový přístup), aliasy služeb (`usr`, `org`, `event`, …).

Vydání: tag `v3.1.0` ve forku; Zvadlo ho bere přes `repositories: [{type: vcs, url: https://github.com/webwingscz/Skautis}]`
a `"skaut/skautis": "^3.1"`, dokud změny nevyjdou upstream na Packagistu.

## 4. Nette napojení: interní balíček `webwingscz/skautis-nette`

Rozšíření `skautis/nette` přidává nad jádrem jen DI extension, session adaptér, PSR-16 adaptér pro
`CacheDecorator` a Tracy panel (asi 670 řádků). Zvadlo potřebuje z toho session adaptér, registraci
šesti služeb a panel pro ladění; PSR-16 adaptér ne, protože cachuje typované DTO v `SkautisGateway`.
Navíc jádro dovolí jen **jeden** PSR-14 dispatcher, který si extension v `dev` bere pro panel, takže by
Zvadlo nemohlo logovat SOAP chyby do Monologu v produkci. Proto je napojení vlastní, ale od začátku ve tvaru
samostatného balíčku po vzoru `packages/skautis-symfony` ve skautIS mobilu, aby šlo později vydat jako
nový balíček, nebo poslat jako verze 3.1 do `skaut/SkautisNette`.

```
rs/packages/skautis-nette/
├── composer.json        webwingscz/skautis-nette, type library, MIT; php ^8.4, skaut/skautis ^3.1,
│                        nette/di ^3.2, nette/http ^3.3, nette/schema ^1.3, psr/event-dispatcher ^1.0;
│                        suggest tracy/tracy; autoload Webwings\SkautisNette\ → src/
├── README.md, CHANGELOG.md, LICENSE (MIT)
├── src/
│   ├── DI/SkautisExtension.php       config: applicationId, testMode, cache, compression,
│   │                                 profiler (výchozí = debugMode), fixtures (cesta | null)
│   ├── SessionAdapter.php            AdapterInterface jádra nad Nette\Http\Session (get / set / remove)
│   ├── EventDispatcher.php           PSR-14; jediný dispatcher jádra, addListener(callable) pro panel i logování
│   ├── Tracy/Panel.php               IBarPanel: seznam SOAP volání, čas, argumenty, výsledek, trace
│   ├── Tracy/QueryLog.php            listener sbírající RequestPostEvent / RequestFailEvent
│   └── Fixture/FixtureWebServiceFactory.php, Fixture/FixtureWebService.php
│                                     WebServiceFactoryInterface: odpovědi z JSON místo SOAP
├── resources/fixtures/               ukázková sada: UserManagement/UserDetail.json, UserRoleAll.json,
│                                     LoginUpdateRefresh.json, OrganizationUnit/UnitDetail.json, UnitAll.json,
│                                     PersonAll.json, PersonContactAll.json, PersonParentAll.json, …
└── tests/                            PHPUnit: extension postaví kontejner, session adaptér, dispatcher,
                                      fixture továrna
```

Pravidla balíčku:

- Žádná závislost na `App\`, na názvech rout ani šablonách; vše, co aplikace potřebuje, jde přes konfiguraci
  extension. Konfigurační klíče jsou stejné jako v `skautis/nette` (`applicationId`, `testMode`, `profiler`,
  `cache`, `compression`), aby byl přechod oběma směry triviální; navíc `fixtures`.
- Extension registruje `Config`, `WebServiceFactory` (nebo `FixtureWebServiceFactory`, je-li `fixtures`
  nastaveno), `WsdlManager`, `SessionAdapter`, `User`, `Skautis` a `EventDispatcher`; panel jen když
  `profiler` a je dostupná Tracy.
- Fixture továrna: `{fixtures}/{Služba}/{Metoda}.json`; varianta podle jednoho argumentu
  `{Metoda}__{Klíč}={Hodnota}.json` (např. `PersonAll__ID_Unit=1002.json`); chybějící soubor vyhodí výjimku
  s cestou, kterou je třeba doplnit. Přihlášení projde stejnou cestou jako doopravdy: test pošle na login
  routu POST se `skautIS_Token` z fixtur a `LoginUpdateRefresh.json` vrací `DateLogout` v budoucnosti.
  Díky tomu má Zvadlo **jedinou** implementaci `SkautisGateway` a testy procházejí skutečným mapováním.
- Vlastní `phpunit.xml.dist`, PHPStan level max, php-cs-fixer se stejnými pravidly jako Zvadlo.
  `make test-packages` spouští testy balíčku, `make ci` je zahrnuje.
- Zvadlo ho bere přes `repositories: [{type: path, url: packages/skautis-nette, options: {symlink: true}}]`
  a `"webwingscz/skautis-nette": "@dev"`. Produkční build používá `COMPOSER_MIRROR_PATH_REPOS=1`, aby ve
  `vendor/` byla kopie, ne symlink (rsync na hosting).
- Vyčlenění do vlastního repozitáře, až na to dojde: `git subtree split --prefix=packages/skautis-nette`,
  potom se `repositories` nahradí verzí z Packagistu. Pro variantu „update SkautisNette“ se přenese obsah
  `src/` a testy do forku `webwingscz/SkautisNette` s namespace `Skautis\Nette` a licencí BSD-3-Clause.

## 5. h.skauting

Migrace h.skautingu na jádro 3.1 je **poslední fáze** a teď se neřeší. Až na ni dojde, jsou zpětně
nekompatibilní rozdíly 2.x → 3.x tyto: namespace `Skautis\` → `Skaut\Skautis\`; prázdná odpověď `null` / `[]`
místo prázdného `stdClass` (h.skauting testuje `is_object` / `instanceof stdClass` na mnoha místech);
`ArrayCache` a `CacheInterface` nahrazeny PSR-16 a TTL v sekundách; vlastní event dispatcher → PSR-14;
`Config` immutable s výchozím `testMode = true`; `confirmAuth` / `updateLogoutTime` vrací `bool`; neznámá
služba vyhodí `WebServiceNotFoundException`. Konfigurace `skautis:` v NEONu zůstane stejná.

## 6. Pořadí prací

1. Jádro: v klonu `Skautis` přepnout na `3.x`, větev `3.1`, změny z kapitoly 3, testy zelené na PHP 8.4
   i 8.5 v Dockeru (`php:8.4-cli` a `php:8.5-cli` s `ext-soap`, Composer v kontejneru; na hostiteli se nic
   neinstaluje), tag `v3.1.0`.
2. Balíček `packages/skautis-nette` ve Zvadle podle kapitoly 4, včetně fixtur a testů; vzniká ve fázi 0
   Zvadla spolu s kostrou aplikace.
3. Kouřový test: fáze 0 Zvadla se přihlásí přes testovací skautIS (`dev`) i přes fixtury (`ci`).
4. Pull request upstream do `skaut/Skautis`; po vydání na Packagistu Zvadlo smaže `vcs` repozitář.
5. Rozhodnutí zadavatele, zda balíček vydat jako nový, nebo z něj udělat SkautisNette 3.1.
