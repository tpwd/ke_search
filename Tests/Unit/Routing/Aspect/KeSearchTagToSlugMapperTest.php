<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Routing\Aspect;

use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Routing\Aspect\KeSearchTagToSlugMapper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class KeSearchTagToSlugMapperTest extends TestCase
{
    #[Test]
    #[DataProvider('placeholderValues')]
    public function resolveReturnsPlaceholderValuesUnchanged(string $value): void
    {
        $mapper = new KeSearchTagToSlugMapper([]);

        self::assertSame($value, $mapper->resolve($value));
    }

    #[Test]
    public function resolveReturnsNullWhenSlugCannotBeResolved(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getPropertyFromAspect')->willReturn(0);
        GeneralUtility::setSingletonInstance(Context::class, $context);

        $result = $this->createMock(Result::class);
        $result->expects(self::once())->method('fetchAssociative')->willReturn(false);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('condition');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('parameter');
        $queryBuilder->expects(self::once())->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with('tx_kesearch_filteroptions')
            ->willReturn($queryBuilder);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPool);

        try {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertNull($mapper->resolve('unknown-slug'));
        } finally {
            GeneralUtility::removeSingletonInstance(Context::class, $context);
        }
    }

    public static function placeholderValues(): iterable
    {
        yield 'empty default' => [''];
        yield 'hyphen default' => ['-'];
    }
}
