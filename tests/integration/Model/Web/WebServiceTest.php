<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Web;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Web\EventContent;
use App\Model\Web\EventContentRepository;
use App\Model\Web\FileStorage;
use App\Model\Web\InvalidUploadException;
use App\Model\Web\WebService;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Http\FileUpload;

final class WebServiceTest extends Unit
{
    protected IntegrationTester $tester;

    private WebService $service;

    private EventContentRepository $contents;

    private FileStorage $files;

    private Event $event;

    protected function _before(): void
    {
        $this->tester->grabService(EntityManagerInterface::class)->createQuery('DELETE FROM '.Event::class)->execute();
        $this->service = $this->tester->grabService(WebService::class);
        $this->contents = $this->tester->grabService(EventContentRepository::class);
        $this->files = $this->tester->grabService(FileStorage::class);
        $this->event = $this->tester->grabService(EventService::class)->create(1003, new EventInput(
            name: 'Výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable('+30 days'),
            dateTo: new DateTimeImmutable('+30 days'),
            place: null,
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        ), 1);
    }

    public function testBlocksAreOrderedMovedAndRemovedWithTheirFiles(): void
    {
        $text = $this->service->addText($this->event, 'Co s sebou', "- spacák\n- baterka");
        $link = $this->service->addLink($this->event, 'Fotky', 'https://example.cz/album');
        $file = $this->service->addFile($this->event, '', $this->upload('Zvadlo Podzim.pdf'));

        self::assertSame('zvadlo-podzim.pdf', $file->getOriginalName());
        self::assertSame('application/pdf', $file->getMimeType());
        self::assertSame('Zvadlo Podzim', $file->getTitle(), 'title defaults to the file name');
        self::assertTrue($this->files->exists($file->getPath()));
        self::assertStringStartsWith('events/'.$this->event->getId().'/', $file->getPath());

        self::assertSame([$text, $link, $file], $this->contents->findByEvent($this->event));
        $this->service->move($file, -1);
        self::assertSame([$text, $file, $link], $this->contents->findByEvent($this->event));
        $this->service->move($text, -1);
        self::assertSame([$text, $file, $link], $this->contents->findByEvent($this->event), 'the first block stays first');

        $path = $file->getPath();
        $this->service->remove($file);
        self::assertFalse($this->files->exists($path));
        self::assertCount(2, $this->contents->findByEvent($this->event));
        self::assertInstanceOf(EventContent::class, $this->contents->find($text->getId()));
    }

    public function testOnlyPdfAndImagesAreAccepted(): void
    {
        $this->expectException(InvalidUploadException::class);
        $this->service->addFile($this->event, 'Skript', $this->upload('evil.php', '<?php echo 1;'));
    }

    public function testReplacingTheFileDeletesTheOldOne(): void
    {
        $file = $this->service->addFile($this->event, 'Seznam', $this->upload('a.pdf'));
        $old = $file->getPath();

        $this->service->updateFile($file, 'Seznam věcí', $this->upload('b.pdf'));

        self::assertSame('Seznam věcí', $file->getTitle());
        self::assertNotSame($old, $file->getPath());
        self::assertFalse($this->files->exists($old));
        self::assertTrue($this->files->exists($file->getPath()));
    }

    private function upload(string $name, ?string $content = null): FileUpload
    {
        $temp = tempnam(sys_get_temp_dir(), 'zvadlo');
        \assert(\is_string($temp));
        file_put_contents($temp, $content ?? (string) file_get_contents(__DIR__.'/../../../Support/Data/zvadlo.pdf'));

        return new FileUpload(['name' => $name, 'tmp_name' => $temp, 'size' => (int) filesize($temp), 'error' => \UPLOAD_ERR_OK]);
    }
}
