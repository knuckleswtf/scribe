<?php

$finder = PhpCsFixer\Finder::create()->in([
    __DIR__ . DIRECTORY_SEPARATOR . 'src',
    __DIR__ . DIRECTORY_SEPARATOR . 'tests',
    __DIR__ . DIRECTORY_SEPARATOR . 'camel',
]);

$config = new PhpCsFixer\Config();

return $config->setRules([
    // PSRs
    '@PHP80Migration' => true,
    '@PSR12' => true,
])->setFinder($finder);