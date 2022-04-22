.. include:: /Includes.rst.txt

.. _dateRangeFilter:

=================
Date range filter
=================

The date range filter allows the user to enter a start date and an end date and finds results in between these
two dates.

.. figure:: /Images/Filters/daterange-frontend.png
   :alt: Date range filter in the frontend
   :class: with-border

Add the date range filter
=========================

.. rst-class:: bignums-xxl

   #. Create a filter

      Create a new filter in your search storage page and set the type to "Date range". In opposite to the tag
      based filters there are no filter options to define.

      .. figure:: /Images/Filters/daterange-create-filter.png
         :alt: Add a new filter of type "date range"
         :class: with-border

   #. Add filter to search plugin

      Then add that filter to the list of filters which should be displayed in your search box plugin.

      .. figure:: /Images/Filters/daterange-select-filter.png
         :alt: Add the filter to the searchbox plugin
         :class: with-border

Where the date is fetched from
==============================

The date which is used for filtering is the same date which is used if the results are sorted by date. It is the
field `sortdate` in the index table.

The page indexer fetches the date from the database field `SYS_LASTCHANGED` which is set by TYPO3 internally to the
date when the page has been updated the last time. This can be overwritten by editors by setting the
:guilabel:`Last update` field in the :guilabel:`Metadata` tab of the page properties.

The news indexer fetches the date from the "Date & Time" field of EXT:news.

Templating
==========================

Use a custom template for `Partials/Filters/DateRange.html`.