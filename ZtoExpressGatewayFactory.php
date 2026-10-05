<?php

namespace Omnibus\ZtoExpress;

use Omnibus\Config;
use Omnibus\GatewayFactory;
use Omnibus\ZtoExpress\Action\CancelAction;
use Omnibus\ZtoExpress\Action\ShippingAction;
use Omnibus\ZtoExpress\Action\TrackingAction;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     company_id: '%env(ZTO_COMPANY_ID)%'    # the open platform's company id
 *     key: '%env(ZTO_KEY)%'                  # its key
 *     partner_code: null                     # the 合作商编码 for electronic waybills
 *     sandbox: true
 *     rates: [...]                           # prices from configuration: ZTO quotes by contract
 *
 * No pickup points. Unverified until an account's keys are at hand.
 */
final class ZtoExpressGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'zto_express',
            'omnibus.factory_title' => 'ZTO Express',
            'omnibus.required_options' => ['company_id', 'key'],
            'partner_code' => null,
            'sandbox' => false,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? HttpClient::create();

                return new Api($http, (string) $c['company_id'], (string) $c['key'], $c['partner_code'] ?: null, (bool) $c['sandbox']);
            },
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
            'omnibus.action.cancel' => new CancelAction(),
        ]);
    }
}
