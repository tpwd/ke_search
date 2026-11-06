.. include:: /Includes.rst.txt

.. _systemcategories:

=====================================
Using system categories for filtering
=====================================

You can use the system categories the TYPO3 core provides for filtering.

ke_search automatically creates tags for assigned system categories, this
applies only to the indexers for
*pages, files, news (EXT:news and EXT:tt_news) and (since version 4.6.0) tt_address*
as these records can have system categories assigned by default.

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
   * A category's hidden state is synchronized to its filter options when saved
     (since version 7.4.0). Newly created options, including those for
     subcategories, use that state.
   * Synchronization is language-specific: a category in the default language
     updates default-language options; a translated category updates only the
     option in the same language.
   * The category's hidden state takes precedence over a filter option's manually
     set hidden state. Saving a visible category makes its options visible again.
   * Existing categories synchronize when next saved. Tags already stored in the
     search index remain until the next full re-index. Category start and end
     times are not mirrored to filter options.
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
