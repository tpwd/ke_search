<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Search;

use Psr\Http\Message\ServerRequestInterface;

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
 * Value object for search request parameters (public API).
 * Use SearchRequest::fromContext() to build from current plugin context (e.g. in a hook)
 * and withEmptySearchPhrase() for RAG use case (same search without search word).
 */
final class SearchRequest
{
    public function __construct(
        private string $searchWord = '',
        private string $startingPoints = '',
        private array $filter = [],
        private int $page = 1,
        private ?string $sortByField = null,
        private ?string $sortByDir = null,
        private ?int $limit = null,
        private array $conf = [],
        private ?int $languageId = null,
        private ?ServerRequestInterface $request = null,
    ) {}

    public function getSearchWord(): string
    {
        return $this->searchWord;
    }

    public function getStartingPoints(): string
    {
        return $this->startingPoints;
    }

    /** @return array<int, array<int, string>|string> */
    public function getFilter(): array
    {
        return $this->filter;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getSortByField(): ?string
    {
        return $this->sortByField;
    }

    public function getSortByDir(): ?string
    {
        return $this->sortByDir;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getConf(): array
    {
        return $this->conf;
    }

    public function getLanguageId(): ?int
    {
        return $this->languageId;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Build a SearchRequest from the current plugin/context (e.g. in a hook).
     * Copies conf, startingPoints, filter selection, sort, page and limit.
     */
    public static function fromContext(SearchContextInterface $context): self
    {
        $conf = $context->getConf();
        $piVars = $context->getPiVars();
        $limit = isset($conf['resultsPerPage']) ? (int)$conf['resultsPerPage'] : null;
        return new self(
            searchWord: $context->getSword(),
            startingPoints: $context->getStartingPoints(),
            filter: $piVars['filter'] ?? [],
            page: isset($piVars['page']) ? (int)$piVars['page'] : 1,
            sortByField: $piVars['sortByField'] ?? null,
            sortByDir: $piVars['sortByDir'] ?? null,
            limit: $limit,
            conf: $conf,
            request: $context->getRequest(),
        );
    }

    /**
     * Same parameters as this request but with empty search phrase (for RAG / AI context).
     */
    public function withEmptySearchPhrase(): self
    {
        return new self(
            searchWord: '',
            startingPoints: $this->startingPoints,
            filter: $this->filter,
            page: $this->page,
            sortByField: $this->sortByField === 'score' ? '' : $this->sortByField,
            sortByDir: $this->sortByDir,
            limit: $this->limit,
            conf: $this->conf,
            languageId: $this->languageId,
            request: $this->request,
        );
    }

    /**
     * Build from array (e.g. for custom API calls).
     * @param array{searchWord?: string, startingPoints?: string, filter?: array, page?: int, sortByField?: string, sortByDir?: string, limit?: int, conf?: array, languageId?: int, request?: ServerRequestInterface} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            searchWord: (string)($data['searchWord'] ?? ''),
            startingPoints: (string)($data['startingPoints'] ?? ''),
            filter: $data['filter'] ?? [],
            page: isset($data['page']) ? (int)$data['page'] : 1,
            sortByField: isset($data['sortByField']) ? (string)$data['sortByField'] : null,
            sortByDir: isset($data['sortByDir']) ? (string)$data['sortByDir'] : null,
            limit: isset($data['limit']) ? (int)$data['limit'] : null,
            conf: $data['conf'] ?? [],
            languageId: isset($data['languageId']) ? (int)$data['languageId'] : null,
            request: $data['request'] ?? null,
        );
    }
}
