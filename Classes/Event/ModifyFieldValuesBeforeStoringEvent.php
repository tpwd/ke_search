<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Event;

class ModifyFieldValuesBeforeStoringEvent
{
    private array $indexerConfig;
    private array $fieldValues;

    public function __construct(array $indexerConfig, array $fieldValues)
    {
        $this->indexerConfig = $indexerConfig;
        $this->fieldValues = $fieldValues;
    }

    /**
     * @return array
     */
    public function getFieldValues(): array
    {
        return $this->fieldValues;
    }

    /**
     * @param array $fieldValues
     */
    public function setFieldValues(array $fieldValues): void
    {
        $this->fieldValues = $fieldValues;
    }

    /**
     * @return array
     */
    public function getIndexerConfig(): array
    {
        return $this->indexerConfig;
    }
}
