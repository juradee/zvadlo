# Testovací skautIS: účty a ruční scénáře

Testovací instance `test-is.skaut.cz` má veřejně publikované účty (zadavatel je dodal 30. 9. 2026; nejde
o citlivé údaje, na produkčním skautISu nefungují). Automatizované testy Zvadla skautIS nikdy nevolají,
odpovídají fixtury z `vendor/skaut/skautis-nette/resources/fixtures`; tyto účty slouží k ručnímu ověření
v prohlížeči proti testovacímu skautISu (`APP_ENV=dev`, `SKAUTIS_TEST_MODE=true`).

| Uživatelské jméno | Heslo | Oprávnění |
|---|---|---|
| `kraj.vary` | `kvKraj.Web7` | vedoucí/administrátor kraje: testovací „Karlovy Vary“ |
| `kraj.tgm` | `tgm.Web4` | vedoucí/administrátor kraje: testovací „Jihomoravský kraj T.G.M.“ |
| `okres.blansko` | `blansko.Web2` | vedoucí/administrátor okresu: testovací „okres Blansko“ |
| `stredisko.koprivnice` | `koprivnice.Web5` | vedoucí/administrátor střediska: testovací „středisko Kopřivnice“ v KV kraji |
| `snem.sneznik.kk` | `komise1` | člen kandidátní komise střediskového sněmu |
| `snem.sneznik.uc` | `ucastnik1` | účastník střediskového sněmu |
| `spravce.akci` | `spravce6` | středisko: správce akcí Kopřivnice |

Žádný z účtů není podle popisu rodič s vazbou na dítě; vazbu „zákonný zástupce“ lze v testovacím skautISu
založit účtem `stredisko.koprivnice` u libovolného člena střediska a pak scénář 2 zopakovat s tímto účtem.

## Co ověřit ručně (otevřené body specifikace)

1. **Přihlášení vedoucího a role** (7.1, 7.2): `stredisko.koprivnice` a `spravce.akci`. V přehledu se má
   nabídnout přepínání rolí, zapamatovaná role se má po odhlášení a přihlášení vrátit, ping `/session/keep-alive`
   má vracet `{"ok":true}`.
2. **Přihlášení účastníka přes skautIS** (4.6, rozhodnutí 27): založte akci v režimu „jen přes skautIS“,
   otevřete ji, zkopírujte veřejný odkaz, odhlaste se a na veřejné stránce klikněte „Přihlásit se přes skautIS“.
   Přihlaste se `stredisko.koprivnice`. Očekává se stránka „Koho přihlašujete?“ s osobou účtu (a dětmi, když
   vazba existuje). Když skautIS vrátí chybu oprávnění, AppID nemá pro roli člena povolené `UserDetail`,
   `PersonDetail` nebo `PersonParentAllPersonChildren`; požádejte o ně na ws.skautis.cz (9.3).
3. **Zápis osoby z jiné jednotky do akce** (rozhodnutí 31, 9.2): akci založte pod `stredisko.koprivnice`,
   účastníka přihlaste účtem `okres.blansko` (jiná jednotka) a na kartě „skautIS“ akce spusťte propsání.
   `ParticipantGeneralInsert` s `ID_Person` mimo strom jednotky buď projde, nebo skautIS odmítne; v druhém
   případě Zvadlo chybu zobrazí u přihlášky a účastníka je třeba přidat ve skautISu ručně.
4. **Tábory** (4.1): `EventCampAll` musí vrátit tábory střediska a `ParticipantCampInsert` pustit účastníky až
   mimo stav `draft`.
5. **Gmail účet** (8.2): propojení vyžaduje skutečné přihlašovací údaje Googlu v `app/config/google-credentials.json`;
   testovací skautIS s tím nesouvisí.

Po ručním ověření doplňte výsledek do rozhodnutí 27 a 31 ve [specifikaci](specifikace.md).
