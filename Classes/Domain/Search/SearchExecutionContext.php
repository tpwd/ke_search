<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Search;

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

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
 * Search context for programmatic API search. Implements SearchContextInterface
 * so Db, Searchphrase and Filters can run with the same logic as the frontend plugin.
 */
class SearchExecutionContext implements SearchContextInterface
{
    private array $conf = [];
    private array $extConf = [];
    private array $extConfPremium = [];
    private array $piVars = [];
    private string $sword = '';
    private string $wordsAgainst = '';
    private string $scoreAgainst = '';
    private array $tagsAgainst = [];
    private string $startingPoints = '';
    private bool $isEmptySearch = true;
    private bool $hasTooShortWords = false;
    private array $preselectedFilter = [];
    private ?ServerRequestInterface $request = null;
    /** @var array<string, int>|false */
    private array|false $tagsInSearchResult = false;

    private ?Filters $filters = null;
    private ?Db $db = null;

    public function getConf(): array
    {
        return $this->conf;
    }

    public function setConf(array $conf): void
    {
        $this->conf = $conf;
    }

    public function getExtConf(): array
    {
        return $this->extConf;
    }

    public function setExtConf(array $extConf): void
    {
        $this->extConf = $extConf;
    }

    public function getExtConfPremium(): array
    {
        return $this->extConfPremium;
    }

    public function setExtConfPremium(array $extConfPremium): void
    {
        $this->extConfPremium = $extConfPremium;
    }

    public function &getPiVars(): array
    {
        return $this->piVars;
    }

    public function setPiVars(array $piVars): void
    {
        $this->piVars = $piVars;
    }

    public function getSword(): string
    {
        return $this->sword;
    }

    public function setSword(string $sword): void
    {
        $this->sword = $sword;
    }

    public function getWordsAgainst(): string
    {
        return $this->wordsAgainst;
    }

    public function setWordsAgainst(string $wordsAgainst): void
    {
        $this->wordsAgainst = $wordsAgainst;
    }

    public function getScoreAgainst(): string
    {
        return $this->scoreAgainst;
    }

    public function setScoreAgainst(string $scoreAgainst): void
    {
        $this->scoreAgainst = $scoreAgainst;
    }

    public function getTagsAgainst(): array
    {
        return $this->tagsAgainst;
    }

    public function setTagsAgainst(array $tagsAgainst): void
    {
        $this->tagsAgainst = $tagsAgainst;
    }

    public function getStartingPoints(): string
    {
        return $this->startingPoints;
    }

    public function setStartingPoints(string $startingPoints): void
    {
        $this->startingPoints = $startingPoints;
    }

    public function getIsEmptySearch(): bool
    {
        return $this->isEmptySearch;
    }

    public function setIsEmptySearch(bool $isEmptySearch): void
    {
        $this->isEmptySearch = $isEmptySearch;
    }

    public function getFilters(): Filters
    {
        if ($this->filters === null) {
            throw new \RuntimeException('Filters not initialized. SearchService must call Filters::initialize() first.', 1710000001);
        }
        return $this->filters;
    }

    public function setFilters(Filters $filters): void
    {
        $this->filters = $filters;
    }

    public function getDb(): Db
    {
        if ($this->db === null) {
            throw new \RuntimeException('Db not set. SearchService must call setDb() first.', 1710000002);
        }
        return $this->db;
    }

    public function setDb(Db $db): void
    {
        $this->db = $db;
    }

    public function pi_getPidList(string $pid_list, int $recursive = 0): string
    {
        $recursive = MathUtility::forceIntegerInRange($recursive, 0);
        $pid_list_arr = array_unique(GeneralUtility::intExplode(',', $pid_list, true));
        $pid_list = GeneralUtility::makeInstance(PageRepository::class)->getPageIdsRecursive($pid_list_arr, $recursive);
        return implode(',', $pid_list);
    }

    public function translate(string $key, string $alternativeLabel = ''): string
    {
        if (!str_starts_with($key, 'LLL:')) {
            $key = 'LLL:EXT:ke_search/Resources/Private/Language/locallang.xlf:' . $key;
        }
        $label = LocalizationUtility::translate($key, 'KeSearch');
        return $label !== null ? $label : $alternativeLabel;
    }

    public function getPreselectedFilter(): array
    {
        return $this->preselectedFilter;
    }

    public function setPreselectedFilter(array $preselectedFilter): void
    {
        $this->preselectedFilter = $preselectedFilter;
    }

    public function getHasTooShortWords(): bool
    {
        return $this->hasTooShortWords;
    }

    public function setHasTooShortWords(bool $value): void
    {
        $this->hasTooShortWords = $value;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @param mixed $needle
     * @param array<int|string, mixed> $haystack
     */
    public function in_multiarray($needle, array $haystack): bool
    {
        foreach ($haystack as $value) {
            if (is_array($value)) {
                if ($this->in_multiarray($needle, $value)) {
                    return true;
                }
            } else {
                if ($value == $needle) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getTagsInSearchResult(): array|false
    {
        return $this->tagsInSearchResult;
    }

    public function setTagsInSearchResult(array|false $value): void
    {
        $this->tagsInSearchResult = $value;
    }
}
