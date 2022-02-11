.. include:: /Includes.rst.txt

.. _customIndexer:

==============
Custom Indexer
==============

You may write your own indexer and plug it into ke_search.

Feel free to use that extension as a kickstarter for your own custom indexer:

https://github.com/teaminmedias-pluswerk/ke_search_hooks

.. hint::
   * Make sure you fill :php:`$pid`, :php:`$type`, :php:`$language` and (important) :php:`$additional_fields['orig_uid']`.
     These fields are needed for the check if a content element already has been indexed. If you don't fill them, it may
     happen that only one content element of your specific type is indexed because all the elements are interpreted as
     the same record.
   * You don't need to fill :php:`$tags` if you don't use faceting.
   * You don't need to fill :php:`$abstract`, it will then generated automatically from $content.
   * You will have to fill :php:`$params` if you want to link to a extension which expects a certain parameter, e.g.
     `&tx_myextension_pi1[showUid]=123`
