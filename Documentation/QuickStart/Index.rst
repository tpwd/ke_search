.. include:: /Includes.rst.txt

.. _quickstart:

===========
Quick start
===========

Follow the steps below to set up a simple fulltext search for your pages.
In order to use the faceting feature see "Faceting".

.. contents::
   :depth: 1
   :local:

Download and installation
=========================

Install the extension ke_search via extension manager or via composer (recommended):

.. code-block:: bash

   composer require tpwd/ke_search

You can find the current version (and older ones) at

https://extensions.typo3.org/extension/ke_search

Include TypoScript
==================

.. _configuration-site-set:
Site Sets
---------
..  versionadded:: TYPO3 v13.1 / ke_search v6.3.0
    If you are working with TYPO3 v12.4, use :ref:`configuration-typoscript-record`.

The extension ships some TypoScript code which can be included in the site
configuration via :ref:`Site sets <t3coreapi/13:site-sets>`:

#.  Got to backend module :guilabel:`Site Management > Sites`.
#.  Edit the configuration of your site.
#.  On the first tab go to :guilabel:`Sets for this Site`.
#.  Include the set :guilabel:`Faceted Search (ke_search)`.

.. _configuration-typoscript-record:

TypoScript sets (the traditional way)
-------------------------------------

If you are not using :ref:`configuration-site-set` you can
add the TypoScript via the TypoScript module (formerly known as "Include
static template"):

#.  Got to backend module :guilabel:`Site Management > TypoScript`.
#.  Select :guilabel:`Edit TypoScript record` --> :guilabel:`Edit the whole TypoScript record`.
#.  On the first tab go to :guilabel:`Include TypoScript sets`.
#.  Include the set :guilabel:`Faceted Search (ke_search)`.

Create pages
============

Create a new page called "Search" (or similar) and a sysfolder called "Search data" (or similar).

.. figure:: /Images/QuickStart/page-structure.png
   :alt: Page tree with search page
   :class: with-border

Configure Plugins
=================

You need to create two plugins: The searchbox and the resultlist.

.. figure:: /Images/QuickStart/plugins-1.png
   :alt: Faceted Search plugin in new content element wizard
   :class: with-border

.. rst-class:: bignums-xxl

   #. Create a plugin :guilabel:`Faceted Search: Show searchbox and filters` on the page `Search`

      Fill in the field :guilabel:`Record Storage Page` in the Tab :guilabel:`Plugin` > :guilabel:`General` with
      the folder that you created in step 2 (our example: `Search data`).

      .. figure:: /Images/QuickStart/plugins-3.png
         :alt: Plugin tab on "searchbox and filters" plugin
         :class: with-border

      .. hint::
         It is useful to give the plugin :guilabel:`Searchbox and Filters` a header (our example:
         `Searchbox`, can also set to `hidden`):

         .. figure:: /Images/QuickStart/plugins-2.png
            :alt: Headlines palette in plugin
            :class: with-border

         That makes it easier to identify the correct content element in the next step.

   #. Create a plugin :guilabel:`Faceted Search: Show resultlist` on the page `Search`

      In the field :guilabel:`Load FlexForm config from this search box` fill in the Search-Plug-In that you created in
      the previous step (our example: `Searchbox`).

      .. figure:: /Images/QuickStart/plugins-5.png
         :alt: Plugin tab
         :class: with-border

      After this steps, you should have two plugins on your search page.

      .. figure:: /Images/QuickStart/plugins-4.png
         :alt: Page module view with two Faceted Search plugins
         :class: with-border


Create the indexer configuration
================================

Use the :guilabel:`List` module to create an indexer configuration on the page `Search data`.

.. figure:: /Images/QuickStart/indexer-configuration-1.png
   :alt: New record view
   :class: with-border


* Choose a title.
* Set the :guilabel:`Type` to `Pages`.
* Set the :guilabel:`Storage` to your folder `Search data`.
* Choose the pages you wish to index. You can decide whether the indexing process runs on all pages recursively or
  if only one page will be indexed. You can combine both fields.

.. figure:: /Images/QuickStart/indexer-configuration-2.png
   :alt: Example for an indexer configuration
   :class: with-border

Start Indexer
=============

Open the backend module :guilabel:`Web` > :guilabel:`Faceted Search` and start the indexing process.

.. figure:: /Images/QuickStart/start.png
   :alt: Backend module view
   :class: with-border

You're done!

Open the `Search` page in the frontend and start finding ...
