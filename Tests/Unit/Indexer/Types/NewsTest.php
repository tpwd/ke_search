<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Indexer\Types;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tpwd\KeSearch\Domain\Repository\PageRepository;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Indexer\Types\News;
use TYPO3\CMS\Core\LinkHandling\EmailLinkHandler;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\PageLinkHandler;
use TYPO3\CMS\Core\LinkHandling\TelephoneLinkHandler;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\LinkHandling\UrlLinkHandler;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NewsTest extends TestCase
{
    #[Test]
    public function contentElementHeadersAreIndexedOnlyWhenPresentAndVisible(): void
    {
        $indexer = (new \ReflectionClass(News::class))->newInstanceWithoutConstructor();

        $contentElements = [
            [
                'header' => null,
                'header_layout' => 0,
                'bodytext' => '<p>Body without header</p>',
            ],
            [
                'header_layout' => 0,
                'bodytext' => '<p>Body with missing header key</p>',
            ],
            [
                'header' => '<b>Visible header</b>',
                'header_layout' => 0,
                'bodytext' => '<p>Body with header</p>',
            ],
            [
                'header' => '',
                'header_layout' => 0,
                'bodytext' => '<p>Body with empty header</p>',
            ],
            [
                'header' => 'Hidden header',
                'header_layout' => 100,
                'bodytext' => '<p>Body with hidden header</p>',
            ],
        ];

        $content = $indexer->getContentFromContentElements($contentElements);

        self::assertStringContainsString('Body without header', $content);
        self::assertStringContainsString('Body with missing header key', $content);
        self::assertStringContainsString('Visible header', $content);
        self::assertStringNotContainsString('<b>', $content);
        self::assertStringContainsString('Body with empty header', $content);
        self::assertStringContainsString('Body with hidden header', $content);
        self::assertStringNotContainsString('Hidden header', $content);
        self::assertStringNotContainsString("\n\n\n", $content);
    }

    /**
     * The resolved links are what LinkService::resolve() of TYPO3 14.3
     * returns for the typolink; "FILE" stands for a file object, "MISSING"
     * for the object of a file that is gone from its storage.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool|null, 3: bool}>
     */
    public static function internalLinks(): array
    {
        $page = LinkService::TYPE_PAGE;
        // typolink, resolved link, page found (null: the page must not be looked up), expected result
        return [
            'existing page' => ['t3://page?uid=5', ['pageuid' => 5, 'type' => $page], true, true],
            'existing page, plain uid' => ['5', ['type' => $page, 'pageuid' => 5], true, true],
            'existing page, legacy anchor' => ['5#42', ['type' => $page, 'fragment' => '42', 'pageuid' => 5], true, true],
            'missing page' => ['t3://page?uid=5', ['pageuid' => 5, 'type' => $page], false, false],
            'page uid with trailing characters' => ['5abc', ['type' => $page, 'pageuid' => '5abc'], null, false],
            'current page' => ['t3://page?uid=current', ['pageuid' => 'current', 'type' => $page], null, false],
            'existing file' => ['t3://file?uid=123 _blank', ['file' => 'FILE', 'type' => 'file'], null, true],
            'existing file, legacy notation' => ['file:123', ['type' => 'file', 'file' => 'FILE'], null, true],
            'file gone from the storage' => ['t3://file?uid=124', ['file' => 'MISSING', 'type' => 'file'], null, false],
            'missing file' => ['t3://file?uid=999', ['file' => null, 'type' => 'file'], null, false],
            'missing file, legacy notation' => ['file:999', ['type' => 'unknown', 'file' => '999'], null, false],
            'path of a missing file' => ['fileadmin/gone.pdf', ['type' => 'unknown', 'file' => 'fileadmin/gone.pdf'], null, false],
            'web address' => ['https://example.org/x', ['url' => 'https://example.org/x', 'type' => 'url'], null, true],
            'web address without scheme' => ['www.example.org', ['type' => 'url', 'url' => 'http://www.example.org'], null, true],
            'protocol relative address' => ['//example.org/x', ['url' => '//example.org/x', 'type' => 'url'], null, true],
            'address relative to the site root' => ['/contact/', ['type' => 'url', 'url' => '/contact/'], null, true],
            'address with another scheme' => ['phar://x', ['url' => 'phar://x', 'type' => 'url'], null, false],
            'unresolved t3 link' => [
                't3://file?identifier=1:/gone.pdf',
                ['type' => 't3', 'url' => 't3://file?identifier=1:/gone.pdf', 'value' => '//file?identifier=1:/gone.pdf'],
                null,
                false,
            ],
            'mail address' => ['mailto:a@example.org', ['email' => 'a@example.org', 'type' => 'email'], null, false],
            'record' => ['t3://record?identifier=tx_foo&uid=9', ['identifier' => 'tx_foo', 'uid' => '9', 'type' => 'record'], null, false],
            'javascript' => ['javascript:void(0)', ['type' => 'javascript', 'url' => 'javascript:void(0)', 'value' => 'void(0)'], null, false],
        ];
    }

    /**
     * @param array<string, mixed> $resolvedLink
     */
    #[Test]
    #[DataProvider('internalLinks')]
    public function internalTargetExistsAcceptsExistingPagesFilesAndWebAddresses(
        string $typoLink,
        array $resolvedLink,
        ?bool $pageFound,
        bool $expected
    ): void {
        if (in_array($resolvedLink['file'] ?? null, ['FILE', 'MISSING'], true)) {
            $file = $this->createMock(File::class);
            $file->method('isMissing')->willReturn($resolvedLink['file'] === 'MISSING');
            $resolvedLink['file'] = $file;
        }
        $linkService = $this->createMock(LinkService::class);
        $linkService->expects(self::once())
            ->method('resolve')
            ->with(explode(' ', $typoLink)[0])
            ->willReturn($resolvedLink);
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($pageFound === null ? self::never() : self::once())
            ->method('findByUid')
            ->with(5)
            ->willReturn($pageFound ? ['uid' => 5] : false);

        self::assertSame($expected, $this->callInternalTargetExists($typoLink, $linkService, $pageRepository));
    }

    #[Test]
    public function internalTargetExistsSkipsAndLogsLinkThatCannotBeResolved(): void
    {
        $linkService = $this->createMock(LinkService::class);
        $linkService->expects(self::exactly(3))
            ->method('resolve')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new UnknownLinkHandlerException('LinkHandler for unknown was not registered', 1460581769)),
                self::throwException(new \RuntimeException('broken', 1699999999)),
                self::throwException(new \TypeError('broken handler'))
            );
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::exactly(3))->method('warning');

        self::assertFalse($this->callInternalTargetExists('t3://unknown?uid=1', $linkService, null, $logger));
        self::assertFalse($this->callInternalTargetExists('anything', $linkService, null, $logger));
        self::assertFalse($this->callInternalTargetExists('anything else', $linkService, null, $logger));
    }

    /**
     * @return array<string, array{0: string, 1: bool|null, 2: bool}>
     */
    public static function linksResolvedByTheCore(): array
    {
        // typolink, page found (null: the page must not be looked up), expected result
        return [
            'page' => ['t3://page?uid=5', true, true],
            'page, plain uid with target' => ['5 _blank', true, true],
            'missing page' => ['t3://page?uid=5', false, false],
            'page uid with trailing characters' => ['5abc', null, false],
            'current page' => ['t3://page?uid=current', null, false],
            'web address' => ['https://example.org/x', null, true],
            'protocol relative address' => ['//example.org/x', null, true],
            'address with another scheme' => ['phar://x', null, false],
            'mail address' => ['mailto:a@example.org', null, false],
            'phone number' => ['tel:+4930123', null, false],
            'javascript' => ['javascript:void(0)', null, false],
            'unknown t3 link' => ['t3://unknown?uid=1', null, false],
        ];
    }

    /**
     * Runs the links that need neither a database nor the file system
     * through the real LinkService of the installed TYPO3 version, so that
     * a changed return structure of the core is noticed. Links with "/",
     * "." or "#" in legacy notation make the core look for a file and are
     * only covered with recorded return values above.
     */
    #[Test]
    #[DataProvider('linksResolvedByTheCore')]
    public function internalTargetExistsUnderstandsWhatTheCoreResolves(
        string $typoLink,
        ?bool $pageFound,
        bool $expected
    ): void {
        $linkHandlersBefore = $GLOBALS['TYPO3_CONF_VARS']['SYS']['linkHandler'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['linkHandler'] = [
            'page' => PageLinkHandler::class,
            'url' => UrlLinkHandler::class,
            'email' => EmailLinkHandler::class,
            'telephone' => TelephoneLinkHandler::class,
        ];
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($pageFound === null ? self::never() : self::once())
            ->method('findByUid')
            ->with(5)
            ->willReturn($pageFound ? ['uid' => 5] : false);

        // The LinkService fetches an event dispatcher while resolving, depending on the TYPO3 version
        for ($i = 0; $i < 3; $i++) {
            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $eventDispatcher->method('dispatch')->willReturnArgument(0);
            GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcher);
        }

        try {
            self::assertSame($expected, $this->callInternalTargetExists($typoLink, new LinkService(), $pageRepository));
        } finally {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['linkHandler'] = $linkHandlersBefore;
        }
    }

    #[Test]
    public function internalTargetExistsSkipsEmptyLink(): void
    {
        $linkService = $this->createMock(LinkService::class);
        $linkService->expects(self::never())->method('resolve');

        self::assertFalse($this->callInternalTargetExists(null, $linkService));
        self::assertFalse($this->callInternalTargetExists(' ', $linkService));
    }

    private function callInternalTargetExists(
        ?string $typoLink,
        LinkService $linkService,
        ?PageRepository $pageRepository = null,
        ?Logger $logger = null
    ): bool {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkService);
        GeneralUtility::addInstance(TypoLinkCodecService::class, new TypoLinkCodecService($eventDispatcher));
        if ($pageRepository !== null) {
            GeneralUtility::addInstance(PageRepository::class, $pageRepository);
        }

        $indexer = (new \ReflectionClass(News::class))->newInstanceWithoutConstructor();
        $indexer->pObj = (new \ReflectionClass(IndexerRunner::class))->newInstanceWithoutConstructor();
        $indexer->pObj->logger = $logger ?? $this->createMock(Logger::class);
        $method = (new \ReflectionClass(News::class))->getMethod('internalTargetExists');

        try {
            return $method->invoke($indexer, ['internalurl' => $typoLink]);
        } finally {
            GeneralUtility::removeSingletonInstance(LinkService::class, $linkService);
            GeneralUtility::purgeInstances();
        }
    }
}
