# Oprávnění aplikace ve skautISu

Úplný soupis volání skautISu, která Zvadlo dělá, podle kódu ke dni 2. 10. 2026
(`App\Model\Skautis\SoapSkautisGateway` a jádro `skaut/skautis`). Slouží jako podklad k žádosti o oprávnění
aplikace (AppID) na testovacím i produkčním skautISu. Testovací (`test-is.skaut.cz`) a produkční
(`is.skaut.cz`) skautIS mají každý vlastní registraci aplikace i vlastní AppID.

Žádost se podává na webové metody ve tvaru `Služba.Metoda`. Názvy akcí ve sloupci „Akce ve skautISu“ pocházejí
z kódu (`Authorizator`) nebo ze specifikace a před podáním je potřeba je ověřit proti číselníku skautISu;
prázdné pole znamená, že název akce zatím neznáme.

## Registrace aplikace

| Položka | Hodnota |
|---|---|
| návratová adresa po přihlášení | `{APP_BASE_URL}/login` (skautIS sem posílá POST s tokenem; stačí jedna pro vedoucí i účastníky, cílovou stránku nese `ReturnUrl`) |
| návratová adresa po odhlášení | `{APP_BASE_URL}/sign/skautis-logout` |
| přístup | vedoucí pod svou rolí v jednotce; účastník (člen nebo rodič, i bez funkce v jednotce) jen při přihlášení na akci přes skautIS |

## UserManagement

| Metoda | Účel ve Zvadle | Kdo | Typ | Akce ve skautISu |
|---|---|---|---|---|
| `UserDetail` | osoba přihlášeného účtu (ID osoby, jméno) | vedoucí, účastník | čtení | `UM_User_DETAIL` |
| `UserRoleAll` | role uživatele po přihlášení (`ID_User`, `IsActive=true`) | vedoucí | čtení | `UM_UserRole_ALL_User` |
| `LoginUpdate` | přepnutí role (`ID`, `ID_UserRole`) | vedoucí | zápis přihlášení | `UM_Login_UPDATE` |
| `LoginUpdateRefresh` | ověření a prodloužení přihlášení o 30 minut (volá jádro `skaut/skautis`) | vedoucí, účastník | zápis přihlášení | `UM_Login_UPDATE` |
| `ActionVerify` | co smí aktivní role u akce a tábora (`ID_Table`, `ID`), podle toho Zvadlo nabízí zakládání a propsání | vedoucí | čtení | |

## OrganizationUnit

| Metoda | Účel ve Zvadle | Kdo | Typ | Akce ve skautISu |
|---|---|---|---|---|
| `UnitDetail` | detail jednotky role (název, typ, nadřízená) | vedoucí | čtení | `OU_Unit_DETAIL` |
| `UnitAll` | podřízené jednotky (`ID_UnitParent`), strom pro přístup a přidávání členů | vedoucí | čtení | `OU_Unit_ALL` |
| `PersonAll` | členové jednotky (`ID_Unit`, `OnlyDirectMember`) pro přidání účastníků a párování veřejných přihlášek | vedoucí | čtení | `OU_Person_ALL` |
| `PersonContactAll` | e-maily a telefon člena (`ID_Person`) na stránce přidání účastníků; vlastní e-mail vedoucího jako kontakt nové akce | vedoucí | čtení | `OU_PersonContact_ALL_Person` |
| `PersonParentAll` | e-maily rodičů člena do 18 let (`ID_Person`) | vedoucí | čtení | |
| `PersonDetail` | vlastní osoba účastníka (`ID`) při přihlášení na akci přes skautIS | účastník | čtení | `OU_Person_DETAIL` |
| `PersonParentAllPersonChildren` | účastníci, u kterých je přihlášený zákonným zástupcem (`ID_Person`) | účastník | čtení | |
| `PersonUpdateBasic` | doplnění údajů osoby nečlena, kterou Zvadlo právě založilo (jméno, přezdívka, pohlaví, narození, adresa) | vedoucí | zápis | `OU_Person_UPDATE` |

## Events

