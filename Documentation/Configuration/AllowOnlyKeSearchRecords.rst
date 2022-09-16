.. include:: /Includes.rst.txt

.. _configuration-allow-only-ke_search-records:

======================================
Allow only ke_search records on a page
======================================

Most likely you will create a sysfolder in which all the ke_search related data (indexer configurations, filters and
the index) will be stored.

You can reduce the allowed records in the "New record" wizard to ke_search records by including the static Page TSconfig
file "Restrict pages to ke_search records". This will reduce the items shown in the "New record" wizard to indexer
configurations and filters.

.. figure:: /Images/Configuration/AllowedNewTables-01.png
   :alt: Select static Page TSconfig file
   :class: with-border

.. figure:: /Images/Configuration/AllowedNewTables-02.png
   :alt: Available records are reduced to ke_search indexer configurations and filters
   :class: with-border