<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\Service;

use Tpwd\KeSearch\Service\TagInfoService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @see https://github.com/tpwd/ke_search/issues/241
 */
class TagInfoServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    protected TagInfoService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Hooks/Fixtures/localized_filter_options.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/nested_categories.csv');

        $this->subject = GeneralUtility::makeInstance(TagInfoService::class);
    }

    public function testTagWithoutFilterOptionOrCategoryReturnsEmptyInfo(): void
    {
        $result = $this->subject->getTagsInfo('#some_custom_indexer_tag#', 0);

        self::assertCount(1, $result);
        self::assertSame('some_custom_indexer_tag', $result[0]['tag']);
        self::assertSame([], $result[0]['filterOptions']);
        self::assertNull($result[0]['category']);
    }

    public function testTagResolvesAllMatchingFilterOptionsInDefaultLanguage(): void
    {
        $result = $this->subject->getTagsInfo('#syscat100#', 0);

        self::assertCount(1, $result);
        $filterOptionTitles = array_column($result[0]['filterOptions'], 'title');
        // "syscat100" is assigned to two filter options (uid 1000 and 2000), both must be returned.
        self::assertContains('Category EN', $filterOptionTitles);
        self::assertContains('Category EN 2', $filterOptionTitles);
    }

    public function testTagResolvesTranslatedFilterOptionTitles(): void
    {
        $result = $this->subject->getTagsInfo('#syscat100#', 1);

        $filterOptionTitles = array_column($result[0]['filterOptions'], 'title');
        self::assertContains('Kategorie DE', $filterOptionTitles);
        self::assertContains('Kategorie DE 2', $filterOptionTitles);
    }

    public function testTagFallsBackToDefaultLanguageFilterOptionIfNoTranslationExists(): void
    {
        // filter option 2000 ("syscat100") has no RU translation (language uid 2)
        $result = $this->subject->getTagsInfo('#syscat100#', 2);

        $filterOptionTitles = array_column($result[0]['filterOptions'], 'title');
        self::assertContains('Category EN 2', $filterOptionTitles);
    }

    public function testTagResolvesSystemCategoryTitle(): void
    {
        $result = $this->subject->getTagsInfo('#syscat100#', 0);

        self::assertIsArray($result[0]['category']);
        self::assertSame(100, $result[0]['category']['uid']);
        self::assertSame('Category EN', $result[0]['category']['title']);
    }

    public function testTagResolvesTranslatedSystemCategoryTitle(): void
    {
        $result = $this->subject->getTagsInfo('#syscat100#', 1);

        self::assertSame('Kategorie DE', $result[0]['category']['title']);
    }

    public function testNestedCategoryReturnsParentCategories(): void
    {
        // syscat500 (child) -> syscat400 (parent) -> syscat300 (grandparent)
        $result = $this->subject->getTagsInfo('#syscat500#', 0);

        self::assertSame('Child Category', $result[0]['category']['title']);
        $parentTitles = array_column($result[0]['category']['parents'], 'title');
        self::assertSame(['Parent Category', 'Grandparent Category'], $parentTitles);
    }

    public function testNestedCategoryParentsAreTranslated(): void
    {
        $result = $this->subject->getTagsInfo('#syscat500#', 1);

        self::assertSame('Kind Kategorie', $result[0]['category']['title']);
        $parentTitles = array_column($result[0]['category']['parents'], 'title');
        self::assertSame(['Eltern Kategorie', 'Großeltern Kategorie'], $parentTitles);
    }
}
