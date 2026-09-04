.. include:: /Includes.rst.txt

.. _indexing:

========
Indexing
========

ke_search fetches content from pages, news, files etc. and stores it into an index table. This process is called
"indexing". For each content type (pages, news etc.) ke_search needs an "indexer" (see below, :ref:`available-indexers`).

Whenever the content in your website changes, the indexing process needs to be started to reflect that changes in
the search results.

Create indexer configurations
=============================

You will have to create an indexer configuration for each content type you want to index (pages, news, ...). You may
create more than one indexer configuration of one type, eg. two page indexer configurations for different page trees.

Indexer configurations can be stored either as database records (see :ref:`database-indexer-configuration`) or, since
version 7.2.0, as YAML configuration files (see :ref:`yaml-indexer-configuration`).

Full indexing and incremental indexing
======================================

There are two ways of indexing the data: full and incremental (see :ref:`full-and-incremental-indexing`).

Starting the indexing process manually
======================================

You can start the indexing process in the :guilabel:`Faceted Search` backend module.

.. figure:: /Images/QuickStart/start.png
   :alt: Backend module view
   :class: with-border

You can also start the indexer using the command line

.. code-block:: bash

	vendor/bin/typo3 ke_search:indexing

Or if you want to use the incremental mode

.. code-block:: bash

	vendor/bin/typo3 ke_search:indexing --indexingMode=incremental


Starting the indexing process automatically
===========================================

For keeping the index up-to-date it is recommended to use the TYPO3 scheduler.

You can execute the console command via scheduler.

* Create a task, choose "Execute console commands".
* Choose `ke_search:indexing` in the dropdown :guilabel:`Schedulable command`.
* After saving the form you can choose whether you want to do full indexing (default) or incremental indexing by setting
  the option :guilabel:`indexingMode` to either `full` or `incremental`.
* Deactivate the :guilabel:`Allow Parallel Execution` option (default).

.. _available-indexers:

Available indexers
==================

ke_search comes with indexers for the most important content types.

.. toctree::
	:maxdepth: 3
	:titlesonly:
	:glob:

	IndexerTypes/Pages
	IndexerTypes/ContentElements
	IndexerTypes/News
	IndexerTypes/TtNews
	IndexerTypes/Addresses
	IndexerTypes/Files
	IndexerTypes/Custom
	IndexerTypes/CustomContentFields

Indexer configurations and indexing
===================================

.. toctree::
	:maxdepth: 1
	:titlesonly:
	:glob:

	DatabaseIndexerConfiguration
	YamlIndexerConfiguration
	FullAndIncrementalIndexing

