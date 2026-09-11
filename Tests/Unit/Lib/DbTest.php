<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Lib;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tpwd\KeSearch\Domain\Search\SearchExecutionContext;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;

class DbTest extends TestCase
{
    #[Test]
    public function createQueryForDateRangeAppliesDateRangeForMatchingFilter(): void
    {
        $filters = $this->createMock(Filters::class);
        $filters->method('getFilters')->willReturn([1 => ['rendertype' => 'dateRange']]);

        $searchContext = new SearchExecutionContext();
        $searchContext->setFilters($filters);
        $searchContext->setPiVars(['filter' => [1 => ['start' => '2024-01-01', 'end' => '2024-01-31']]]);

        $db = new Db($this->createMock(EventDispatcherInterface::class));
        $db->searchContext = $searchContext;

        $result = $db->createQueryForDateRange();

        self::assertStringContainsString('AND tx_kesearch_index.sortdate >=', $result);
        self::assertStringContainsString('AND tx_kesearch_index.sortdate <=', $result);
    }
}
