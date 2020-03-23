<?php

namespace Magmell\Contao\InstagramNews\ContaoManager;

use Codefog\InstagramBundle\CodefogInstagramBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Magmell\Contao\InstagramNews\InstagramNewsBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(InstagramNewsBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class,
                    CodefogInstagramBundle::class
                ])
        ];
    }
}
