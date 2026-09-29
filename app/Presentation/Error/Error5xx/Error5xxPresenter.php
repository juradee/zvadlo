<?php

declare(strict_types=1);

namespace App\Presentation\Error\Error5xx;

use Nette\Application\Attributes\Requires;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\CallbackResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Throwable;
use Tracy\ILogger;

#[Requires(forward: true)]
final class Error5xxPresenter implements IPresenter
{
    public function __construct(private readonly ILogger $logger)
    {
    }

    public function run(Request $request): Response
    {
        $exception = $request->getParameter('exception');
        if ($exception instanceof Throwable) {
            $this->logger->log($exception, ILogger::EXCEPTION);
        }

        return new CallbackResponse(static function (IRequest $httpRequest, IResponse $httpResponse): void {
            if (preg_match('#^text/html(?:;|$)#', (string) $httpResponse->getHeader('Content-Type')) === 1) {
                require __DIR__.'/500.phtml';
            }
        });
    }
}
