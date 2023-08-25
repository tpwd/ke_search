.. include:: /Includes.rst.txt

.. _configuration-avoid404error:

=========================================
Avoid 404 error from cacheHash validation
=========================================

In TYPO3 10.4.35/11.5.23/12.2 an option `enforcevalidation` has been introduced
to enforce the validation of the "cHash" parameter. This is recommended to
be enabled and is enabled by default for new installations, see
https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Configuration/Typo3ConfVars/FE.html#enforcevalidation

For ke_search it is not possible to calculate a cHash because ke_search uses a
form using the GET method to send data and the options are modified by the user.

Therefore the default ke_search parameters are excluded in the
`ext_localconf.php` using the `['FE']['cacheHash']['excludedParameters']` option.

In some cases you will need to add a similar configuration to your sitepackage
as well:

* You have enabled the `enforcevalidation` option **and**
* use filters **or**
* have changed the parameter for the search word using `plugin.tx_kesearch_pi1.searchWordParameter`

In these cases you will have to exclude the filter parameters and/or the
search word parameter in the same way.

Example
=======

.. code-block:: php

   // to be used in ext_localconf.php
   $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
       $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'],
       [
           'q', // e.g. if "plugin.tx_kesearch_pi1.searchWordParameter = q"
           'tx_kesearch_pi1[filter_1]', // Filter with UID 1
       ]
   );
