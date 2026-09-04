.. include:: /Includes.rst.txt

.. _yaml-indexer-configuration:

==========================
YAML Indexer Configuration
==========================

In addition to creating indexer configurations as :ref:`database records
<database-indexer-configuration>` in the TYPO3 backend, you can define
indexer configurations in YAML files within your extensions or sitepackages.

YAML-based indexer configurations allow you to keep search configuration
version-controlled, automated, and deployment-friendly without requiring
manual database records.

.. contents::
   :depth: 2
   :local:

File Location and Discovery
===========================

ke_search automatically discovers YAML configuration files located in the
following directory of any active TYPO3 extension:

.. code-block:: text

   EXT:<my_extension>/Configuration/KeSearch/IndexerConfigurations/

All files ending in ``.yaml`` or ``.yml`` within this folder (including
subdirectories) are automatically loaded in deterministic alphabetical order.

.. important::

   YAML indexer configuration files are loaded and activated automatically
   as soon as the containing extension is active - **without** any further
   review or approval in the TYPO3 backend. This is different from database
   indexer configurations, which can only be created or changed by backend
   users who have the necessary access rights.

   Because of this, a YAML indexer configuration file must be treated with
   the same level of trust as PHP extension code: only place such files in
   extensions or sitepackages that come from trusted sources (e.g. your own
   development team or vetted third-party extensions). Do not allow
   untrusted parties (e.g. via file uploads, unreviewed composer
   dependencies, or writable directories) to place files into
   ``Configuration/KeSearch/IndexerConfigurations/`` of an active extension,
   since this would let them control indexing behaviour (e.g.
   ``storagepid``, ``targetpid``, which tables/fields are indexed) without
   any further checks.

   For the same reason, symbolic links pointing outside of the extension's
   own directory are not followed when scanning for configuration files.

YAML Schema and Structure
=========================

Each YAML file defines a single indexer configuration.

General Properties (All Indexer Types)
======================================

Required Properties
-------------------

* ``identifier`` (string): Unique machine-name identifier for the indexer
  configuration (e.g. ``main_pages``, ``news_archive``).
* ``title`` (string): Human-readable title displayed in backend modules and
  logs.
* ``type`` (string): The registered indexer type key (``page``,
  ``tt_content``, ``news``, ``tt_address``, ``file``, ``tt_news``, or custom
  indexer key).

Common Optional Properties
--------------------------

* ``storagepid`` (integer, default: ``0``): Page UID where search index
  records are stored.
* ``pid`` (integer, default: ``storagepid``): "Virtual" Page UID where search
  index configuration is stored, for compatibility with indexer
  configurations from the database, falls back to ``storagepid`` if not set.
* ``hidden`` (boolean or integer, default: ``false`` / ``0``): Set to
  ``true`` / ``1`` to disable this indexer configuration.
* ``filteroption`` (integer, default: ``0``): UID of a filter option / tag to
  automatically assign to all records indexed by this configuration.

Properties by Indexer Type
==========================

Page Indexer (``type: page``)
-----------------------------

Indexes standard TYPO3 pages and aggregates their content elements into a
single search result per page.

* ``startingpoints_recursive`` (array of integers or comma-separated string):
  Starting page UIDs to index recursively.
* ``single_pages`` (array of integers or comma-separated string): Specific
  single page UIDs to index non-recursively.
* ``index_page_doctypes`` (array of integers or comma-separated string,
  default: ``1``): Page types (``doktype``) to index (``1`` = standard page).
* ``index_content_with_restrictions`` (boolean or string: ``yes`` / ``no``,
  default: ``no``): Index content elements even if frontend user group access
  restrictions are set.
* ``contenttypes`` (array of strings or comma-separated string, default:
  ``text,textmedia,textpic,bullets,table,html,header,uploads,shortcut,
  accordion,tab,carousel,carousel_fullscreen,carousel_small,icon_group,
  card_group,timeline``): Content element types (``CType``) to index.
