<?php

namespace Omnibus\ZtoExpress;

use Omnibus\Core\GatewayFactory;

class ZtoExpressGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'zto-express',
            'omnibus.factory_title' => 'ZTO Express'
        ]);
    }
}