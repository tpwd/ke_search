.. include:: /Includes.rst.txt

.. _publicSearchApi:

===================
Public Search API
===================

Since ke_search 7.1, third-party extensions (e.g. ke_search_premium) can execute searches programmatically
via a public API. The same search logic as the frontend plugins is used (search phrase, filters, hooks),
so results stay consistent.

Overview
========

* **SearchService** – Entry point. Inject :php:`Tpwd\KeSearch\Service\SearchService`. Use :php:`search(SearchRequest $request): SearchResult` for paginated full results, or :php:`searchUids(SearchRequest $request): array` for all matching UIDs only (no pagination).
* **SearchRequest** – Value object for search parameters (search word, starting points, filters, sort, limit, etc.).
* **SearchResult** – Value object with :php:`getResults()` (raw index rows) and :php:`getTotalCount()`.

Basic usage
===========

Inject the service and run a search with custom parameters:

.. code-block:: php

   use Tpwd\KeSearch\Domain\Search\SearchRequest;
   use Tpwd\KeSearch\Service\SearchService;

   class MyController
   {
       public function __construct(
           private readonly SearchService $searchService
       ) {}

       public function myAction(): void
       {
           $request = SearchRequest::fromArray([
               'searchWord' => 'my search term',
               'startingPoints' => '1,2,3',   // comma-separated page UIDs
               'limit' => 20,
               'filter' => [123 => ['tag1', 'tag2']],  // optional: filter UID => selected option tags
           ]);
           $result = $this->searchService->search($request);

           $rows = $result->getResults();      // array of tx_kesearch_index rows
           $total = $result->getTotalCount();
       }
   }

RAG / AI use case (same search, empty phrase)
=============================================

This feature can be useful e.g. to run the **same** search as the current plugin (same filters, starting points, configuration)
but **without** a search phrase, e.g. to use the result set as context for a RAG-based AI answer.

Use this in a hook (e.g. :ref:`modifyResultList <hooks>`) where you have access to the plugin instance
(:php:`$pObj` or :php:`$this` in a hook class that receives the plugin):

.. code-block:: php

   use Tpwd\KeSearch\Domain\Search\SearchRequest;
   use Tpwd\KeSearch\Service\SearchService;

   // Inside a hook (e.g. modifyResultList) with access to the plugin $pObj:
   $ragRequest = SearchRequest::fromContext($pObj)->withEmptySearchPhrase();
   $ragResult = $this->searchService->search($ragRequest);

   $indexRows = $ragResult->getResults();   // Use e.g. as context for your AI/RAG pipeline
   $total = $ragResult->getTotalCount();

:php:`SearchRequest::fromContext($context)` copies the current plugin's configuration (FlexForm/conf),
starting points, selected filters, sort order, and results per page. :php:`withEmptySearchPhrase()` returns
a new request with the same parameters but an empty search word.

RAG: all result UIDs only (efficient, no pagination)
===================================================

For RAG you often need **all** matching index records (not just the first page) but only the **UIDs**
to load full records or content elsewhere. Use :php:`searchUids()` to run the same search without
pagination and get a list of UIDs (no full row data, efficient):

.. code-block:: php

   // Inside a hook with access to the plugin $pObj:
   $ragRequest = SearchRequest::fromContext($pObj)->withEmptySearchPhrase();
   $uids = $this->searchService->searchUids($ragRequest);   // list<int> of tx_kesearch_index UIDs

   // Use $uids to load full records, build context for AI, etc.
   foreach ($uids as $uid) {
       // ...
   }

:php:`searchUids(SearchRequest $request): array` runs the same WHERE/ORDER as the normal search but
returns only UIDs and applies no limit, so you get every matching index record's UID.

SearchRequest methods
=====================

* :php:`SearchRequest::fromArray(array $data): self` – Build from an array (e.g. :php:`searchWord`, :php:`startingPoints`, :php:`filter`, :php:`page`, :php:`sortByField`, :php:`sortByDir`, :php:`limit`, :php:`conf`).
* :php:`SearchRequest::fromContext(SearchContextInterface $context): self` – Build from the current plugin/context (e.g. in a hook). Copies conf, filters, sort, limit.
* :php:`withEmptySearchPhrase(): self` – Same parameters as the current request but with :php:`searchWord` set to empty.

SearchResult methods
====================

* :php:`getResults(): array` – Raw index rows (same structure as :php:`tx_kesearch_index`).
* :php:`getTotalCount(): int` – Total number of hits.
* :php:`getTagsFromSearchResult(): ?array` – Tag counts for faceting, if requested (optional).

SearchService::searchUids()
===========================

* :php:`searchUids(SearchRequest $request): array` – Runs the search with the same filters/context but **no pagination** and returns only UIDs: ``list<int>`` of :php:`tx_kesearch_index` UIDs. Use for RAG when you need all matching IDs without full row data.

Notes
=====

* The API uses the same Db, Filters, and Searchphrase logic as the frontend. Hooks such as :ref:`getQueryParts <hooks>` and :ref:`modifySearchWords <hooks>` are applied when running a search via the API.
* When building a request with :php:`fromContext()`, the current :php:`ServerRequestInterface` is copied so that language overlay and similar request-dependent behaviour work as in the frontend.
