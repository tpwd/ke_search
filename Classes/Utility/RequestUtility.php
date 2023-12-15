<?php

namespace Tpwd\KeSearch\Utility;

use Psr\Http\Message\ServerRequestInterface;

class RequestUtility
{
    /**
     * @param ServerRequestInterface $request
     * @param $paramName
     * @return array|string|null
     */
    public static function getQueryParam(ServerRequestInterface $request, $paramName)
    {
        if (empty($paramName)) {
            return null;
        }

        $queryParams = $request->getQueryParams();
        $value = $queryParams[$paramName] ?? null;

        // This is there for backwards-compatibility, in order to avoid NULL
        if (isset($value) && !is_array($value)) {
            $value = (string)$value;
        }
        return $value;
    }
}
