.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. _start:

=========
ke_search
=========

ke_search is a search engine for the TYPO3 content management system.

It offers fulltext search and faceting possibilities. Faceting means you
can narrow down your search results by selecting certain categories,
called facets or filter options.

It is very flexible: By writing your own indexer you can put any content
you want into the index.

ke_search comes with strong defaults and with very little configuration.
You can have a powerful search engine in your TYPO3 website, eg. images in
the search result list and faceting without templating or coding.

ke_search does not use frontend crawling but fetches content elements and data
records directly from the database. For the most used content types indexers
are provided within the extension itself, including pages and news.

If you found a bug or want to ask for a feature, please use
https://github.com/tpwd/ke_search/issues

Contributing
------------

Code contributions are welcome.

The recommended way is to fork the project on GitHub and create a pull request.

Please explain what your patch is intended to do either by creating an issue
first or by adding an explanation to the pull request.

You can checkout the project locally with

```
git clone git@github.com:tpwd/ke_search.git
```

(adapt the repository URL to your cloned repository)

You can then install the dependencies with

```
cd ke_search
composer install
```

Testing
~~~~~~~

To test your code set up a TYPO3 instance for testing and symlink or deploy
the directory `ke_search` to your TYPO3 test instance to
`typo3conf/ext/ke_search`. If you deploy the code (e.g. by using PHPStorm
"Deployment" feature), you can ignore the `.Build` directory.

To run the unit tests:

```
composer test:unit
```

The tests are also executed by a GitHub pipeline.

To check the code with PHPStan

```
composer test:phpstan
```

(this will create a file `phpstan-report.log`)
