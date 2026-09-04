.. include:: /Includes.rst.txt

.. _full-and-incremental-indexing:
.. _full-indexing-and-incremental-indexing:

======================================
Full indexing and incremental indexing
======================================

Since version 3.8.0 there are two ways of indexing the data: full and incremental.

.. contents::
   :depth: 2
   :local:

.. note::
   Incremental indexing is a lot faster than full indexing!

Full indexing
=============

Full indexing goes through all the data which should be indexed (records, files, custom indexers) and checks for each
record if it has been changed or if it is new. In this case the data will be updated or stored to the index. After that
a cleanup process is started and all old data will be deleted.

Incremental indexing
====================

The incremental indexing process fetches only the records which have been added, changed or deleted since the last
indexing process took place. It only adds, updates or deletes those records from the index.

Drawbacks of incremental indexing
---------------------------------

Incremental indexing has a few drawbacks:

* Not every indexer has incremental indexing capabilities. The indexer class needs to implement the method
  *startIncrementalIndexing*. If this method does not exist, a full indexing is started for this indexer even
  if the indexing process is started in incremental mode. Since there is no cleanup in incremental mode, *old entries
  won't be deleted in incremental mode if the indexer does not support incremental indexing*. The indexing report
  will mention if no incremental indexing is available ("Incremental indexing is not available for this indexer,
  starting full indexing.")
* Changes to files won't be recognized if you use the page or content element indexer, only if you use the
  dedicated file indexer.

Recommendation
==============

Therefore it is recommended to run the full indexing process once in a while (like once a day or once a week) and run
the incremental indexer more often (like once an hour). You can do so by creating two scheduler tasks, one for the full
indexing and one for the incremental indexing.

After adding or removing an indexer configuration you should always run a full indexing process.
