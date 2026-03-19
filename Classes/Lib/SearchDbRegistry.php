<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Lib;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Domain\Search\SearchContextInterface;

/***************************************************************
 *  Copyright notice
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 ***************************************************************/

/**
 * Provides one shared Db instance per frontend request so that the search plugin
 * and result list plugin (and any other plugins on the same page) reuse the same
 * search execution and results. The expensive database search runs only once per request.
 *
 * Not used by SearchService (API) – that creates its own Db instance so API searches
 * do not overwrite the plugin results.
 */
class SearchDbRegistry
{
    /** @var array<int, Db> */
    private static array $dbByRequestId = [];

    /**
     * Returns the Db instance for the given request. Creates and stores one if none exists yet.
     * Subsequent calls with the same request (e.g. from result list plugin after searchbox plugin)
     * receive the same instance, so the search is executed only once.
     */
    public static function getDbForRequest(
        ServerRequestInterface $request,
        EventDispatcherInterface $eventDispatcher,
        SearchContextInterface $context
    ): Db {
        $id = spl_object_id($request);
        if (!isset(self::$dbByRequestId[$id])) {
            $db = new Db($eventDispatcher);
            $db->setSearchContext($context);
            self::$dbByRequestId[$id] = $db;
        }
        return self::$dbByRequestId[$id];
    }
}
