.. include:: /Includes.rst.txt

.. _indexingCustomContentFields:

==============================
Indexing custom content fields
==============================

If you added fields to the `tt_content` table in order to use them with your own content elements, you can index
these fields with the default page indexer, too.

Two hooks are needed:

* One adds the new field to the list of fields fetched from the tt_content table,
* the other one adds the field to the content written to the index.

.. rst-class:: bignums-xxl

   #. Register the hooks

      You need to register the hooks in your :file:`ext_localconf.php` as follows:

      .. code-block:: php

         // Register hooks for indexing additional fields.
         $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPageContentFields'][] =
            \MyVendor\KeSearchHooks\AdditionalContentFields::class;

         $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentElement'][] =
            \MyVendor\KeSearchHooks\AdditionalContentFields::class;

   #. Hook class

      .. code-block:: php

         class AdditionalContentFields {

            public function modifyPageContentFields(&$fields, $pageIndexer)
            {
               // Add the field "subheader" from the tt_content table, which is normally
               // not indexed, to the list of fields.
               $fields .= ",subheader";
            }

            public function modifyContentFromContentElement(string &$bodytext, array $ttContentRow, $pageIndexer)
            {
               // Add the content of the field "subheader" to $bodytext, which is, what
               // will be saved to the index.
               $bodytext .= strip_tags($ttContentRow['subheader']);
            }

         }

Example
=======

You can find an example in the extension "ke_search_hooks": https://extensions.typo3.org/extension/ke_search_hooks
