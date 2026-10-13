<?php

declare(strict_types=1);

// Gives phpstan-doctrine the real entity manager so it can check DQL and entity metadata.

require __DIR__.'/../../vendor/autoload.php';

$configurator = App\Bootstrap::boot();
// The application lets DBAL ask the server for its version; PHPStan runs without a database.
$configurator->addConfig(['dbal' => ['connections' => ['default' => ['serverVersion' => 'mariadb-11.4.0']]]]);

return $configurator->createContainer()->getByType(Doctrine\ORM\EntityManagerInterface::class);
