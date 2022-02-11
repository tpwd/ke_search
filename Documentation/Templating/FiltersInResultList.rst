.. include:: /Includes.rst.txt

.. _filtersInResultList:

=============================
Render filters in result list
=============================

Since version 3.9.0 it is possible to show filters also in the result list plugin which gives you more flexibility
in placing them in relation to the result list.

In order to use this feature you need to enable the filters in the result list with the TypoScript setup setting
:typoscript:`plugin.tx_kesearch_pi2.includeFilters = 1`.

This is useful if you want to show e.g. the filters in the right-hand side and only if they are present.

HowTo
=====

.. rst-class:: bignums

   #. Enable the filters in the result list with this snippet in the TypoScript setup:

      .. code-block:: typoscript

         plugin.tx_kesearch_pi2.includeFilters = 1


   #. Create a :file:`Resources/Private/Partials/FiltersForm.html`:

      This is a modification of the :file:`Resources/Private/Partials/Filters.html` which looks like:

      .. code-block:: html

         <f:for each="{filters}" as="filter">
            <f:switch expression="{filter.rendertype}">
               <f:case value="select"><f:render partial="Filters/Select" arguments="{conf: conf, filter: filter}" /></f:case>
               <f:case value="checkbox"><f:render partial="Filters/Checkbox" arguments="{conf: conf, filter: filter}" /></f:case>
            </f:switch>
         </f:for>

   #. Add a :file:`Resources/Private/Partials/FiltersResults.html` which contains:

      .. code-block:: html

         <f:for each="{filters}" as="filter">
            <f:switch expression="{filter.rendertype}">
               <f:case value="list"><f:render partial="Filters/List" arguments="{conf: conf, filter: filter}" /></f:case>
               <f:case value="custom"><f:format.raw>{filter.rawHtmlContent}</f:format.raw></f:case>
            </f:switch>
         </f:for>

   #. In :file:`Resources/Private/Templates/ResultList.html` include:

      .. code-block:: html

         <f:if condition="{filters}">
            <div class="filters filtersResults">
               <f:render partial="FiltersResults" arguments="{conf: conf, numberofresults: numberofresults, resultrows: resultrows, filters: filters}" />
            </div>
         </f:if>

   #. And in :file:`Resources/Private/Templates/SearchForm.html` include:

      .. code-block:: html

         <f:if condition="{filters}">
            <div class="filters filtersForm">
               <f:render partial="FiltersForm" arguments="{conf: conf, numberofresults: numberofresults, resultrows: resultrows, filters: filters}" />
            </div>
         </f:if>
