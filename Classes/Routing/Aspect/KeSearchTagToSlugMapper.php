<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Tpwd\KeSearch\Routing\Aspect;

use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class KeSearchTagToSlugMapper implements StaticMappableAspectInterface
{
    /**
     * @var array
     */
    protected $settings;

    /**
     * @param array $settings
     * @throws \InvalidArgumentException
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function generate(string $value): ?string
    {
        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $languageId = $context->getPropertyFromAspect('language', 'id');

        /** @var FilterOptionRepository $filterOptionRepository */
        $filterOptionRepository = GeneralUtility::makeInstance(FilterOptionRepository::class);
        $result = $filterOptionRepository->findByTagAndLanguage($value, $languageId);
        if ($result) {
            return $result[0]['slug'];
        }
        return $value;
    }

    public function resolve(string $value): ?string
    {
        if ($value === '' || $value === '-') {
            return $value;
        }

        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $languageId = $context->getPropertyFromAspect('language', 'id');

        /** @var FilterOptionRepository $filterOptionRepository */
        $filterOptionRepository = GeneralUtility::makeInstance(FilterOptionRepository::class);
        $result = $filterOptionRepository->findBySlugAndLanguage($value, $languageId);
        if ($result) {
            return $result[0]['tag'];
        }
        return null;
    }
}
