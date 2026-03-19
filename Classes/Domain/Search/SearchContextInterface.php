<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Search;

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;

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
 * Interface for search context used by Db, Searchphrase and Filters.
 * Implemented by PluginBase (frontend) and SearchExecutionContext (API).
 */
interface SearchContextInterface
{
    public function getConf(): array;

    public function getExtConf(): array;

    public function getExtConfPremium(): array;

    public function getPiVars(): array;

    public function getSword(): string;

    public function getWordsAgainst(): string;

    public function getScoreAgainst(): string;

    /** @return array<int, string> */
    public function getTagsAgainst(): array;

    public function getStartingPoints(): string;

    public function getIsEmptySearch(): bool;

    public function getFilters(): Filters;

    public function getDb(): Db;

    public function pi_getPidList(string $pid_list, int $recursive = 0): string;

    public function translate(string $key, string $alternativeLabel = ''): string;

    /** @return array<int, array<int, string>> Preselected filter options (filterUid => [option tags]) */
    public function getPreselectedFilter(): array;

    public function getHasTooShortWords(): bool;

    public function setHasTooShortWords(bool $value): void;

    public function getRequest(): ?ServerRequestInterface;

    /**
     * @param mixed $needle
     * @param array<int|string, mixed> $haystack
     */
    public function in_multiarray($needle, array $haystack): bool;

    /** @return array<string, int>|false */
    public function getTagsInSearchResult(): array|false;

    /** @param array<string, int>|false $value */
    public function setTagsInSearchResult(array|false $value): void;
}
