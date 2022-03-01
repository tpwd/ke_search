.. include:: /Includes.rst.txt

.. _configurationNotes:

=====
Notes
=====

Notes on TypoScript and FlexForm settings
=========================================

Each property in FlexForm overwrites the property defined by TypoScript.

Each property has stdWrap properties.

With the following TypoScript, you can define the result page:

.. code-block:: typoscript

   plugin.tx_kesearch_pi1.resultPage = 9

or you can define the result page with help of an URL param if you want:

.. code-block:: typoscript

   plugin.tx_kesearch_pi1.resultPage.data = GP:tx_kesearch_pi1|resultPage

Notes on TypoScript and extension configuration
===============================================

In :guilabel:`Admin Tools` > :guilabel:`Settings` > :guilabel:`Extension Configuration` you can define basic options
like the minimal length of searchwords.

You can overwrite this configuration in your page TypoScript setup:

.. code-block:: typoscript

   ke_search_premium.extconf.override.searchWordLength = 3

or

.. code-block:: typoscript

   ke_search_premium.extconf.override.enableSphinxSearch = 0
