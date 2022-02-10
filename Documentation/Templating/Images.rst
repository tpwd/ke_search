.. include:: /Includes.rst.txt

.. _templatingImages:

======
Images
======

ke_search renders images (icons and thumbnails) in the list view for the following cases.

* Icons for the type of result (page, news, file, ...)
* Preview images for pages
* Preview images for news (using the first image of the news record)
* Preview images for files (thumbnails will be created automatically for PDF and image files)

You can enable / disable them in the plugin configuration.

.. figure:: /Images/Templating/plugin-image-settings.png
   :alt: "Images" tab in plugin view
   :class: with-border

Type icons
==========

You can change the icons which are used in the list view.

Example configuration (Template Setup):

.. code-block:: typoscript

	plugin.tx_kesearch_pi2.resultListTypeIcon.page.file = EXT:mysite/Resources/Public/Images/example-icon.png

`page` stands for the record type and corresponds to the indexer type.
For file formats like xls, doc etc. you can use file_xls, file_doc etc.

Page preview images
===================

If enabled in the plugin configuration, the image set in the page properties :guilabel:`Search` > :guilabel:`Search result image` will
be shown in the result list. If no image is set there, it falls back to :guilabel:`Resources` > :guilabel:`Media`.

Changing the size of images
===========================

To change the size of the images, you will have to adjust the corresponding fluid partial.
Please have a look at the partial :file:`ResultRow.html` in the section "typeIconOrPreviewImage".

.. _imagesInCustomIndexers:

Images in custom indexers
=========================

If you have implemented a custom indexer you can display images which are attached to the original record.

The image needs to be attached as FAL record to the original record.

The configuration setting shown below has to be added to your :file:`ext_localconf.php`.

* `INDEXER_KEY` is the key of your custom indexer (column `type` in the table `tx_kesearch_index`).
* `table` refers to the column `tablenames` in the table `sys_file_reference`
* `field` refers to the column `fieldnames` in the table `sys_file_reference`
* `TABLE_NAME` is the table of the original record
* `IMAGE_FIELD_NAME` is the column of the original record where the image is attached

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'][INDEXER_KEY]['table'] = TABLE_NAME;
   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'][INDEXER_KEY]['field'] = IMAGE_FIELD_NAME;

This example shows the configuration for the `fe_users` table if your indexer configuration key is also `fe_users`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes']['fe_users']['table'] = 'fe_users';
   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes']['fe_users']['field'] = 'image';
