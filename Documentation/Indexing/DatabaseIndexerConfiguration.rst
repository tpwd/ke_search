.. include:: /Includes.rst.txt

.. _database-indexer-configuration:

==============================
Database Indexer Configuration
==============================

Indexer configurations can be created as database records in the TYPO3 backend using the :guilabel:`List` module.

Creating an indexer configuration record
========================================

Use the list module and open your search storage page and create an "indexer configuration" record.

Configure the indexer configuration
===================================

* Set the title, this field is used only internally.
* Set the storage page for the index. Set this page to your search storage folder.
* If you are working with filters, you can define that every index entry gets a tag automatically if it has been indexed
  by this indexer by setting :guilabel:`Add tag to all indexed elements` to a filter option you like. By doing so you can for
  example create a filter by content type (news, page, event, ...) if you have an indexer for each content type. Please
  remember that you will have to create the filter options first.

The other indexer configurations options differ from type to type.
