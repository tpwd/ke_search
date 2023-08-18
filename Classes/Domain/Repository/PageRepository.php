<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Repository;

use PDO;
use TYPO3\CMS\Core\Database\Connection;

/***************************************************************
 *  Copyright notice
 *  (c) 2020 Christian Bülter
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * @author Christian Bülter
 */
class PageRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'pages';

    /**
     * @param array $uidList
     * @param int $tstamp
     * @return mixed[]
     */
    public function findAllDeletedAndHiddenByUidListAndTimestampInAllLanguages(array $uidList, int $tstamp)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidList, Connection::PARAM_INT_ARRAY))
            )
            ->orWhere(
                $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter($uidList, Connection::PARAM_INT_ARRAY))
            )
            ->andWhere(
                '('
                . $queryBuilder->expr()->eq('deleted', 1)
                . ' OR '
                . $queryBuilder->expr()->eq('hidden', 1)
                . ')',
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($tstamp, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
