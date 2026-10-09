<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Routing\Aspect;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use Tpwd\KeSearch\Routing\Aspect\KeSearchTagToSlugMapper;
use TYPO3\CMS\Core\Context\Context;
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
    public function generateReturnsSlugFoundByRepository(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getPropertyFromAspect')->willReturn(0);
        GeneralUtility::setSingletonInstance(Context::class, $context);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findByTagAndLanguage')
            ->with('news', 0)
            ->willReturn([['slug' => 'latest-news']]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        try {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertSame('latest-news', $mapper->generate('news'));
        } finally {
            GeneralUtility::removeSingletonInstance(Context::class, $context);
        }
    }

    #[Test]
    public function resolveReturnsTagFoundByRepository(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getPropertyFromAspect')->willReturn(2);
        GeneralUtility::setSingletonInstance(Context::class, $context);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlugAndLanguage')
            ->with('latest-news', 2)
            ->willReturn([['tag' => 'news']]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        try {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertSame('news', $mapper->resolve('latest-news'));
        } finally {
            GeneralUtility::removeSingletonInstance(Context::class, $context);
        }
    }

    #[Test]
    public function resolveReturnsNullWhenSlugCannotBeResolved(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getPropertyFromAspect')->willReturn(0);
        GeneralUtility::setSingletonInstance(Context::class, $context);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlugAndLanguage')
            ->with('unknown-slug', 0)
            ->willReturn([]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

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
