<?php

declare(strict_types=1);

/**
 * Shown by index.php while MAINTENANCE_MODE is on; $maintenance comes from App\Environment.
 *
 * @var array{startedAt: ?string, endsAt: ?string} $maintenance
 */
$maintenance ??= ['startedAt' => null, 'endsAt' => null];
$format = static function (?string $iso): ?string {
    if ($iso === null || $iso === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Europe/Prague'))->format('d.m.Y H:i');
    } catch (Exception) {
        return null;
    }
};
$startedAt = $format($maintenance['startedAt'] ?? null);
$endsAt = $format($maintenance['endsAt'] ?? null);

header('HTTP/1.1 503 Service Unavailable');
header('Retry-After: 300');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Zvadlo – probíhá údržba</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f6f4; color: #1f2a2a; }
        main { max-width: 32rem; background: #fff; border-radius: 16px; padding: 28px 24px; box-shadow: 0 12px 40px rgba(31, 42, 42, .12); }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; }
        p { line-height: 1.5; margin: 0 0 .75rem; }
        dl { margin: 1rem 0 0; display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; }
        dt { color: #63706d; }
        dd { margin: 0; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1>Zvadlo má právě odstávku</h1>
    <p>Probíhá plánovaná údržba. Přihlášky, které už jste odeslali, jsou v bezpečí a vaše osobní odkazy budou po skončení údržby fungovat dál.</p>
    <p>Zkuste to prosím později.</p>
    <?php if ($startedAt !== null || $endsAt !== null): ?>
    <dl>
        <?php if ($startedAt !== null): ?><dt>Začátek</dt><dd><?= htmlspecialchars($startedAt, ENT_QUOTES) ?></dd><?php endif; ?>
        <?php if ($endsAt !== null): ?><dt>Předpokládaný konec</dt><dd><?= htmlspecialchars($endsAt, ENT_QUOTES) ?></dd><?php endif; ?>
    </dl>
    <?php endif; ?>
</main>
</body>
</html>
