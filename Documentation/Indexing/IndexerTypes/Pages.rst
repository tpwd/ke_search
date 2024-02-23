.. include:: /Includes.rst.txt

.. _pagesIndexer:

=====
Pages
=====

The page indexer indexes standard TYPO3 pages.

All content elements on a page will be grouped and written to the index in one
index entry. That means if your search word appears in two different text
elements on a page, you will get only one search result for the page these two
elements belong to.

Configuration
=============

* Set the type of the indexer configuration to `Pages`.
* Set a title (only for internal use).
* Set the field :guilabel:`Storage` to the folder where you want to store your search data.
* Set :guilabel:`Startingpoints (recursive)` to the pages you want to index recursively.
* Set :guilabel:`Single Pages` to the pages you want to index non-recursively.

Content Types
=============

By default the page indexer indexes content element of the following types
which are delivered by the TYPO3 core:

* Text (CType `text`)
* Text with image / Media element (CTypes `textmedia` and  `textpic`)
* Bullet list (CType `bullets`)
* Table (CType `table`)
* Plain HTML (CType `html`)
* Header (CType `header`)
* File lists (CType `uploads`)
* Referenced content elements (CType `shortcut`)

Additionally it indexes content elements provided by the EXT:bootstrap_package:

* Accordion (CType `accordion`)
* Tab (CType `tab`)
* Carousel (CTypes `carousel`, `carousel_fullscreen`, `carousel_small`)
* Icon Group (CType `icon_group`)
* Card Group (CType `card_group`)
* Timeline (CType `timeline`)

File indexing
=============

Files linked in RTE text fields will be detected and indexed. Also files linked
in fields of type "link" (e.g. `header_link`) will be detected.

Abstract
========

In the page properties there's the field :guilabel:`Abstract for search result`
in the tab :guilabel:`Search`. Here you can enter a short description of the
page, this text will be used as an abstract in the search result list. If this
field is empty, it falls back to the field :guilabel:`Description` in the
:guilabel:`Metadata` tab of the page properties. In the FlexForm of the
search plugin you can define the "Text for used for search result
(preview text)" and set it to "Always show the abstract if set" or
"Show abstract if it contains searchword, otherwise show an excerpt from
the content".

Advanced options
----------------

* If you set :guilabel:`Index content elements with restrictions` to `yes`,
  content elements will be indexed even if they have frontend user group access
  restrictions. This function may be used to "tease" certain content elements in
  your search and then tell the user that he will have to log in to see the full
  content once he clicks on the search result.
* If you created custom page types which you want to index, you can add them in
  :guilabel:`Page types which should be indexed` set the page types you want
  to index.
* in :guilabel:`Content element types which should be indexed` you can add your
  own content element types. For example those created with EXT:mask or from
  EXT:bootstrap_package. If you are not sure what to enter here, have a look a
  the table `tt_content` in the column `CType`.
* (since version 5.3.0) In :guilabel:`Additional tables for content elements`
  you can define tables which hold additional content. That is used for example
  by EXT:bootstrap_package or EXT:mask. See below ("Index content from
  additional tables") for details.
* In :guilabel:`tt_content fields which should be indexed` you can define custom
  fields which should be indexed. Default is here "bodytext,subheader,
  header_link" which is used for the default content elements. This is useful
  if you added your custom content elements for example using EXT:mask.
* Using the field :guilabel:`Comma separated list of allowed file extensions`
  you can set the allowed file extension of files to index. By default this is
  set to `pdf,ppt,doc,xls,docx,xlsx,pptx`. For pdf, ppt, doc and xls files you
  need to install external tools on the server.
* Using the field :guilabel:`tt_content fields which should be indexed for file references`
  you can add fields from `tt_content` which hold file references and for which
  the attached files should be indexed.
* You can choose to add a tag to all index entries created by this indexer.
* You can choose to add that tag also to files indexed by this indexer.

Index content from additional tables (eg. EXT:mask, EXT:bootstrap_package)
--------------------------------------------------------------------------
Some extension like the widely used `mask` and `bootstrap_package` extensions
store content not in the tt_content table but in additional tables which
hold a reference to the record in tt_content.

Since version 5.3.0 it is possible to index those tables without the need
for a 3rd party extension or custom indexer. In the field
:guilabel:`Additional tables for content elements` you can configure those
tables. The `ini` configuration format is used here.

You need to define the table name, the field which holds the reference to the
tt_content table and the fields which should be indexed.

Options
.......

first line (eg. `[custom_element]`)
    The content type, stored as `CType` in the table `tt_content`. You will
    also have to add this to :guilabel:`Content element types which
    should be indexed`

table
    This is the table that holds the content.

referenceFieldName
    This ist the field that holds the relation to the tt_content record (the
    UID of the record). In EXT:bootstrap_package it is named `tt_content`,
    in EXT:mask it is named `parentid`.

fields[]
    A list of database fields which should be indexed. If the field is
    configured as type "file" in the TCA the indexer will check if it links
    to a file and index that file. Otherwise the field will be treated as a
    text field and will be indexed like other fields, e.g. the `bodytext` field
    in content elements. Links to files will also be resolved here and the
    files will be indexed.

Examples
--------

Add this to :guilabel:`Additional tables for content elements` to
index the bootstrap package element "accordion" (remember to also add
`accordion` to :guilabel:`Content element types which should be indexed`:

.. code-block:: ini

   [accordion]
   table = tx_bootstrappackage_accordion_item
   referenceFieldName = tt_content
   fields[] = header
   fields[] = bodytext

Add this to :guilabel:`Additional tables for content elements` to
index a mask element (remember to also add
`mask_list` to :guilabel:`Content element types which should be indexed`:

.. code-block:: ini

    [mask_list]
    table = tx_mask_content
    referenceFieldName = parentid
    fields[] = tx_mask_content_item

This is an example for a some mask elements:

* The element `mask_custom_text_element`  adds a field `tx_mask_customtext`
  to the `tt_content` table.
* The element `mask_custom_file_download` adds a file download field
  `tx_mask_file` to the `tt_content` table.
* The element `mask_list` stores content in the table `tx_mask_content`.

.. figure:: /Images/Indexing/custom-elements-01.png
   :alt: Example for indexing a custom elements created with mask 1/2
   :class: with-border

.. figure:: /Images/Indexing/custom-elements-02.png
   :alt: Example for indexing a custom elements created with mask 2/2
   :class: with-border
