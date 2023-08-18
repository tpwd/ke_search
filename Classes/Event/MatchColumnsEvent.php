<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Event;

class MatchColumnsEvent
{
    private string $matchColumns;

    public function __construct(string $matchColumns)
    {
        $this->matchColumns = $matchColumns;
    }

    /**
     * @return string
     */
    public function getMatchColumns(): string
    {
        return $this->matchColumns;
    }

    /**
     * @param string $matchColumns
     */
    public function setMatchColumns(string $matchColumns): void
    {
        $this->matchColumns = $matchColumns;
    }
}
