<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Routing\Aspect;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use Tpwd\KeSearch\Routing\Aspect\KeSearchTagToSlugMapper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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
        $language = $this->createMock(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(2);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('language')->willReturn($language);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlugAndLanguage')
            ->with('latest-news', 2)
            ->willReturn([['tag' => 'news']]);
        $filterOptionRepository->expects(self::never())->method('findBySlug');
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        $this->withRequest($request, function (): void {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertSame('news', $mapper->resolve('latest-news'));
        });
    }

    #[Test]
    public function resolveDeterminesLanguageFromSiteLanguageBasePath(): void
    {
        $defaultBase = $this->createMock(UriInterface::class);
        $defaultBase->method('getPath')->willReturn('/');
        $defaultLanguage = $this->createMock(SiteLanguage::class);
        $defaultLanguage->method('getLanguageId')->willReturn(0);
        $defaultLanguage->method('getBase')->willReturn($defaultBase);

        $englishBase = $this->createMock(UriInterface::class);
        $englishBase->method('getPath')->willReturn('/en/');
        $englishLanguage = $this->createMock(SiteLanguage::class);
        $englishLanguage->method('getLanguageId')->willReturn(1);
        $englishLanguage->method('getBase')->willReturn($englishBase);

        $site = $this->createMock(SiteInterface::class);
        $site->method('getLanguages')->willReturn([0 => $defaultLanguage, 1 => $englishLanguage]);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/en/search/search-results');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['language', null],
            ['site', $site],
        ]);
        $request->method('getUri')->willReturn($uri);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlugAndLanguage')
            ->with('dedications', 1)
            ->willReturn([['tag' => 'composer']]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        $this->withRequest($request, function (): void {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertSame('composer', $mapper->resolve('dedications'));
        });
    }

    #[Test]
    public function resolveFallsBackToSlugLookupAcrossLanguages(): void
    {
        $language = $this->createMock(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(1);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('language')->willReturn($language);

        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlugAndLanguage')
            ->with('latest-news', 1)
            ->willReturn([]);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlug')
            ->with('latest-news')
            ->willReturn([['tag' => 'news']]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        $this->withRequest($request, function (): void {
            $mapper = new KeSearchTagToSlugMapper([]);
            self::assertSame('news', $mapper->resolve('latest-news'));
        });
    }

    #[Test]
    public function resolveReturnsNullWhenSlugCannotBeResolved(): void
    {
        $filterOptionRepository = $this->createMock(FilterOptionRepository::class);
        $filterOptionRepository->expects(self::once())
            ->method('findBySlug')
            ->with('unknown-slug')
            ->willReturn([]);
        GeneralUtility::addInstance(FilterOptionRepository::class, $filterOptionRepository);

        $mapper = new KeSearchTagToSlugMapper([]);
        self::assertNull($mapper->resolve('unknown-slug'));
    }

    public static function placeholderValues(): iterable
    {
        yield 'empty default' => [''];
        yield 'hyphen default' => ['-'];
    }

    private function withRequest(ServerRequestInterface $request, callable $callback): mixed
    {
        $hadRequest = array_key_exists('TYPO3_REQUEST', $GLOBALS);
        $originalRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $GLOBALS['TYPO3_REQUEST'] = $request;
        try {
            return $callback();
        } finally {
            if ($hadRequest) {
                $GLOBALS['TYPO3_REQUEST'] = $originalRequest;
            } else {
                unset($GLOBALS['TYPO3_REQUEST']);
            }
        }
    }
}
