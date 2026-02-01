
<?php

namespace raum51\ContaoZoteroBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use raum51\ContaoZoteroBundle\Raum51ContaoZoteroBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * @return array<int, BundleConfig>
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(Raum51ContaoZoteroBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }
}
