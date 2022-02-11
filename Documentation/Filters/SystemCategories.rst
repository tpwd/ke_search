.. include:: /Includes.rst.txt

.. _systemcategories:

=====================================
Using system categories for filtering
=====================================

You can use the system categories the TYPO3 core provides for filtering in ke_search.

ke_search automatically creates tags for assigned system categories, this applies
*only to the pages, files and news indexer* as these records can have categories assigned by default.
If you want to use system categories as filter options for other
content, you will have to write a custom indexer or extend existing ones via a hook.

You can then select for which filter a category should be used as a filter option:

.. rst-class:: bignums-xxl

   #. Create a "filter" record in your search data folder.

      .. figure:: /Images/Filters/create-filter.png
         :alt: Filter record view
         :class: with-border

   #. Select that filter in the tab "Search"

      You can also choose to use sub-categories as filter options. You can select
      more than one filter if you want to use this category as a filter option for more than one filter.

      .. figure:: /Images/Filters/category_select_filter.png
         :alt: Assign filters in page properties
         :class: with-border

.. note::

   * The filter options will be created via a backend hook whenever a category is edited (created, updated or deleted).
   * Up to version 3.1.6 ke_search only created the tags, you had to create the filter options
     yourself and use the auto-generated tag names.
   * For each assigned system category, two tags are created:

     #. The first tag uses this naming schema `syscat` + UID of the system category (eg. `syscat123`). This tag is used in the auto-created filter options.
     #. The second tag uses the title of the category. Non-alphanumeric characters will be removed.

   * If you use the tag derived from the category title, remember that this will change if the category title changes. It
     may be better to use the tag which relies on the uid of the category.

     .. figure:: /Images/Filters/syscat-tag.png
        :alt: Tags
        :class: with-border
