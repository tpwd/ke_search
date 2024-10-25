.. include:: /Includes.rst.txt

.. _configuration-override-record-storage-page:

============================================
Override record storage page (Startingpoint)
============================================

It is possible to override the record storage page (or "Startingpoint") defined
in the plugin using TypoScript. This is useful if you want to serve different
search results depending on TypoScript conditions or if you include the plugin
via TypoScript.

For example you could serve different search results to logged in users by
setting the override record storage page to a different page id.

Setup TypoScript:

.. code-block:: typoscript

   plugin.tx_kesearch_pi1.overrideStartingPoint = 123
   plugin.tx_kesearch_pi1.overrideStartingPointRecursive = 1
   plugin.tx_kesearch_pi2.overrideStartingPoint = 123
   plugin.tx_kesearch_pi2.overrideStartingPointRecursive = 1
