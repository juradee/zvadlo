<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use RuntimeException;

/**
 * Creates issues and comments in the project's GitHub repository from a bug report, on the admin's request.
 */
final class GitHubIssueClient
{
    private readonly ?string $token;

    private readonly ?string $owner;

    private readonly ?string $repository;

    /** @var list<string> */
    private readonly array $labels;

    /**
     * @param array{token?: string|null, owner?: string|null, repository?: string|null, labels?: list<string>} $config
     */
    public function __construct(
        private readonly ClientInterface $http,
        array $config,
    ) {
        $this->token = self::nullable($config['token'] ?? null);
        $this->owner = self::nullable($config['owner'] ?? null);
        $this->repository = self::nullable($config['repository'] ?? null);
        $this->labels = array_values(array_filter($config['labels'] ?? [], static fn (string $label): bool => trim($label) !== ''));
    }

    public function isConfigured(): bool
    {
        return $this->token !== null && $this->owner !== null && $this->repository !== null;
    }

    /**
     * @return array{number: int, url: string}
     */
    public function createIssue(TechnicalErrorReport $report): array
    {
        $payload = [
            'title' => \sprintf('Hlášení technické chyby #%d ze Zvadla', $report->getId()),
            'body' => implode("\n", [
                'Automatické issue ze Zvadla.',
                '',
                '## Hlášení',
                '- Nahlášeno: '.$report->getCreatedAt()->format('d.m.Y H:i:s'),
                '- Uživatel: '.$report->getReporterDisplayName().' (ID '.$report->getReporterUserId().')',
                '- URL chyby: '.($report->getReportedUrl() ?? '-'),
                '- Release: '.$report->getAppRelease(),
                '',
                '## Popis',
                $report->getDescription(),
            ]),
        ];
        if ($this->labels !== []) {
            $payload['labels'] = $this->labels;
        }

        $data = $this->request('/issues', $payload);
        if (! isset($data['number'], $data['html_url']) || ! \is_int($data['number']) || ! \is_string($data['html_url'])) {
            throw new RuntimeException('GitHub vrátil neplatnou odpověď na založení issue.');
        }

        return ['number' => $data['number'], 'url' => $data['html_url']];
    }

    public function addComment(TechnicalErrorReport $report, string $message): string
    {
        $number = $report->getGithubIssueNumber() ?? throw new RuntimeException('Hlášení nemá GitHub issue.');
        $data = $this->request(\sprintf('/issues/%d/comments', $number), [
            'body' => "Správce odeslal uživateli odpověď ze Zvadla.\n\n```\n".$message."\n```",
        ]);
        if (! isset($data['html_url']) || ! \is_string($data['html_url'])) {
            throw new RuntimeException('GitHub vrátil neplatnou odpověď na komentář.');
        }

        return $data['html_url'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Propojení s GitHubem není nastavené (GITHUB_ISSUES_TOKEN, OWNER, REPOSITORY).');
        }

        try {
            $response = $this->http->request('POST', \sprintf('https://api.github.com/repos/%s/%s%s', $this->owner, $this->repository, $path), [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer '.$this->token,
                    'User-Agent' => 'zvadlo',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
                'json' => $payload,
            ]);
            $data = Json::decode((string) $response->getBody(), forceArrays: true);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Požadavek na GitHub selhal: '.$exception->getMessage(), 0, $exception);
        } catch (JsonException $exception) {
            throw new RuntimeException('GitHub vrátil neplatný JSON.', 0, $exception);
        }

        return \is_array($data) ? $data : [];
    }

    private static function nullable(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
