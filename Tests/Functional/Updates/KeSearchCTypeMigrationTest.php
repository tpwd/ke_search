<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\Updates;

use Tpwd\KeSearch\Updates\KeSearchCTypeMigration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class KeSearchCTypeMigrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    public function testMigratesBackendGroupPermissionsToKeSearchContentElements(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_groups');
        $connection->insert(
            'be_groups',
            [
                'uid' => 4711,
                'pid' => 0,
                'title' => 'ke_search editors',
                'explicit_allowdeny' => implode(',', [
                    'pages:edit',
                    'tt_content:list_type:ke_search_pi1',
                    'tt_content:list_type:ke_search_pi2',
                    'tt_content:list_type:ke_search_pi3',
                    'tt_content:list_type:other_plugin',
                ]),
            ]
        );

        $migration = new KeSearchCTypeMigration(GeneralUtility::makeInstance(ConnectionPool::class));

        self::assertTrue($migration->updateNecessary());
        self::assertTrue($migration->executeUpdate());

        self::assertSame(
            implode(',', [
                'pages:edit',
                'tt_content:CType:ke_search_pi1',
                'tt_content:CType:ke_search_pi2',
                'tt_content:CType:ke_search_pi3',
                'tt_content:list_type:other_plugin',
            ]),
            $connection->select(
                ['explicit_allowdeny'],
                'be_groups',
                ['uid' => 4711]
            )->fetchOne()
        );
    }
}
