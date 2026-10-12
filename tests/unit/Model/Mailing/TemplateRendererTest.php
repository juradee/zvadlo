<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Mailing;

use App\Model\Mailing\EmailTemplate;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\MarkdownRenderer;
use App\Model\Mailing\TemplateRenderer;
use Codeception\Test\Unit;

final class TemplateRendererTest extends Unit
{
    public function testSymbolsAreReplacedAndUnknownOnesStay(): void
    {
        $renderer = new TemplateRenderer();

        $text = $renderer->render('Zvadlo: %akce% (%termin%) pro %jmeno% %prijmeni% %neznamy%', [
            'akce' => 'Výprava',
            'termin' => '1. 5. 2027',
            'jmeno' => 'Anna',
        ]);

        self::assertSame('Zvadlo: Výprava (1. 5. 2027) pro Anna  %neznamy%', $text);
    }

    public function testEveryDefaultTemplateUsesOnlyKnownSymbols(): void
    {
        foreach (EmailType::cases() as $type) {
            $template = EmailTemplate::default($type);
            preg_match_all('~%([a-z-]+)%~', $template->subject.' '.$template->body, $matches);
            foreach ($matches[1] as $symbol) {
                self::assertArrayHasKey($symbol, TemplateRenderer::SYMBOLS, \sprintf('%s uses unknown symbol %%%s%%', $type->value, $symbol));
            }
        }
    }

    public function testMarkdownEscapesHtmlAndLinksUrls(): void
    {
        $html = (new MarkdownRenderer())->toHtml("Ahoj **rodiče**,\n\nodkaz: https://example.cz/p?t=1 <script>alert(1)</script>");

        self::assertStringContainsString('<strong>rodiče</strong>', $html);
        self::assertStringContainsString('<a href="https://example.cz/p?t=1">', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testReminderRendersFromTheInvitationTemplate(): void
    {
        self::assertSame(EmailType::Invitation, EmailType::Reminder->templateType());
        self::assertSame(EmailType::Confirmation, EmailType::Confirmation->templateType());
        self::assertContains(EmailType::Rejected, EmailType::unitTemplates());
        self::assertNotContains(EmailType::Rejected, EmailType::eventTemplates());
        self::assertNotContains(EmailType::Reminder, EmailType::eventTemplates());
    }
}
