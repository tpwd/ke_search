.. include:: /Includes.rst.txt

.. _customfilter:

=============
Custom Filter
=============

You can provide your own code to render a filter based on the results and filter options ke_search has found.

.. note::
   You can find the example below in the extension ke_search_hooks: https://extensions.typo3.org/extension/ke_search_hooks

These are the steps for adding custom filter:

.. rst-class:: bignums-xxl

   #. Register the hook

      In the `ext_localconf.php` file of your sitepackage extension add:

      .. code-block:: php

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customFilterRenderer'][] =
            \Tpwd\KeSearchHooks\ExampleFilterRenderer::class;

   #. Add the hook class

      This is very basic example which replaces the "select" filter type with a custom type and uses a Fluid standalone
      view to render it. The class is placed in `ke_search_hooks/Classes/ExampleFilterRenderer.php`.
      You can freely choose the class name, the function name must be `customFilterRenderer`.

      .. code-block:: php

        <?php

        namespace Tpwd\KeSearchHooks;

        use Tpwd\KeSearch\Lib\Pluginbase;
        use TYPO3\CMS\Core\Utility\GeneralUtility;

        class ExampleFilterRenderer
        {
            public function customFilterRenderer(int $filterUid, array $options, Pluginbase $plugin, array &$filterData)
            {
                if ($filterData['rendertype'] == 'select') {
                    /** @var \TYPO3\CMS\Fluid\View\StandaloneView $view */
                    $view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
                    $view->setTemplateRootPaths([10 => 'EXT:ke_search_hooks/Resources/Private/Templates']);
                    $view->setTemplate('Filters/CustomFilter');
                    $view->assign('filter', $filterData);

                    $filterData['rendertype'] = 'custom';
                    $filterData['rawHtmlContent'] = $view->render();
                }
            }
        }

      In order to trigger the rendering of the custom filter the last two lines inside the loop are important:

      .. code-block:: php

        $filterData['rendertype'] = 'custom';
        $filterData['rawHtmlContent'] = $view->render();

      When the "rendertype" is set to "custom", the given "rawHtmlContent" will be used instead of the rendering
      of ke_search itself.

   #. Add the template

      The template is placed in `ke_search_hooks/Resources/Private/Templates/Filters/CustomFilter.html`. Here we use
      radio buttons for the filter options.

      .. code-block:: html

        <fieldset>
            <legend>Custom filter</legend>
            <div id="{filter.id}">
                <f:for each="{filter.options}" as="option">
                    <input type="radio" name="{filter.name}" value="{option.value}" {f:if(condition: '{option.selected}', then: 'checked')} />
                    {option.title}<f:if condition="{option.selected} == ''"><f:if condition="{filter.shownumberofresults} && {option.results}"> ({option.results})</f:if></f:if><br />
                </f:for>
            </div>
        </fieldset>

Result
======

The result is a filter rendered as radio buttons.

.. figure:: /Images/Filters/CustomFilterExample.png
   :alt: Example for a custom filter
   :class: with-border
