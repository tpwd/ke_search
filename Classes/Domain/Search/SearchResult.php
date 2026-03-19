<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Search;

/***************************************************************
 *  Copyright notice
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 ***************************************************************/

/**
 * Value object for search result (public API).
 */
final class SearchResult
{
    /**
     * @param array<int, array<string, mixed>> $results Raw index rows from tx_kesearch_index
     */
    public function __construct(
        private array $results,
        private int $totalCount,
        private ?array $tagsFromSearchResult = null,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /** @return array<string, int>|null Tag counts for faceting, if requested */
    public function getTagsFromSearchResult(): ?array
    {
        return $this->tagsFromSearchResult;
    }
}
