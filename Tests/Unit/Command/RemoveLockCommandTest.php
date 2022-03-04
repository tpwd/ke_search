<?php

declare(strict_types=1);


namespace Tpwd\KeSearch\Tests\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Tpwd\KeSearch\Command\RemoveLockCommand;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Registry;

class RemoveLockCommandTest extends TestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var MockObject|Registry
     */
    private $registryMock;

    protected function setUp(): void
    {
        $this->registryMock = $this->createMock(Registry::class);

        $command = new RemoveLockCommand($this->registryMock);
        $command->setLogger(new NullLogger());
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function startTimeOfIndexerIsNotDefinedThenNoteIsDisplayed(): void
    {
        $this->registryMock
            ->expects(self::once())
            ->method('get')
            ->with('tx_kesearch', 'startTimeOfIndexer')
            ->willReturn(null);
        $this->registryMock
            ->expects(self::never())
            ->method('removeAllByNamespace')
            ->with('tx_kesearch');

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[NOTE] Indexer lock is not set',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @test
     */
    public function startTimeOfIndexerIsDefinedThenSuccessMessageIsDisplayed(): void
    {
        $this->registryMock
            ->expects(self::once())
            ->method('get')
            ->with('tx_kesearch', 'startTimeOfIndexer')
            ->willReturn(1644951482);

        $this->registryMock
            ->expects(self::once())
            ->method('removeAllByNamespace')
            ->with('tx_kesearch');

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[OK] Indexer lock was successfully removed',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @test
     */
    public function errorMessageIsDisplayedWhenExceptionOccurs(): void
    {
        $this->registryMock
            ->method('get')
            ->with('tx_kesearch', 'startTimeOfIndexer')
            ->willThrowException(new \RuntimeException('some exception'));

        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[ERROR] There was an error accessing the TYPO3 registry',
            $this->commandTester->getDisplay()
        );
    }
}
