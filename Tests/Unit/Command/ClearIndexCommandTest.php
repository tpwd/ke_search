<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Tpwd\KeSearch\Command\ClearIndexCommand;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;

class ClearIndexCommandTest extends TestCase
{
    /**
     * @var MockObject|IndexRepository
     */
    private $indexRepositoryMock;

    /**
     * @var CommandTester
     */
    private $commandTester;

    protected function setUp(): void
    {
        $this->indexRepositoryMock = $this->createMock(IndexRepository::class);

        $command = new ClearIndexCommand($this->indexRepositoryMock);
        $command->setLogger(new NullLogger());
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function noEntriesInIndexTableThenNoteIsDisplayed(): void
    {
        $this->indexRepositoryMock
            ->expects(self::once())
            ->method('getTotalNumberOfRecords')
            ->willReturn(0);
        $this->indexRepositoryMock
            ->expects(self::never())
            ->method('truncate');

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[NOTE] There are no entries in ke_search index table',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @test
     */
    public function entriesInTableThenSuccessMessageIsDisplayed(): void
    {
        $this->indexRepositoryMock
            ->expects(self::once())
            ->method('getTotalNumberOfRecords')
            ->willReturn(42);
        $this->indexRepositoryMock
            ->expects(self::once())
            ->method('truncate');

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '42 index records found',
            $this->commandTester->getDisplay()
        );
        self::assertStringContainsString(
            '[OK] ke_search index table was truncated',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @test
     */
    public function errorMessageIsDisplayedWhenExceptionOccurs(): void
    {
        $this->indexRepositoryMock
            ->expects(self::once())
            ->method('getTotalNumberOfRecords')
            ->willReturn(42);
        $this->indexRepositoryMock
            ->method('truncate')
            ->willThrowException(new \RuntimeException('some exception occurred'));

        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[ERROR] some exception occurred',
            $this->commandTester->getDisplay()
        );
    }
}
