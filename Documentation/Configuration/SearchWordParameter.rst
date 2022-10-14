.. include:: /Includes.rst.txt

.. _configuration-search-word-parameter:

================================
Change the search word parameter
================================

By default the URL parameter for the searchword is `tx_kesearch_pi1[sword]` (following the TYPO3 "piVar" standard).

You may change this parameter to something else, e.g. `query` which allows the search words to be tracked by GA4 with
the TypoScript configuration option `searchWordParameter`.

Example (Setup TypoScript):

.. code-block:: typoscript

   plugin.tx_kesearch_pi1.searchWordParameter = query