* ``content_fields`` (array of strings or comma-separated string, default:
  ``bodytext,subheader,header_link``): Fields from the ``tt_content`` table
  to include in fulltext indexing.
* ``additional_tables`` (YAML mapping, array, or INI string): Configuration
  for indexing content from child/related database tables (e.g.
  EXT:bootstrap_package accordion/tab/carousel items, EXT:mask,
  EXT:content_blocks).
* ``fileext`` (array of strings or comma-separated string, default:
  ``pdf,ppt,doc,xls,docx,xlsx,pptx``): Allowed file extensions for linked
  files to index.
* ``file_reference_fields`` (array of strings or comma-separated string,
  default: ``media``): Database fields in ``tt_content`` holding file
  references whose linked files should be indexed.
* ``index_use_page_tags_for_files`` (boolean or integer, default: ``0``): If
  set to ``true`` / ``1``, tags assigned to the parent page will be applied
  to indexed linked files.

Content Element Indexer (``type: tt_content``)
----------------------------------------------

Indexes individual content elements separately. Each matching content element
generates its own search result linking directly to the element via an anchor
link.

* ``startingpoints_recursive`` (array of integers or comma-separated string):
  Starting page UIDs to index recursively.
* ``single_pages`` (array of integers or comma-separated string): Specific
  single page UIDs to index non-recursively.
* ``contenttypes`` (array of strings or comma-separated string, default:
  ``text,textmedia,textpic,bullets,table,html,header,uploads,shortcut,
  accordion,tab,carousel,carousel_fullscreen,carousel_small,icon_group,
  card_group,timeline``): Content element types (``CType``) to index.
* ``content_fields`` (array of strings or comma-separated string, default:
  ``bodytext,subheader,header_link``): Fields from the ``tt_content`` table
  to include in fulltext indexing.
* ``additional_tables`` (YAML mapping, array, or INI string): Configuration
  for indexing content from child/related database tables.
* ``fileext`` (array of strings or comma-separated string, default:
  ``pdf,ppt,doc,xls,docx,xlsx,pptx``): Allowed file extensions for linked
  files to index.
* ``file_reference_fields`` (array of strings or comma-separated string,
  default: ``media``): Database fields in ``tt_content`` holding file
  references whose linked files should be indexed.
* ``index_use_page_tags_for_files`` (boolean or integer, default: ``0``): If
  set to ``true`` / ``1``, tags assigned to the parent page will be applied
  to indexed linked files.

News Indexer (``type: news`` - EXT:news)
----------------------------------------

Indexes news records from EXT:news (``tx_news_domain_model_news``).

* ``sysfolder`` (array of integers or comma-separated string): Storage folder
  UIDs containing news records.
* ``startingpoints_recursive`` (array of integers or comma-separated string):
  Starting page/folder UIDs for recursive folder lookup.
* ``targetpid`` (integer, default: ``0``): Target page UID where the news
  detail view plugin is located.
* ``index_news_category_mode`` (integer, default: ``1``, or ``2`` if
  categories are specified):

  * ``1``: Index all news records regardless of assigned categories.
  * ``2``: Index only news records matching the selected categories
    (``index_extnews_category_selection``).

* ``index_extnews_category_selection`` / ``category_selection`` (array of
  category UIDs or category titles, or comma-separated string): System
  categories to filter by when ``index_news_category_mode`` is ``2``. The
  generic alias ``category_selection`` can be used instead, e.g. for custom
  indexers.
* ``index_news_archived`` (integer, default: ``0``):

  * ``0``: Index all news records (active and archived).
  * ``1``: Index active (non-archived) news only.
  * ``2``: Index archived news only.

* ``index_news_files_mode`` (integer, default: ``0``):

  * ``0``: Index attached file content into the news search result.
  * ``1``: Index attached files as separate search results.

* ``fileext`` (array of strings or comma-separated string, default:
  ``pdf,ppt,doc,xls,docx,xlsx,pptx``): Allowed file extensions for attached
  files.
