<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Lib\Filters;
use Tpwd\KeSearch\Plugins\PluginBase;

class PluginBaseTest extends TestCase
{
    #[Test]
    public function isEmptySearchDetectsSelectedFilter(): void
    {
        $filters = $this->createMock(Filters::class);
        $filters->method('getFilters')->willReturn([['uid' => 5]]);

        $pluginBase = (new \ReflectionClass(PluginBase::class))->newInstanceWithoutConstructor();
        $pluginBase->filters = $filters;
        $pluginBase->sword = '';
        $pluginBase->piVars = ['filter' => [5 => 'some-tag']];

        self::assertFalse($pluginBase->isEmptySearch());
    }

    #[Test]
    public function renderNumberOfResultsStringReturnsEmptyStringWhenOptionIsSelected(): void
    {
        $pluginBase = (new \ReflectionClass(PluginBase::class))->newInstanceWithoutConstructor();

        $filter = ['shownumberofresults' => true, 'selectedOptions' => [5]];

        $result = $pluginBase->renderNumberOfResultsString(3, $filter);

        self::assertSame('', $result);
    }
}
