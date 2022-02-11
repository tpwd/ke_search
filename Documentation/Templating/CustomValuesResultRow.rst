.. include:: /Includes.rst.txt

.. _custom-values-resultrow:

=======================================
Custom values in the search result list
=======================================

By default the indexed content will be shown in the search result list (either the abstract or a part of the indexed
content).

The partial for a single result row is stored at :file:`ke_search/Resources/Private/Partials/ResultRow.html`.

If you want to adapt your search result list to show other content than the indexed content, e.g. the content of
individual database fields, you have the following possibilities.

.. contents::
   :depth: 1
   :local:

Format date for different locales
=================================

The date of the search result will printed if the setting :guilabel:`Show date` is activated in the plugin settings. You will get
the date as a unix timestamp and can use the :html:`<f:format.date>` viewhelper to use different locales.

Example:

.. code-block:: html

   <f:format.date format="%d. %B %Y">{resultrow.date_timestamp}</f:format.date>


Accessing the original database row
===================================

The UID and the PID and also the original database row are available in the result list by using the following variables

.. code-block:: none

    {resultrow.orig_uid}
    {resultrow.orig_pid}
    {resultrow.orig_row.uid}
    {resultrow.orig_row.title}
    [...]

The `orig_row` is only available for pages, news and tt_address records or if the key for a custom indexer is exactly the
same as the corresponding table name (e.g. create a custom indexer for frontend users and use the key `fe_users`).
But you can also register your own table names. See :file:`ke_search/Classes/Domain/Repository/GenericRepository.php`.

Add a hook
==========

You can add a hook `additionalResultMarker` in order to add more variables to the fluid template, see
:file:`ke:search/Classes/Lib/Pluginbase.php`.

Write a view helper
===================

You could also write a view helper. Since you have the type and the UID of the original record available, you could
pass this to the view helper and you are then free to create whatever content you want.

Debug available values
======================

By adding

.. code-block:: html

   <f:debug>{resultrow}</f:debug>

to the Fluid template, you can see all the available values for each result.
