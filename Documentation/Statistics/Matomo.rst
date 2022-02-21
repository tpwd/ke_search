.. include:: /Includes.rst.txt

.. _matomo:

=======================
Statistics using Matomo
=======================

If you use Matomo on your website, you can use it to generate a report for search queries:

.. rst-class:: bignums-xxl

   #. Activate site search

      In Matomo go to :guilabel:`Administration` > :guilabel:`Measurables` > :guilabel:`Manage` and select
      the according website. Select the option `Site Search tracking enabled` of the :guilabel:`Site Search` field:

      .. figure:: /Images/Statistics/statistics-matomo-1.png
         :alt: Site search related fields in Matomo administration
         :class: with-border

      You can disable the option :guilabel:`Use default Site Search parameters` (as these are not used) and enter
      the value `tx_kesearch_pi1[sword]` into the field :guilabel:`Query parameter`.

   #. Display site search metrics

      Go to :guilabel:`Behaviour` > :guilabel:`Site Search`.

      .. figure:: /Images/Statistics/statistics-matomo-2.png
         :alt: Site search metrics in Matomo
         :class: with-border

.. tip::
   It is also possible to add more information to Matomo like keywords with no results or the query time.
   Take a look into the blog post `Display search metrics from TYPO3 extension ke_search in Matomo`_ how
   to achieve that.


.. _Display search metrics from TYPO3 extension ke_search in Matomo: https://brot.krue.ml/search-metrics-typo3-extension-ke-search-matomo/
