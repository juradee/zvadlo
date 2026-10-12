<?php

declare(strict_types=1);

namespace App\Presentation\Health;

use App\Presentation\BasePresenter;
use Doctrine\DBAL\Connection;
use Nette\Http\IResponse;
use Throwable;

/**
 * GET /health for the deploy check and uptime monitoring: the release that runs and whether the database answers.
 */
final class HealthPresenter extends BasePresenter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $releaseHash,
    ) {
        parent::__construct();
    }

    public function actionDefault(): void
    {
        $this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (Throwable) {
            $this->getHttpResponse()->setCode(IResponse::S503_ServiceUnavailable);
            $this->sendJson(['status' => 'error', 'release' => $this->releaseHash]);
        }

        $this->sendJson(['status' => 'ok', 'release' => $this->releaseHash]);
    }
}
