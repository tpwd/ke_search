.. include:: /Includes.rst.txt

.. _templatingCss:

================
Use your own CSS
================

ke_search comes with a default css file which is loaded automatically.

If you do not wish to use that file, you have the following possibilities.

Unset via TypoScript
====================

If you do not wish to load that file, you can unset it via TypoScript:

.. code-block:: typoscript

	plugin.tx_kesearch_pi1.cssFile >
	plugin.tx_kesearch_pi2.cssFile >

Use plugin configuration
========================

You can also make use of the field in the plugin configuration. This overwrites the default CSS file.

.. figure:: /Images/Templating/templating-css-file.png
   :alt: Configure CSS file in plugin
   :class: with-border