| Metoda | Účel ve Zvadle | Kdo | Typ | Akce ve skautISu |
|---|---|---|---|---|
| `EventGeneralTypeAll` | číselník typů akce | vedoucí | čtení | `EV_EventGeneralType_ALL` |
| `EventGeneralScopeAll` | číselník rozsahů akce | vedoucí | čtení | `EV_EventGeneralScope_ALL` |
| `EventGeneralInsert` | založení akce | vedoucí | zápis | `EV_EventGeneral_INSERT` |
| `ParticipantGeneralAll` | účastníci akce ve skautISu (rozdíl před propsáním) | vedoucí | čtení | `EV_ParticipantGeneral_ALL_EventGeneral` |
| `ParticipantGeneralInsert` | přidání člena (`ID_Person`) nebo založení osoby nečlena (`Person{…}`) do akce | vedoucí | zápis | `EV_ParticipantGeneral_INSERT_EventGeneral` |
| `ParticipantGeneralDelete` | odebrání účasti (`DeletePerson=false`, osoba zůstává) | vedoucí | zápis | `EV_ParticipantGeneral_DELETE_EventGeneral` |
| `EventCampAll` | tábory jednotky za rok (`Year`) k výběru | vedoucí | čtení | `EV_EventCamp_ALL` |
| `EventCampDetail` | stav tábora (`ID_EventCampState`), propisuje se jen mimo `draft` | vedoucí | čtení | `EV_EventCamp_DETAIL` |
| `ParticipantCampAll` | účastníci tábora ve skautISu | vedoucí | čtení | `EV_ParticipantCamp_ALL_EventCamp` |
| `ParticipantCampInsert` | přidání člena nebo založení osoby nečlena do tábora | vedoucí | zápis | `EV_ParticipantCamp_INSERT_EventCamp` |
| `ParticipantCampDelete` | odebrání účasti v táboře (`DeletePerson=false`) | vedoucí | zápis | `EV_ParticipantCamp_DELETE` |

## Co musí fungovat i bez funkce v jednotce

Přihlášení na akci přes skautIS (specifikace 4.6) pouští člena z jiné jednotky nebo rodiče, který v jednotce
žádnou funkci nemá. Pod jeho přihlášením Zvadlo volá jen tyto čtyři metody a token hned zahodí:

- `UserManagement.LoginUpdateRefresh`
- `UserManagement.UserDetail`
- `OrganizationUnit.PersonDetail` (vlastní osoba)
- `OrganizationUnit.PersonParentAllPersonChildren` (vlastní děti v evidenci)

Oprávnění aplikace proto musí tyto metody povolit pro libovolnou roli, ne jen pro činovníky jednotky.

## Akce, které Zvadlo ověřuje přes `ActionVerify`

Nejsou to oprávnění aplikace, ale role uživatele. Zvadlo podle nich vedoucímu nabízí nebo skrývá tlačítka.

| Tabulka (`ID_Table`) | Akce |
|---|---|
| `EV_EventGeneral` | `EV_EventGeneral_INSERT`, `EV_ParticipantGeneral_INSERT_EventGeneral`, `EV_ParticipantGeneral_DELETE_EventGeneral` |
| `EV_EventCamp` | `EV_ParticipantCamp_INSERT_EventCamp`, `EV_ParticipantCamp_DELETE` |

## Co se stane bez oprávnění

| Chybí | Dopad |
|---|---|
| `PersonParentAll` | rodiče se nenabídnou, u člena do 18 let se předvybere jeho hlavní e-mail (aplikace skautIS mobil kontakty rodičů také nemá) |
| `PersonContactAll` | Zvadlo chybu neodchytává: načítání e-mailů na stránce přidání a samotné přidání členů skončí chybou; předvyplnění kontaktu nové akce zůstane prázdné |
| `PersonDetail` pro běžné role | přihlášení na akci přes skautIS skončí chybou |
| `PersonParentAllPersonChildren` pro běžné role | přihlášení přes skautIS nabídne jen osobu účtu, ne účastníky, u kterých je zákonným zástupcem |
| zápisové metody `Events.*` | akci ani účastníky nejde propsat; Zvadlo ukáže chybu ze skautISu |
| `PersonUpdateBasic` | osoba nečlena ve skautISu zůstane jen se jménem |

## Co Zvadlo nevolá

O tyto metody není potřeba žádat. Dřívější verze specifikace je uváděla: `OrganizationUnit.MembershipAllPerson`
(`OU_Membership_ALL_Person`), `Events.EventGeneralDetail` (`EV_EventGeneral_DETAIL`), `UM_Role_ALL`
a `UM_Login_DETAIL`. Tábory Zvadlo nezakládá (`EventCampInsert` neexistuje) a akce ve skautISu neupravuje.
