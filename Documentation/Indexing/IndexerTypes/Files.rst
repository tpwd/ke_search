.. include:: /Includes.rst.txt

.. _filesIndexer:

=====
Files
=====

.. contents::
   :depth: 1
   :local:

The files indexer allows you to index content of files from the file system.
Currently the indexer supports indexing of the following files: PDF, DOC, PPT, XLS, DOCX, PPTX, XLSX.

.. note::
   Until version 1.2 only PDF and PPT files could be indexed.

There are two ways to index files:

* Use the page or content element indexer. It will automatically detect links to files and index those files.
* Use directory based file indexer. You can specify folder which should be indexed.
  You can either choose to use FAL (File Abstraction Layer), that will also index the metadata of files. Or you can
  decide not to use FAL. That will only index file contents and no file metadata.

An incremental indexer is available. It will detect changes by comparing the file modification time against the last
indexing time and re-index those files.

System requirements
===================

* For PDF indexing you will need to have the external tools "pdfinfo" and "pdftotext" installed
  (in Ubuntu Linux they come with the package "poppler-utils").
* For PPT indexing you will need to have the external tool "catppt" installed (in Ubuntu Linux it comes
  with the package "catdoc").

Please use the extension configuration to tell ke_search the file paths where to find these tools.

Directory based file indexer
============================

You can specify the folders ke_search should index. Since the files are indexed directly from the file system,
there's no access check! Please make sure only public content is in the folders you make searchable.

You can select a FAL storage from where you want to index files. If you do so, FAL metadata will be indexed.
Categories will be used to generate tags (like in the pages and news indexer), this makes it possible to do
faceting over files, see also :ref:`systemcategories`.

Configuration
-------------

* Set a title (only for internal use).
* Set the Record storage page of search data your search data folder.
* Set the type to `Files`.
* Select FAL storage or select `Don't use FAL`.
* Define one or more directories which contain your files to be indexed.
  If you selected a FAL storage, the directories must be subdirectories of the selected storage,
  e. g. :file:`my_directory`. If you selected `Don't use FAL`, you need to specify the folders including the
  storage path, e. g. :file:`fileadmin/my_directory`. Multiple directories can be entered comma-separated.
  If you want to index all files in the given storage, just enter a dot (".") in the field :guilabel:`Directories`.
* Enter the list of allowed file extensions. Only files with extensions the indexer supports will be indexed. If you
  use FAL indexing, you can also provide other filetypes, eg. JPG. From these files the metadata will be indexed.

The indexer will go recursively into the defined directories and index files in there.

FAL metadata will be indexed if you select a FAL storage (title, alternative text and description).
Tags will be generated from categories (like in the news and pages indexer), see also :ref:`systemcategories`.

Content based file indexer
==========================

This indexer detects files while indexing pages and content elements and indexes the files automatically.
Supported content element types are `Text`, `Text with image` and `Filelinks`.

Just create an indexer configuration of type "pages" or "content elements".
Indexing of files will take place automatically.

File types which should be indexed can be specified in the indexer configuration.
Leaving the field empty will have the effect that no files will be indexed.

Content restrictions from the linking content elements will be taken into account.

FAL metadata will be indexed if you select a FAL storage (title, alternative text and description).
Tags will be generated from categories (like in the news and pages indexer), see also :ref:`systemcategories`.
