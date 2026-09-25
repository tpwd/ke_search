<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Indexer\Types;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Indexer\Types\Page;

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
}
