<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Indexer\Types;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Indexer\Types\Page;
use TYPO3\CMS\Core\Log\Logger;

class PageTest extends TestCase
{
    #[Test]
    public function shortcutsInHiddenContainersAreFilteredBeforeProcessing(): void
    {
        $indexer = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['contentElementShouldBeIndexed'])
            ->getMock();

        $indexer->expects(self::exactly(2))
            ->method('contentElementShouldBeIndexed')
            ->willReturnCallback(
                static fn(array $row): bool => $row['uid'] !== 2
            );

        $method = (new \ReflectionClass(Page::class))->getMethod('filterShortcutsBeforeProcessing');
        $method->setAccessible(true);

        $rows = $method->invoke($indexer, [
            ['uid' => 1, 'CType' => 'shortcut'],
            ['uid' => 2, 'CType' => 'shortcut'],
            ['uid' => 3, 'CType' => 'text'],
        ]);

        self::assertSame([
            ['uid' => 1, 'CType' => 'shortcut'],
            ['uid' => 3, 'CType' => 'text'],
        ], $rows);
    }

    #[Test]
    public function pageRecordFieldsAreConvertedToPlainText(): void
    {
        $previousColumns = $GLOBALS['TCA']['pages']['columns'] ?? null;
        $GLOBALS['TCA']['pages']['columns'] = [
            'author' => ['config' => ['type' => 'input']],
            'description' => ['config' => ['type' => 'text']],
            'author_email' => ['config' => ['type' => 'email']],
            'empty_field' => ['config' => ['type' => 'input']],
        ];

        try {
            $indexer = (new \ReflectionClass(Page::class))->newInstanceWithoutConstructor();
            $logger = $this->createMock(Logger::class);
            $logger->expects(self::once())
                ->method('warning')
                ->with(
                    'Page field "{field}" is not defined in TCA, skipping.',
                    ['field' => 'unknown_field']
                );
            $indexer->pObj = $this->createMock(IndexerRunner::class);
            $indexer->pObj->logger = $logger;

            self::assertSame(
                "Jane Doe\nTYPO3 expert\njane@example.com",
                $indexer->getContentFromPageRecord(
                    [
                        'author' => '<p>Jane <strong>Doe</strong></p>',
                        'description' => ' TYPO3 expert ',
                        'author_email' => 'jane@example.com',
                        'empty_field' => '',
                    ],
                    ['author', 'description', 'author_email', 'empty_field', 'unknown_field']
                )
            );
        } finally {
            if ($previousColumns === null) {
                unset($GLOBALS['TCA']['pages']['columns']);
            } else {
                $GLOBALS['TCA']['pages']['columns'] = $previousColumns;
            }
        }
    }

    #[Test]
    public function pageFieldsWhichAreNotAllowedForIndexingAreSkipped(): void
    {
        $previousColumns = $GLOBALS['TCA']['pages']['columns'] ?? null;
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => ['config' => ['type' => 'input']],
            'TSconfig' => [
                'displayCond' => 'HIDE_FOR_NON_ADMINS',
                'config' => ['type' => 'text', 'renderType' => 'codeEditor'],
            ],
            'code_field' => ['config' => ['type' => 'text', 'renderType' => 'codeEditor']],
            'admin_only_field' => [
                'displayCond' => ['AND' => ['FIELD:doktype:=:1', 'HIDE_FOR_NON_ADMINS']],
                'config' => ['type' => 'input'],
            ],
            'layout' => ['config' => ['type' => 'select', 'renderType' => 'selectSingle']],
            'no_config_field' => [],
        ];

        try {
            $indexer = (new \ReflectionClass(Page::class))->newInstanceWithoutConstructor();
            $logger = $this->createMock(Logger::class);
            $skippedFields = [];
            $logger->expects(self::exactly(5))
                ->method('warning')
                ->willReturnCallback(
                    static function (string $message, array $context) use (&$skippedFields): void {
                        self::assertSame('Page field "{field}" is not allowed for indexing, skipping.', $message);
                        $skippedFields[] = $context['field'];
                    }
                );
            $indexer->pObj = $this->createMock(IndexerRunner::class);
            $indexer->pObj->logger = $logger;

            self::assertSame(
                'Page title',
                $indexer->getContentFromPageRecord(
                    [
                        'title' => 'Page title',
                        'TSconfig' => 'secret = 1',
                        'code_field' => 'code',
                        'admin_only_field' => 'admin',
                        'layout' => '1',
                        'no_config_field' => 'value',
                    ],
                    ['title', 'TSconfig', 'code_field', 'admin_only_field', 'layout', 'no_config_field']
                )
            );
            self::assertSame(
                ['TSconfig', 'code_field', 'admin_only_field', 'layout', 'no_config_field'],
                $skippedFields
            );
        } finally {
            if ($previousColumns === null) {
                unset($GLOBALS['TCA']['pages']['columns']);
            } else {
                $GLOBALS['TCA']['pages']['columns'] = $previousColumns;
            }
        }
    }
}
