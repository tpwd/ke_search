.. include:: /Includes.rst.txt

.. _customIndexer:

==============
Custom Indexer
==============

You may write your own indexer and plug it into ke_search.

Feel free to use that extension as a kickstarter for your own custom indexer:

https://github.com/tpwd/ke_search_hooks

.. hint::
   * Make sure you fill :php:`$pid`, :php:`$type`, :php:`$language` and (important) :php:`$additional_fields['orig_uid']`.
     These fields are needed for the check if a content element already has been indexed. If you don't fill them, it may
     happen that only one content element of your specific type is indexed because all the elements are interpreted as
     the same record.
   * You don't need to fill :php:`$tags` if you don't use faceting.
   * You don't need to fill :php:`$abstract`, it will then generated automatically from $content.
   * You will have to fill :php:`$params` if you want to link to a extension which expects a certain parameter, e.g.
     `&tx_myextension_pi1[showUid]=123`

Extending existing indexers
---------------------------

The indexers shipped with ke_search have hooks built in which allow you to modify the indexed data without
writing a custom indexer. For example the page indexer provides the hook `modifyPagesIndexEntry`.

Adding hidden content
---------------------

It is possible since version 4.5.0 to add content to the index which is searched but not shown in the result list. This
is e.g. useful for Synonyms, different spellings, additional keywords and so on. The event
`ModifyFieldValuesBeforeStoringEvent` in the class `IndexerRunner` is used for that.