* ``index_use_page_tags`` (boolean or integer, default: ``0``): If set to
  ``true`` / ``1``, tags assigned to the parent storage folder will be added
  to the indexed news records.

Address Indexer (``type: tt_address`` - EXT:tt_address)
-------------------------------------------------------

Indexes address records from EXT:tt_address (``tt_address``).

* ``sysfolder`` (array of integers or comma-separated string): Storage folder
  UIDs containing address records.
* ``startingpoints_recursive`` (array of integers or comma-separated string):
  Starting page/folder UIDs for recursive folder lookup.
* ``targetpid`` (integer, default: ``0``): Target page UID for single view /
  address display.
* ``index_use_page_tags`` (boolean or integer, default: ``0``): If set to
  ``true`` / ``1``, tags assigned to the parent storage folder will be added
  to indexed address records.

File Indexer (``type: file``)
-----------------------------

Indexes files directly from directories on the file system or FAL (File
Abstraction Layer) storage.

* ``directories`` (array of strings or newline/comma-separated string):
  Directory paths to index recursively (e.g. ``fileadmin/documents/`` or
  relative path within the FAL storage like ``documents/``). Enter ``.`` to
  index all files in the storage.
* ``fal_storage`` (integer, default: ``0``): UID of the FAL storage record
  (``sys_file_storage``), or ``0`` to index directly from the file system
  without FAL.
* ``file_collections`` (array of integers or comma-separated string): UIDs of
  ``sys_file_collection`` records to index.
* ``fileext`` (array of strings or comma-separated string, default:
  ``pdf,ppt,doc,xls,docx,xlsx,pptx``): Allowed file extensions to index.

News Indexer (``type: tt_news`` - EXT:tt_news)
----------------------------------------------

Indexes news records from legacy EXT:tt_news (``tt_news``).

* ``sysfolder`` (array of integers or comma-separated string): Storage folder
  UIDs containing news records.
* ``startingpoints_recursive`` (array of integers or comma-separated string):
  Starting page/folder UIDs for recursive folder lookup.
* ``targetpid`` (integer, default: ``0``): Target page UID where the single
  view plugin is located.
* ``index_news_files_mode`` (integer, default: ``0``):

  * ``0``: Index attached file content into the news search result.
  * ``1``: Index attached files as separate search results.

* ``fileext`` (array of strings or comma-separated string, default:
  ``pdf,ppt,doc,xls,docx,xlsx,pptx``): Allowed file extensions for attached
  files.
* ``index_use_page_tags`` (boolean or integer, default: ``0``): If set to
  ``true`` / ``1``, tags assigned to the parent storage folder will be added
  to indexed news records.

Custom Indexers (``type: <custom_key>``)
----------------------------------------

For custom indexers registered via hooks or event listeners:

* All common properties (such as ``identifier``, ``title``, ``type``,
  ``storagepid``, ``targetpid``, ``startingpoints_recursive``,
  ``sysfolder``, ``filteroption``, ``hidden``) are supported.
* ``category_selection`` (array of category UIDs or category titles, or
  comma-separated string): Generic alias for
  ``index_extnews_category_selection``, useful for custom indexers that need
  to filter by categories.
* Any custom scalar properties defined in YAML are automatically passed
  through and accessible in ``$this->indexerConfig`` within your custom
  indexer class.

Examples
========

Page Indexer Example
--------------------

.. code-block:: yaml

   identifier: site_pages
   title: 'Main Website Pages'
   type: page
   storagepid: 10
   startingpoints_recursive:
     - 1
   fileext:
     - pdf
     - docx

News Indexer Example
--------------------

.. code-block:: yaml

   identifier: news_articles
   title: 'Company News & Blog'
   type: news
   storagepid: 10
   sysfolder:
     - 25
   targetpid: 30
   index_news_category_mode: 2
   index_extnews_category_selection:
     - 5
     - 12

Custom indexer example
----------------------

You can also register your custom indexers in YAML configurations. Just set
the `type` field ot the type you chose in your custom indexer. You can also
pass custom options to custom indexer.

