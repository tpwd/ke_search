.. include:: /Includes.rst.txt

.. _hooks:

=====
Hooks
=====

ke_search includes a lot of hooks you can use to include your own code and customize the behaviour of the extension.

modifyPagesIndexEntry
	Use this hook to modify the page data just before it will be saved into database.

modifyPageContentFields
    Use this hook to modify the page content fields. See chapter “:ref:`Indexing custom content fields <indexingCustomContentFields>`” for further information.

modifyExtNewsIndexEntry
	Use this hook to modify the news data just before it will be saved into database.

modifyExtTtNewsIndexEntry
    Use this hook to modify the tt_news data just before it will be saved into database.

modifyAddressIndexEntry
	Use this hook to modify the tt_address data just before it will be saved into database.

modifyFileIndexEntry
    Use this hook to make custom modifications of the indexed data, e.g. the tags.

modifyFileIndexEntryFromContentIndexer
    Use this hook to make custom modifications of the indexed data, e.g. the tags.

modifyFilterOptions
	Use this hook to modify your filter options for type “select”, e.g. for adding special options, labels, css classes or to preselect an option.

modifyFilterOptionsArray
	Use this hook to modify your filter options, independent from filter type, e.g. for adding special options, css classes or to preselect an option.

modifyFieldValuesBeforeStoring
    Use this hook to manipulate the field values before they go to the database.

modifyContentFromContentElement
    Use this hook for modifying a content element's content.  See chapter “:ref:`Indexing custom content fields <indexingCustomContentFields>`” for further information.

modifyContentIndexEntry
    Use this hook for custom modifications of the indexed data, e. g. the tags.

contentElementShouldBeIndexed
    Use this hook to add a custom check if a specific content element should be indexed.

initials
	Change any variable while initializing the plugin.

modifyFlexFormData
	Access and modify all returned values of ke_search FlexForm.

customFilterRenderer
	You can write your own filter rendering function using this hook. You will have to add your custom filter type to TCA options array. See chapter “Custom filter rendering” for further information.

registerIndexerConfiguration
	Use this hook for registering your custom indexer configuration in TCA. See chapter “:ref:`Write your own custom indexer! <customIndexer>`” for further information.

customIndexer
    Use this hook to register a custom indexer. See chapter “:ref:`Write your own custom indexer! <customIndexer>`” for further information.

cleanup
    Use this hook for cleanup.

registerAdditionalFields
	This hook is important if you have extended the indexer table with your own columns.

renderPagebrowserInit
	Hook for third party pagebrowsers or for modification of build in browser, if the hook return content then return that content.

pagebrowseAdditionalMarker
	Hook for additional markers in pagebrowse.

getOrdering
	Hook for third party extensions to modify the sorting.

getLimit
	Hook for third party pagebrowsers or for modification $this->pObj->piVars['page'] parameter.

modifyResultList
	Hook for adding new markers to the result list

fileReferenceTypes
	Hook for adding third party file previews. See chapter “:ref:`imagesInCustomIndexers`” for further information.
