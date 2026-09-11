<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\Lib;

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Domain\Search\SearchContextInterface;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @see https://github.com/tpwd/ke_search/issues/328
 */
class FiltersLanguageOverlayTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    protected Filters $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Hooks/Fixtures/localized_filter_options.csv');

        $this->subject = GeneralUtility::makeInstance(Filters::class);
        $this->subject->initialize($this->createSearchContextStub());
    }

    /**
     * With fallbackType "free" the LanguageAspect overlay type is OVERLAYS_OFF. Since ke_search
     * always fetches rows by their default language uid, PageRepository::getLanguageOverlay()
     * would not do anything in this case, so the translated title has to be resolved manually.
     */
    public function testLanguageOverlayResolvesTranslatedFilterInFreeFallbackMode(): void
    {
        GeneralUtility::makeInstance(Context::class)->setAspect(
            'language',
            new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF)
        );

        $filterRow = $this->fetchRow('tx_kesearch_filters', 10);
        $result = $this->subject->languageOverlay([10 => $filterRow], 'tx_kesearch_filters');

        self::assertArrayHasKey(10, $result);
        self::assertSame('Filter DE 1', $result[10]['title']);
        // The uid must stay the default language uid, since ke_search relies on it for
        // matching piVars and for reordering the "options" list against the original uid list.
        self::assertSame(10, (int)$result[10]['uid']);
    }

    public function testLanguageOverlayResolvesTranslatedFilterOptionInFreeFallbackMode(): void
    {
        GeneralUtility::makeInstance(Context::class)->setAspect(
            'language',
            new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF)
        );

        $optionRow = $this->fetchRow('tx_kesearch_filteroptions', 1000);
        $result = $this->subject->languageOverlay([1000 => $optionRow], 'tx_kesearch_filteroptions');

        self::assertArrayHasKey(1000, $result);
        self::assertSame('Kategorie DE', $result[1000]['title']);
        // The uid must stay the default language uid, see comment above.
        self::assertSame(1000, (int)$result[1000]['uid']);
    }

    public function testLanguageOverlayKeepsDefaultLanguageRowWhenNoTranslationExistsInFreeFallbackMode(): void
    {
        GeneralUtility::makeInstance(Context::class)->setAspect(
            'language',
            new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF)
        );

        // Filter option 2000 has a DE and a ZH translation, but no RU translation.
        $optionRow = $this->fetchRow('tx_kesearch_filteroptions', 2000);
        $result = $this->subject->languageOverlay([2000 => $optionRow], 'tx_kesearch_filteroptions');

        self::assertArrayHasKey(2000, $result);
        self::assertSame('Category EN 2', $result[2000]['title']);
    }

    /**
     * @see https://github.com/tpwd/ke_search/issues/341
     *
     * Regression test: languageOverlay() used to replace the filter's uid with the translated
     * record's uid in free fallback mode. Since piVars (submitted via the search form) are always
     * keyed by the default language filter uid, this caused getSelectedFilterOptions() to look up
     * the wrong key in piVars and always return an empty "selectedOptions" array on translated pages.
     */
    public function testSelectedFilterOptionsAreResolvedInFreeFallbackMode(): void
    {
        GeneralUtility::makeInstance(Context::class)->setAspect(
            'language',
            new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF)
        );

        $filterRow = $this->fetchRow('tx_kesearch_filters', 10);
        $optionRow = $this->fetchRow('tx_kesearch_filteroptions', 1000);

        $subject = GeneralUtility::makeInstance(Filters::class);
        // The piVar is keyed by the default language filter uid (10), exactly as it would be
        // submitted by a search form rendered on the translated page.
        $subject->initialize($this->createSearchContextStub(
            [],
            ['filter' => [10 => 'syscat100']]
        ));

        $overlaidFilter = $subject->languageOverlay([10 => $filterRow], 'tx_kesearch_filters')[10];
        $overlaidFilter['options'] = [
            $overlaidFilter['options'] => $subject->languageOverlay(
                [1000 => $optionRow],
                'tx_kesearch_filteroptions'
            )[1000],
        ];

        $selectedOptions = $subject->getSelectedFilterOptions($overlaidFilter);

        self::assertSame(10, (int)$overlaidFilter['uid']);
        self::assertNotEmpty($selectedOptions);
    }

    protected function fetchRow(string $table, int $uid): array
    {
        $queryBuilder = Db::getQueryBuilder($table);
        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row);
        return $row;
    }

    protected function createSearchContextStub(array $conf = [], array $piVars = []): SearchContextInterface
    {
        return new class ($conf, $piVars) implements SearchContextInterface {
            public function __construct(private array $conf = [], private array $piVars = [])
            {
            }

            public function getConf(): array
            {
                return $this->conf;
            }

            public function getExtConf(): array
            {
                return ['prePostTagChar' => '#'];
            }

            public function getExtConfPremium(): array
            {
                return [];
            }

            public function getPiVars(): array
            {
                return $this->piVars;
            }

            public function setPiVars(array $piVars): void {}

            public function getSword(): string
            {
                return '';
            }

            public function getWordsAgainst(): string
            {
                return '';
            }

            public function getScoreAgainst(): string
            {
                return '';
            }

            public function getTagsAgainst(): array
            {
                return [];
            }

            public function getStartingPoints(): string
            {
                return '1';
            }

            public function getIsEmptySearch(): bool
            {
                return true;
            }

            public function getFilters(): Filters
            {
                return GeneralUtility::makeInstance(Filters::class);
            }

            public function getDb(): Db
            {
                return GeneralUtility::makeInstance(Db::class);
            }

            public function pi_getPidList(string $pid_list, int $recursive = 0): string
            {
                return $pid_list;
            }

            public function translate(string $key, string $alternativeLabel = ''): string
            {
                return $alternativeLabel ?: $key;
            }

            public function getPreselectedFilter(): array
            {
                return [];
            }

            public function getHasTooShortWords(): bool
            {
                return false;
            }

            public function setHasTooShortWords(bool $value): void {}

            public function getRequest(): ?ServerRequestInterface
            {
                return null;
            }

            public function in_multiarray($needle, array $haystack): bool
            {
                return false;
            }

            public function getTagsInSearchResult(): array|false
            {
                return false;
            }

            public function setTagsInSearchResult(array|false $value): void {}
        };
    }
}
