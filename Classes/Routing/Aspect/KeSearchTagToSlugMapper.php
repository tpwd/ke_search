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

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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

        /** @var FilterOptionRepository $filterOptionRepository */
        $filterOptionRepository = GeneralUtility::makeInstance(FilterOptionRepository::class);
        $languageId = $this->getRequestLanguageId();
        if ($languageId !== null) {
            $result = $filterOptionRepository->findBySlugAndLanguage($value, $languageId);
            if (!empty($result)) {
                return $result[0]['tag'];
            }
        }

        // Translated options share a tag, so a cross-language lookup can still resolve the route.
        $result = $filterOptionRepository->findBySlug($value);
        if (!empty($result)) {
            return $result[0]['tag'];
        }
        return null;
    }

    /**
     * Determines the language ID that should be used to resolve a filter-option slug.
     *
     * During route resolving, TYPO3 may not yet have initialized the language aspect in
     * the Context. The request's `language` attribute is therefore preferred when it
     * contains a SiteLanguage. If it is unavailable, the language is determined by
     * matching the request path against the base paths of the request's site.
     *
     * Returns null when there is no TYPO3 request, no SiteInterface on the request, or
     * no language base path matches the request path.
     */
    private function getRequestLanguageId(): ?int
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            return $language->getLanguageId();
        }

        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return null;
        }

        return $this->findLanguageIdByRequestPath($site, $request->getUri()->getPath());
    }

    /**
     * Finds the language whose base path most specifically matches the request path.
     *
     * The longest matching base path wins, allowing language bases nested below shared
     * paths to take precedence. If equally long paths match, language ID 0 is preferred.
     * A root base path (`/`) matches every request path and therefore identifies the
     * default language when no more specific base path matches.
     */
    private function findLanguageIdByRequestPath(SiteInterface $site, string $requestPath): ?int
    {
        $matchedLanguageId = null;
        $matchedBasePathLength = -1;
        foreach ($site->getLanguages() as $siteLanguage) {
            $basePath = rtrim($siteLanguage->getBase()->getPath(), '/');
            if ($basePath === '') {
                $basePath = '/';
            }

            $matches = $basePath === '/'
                || $requestPath === $basePath
                || str_starts_with($requestPath, $basePath . '/');
            $basePathLength = strlen($basePath);
            if (
                $matches
                && (
                    $basePathLength > $matchedBasePathLength
                    || ($basePathLength === $matchedBasePathLength && $siteLanguage->getLanguageId() === 0)
                )
            ) {
                $matchedLanguageId = $siteLanguage->getLanguageId();
                $matchedBasePathLength = $basePathLength;
            }
        }

        return $matchedLanguageId;
    }
}
