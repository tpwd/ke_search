<?php

namespace Tpwd\KeSearch\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

class ExplodeViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('string', 'string', 'String to count, if not provided as tag content');
        $this->registerArgument('delimiter', 'string', 'Delimiter to explode the string with', false, ',');
    }

    /**
     * @return string[]
     */
    public function render(): array
    {
        $delimiter = $this->arguments['delimiter'] ?? ',';
        $string = $this->arguments['string'] ?? '';
        return GeneralUtility::trimExplode($delimiter, $string, true);
    }
}
