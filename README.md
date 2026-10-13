# Zvadlo

Registrační systém pro jednotky Junáka – českého skauta: zvadlo na výpravu, přihláška přes osobní odkaz,
miniweb akce, e-maily z účtu jednotky a propsání do skautISu, kde akci vidí Skautské hospodaření.

## Rychlý start

```bash
make init
make up
```

Rezerví adresa aplikace je  http://moje-zvadlo.cz , adresu jde uvést v hosts. Testovací appId směřuje na tuto doménu. 
Viz [instalace](.docs/instalace.md)). `make help` vypíše všechny cíle, `make ci` spustí totéž co GitHub Actions.

## Dokumentace

- [Specifikace](.docs/specifikace.md) – co systém dělá, aktéři, tok přihlášky, oprávnění, datový model, rozhodnutí zadavatele
- [Implementační plán](.docs/implementacni-plan.md) – technický základ, struktura, proměnné prostředí, fáze
- [Instalace](.docs/instalace.md) – lokální vývoj v Dockeru
- [Nasazení](.docs/nasazeni.md) – GitHub environments, Deployer, cron, údržba, logy, retence
- [Testovací skautIS](.docs/skautis-testovaci-ucty.md) – veřejné testovací účty a ruční scénáře k ověření
- [Knihovny skautIS](.docs/skautis-knihovny-3.1.md) – jádro `skaut/skautis` 3.1 a rozšíření `skaut/skautis-nette` 3.1
- [Pokyny pro agenty](.agents/AGENTS.md) – konvence pro automatizované nástroje

Sesterské projekty, ze kterých Zvadlo přebírá konvence a chování: [Skautské hospodaření](https://github.com/skaut/Skautske-hospodareni)
(h.skauting.cz) a [SRS](https://github.com/skaut/SRS).

Licence: [AGPL-3.0-or-later](LICENSE).
