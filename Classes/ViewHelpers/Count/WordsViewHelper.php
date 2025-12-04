<?php

namespace Tpwd\KeSearch\ViewHelpers\Count;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class WordsViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('string', 'string', 'String to count, if not provided as tag content');
    }

    /**
     * @return int
     */
    public function render()
    {
        return str_word_count($this->renderChildren());
    }
}
