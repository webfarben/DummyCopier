<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\ContaoManager;

use Webfarben\DummyCopier\DummyCopierBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Bundle\Plugin\BundlePluginInterface;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(DummyCopierBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
