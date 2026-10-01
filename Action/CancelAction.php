<?php

namespace Omnibus\ZtoExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;
use Omnibus\ZtoExpress\Api;

/** zto.open.cancelOrder, by the bill code. */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $data = $this->api->call('zto.open.cancelOrder', ['billCode' => $request->trackingNumber, 'reason' => 'cancelled by the shipper']);
        $request->setResult(!isset($data['value']) || \in_array($data['value'], [true, 1, '1', 'true'], true));
    }
}
