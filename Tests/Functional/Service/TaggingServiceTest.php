<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\Service;

use Tpwd\KeSearch\Service\TaggingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class TaggingServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    protected TaggingService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = GeneralUtility::makeInstance(TaggingService::class);
    }

    public function testAddTagsToPageRecordsWithPageProperties(): void
    {
        self::markTestSkipped('FIND_IN_SET is not supported in SQLite');
    }

    public function testAddTagsToPageRecordsWithAutomatedTagging(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tx_kesearch_filteroptions.csv');

        $pageRecords = [
            10 => ['uid' => 10, 'tags' => ''],
            11 => ['uid' => 11, 'tags' => ''],
            12 => ['uid' => 12, 'tags' => ''],
            20 => ['uid' => 20, 'tags' => ''],
        ];
        $tagChar = '#';

        // Mock TreeService
        $treeServiceMock = $this->getMockBuilder(\Tpwd\KeSearch\Service\TreeService::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Expect calls for tag3 (PID 10)
        // Note: TaggingService calls getTreeList for each PID in automated_tagging
        $treeServiceMock->expects(self::any())
            ->method('getTreeList')
            ->willReturnCallback(function ($pid, $depth, $begin, $where) {
                if ($pid == 10) {
                    if (str_contains($where, 'NOT IN (12)')) {
                        return '10,11';
                    }
                    return '10,11,12';
                }
                return (string)$pid;
            });

        // Use reflection to set the mocked treeService
        $reflection = new \ReflectionClass(TaggingService::class);
        $property = $reflection->getProperty('treeService');
        $property->setAccessible(true);
        $property->setValue($this->subject, $treeServiceMock);

        // Directly call addAutomatedTags to bypass addTagsFromPageProperties which uses FIND_IN_SET
        $method = $reflection->getMethod('addAutomatedTags');
        $method->setAccessible(true);
        $result = $method->invoke($this->subject, $pageRecords, $tagChar);

        // tag3 is automated for PID 10 and its children (10, 11, 12)
        self::assertStringContainsString('#tag3#', $result[10]['tags']);
        self::assertStringContainsString('#tag3#', $result[11]['tags']);
        self::assertStringContainsString('#tag3#', $result[12]['tags']);
        self::assertStringNotContainsString('#tag3#', $result[20]['tags']);

        // tag4 is automated for PID 10 and its children, but excludes PID 12
        self::assertStringContainsString('#tag4#', $result[10]['tags']);
        self::assertStringContainsString('#tag4#', $result[11]['tags']);
        self::assertStringNotContainsString('#tag4#', $result[12]['tags']);
    }

    public function testAddTagsToPageRecordsEmptyUids(): void
    {
        $pageRecords = [10 => ['uid' => 10, 'tags' => '']];
        $result = $this->subject->addTagsToPageRecords($pageRecords, [], '#');
        self::assertEquals($pageRecords, $result);
    }
}
