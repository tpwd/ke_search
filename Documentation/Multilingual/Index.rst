.. include:: /Includes.rst.txt

.. _multilangual:

====================
Multilangual support
====================

ke_search has multilingual support in a way that

* if one searches in a specific language, the results will only be shown for that language.
* filters can be translated and be shown in the respective language.

Indexing content in different languages
=======================================

All available languages will be detected automatically and will be indexed.

Translating search result pages
===============================

On the search result page, insert the ke_search plugins in the translated page you just created. You can use the
function "copy default content elements". You can leave the configuration as it has been copied from your default language.

Translating filters
===================

In order to use the multilingual feature for filters you'll have to

Create page translations
    Create alternative page languages for the storage folder where the index and filters are stored and
    for your search result page. You can do that with help of the page module by using the function
    "Create a new translation of this page".

Translate filters and filter options
    Now you can translate the filters and filteroptions to the new language.

Use your own labels
===================

You can overwrite labels used in ke_search by registering `locallangXMLOverride` files.

See also https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Localization/ManagingTranslations.html#custom-translations

You can find the default language files in
`vendor/tpwd/ke_search/Resources/Private/Language` and the language files
for other languages in `var/labels`.

Copy the language files you want to modify (you dont' have to copy all the files)
to your site package into the folder `Resources/Private/Language/Overrides`.

.. figure:: /Images/Multilingual/language-file-structure.png
   :alt: File structure for language file overrides
   :class: with-border

Register the files in the `ext_localconf.php` of your site package
(assuming it's key is `mysite`), here an example for the file
`locallang_searchbox.xlf` (you will have to do it for each file you want to
overwrite):

.. code-block:: typoscript

    // Custom translations
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']
    ['EXT:ke_search/Resources/Private/Language/locallang_searchbox.xlf'][]
        = 'EXT:mysite/Resources/Private/Language/Overrides/locallang_searchbox.xlf';

    // Override a German ("de") translation
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['de']
    ['EXT:ke_search/Resources/Private/Language/locallang_searchbox.xlf'][]
        = 'EXT:mysite/Resources/Private/Language/Overrides/de.locallang_searchbox.xlf';
