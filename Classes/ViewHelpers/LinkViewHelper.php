<?php

namespace Tpwd\KeSearch\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Utility\RequestUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * ViewHelper to render links to search results including filters
 */
class LinkViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerArgument('page', 'int', 'Target page', false);
        $this->registerArgument('piVars', 'array', 'piVars', false);
        $this->registerArgument('resetFilters', 'array', 'Filters to reset', false);
        $this->registerArgument('content', 'string', 'content', false, '');
        $this->registerArgument('keepPiVars', 'boolean', 'keep piVars?', false, '');
        $this->registerArgument('uriOnly', 'bool', 'url only', false, false);
        $this->registerTagAttribute('section', 'string', 'Anchor for links', false);
    }

    /**
     * Render link to a search result page
     *
     * @return string link
     */
    public function render(): string
    {
        // @extensionScannerIgnoreLine
        $page = $this->arguments['page'] ?? $GLOBALS['TSFE']->id;
        $resetFilters = $this->arguments['resetFilters'] ?? [];
        $content = $this->arguments['content'] ?? '';
        $keepPiVars = !empty($this->arguments['keepPiVars']);
        $piVars = $this->arguments['piVars'] ?? [];
        $uriOnly = $this->arguments['uriOnly'] ?? false;

        /** @var ServerRequestInterface $request */
        // @phpstan-ignore-next-line
        $request = $this->renderingContext->getRequest();

        // Use alternative search word parameter (e.g. "query=") in URL but map to tx_kesearch_pi1[sword]=
        $searchWordParameter = SearchHelper::getSearchWordParameter();
        if ($searchWordParameter != 'tx_kesearch_pi1[sword]'
            && !isset($piVars['sword'])
            && RequestUtility::getQueryParam($request, $searchWordParameter)) {
            $piVars['sword'] = RequestUtility::getQueryParam($request, $searchWordParameter);
        }

        if (!empty($piVars)) {
            $piVars = SearchHelper::explodePiVars($piVars);
        }

        if ($keepPiVars) {
            $piVars = array_merge(
                SearchHelper::explodePiVars(
                    RequestUtility::getQueryParam($request, 'tx_kesearch_pi1') ?? []
                ),
                $piVars
            );
        }

        if (isset($piVars['page']) && $piVars['page'] == 1) {
            unset($piVars['page']);
        }

        $linkedContent = $this->renderChildren();
        if (empty($content)) {
            $content = $linkedContent;
        }

        $url = SearchHelper::searchLink($page, $piVars, $resetFilters);

        if ($uriOnly) {
            return $url;
        }

        if ($url === '' || $linkedContent === $url) {
            return $linkedContent;
        }

        if ($this->hasArgument('section')) {
            $url .= '#' . $this->arguments['section'];
        }

        $this->tag->addAttribute('href', $url);

        if (empty($content)) {
            $content = $linkedContent;
        }
        $this->tag->setContent($content);

        return $this->tag->render();
    }
}
