.. include:: /Includes.rst.txt

.. _addressesIndexer:

==========================
Addresses (EXT:tt_address)
==========================

The Address indexer allows you to index tt_address entries.

The following fields are indexed (if they are filled):

* name
* first_name
* middle_name
* last_name
* company
* address
* zip
* city
* country
* region
* email
* phone
* fax
* mobile
* www

If set, the description is used as an abstract (search result list teaser).

Please notice that there is no single view in tt_address, the parameter :guilabel:`tt_address[showUid]` is nevertheless set.
If you need another parameter – e.g. for use with another extension that handles tt_address records –
you will have to modify the indexer content by using your own hook.
