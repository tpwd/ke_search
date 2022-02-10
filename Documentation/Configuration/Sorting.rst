.. include:: /Includes.rst.txt

.. _sorting:

=======
Sorting
=======

You may define the sorting method in the plugin configuration. Available options are `relevance`, `title` and `date`.
More options may be added through third party extensions (see below :ref:`sorting-own-options`).

There are two sorting method options, one if a searchword was given and one if only filters have been used without
a searchword. The reason for that is without a searchword, you don't have a relevance ranking.

Default sorting is `relevance descending` if a searchword has been given and `date descending` if no
searchword has been given.

.. figure:: /Images/Configuration/sorting-plugin-settings.png
   :alt: Sorting plugin options
   :class: with-border

You may also activate the "frontend sorting" feature. This allows the visitor to decide for a sorting method.

.. figure:: /Images/Configuration/sorting-links.png
   :alt: Sorting links in frontend
   :class: with-border

You may then choose the fields you want to allow sorting for. By default these are `relevancy`, `date` and `title`.

.. _sorting-own-options:

Adding your own sorting options
===============================

If you want other sorting options than relevance, date or title, you will have to

* Extend the table `tx_kesearch_index` by the fields you want to use for sorting (for example `mysortfield`) (:file:`ext_tables.sql`, TCA configuration).
* Register your sorting fields by hook `registerAdditionalFields`, so that they are written to the database.
* Write your own indexer or extend an existing one that fills your new field during the indexing process.

.. note::
   If you add an "additional field" to the index **every** indexer must set this field. So make sure you use the
   provided hooks for every indexer you use.

You can find an example in the extension ke_search_hooks: https://extensions.typo3.org/extension/ke_search_hooks

.. code-block:: php

   // in ext_localconf.php:

   // Register hook to register additional fields in the index table
   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerAdditionalFields'][] =
      \MyVendor\KeSearchHooks\AdditionalIndexerFields::class;

.. code-block:: php

    <?php
    namespace MyVendor\KeSearchHooks;

    /**
     * Class AdditionalIndexerFields
     * @package MyVendor\KeSearchHooks
     */
    class AdditionalIndexerFields {
       public function registerAdditionalFields(&$additionalFields) {
          $additionalFields[] = 'mysorting';
       }
    }

Your new database field will automatically appear in the backend selection of sorting fields!

You will have to add a locallang-value to your TypoScript setup:

.. code-block:: typoscript

   plugin.tx_kesearch_pi2 {
      _LOCAL_LANG.default {
         orderlink_mysorting = My sort field label
      }
   }
