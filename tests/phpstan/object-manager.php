<?php

declare(strict_types=1);

// Gives phpstan-doctrine the real entity manager so it can check DQL and entity metadata.

require __DIR__.'/../../vendor/autoload.php';

return App\Bootstrap::boot()->createContainer()->getByType(Doctrine\ORM\EntityManagerInterface::class);
