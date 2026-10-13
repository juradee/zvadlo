<?php

declare(strict_types=1);

// Gives phpstan-doctrine the real entity manager so it can check DQL and entity metadata.

require __DIR__.'/../../vendor/autoload.php';

// PHPStan runs without a database, so DBAL must not ask the server for its version.
putenv('DB_SERVER_VERSION=mariadb-11.4.0');

return App\Bootstrap::boot()->createContainer()->getByType(Doctrine\ORM\EntityManagerInterface::class);
