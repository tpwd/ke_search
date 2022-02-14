.. include:: /Includes.rst.txt

.. _searchbox:

=======================
Searchbox on every page
=======================

There are multiple ways to integrate the searchbox on every page.

* :ref:`By creating a content element and including this on every page <searchbox-contentelement>`
* :ref:`As plain HTML <searchbox-html>`
* :ref:`Directly using TypoScript <searchbox-typoscript>`

.. note::
   Since version 4.2.0 there's a plugin "Cachable searchbox". It is recommended to use this over "Searchbox" because
   this plugin won't disable caching of the entire page where it's used. Only drawback is that the plugin options won't get
   updated by the search currently performed, e. g. all the filter options will be shown even if they do not appear in the
   result list (the normal searchbox will only show the options which are available in the result list).

Best practice is to include the "Cachable searchbox" on every page and then on the result page use standard "Searchbox"
plugin.

.. _searchbox-contentelement:

Including searchbox as a content element
========================================

* Create a folder where you want to store the searchbox content element.
* Insert a "Cachable searchbox" plugin and configure it as you wish.
* Add that content element to every page with TypoScript, e. g.:

.. code-block:: typoscript

   # Include searchbox as a content element
   lib.searchbox = RECORDS
   lib.searchbox {
      tables = tt_content
      source = 7
   }

Where `7` is the UID of your searchbox content element.
You can then include the searchbox in your main page template, e. g.:

.. code-block:: typoscript

   page = PAGE
   page.5 < lib.searchbox
   page.10 < styles.content.get

On the search result page you should then remove the searchbox:

.. code-block:: typoscript

   page.5 >

.. _searchbox-html:

Include searchbox with plain HTML
=================================

.. code-block:: typoscript

   # Include searchbox as plain HTML
   lib.searchbox_html = TEXT
   lib.searchbox_html.value (
      <form method="get" id="form_kesearch_searchfield" name="form_kesearch_searchfield" action="/search/">
        <input type="text" id="ke_search_searchfield_sword" name="tx_kesearch_pi1[sword]" placeholder="Your search phrase" />
        <input type="submit" id="ke_search_searchfield_submit" alt="Find" />
      </form>
   )

   # Default PAGE object:
   page = PAGE
   page.5 < lib.searchbox_html
   page.10 < styles.content.get

The action "/search/" ist the slug of the page you created with your result list plugin.

.. _searchbox-typoscript:

Include searchbox with TypoScript
=================================

This is only possible without displaying filters as they are configured in a FlexForm. If you need filters, it's
recommended to include the searchbox as content element as shown above.

.. code-block:: typoscript

   # Include searchbox as a plugin
   lib.searchbox_plugin = COA
   lib.searchbox_plugin {
      10 < plugin.tx_kesearch_pi3

      # result page
      10.resultPage = 123

      # CSS file
      10.cssFile = EXT:ke_search/Resources/Public/Css/ke_search_pi1.css

      # Content element (search box plugin) from which additional
      # configuration should be loaded (UID of content element).
      # Important: If you have two search boxes on your result page
      # (eg. in the top and in the left area), you should set this value!
      # 10.loadFlexformsFromOtherCE = 123456
   }

The number `123` in this case is a placeholder for the page ID you created with your result list plugin.

You can then include the searchbox in your main page template, e. g.:

.. code-block:: typoscript

   page = PAGE
   page.5 < lib.searchbox_plugin
   page.10 < styles.content.get

On the search result page you should then remove the searchbox:

.. code-block:: typoscript

   page.5 >
