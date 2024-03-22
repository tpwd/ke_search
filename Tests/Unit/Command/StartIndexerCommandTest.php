<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Tpwd\KeSearch\Command\StartIndexerCommand;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;

class StartIndexerCommandTest extends TestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var MockObject|IndexerRunner
     */
    private $indexerRunnerMock;

    protected function setUp(): void
    {
        $this->indexerRunnerMock = $this->createMock(IndexerRunner::class);

        $command = new StartIndexerCommand($this->indexerRunnerMock);
        $command->setLogger(new NullLogger());
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     * @dataProvider dataProviderForIndexingModeOption
     */
    public function indexingModeOptionIsAppliedCorrectly(string $option, string $mode, int $expectedMode): void
    {
        $this->indexerRunnerMock
            ->expects(self::once())
            ->method('startIndexing')
            ->with(true, [], 'CLI', $expectedMode);

        // As we test here only the correct handling of the mode the value
        // of the indexerRunner method is of no interest.
        $this->indexerRunnerMock
            ->method('createPlaintextReport')
            ->willReturn('');

        $options = [];
        if ($option !== '') {
            $options[$option] = $mode;
        }
        $this->commandTester->execute($options);
    }

    public function dataProviderForIndexingModeOption(): iterable
    {
        yield 'No option is given means full indexing' => [
            'option' => '',
            'mode' => '',
            'expectedMode' => IndexerBase::INDEXING_MODE_FULL,
        ];

        yield 'Option "full" is given means full indexing' => [
            'option' => '--indexingMode',
            'mode' => 'full',
            'expectedMode' => IndexerBase::INDEXING_MODE_FULL,
        ];

        yield 'Option "incremental" is given means incremental indexing' => [
            'option' => '--indexingMode',
            'mode' => 'incremental',
            'expectedMode' => IndexerBase::INDEXING_MODE_INCREMENTAL,
        ];

        yield 'Invalid Option "invalid" means full indexing' => [
            'option' => '--indexingMode',
            'mode' => 'invalid',
            'expectedMode' => IndexerBase::INDEXING_MODE_FULL,
        ];

        yield 'Option "full" with shortcut is given means full indexing' => [
            'option' => '-m',
            'mode' => 'full',
            'expectedMode' => IndexerBase::INDEXING_MODE_FULL,
        ];

        yield 'Option "incremental" with shortcut is given means incremental indexing' => [
            'option' => '-m',
            'mode' => 'incremental',
            'expectedMode' => IndexerBase::INDEXING_MODE_INCREMENTAL,
        ];
    }

    /**
     * @test
     */
    public function indexerRunsAlreadyWarningIsDisplayed(): void
    {
        $this->indexerRunnerMock
            ->expects(self::once())
            ->method('startIndexing')
            ->with(true, [], 'CLI', IndexerBase::INDEXING_MODE_FULL)
            ->willReturn('You can\'t start the indexer twice. Please wait while first indexer process is currently running');

        $this->indexerRunnerMock
            ->expects(self::once())
            ->method('createPlaintextReport')
            ->with('You can\'t start the indexer twice. Please wait while first indexer process is currently running')
            ->willReturn('You can\'t start the indexer twice. Please wait while first indexer process is currently running');

        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[WARNING] Indexing lock is set.',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @test
     * @dataProvider dataProviderForIndexerOutput
     */
    public function indexerOutputIsCorrectlyDisplayed(string $indexerResult, string $plaintextResult, array $expectedLines): void
    {
        $this->indexerRunnerMock
            ->expects(self::once())
            ->method('startIndexing')
            ->with(true, [], 'CLI', IndexerBase::INDEXING_MODE_FULL)
            ->willReturn($indexerResult);

        $this->indexerRunnerMock
            ->expects(self::once())
            ->method('createPlaintextReport')
            ->with($indexerResult)
            ->willReturn($plaintextResult);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        foreach ($expectedLines as $line) {
            self::assertStringContainsString($line, $this->commandTester->getDisplay());
        }
    }

    public function dataProviderForIndexerOutput(): iterable
    {
        $indexerResult = <<<EOL
<div class="row"><div class="col-md-6"><div class="alert alert-info">Running indexing process in full mode.</div><table class="table table-striped table-hover"><tr><th>Indexer configuration</th><th>Mode</th><th>Info</th><th>Time</th></tr><tr><td><span class="title">Pages</span></td><td></td><td>10 pages have been selected for indexing in the main language.<br />
3 languages (All languages, English, German) have been found.<br />
2 pages have been indexed. <br />
8 had no content or the content was not indexable.<br />
0 files have been indexed.</td><td><i>Indexing process took 83 ms.</i></td></tr></table><div class="card"><div class="card-content"><h3 class="card-title">Cleanup</h3><p><strong>0</strong> entries deleted.</p>
<p><i>Cleanup process took 1 ms.</i></p>
</div></div><div class="card"><div class="card-content"><p>Indexing finished at 02/16/22, 18:36:34 (took 0 seconds).</p><p>Index contains 2 entries.</p></div></div></div></div>
EOL;
        $plaintextResult = <<<EOL
Running indexing process in full mode.
Pages
10 pages have been selected for indexing in the main language.

3 languages (All languages, English, German) have been found.

2 pages have been indexed.

8 had no content or the content was not indexable.

0 files have been indexed.Indexing process took 83 ms.Cleanup0 entries deleted.

Cleanup process took 1 ms.

Indexing finished at 02/16/22, 18:36:34 (took 0 seconds).
Index contains 2 entries.
EOL;
        yield 'No errors occurred while indexing, tags are correctly stripped off' => [
            'indexerResult' => $indexerResult,
            'plaintextResult' => $plaintextResult,
            'expectedLines' => [
                '10 pages have been selected for indexing in the main language.',
                '3 languages (All languages, English, German) have been found.',
                '2 pages have been indexed.',
                '8 had no content or the content was not indexable.',
                '0 files have been indexed.Indexing process took 83 ms.Cleanup0 entries deleted.',
                'Cleanup process took 1 ms.',
                'Indexing finished at 02/16/22, 18:36:34 (took 0 seconds).',
                'Index contains 2 entries.',
                '[OK] Indexer process completed.',
            ],
        ];
    }
}
