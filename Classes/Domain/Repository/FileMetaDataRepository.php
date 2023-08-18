<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Repository;

use Doctrine\DBAL\Driver\Exception;
use PDO;

/***************************************************************
 *  Copyright notice
 *  (c) 2022 Christian Bülter
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

class FileMetaDataRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'sys_file_metadata';

    /**
     * Retrieves metadata for file
     *
     * @param int $fileUid
     * @param int $languageUid
     * @return array
     * @throws Exception
     */
    public function findByFileUidAndLanguageUid(int $fileUid, $languageUid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $record = $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'file',
                    $queryBuilder->createNamedParameter($fileUid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        return (empty($record)) ? [] : $record;
    }
}
