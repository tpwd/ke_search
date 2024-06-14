<?php

namespace Tpwd\KeSearch\Utility;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SanityCheckUtility
{
    public static function IsIndexTableIndexesEnabled(): bool
    {
        $indexEnabled = true;
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_kesearch_index');
        $statement = $connection->prepare('SHOW INDEX FROM tx_kesearch_index');
        $resultRows = $statement->executeQuery()->fetchAllAssociative();
        if (!empty($resultRows)) {
            foreach ($resultRows as $resultRow) {
                if ($resultRow['Comment'] == 'disabled') {
                    $indexEnabled = false;
                }
            }
        }
        return $indexEnabled;
    }
}
