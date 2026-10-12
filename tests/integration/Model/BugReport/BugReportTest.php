<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\BugReport;

use App\Model\Admin\Statistics;
use App\Model\Admin\SystemRole;
use App\Model\Admin\SystemUserRoleRepository;
use App\Model\BugReport\GitHubIssueClient;
use App\Model\BugReport\TechnicalErrorReport;
use App\Model\BugReport\TechnicalErrorReportRepository;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

/**
 * GitHub issue and comment requests, system roles and the admin statistics.
 */
final class BugReportTest extends Unit
{
    protected IntegrationTester $tester;

    public function testGithubIssueAndCommentAreCreatedThroughTheApi(): void
    {
        $reports = $this->tester->grabService(TechnicalErrorReportRepository::class);
        \assert($reports instanceof TechnicalErrorReportRepository);
        $report = new TechnicalErrorReport('Tlačítko Uložit nereaguje.', 'https://zvadlo.test/akce/1', 1, 'Jan Novák', 'jan.novak@example.cz', 10, 'Vedoucí oddílu', 1003, '3. oddíl Vlčata', '127.0.0.1', 'Codeception', 'test', ['browser' => ['language' => 'cs']], new DateTimeImmutable());
        $reports->add($report);
        $reports->save();

        $mock = new MockHandler([
            new Response(201, [], (string) json_encode(['number' => 42, 'html_url' => 'https://github.com/juradee/zvadlo/issues/42'])),
            new Response(201, [], (string) json_encode(['html_url' => 'https://github.com/juradee/zvadlo/issues/42#issuecomment-1'])),
        ]);
        $client = new GitHubIssueClient(new Client(['handler' => HandlerStack::create($mock)]), ['token' => 'secret', 'owner' => 'juradee', 'repository' => 'zvadlo', 'labels' => ['bug', '']]);

        self::assertTrue($client->isConfigured());
        $issue = $client->createIssue($report);
        $created = $mock->getLastRequest();
        self::assertNotNull($created);
        self::assertSame(42, $issue['number']);
        $report->markGithubIssueCreated($issue['number'], $issue['url'], new DateTimeImmutable());
        $reports->save();
        self::assertTrue($report->hasGithubIssue());

        self::assertSame('https://github.com/juradee/zvadlo/issues/42#issuecomment-1', $client->addComment($report, 'Díky, podíváme se.'));
        $comment = $mock->getLastRequest();
        self::assertNotNull($comment);

        self::assertSame('https://api.github.com/repos/juradee/zvadlo/issues', (string) $created->getUri());
        self::assertSame('Bearer secret', $created->getHeaderLine('Authorization'));
        $body = json_decode((string) $created->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(['bug'], $body['labels'], 'empty labels are dropped');
        self::assertStringContainsString('Tlačítko Uložit nereaguje.', $body['body']);
        self::assertStringContainsString('#'.$report->getId(), $body['title']);
        self::assertSame('https://api.github.com/repos/juradee/zvadlo/issues/42/comments', (string) $comment->getUri());
    }

    public function testUnconfiguredClientRefusesToCall(): void
    {
        $client = new GitHubIssueClient(new Client(), ['token' => null, 'owner' => 'juradee', 'repository' => 'zvadlo', 'labels' => []]);
        $report = new TechnicalErrorReport('x', null, 1, 'Jan', null, null, null, null, null, null, null, 'test', [], new DateTimeImmutable());

        self::assertFalse($client->isConfigured());
        $this->expectException(RuntimeException::class);
        $client->addComment($report, 'x');
    }

    public function testSystemRolesAreReplacedAndRemoved(): void
    {
        $roles = $this->tester->grabService(SystemUserRoleRepository::class);
        \assert($roles instanceof SystemUserRoleRepository);

        $roles->replaceRoles(777, [SystemRole::Support]);
        self::assertTrue($roles->hasRole(777, SystemRole::Support));
        self::assertFalse($roles->hasRole(777, SystemRole::Admin));

        $roles->replaceRoles(777, [SystemRole::Admin, SystemRole::Support]);
        self::assertCount(2, $roles->findAllGroupedByUserId()[777]);
        self::assertTrue($roles->hasRole(777, SystemRole::Admin));

        $roles->removeAllForUser(777);
        self::assertFalse($roles->hasRole(777, SystemRole::Support));
        self::assertArrayNotHasKey(777, $roles->findAllGroupedByUserId());
    }

    public function testStatisticsCoverTheWholeYear(): void
    {
        $statistics = $this->tester->grabService(Statistics::class);
        \assert($statistics instanceof Statistics);

        self::assertArrayHasKey('akcí', $statistics->totals());
        $months = $statistics->perMonth((int) date('Y'));
        self::assertCount(12, $months);
        self::assertSame(['events', 'registrations', 'emailsSent', 'emailsFailed', 'bugReports'], array_keys($months[1]));
        self::assertIsArray($statistics->units());
    }
}
