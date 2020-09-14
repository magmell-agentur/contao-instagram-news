<?php

$GLOBALS['TL_CRON']['hourly'][] = ['Magmell\Contao\InstagramNews\InstagramNewsImporter', 'run'];

if ($_COOKIE['XDEBUG_SESSION'] === 'PHPSTORM_MARKO')
{
    $GLOBALS['TL_HOOKS']['generatePage'][] = ['Magmell\Contao\InstagramNews\InstagramNewsImporter', 'run'];
}