.. code-block:: yaml

  identifier: my_custom_indexer
  title: 'My Custom indexer'
  type: tx_news_domain_model_news
  storagepid: 3
  targetpid: 8
  sysfolder: 6
  my_custom_indexer_option: 123

Page Indexer with Custom Content Fields Example
-----------------------------------------------

When indexing custom content elements (e.g. built with EXT:mask or
EXT:content_blocks), you can add custom database fields to the index using
``content_fields`` and ``file_reference_fields``:

.. code-block:: yaml

   identifier: site_pages_custom_fields
   title: 'Pages with Custom Content Fields'
   type: page
   storagepid: 10
   startingpoints_recursive:
     - 1
   content_fields:
     - bodytext
     - subheader
     - header_link
     - tx_myextension_customfield
     - tx_myextension_teaser
   file_reference_fields:
     - media
     - tx_myextension_custom_files
   contenttypes:
     - text
     - textmedia
     - my_custom_ctype

Content Blocks Example
-----------------------

Content elements created with EXT:content_blocks store their custom fields
directly on the ``tt_content`` table (add them to ``content_fields`` /
``file_reference_fields`` as needed), while repeating/child elements are
stored in additional tables which reference the ``tt_content`` record via
the field ``foreign_table_parent_uid``. Use ``additional_tables`` to index
those child tables. Internally the ``ini`` format is used, but here you need
to use the YAML format (it's converted internally):

.. code-block:: yaml

   identifier: site_pages_content_blocks
   title: 'Pages with Content Blocks Elements'
   type: page
   storagepid: 10
   startingpoints_recursive:
     - 1
   contenttypes:
     - text
     - textmedia
     - myvendor_mycontentblock
   content_fields:
     - bodytext
     - subheader
     - header_link
     - myvendor_mycontentblock_teaser
   additional_tables:
     myvendor_mycontentblock:
       table: myvendor_mycontentblock_collection
       referenceFieldName: foreign_table_parent_uid
       fields:
         - header
         - bodytext

In this example the ``additional_tables`` key ``myvendor_mycontentblock`` must
match the ``CType`` of the Content Blocks element (also listed in
``contenttypes``). If a Content Blocks element has multiple additional
tables (e.g. for repeating child elements), you can add further
configurations for the same ``CType`` by appending a dot and an index to
the key, e.g. ``myvendor_mycontentblock.1``, ``myvendor_mycontentblock.2``.


Synthetic UIDs and Category Handling
====================================

Because YAML configurations do not exist in the database table
``tx_kesearch_indexerconfig``, ke_search assigns them stable, deterministic
**negative UIDs** derived from their ``identifier``.

These synthetic negative UIDs:

* Never clash with auto-increment database record UIDs.
* Ensure consistent tracking and status reporting across indexing runs.
* Support category filtering transparently through in-memory category resolution.

Title-Based Category Selection
==============================

When defining category restrictions (e.g. for the News indexer), you can
specify categories using their **titles** in addition to numeric database UIDs.

How It Works
------------

Category settings such as ``index_extnews_category_selection`` accept:

* Category titles (e.g. ``"News"``, ``"Press Releases"``).
* Numeric database record UIDs (e.g. ``5``, ``12``).
* Formatted as YAML lists or comma-separated strings (e.g.
  ``"News, Events"``).

When the indexer configuration is loaded and normalized, ke_search queries the
``sys_category`` table and resolves the category titles (matching
case-insensitively against the ``title`` field) to their
corresponding record UIDs. If a category title cannot be found in the
database, ke_search logs a warning message and skips the unresolvable entry
while continuing to process any valid categories.

Example
-------

The following example shows a News indexer configuration filtering news
records by category titles:

.. code-block:: yaml

   identifier: news_press_releases
   title: 'Press Releases News Indexer'
   type: news
   storagepid: 10
   sysfolder:
     - 25
   targetpid: 30
   index_news_category_mode: 2
   index_extnews_category_selection:
     - 'News'
     - 'Press Releases'
