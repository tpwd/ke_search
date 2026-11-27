<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();

// Replace the deprecated "@PER-CS1.0" rule set (still used by typo3/coding-standards) with "@PER-CS1x0"
$rules = [];
foreach ($config->getRules() as $rule => $ruleConfig) {
    $rules[$rule === '@PER-CS1.0' ? '@PER-CS1x0' : $rule] = $ruleConfig;
}
$config->setRules($rules);

$config->getFinder()
    ->in(__DIR__)
;

return $config;
