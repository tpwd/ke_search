.. include:: /Includes.rst.txt

.. _templatingCss:

================
Use your own CSS
================

ke_search comes with a default css file which is loaded automatically.

The path to the CSS file is configured in TypoScript:

.. code-block:: typoscript

    // Search box plugin
    plugin.tx_kesearch_pi1.cssFile = EXT:ke_search/Resources/Public/Css/ke_search_pi1.css
    // Result list plugin
    plugin.tx_kesearch_pi2.cssFile = EXT:ke_search/Resources/Public/Css/ke_search_pi1.css
    // Cacheable search box plugin
    plugin.tx_kesearch_pi3.cssFile = EXT:ke_search/Resources/Public/Css/ke_search_pi1.css

If you do not wish to use that file, you have the following possibilities.

Unset via TypoScript
====================

If you do not wish to load that file, you can unset it via TypoScript:

.. code-block:: typoscript

    // Search box plugin
    plugin.tx_kesearch_pi1.cssFile >
    // Result list plugin
    plugin.tx_kesearch_pi2.cssFile >
    // Cacheable search box plugin
    plugin.tx_kesearch_pi3.cssFile >

Use plugin configuration
========================

You can also make use of the field in the plugin configuration. This overwrites the default CSS file.

.. figure:: /Images/Templating/templating-css-file.png
   :alt: Configure CSS file in plugin
   :class: with-border

