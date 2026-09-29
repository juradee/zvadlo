# webwingscz/skautis-nette

Nette integration of the [skaut/skautis](https://github.com/skaut/Skautis) library (3.1+): DI extension,
session adapter, one PSR-14 dispatcher for the Tracy panel and your own listeners, and JSON fixtures that
replace skautIS in tests.

Developed inside the Zvadlo repository as a path repository; it has no dependency on the application and
can be split into its own repository or merged into `skautis/nette` later.

## Configuration

```neon
extensions:
    skautis: Webwings\SkautisNette\DI\SkautisExtension

skautis:
    applicationId: %env.APPLICATION_ID%
    testMode: true          # test-is.skaut.cz; false = is.skaut.cz
    cache: true             # cache WSDL
    compression: true
    profiler: null          # Tracy panel; null = debug mode
    fixtures: null          # directory with JSON fixtures; when set, skautIS is never called
```

The keys are the same as in `skautis/nette`, so switching between the two is a one-line change.

Registered services: `Skaut\Skautis\Skautis`, `Skaut\Skautis\User`, `Skaut\Skautis\Config`,
`Skaut\Skautis\Wsdl\WsdlManager`, `Webwings\SkautisNette\EventDispatcher` (add listeners with
`addListener(callable)`), and with Tracy the panel.

## Fixtures

`{fixtures}/{Service}/{Method}.json` contains the normalised result exactly as the library returns it:
an object, a list of objects or `null`. A variant for one argument, e.g.
`OrganizationUnit/PersonAll__ID_Unit=1002.json`, wins over the plain file; arguments are tried in the order
they were passed. A missing file throws `FixtureNotFoundException` with the path to create.

`resources/fixtures` ships a small unit (středisko 1002 with two oddíly) with persons, contacts, parents,
roles and a login that never expires. Log in the same way as with the real skautIS: POST `skautIS_Token`,
`skautIS_IDRole`, `skautIS_IDUnit` and `skautIS_DateLogout` to your login action.

## Tests

From the Zvadlo repository: `make test-packages`.
