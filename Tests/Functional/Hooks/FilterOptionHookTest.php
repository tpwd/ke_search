<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\Hooks;

use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use Tpwd\KeSearch\Domain\Repository\FilterRepository;
use Tpwd\KeSearch\Hooks\FilterOptionHook;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class FilterOptionHookTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    protected FilterOptionRepository $filterOptionRepository;

    protected FilterRepository $filterRepository;

    protected FilterOptionHook $hook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/localized_filter_options.csv');

        $this->filterOptionRepository = GeneralUtility::makeInstance(FilterOptionRepository::class);
        $this->filterRepository = GeneralUtility::makeInstance(FilterRepository::class);
        $this->hook = GeneralUtility::makeInstance(FilterOptionHook::class);
    }

    public function testFindFilterOptionByL10nParentAndLanguageReturnsCorrectRow(): void
    {
        $chinese = $this->filterOptionRepository->findByL10nParentAndLanguage(1000, 3, true);
        self::assertIsArray($chinese);
        self::assertSame(1003, (int)$chinese['uid']);
        self::assertSame(3, (int)$chinese['sys_language_uid']);

        $russian = $this->filterOptionRepository->findByL10nParentAndLanguage(1000, 2, true);
        self::assertIsArray($russian);
        self::assertSame(1002, (int)$russian['uid']);
        self::assertSame(2, (int)$russian['sys_language_uid']);

        $missing = $this->filterOptionRepository->findByL10nParentAndLanguage(1000, 9, true);
        self::assertFalse($missing);
    }

    public function testFindFilterByL10nParentAndLanguageReturnsCorrectRow(): void
    {
        $chinese = $this->filterRepository->findByL10nParentAndLanguage(10, 3, true);
        self::assertIsArray($chinese);
        self::assertSame(13, (int)$chinese['uid']);
        self::assertSame(3, (int)$chinese['sys_language_uid']);

        $russian = $this->filterRepository->findByL10nParentAndLanguage(10, 2, true);
        self::assertIsArray($russian);
        self::assertSame(12, (int)$russian['uid']);
        self::assertSame(2, (int)$russian['sys_language_uid']);

        $missing = $this->filterRepository->findByL10nParentAndLanguage(10, 9, true);
        self::assertFalse($missing);
    }

    public function testFindersIncludeHiddenRowsWhenFlagIsTrue(): void
    {
        self::assertFalse($this->filterOptionRepository->findByL10nParentAndLanguage(1000, 4));
        $hiddenFilterOption = $this->filterOptionRepository->findByL10nParentAndLanguage(1000, 4, true);
        self::assertIsArray($hiddenFilterOption);
        self::assertSame(1004, (int)$hiddenFilterOption['uid']);

        self::assertFalse($this->filterRepository->findByL10nParentAndLanguage(10, 4));
        $hiddenFilter = $this->filterRepository->findByL10nParentAndLanguage(10, 4, true);
        self::assertIsArray($hiddenFilter);
        self::assertSame(14, (int)$hiddenFilter['uid']);
    }

    public function testSavingLocalizedCategoryUpdatesOnlySavedLanguageFilterOptions(): void
    {
        $newTitle = '分类 ZH updated';
        $this->updateRecord('sys_category', 103, ['title' => $newTitle]);

        $this->hook->updateFilterOptionsForCategoryAndSubCategories(103);

        self::assertSame('Kategorie DE', (string)$this->findFilterOptionByUid(1001)['title']);
        self::assertSame('Категория RU', (string)$this->findFilterOptionByUid(1002)['title']);
        self::assertSame($newTitle, (string)$this->findFilterOptionByUid(1003)['title']);

        self::assertSame('Kategorie DE 2', (string)$this->findFilterOptionByUid(2001)['title']);
        self::assertSame($newTitle, (string)$this->findFilterOptionByUid(2003)['title']);
    }

    public function testSavingDefaultLanguageCategoryOnlyUpdatesDefaultLanguageFilterOptions(): void
    {
        $newTitle = 'Category EN updated';
        $this->updateRecord('sys_category', 100, ['title' => $newTitle]);

        $this->hook->updateFilterOptionsForCategoryAndSubCategories(100);

        self::assertSame($newTitle, (string)$this->findFilterOptionByUid(1000)['title']);
        self::assertSame('Kategorie DE', (string)$this->findFilterOptionByUid(1001)['title']);
        self::assertSame('Категория RU', (string)$this->findFilterOptionByUid(1002)['title']);
        self::assertSame('分类 ZH', (string)$this->findFilterOptionByUid(1003)['title']);

        self::assertSame($newTitle, (string)$this->findFilterOptionByUid(2000)['title']);
        self::assertSame('Kategorie DE 2', (string)$this->findFilterOptionByUid(2001)['title']);
        self::assertSame('分类 ZH 2', (string)$this->findFilterOptionByUid(2003)['title']);
    }

    public function testLocalizedCategoryWithoutL10nParentIsNoOp(): void
    {
        $this->insertCategory([
            'uid' => 104,
            'pid' => 1,
            'title' => '孤立 ZH',
            'sys_language_uid' => 3,
            'l10n_parent' => 0,
            'parent' => 0,
            'tx_kesearch_filter' => '10,20',
            'tx_kesearch_filtersubcat' => '',
            'hidden' => 0,
            'deleted' => 0,
        ]);

        $this->hook->updateFilterOptionsForCategoryAndSubCategories(104);

        self::assertSame('分类 ZH', (string)$this->findFilterOptionByUid(1003)['title']);
        self::assertSame('分类 ZH 2', (string)$this->findFilterOptionByUid(2003)['title']);
        self::assertSame(0, $this->countFilterOptionsByTitleAndLanguage('孤立 ZH', 3));
    }

    public function testMissingLocalizedFilterOptionIsCreatedAndAssignedToMatchingFilterLanguage(): void
    {
        $newTitle = 'Категория RU new';
        $this->updateRecord('sys_category', 102, ['title' => $newTitle]);

        $category = $this->findCategoryByUid(102);
        $this->hook->createOrUpdateFilterOptions([20], $category);

        $created = $this->findFilterOptionByL10nParentAndLanguage(2000, 2);
        self::assertIsArray($created);
        self::assertSame(2, (int)$created['sys_language_uid']);
        self::assertSame(2000, (int)$created['l10n_parent']);
        self::assertSame('syscat100', (string)$created['tag']);
        self::assertSame($newTitle, (string)$created['title']);
        self::assertNotSame('', trim((string)$created['slug']));

        $createdUid = (int)$created['uid'];
        self::assertStringContainsString((string)$createdUid, (string)$this->findFilterByUid(22)['options']);
        self::assertStringNotContainsString((string)$createdUid, (string)$this->findFilterByUid(21)['options']);
        self::assertStringNotContainsString((string)$createdUid, (string)$this->findFilterByUid(23)['options']);
    }

    public function testNoFilterOptionIsCreatedIfFilterTranslationForLanguageIsMissing(): void
    {
        $this->deleteRecord('tx_kesearch_filters', 22);

        $category = $this->findCategoryByUid(102);
        $this->hook->createOrUpdateFilterOptions([20], $category);

        self::assertFalse($this->findFilterOptionByL10nParentAndLanguage(2000, 2));
    }

    public function testHiddenLocalizedFilterOptionIsUpdatedInsteadOfDuplicated(): void
    {
        $this->insertCategory([
            'uid' => 105,
            'pid' => 1,
            'title' => 'Categoria ES updated',
            'sys_language_uid' => 4,
            'l10n_parent' => 100,
            'parent' => 0,
            'tx_kesearch_filter' => '10',
            'tx_kesearch_filtersubcat' => '',
            'hidden' => 0,
            'deleted' => 0,
        ]);

        self::assertSame(1, $this->countFilterOptionsByParentAndLanguage(1000, 4, true));

        $this->hook->updateFilterOptionsForCategoryAndSubCategories(105);

        self::assertSame(1, $this->countFilterOptionsByParentAndLanguage(1000, 4, true));
        $updatedHiddenRecord = $this->filterOptionRepository->findByL10nParentAndLanguage(1000, 4, true);
        self::assertIsArray($updatedHiddenRecord);
        self::assertSame('Categoria ES updated', (string)$updatedHiddenRecord['title']);
    }

    public function testTwoAssignedFiltersCreateExactlyOneLocalizedOptionPerFilter(): void
    {
        $this->deleteRecord('tx_kesearch_filteroptions', 1002);

        $newTitle = 'Категория RU two-filters';
        $this->updateRecord('sys_category', 102, ['title' => $newTitle]);

        $this->hook->updateFilterOptionsForCategoryAndSubCategories(102);

        self::assertSame(1, $this->countFilterOptionsByParentAndLanguage(1000, 2));
        self::assertSame(1, $this->countFilterOptionsByParentAndLanguage(2000, 2));

        $createdForFilter10 = $this->findFilterOptionByL10nParentAndLanguage(1000, 2);
        $createdForFilter20 = $this->findFilterOptionByL10nParentAndLanguage(2000, 2);

        self::assertIsArray($createdForFilter10);
        self::assertIsArray($createdForFilter20);
        self::assertSame($newTitle, (string)$createdForFilter10['title']);
        self::assertSame($newTitle, (string)$createdForFilter20['title']);
    }

    protected function updateRecord(string $tableName, int $uid, array $fields): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder
            ->update($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            );

        foreach ($fields as $field => $value) {
            $queryBuilder->set($field, $value);
        }

        $queryBuilder->executeStatement();
    }

    protected function insertCategory(array $fields): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_category')
            ->insert('sys_category', $fields);
    }

    protected function deleteRecord(string $tableName, int $uid): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder
            ->delete($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    /**
     * @return array<string, mixed>
     */
    protected function findCategoryByUid(int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');

        $record = $queryBuilder
            ->select('*')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($record);
        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function findFilterByUid(int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_kesearch_filters');

        $record = $queryBuilder
            ->select('*')
            ->from('tx_kesearch_filters')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($record);
        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function findFilterOptionByUid(int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_kesearch_filteroptions');

        $record = $queryBuilder
            ->select('*')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($record);
        return $record;
    }

    protected function countFilterOptionsByTitleAndLanguage(string $title, int $languageUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_kesearch_filteroptions');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($title, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function findFilterOptionByL10nParentAndLanguage(int $l10nParent, int $languageUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_kesearch_filteroptions');

        return $queryBuilder
            ->select('*')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($l10nParent, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    protected function countFilterOptionsByParentAndLanguage(
        int $l10nParent,
        int $languageUid,
        bool $includeHidden = false
    ): int {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_kesearch_filteroptions');

        if ($includeHidden) {
            $queryBuilder->getRestrictions()->removeByType(\TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction::class);
        }

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($l10nParent, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }
}
