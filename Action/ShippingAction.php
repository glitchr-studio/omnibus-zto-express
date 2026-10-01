<?php

namespace Omnibus\ZtoExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;
use Omnibus\ZtoExpress\Api;

/** zto.open.createOrder: the order with an electronic waybill (partner code), its bill code; the label as ZTO's print data. */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $data = $this->api->call('zto.open.createOrder', array_filter([
            'partner' => $this->api->partnerCode,
            'id' => $s->reference ?? 'omnibus-'.bin2hex(random_bytes(6)),
            'type' => (int) $s->option('type', 1),
            'tradeId' => $s->reference,
            'orderSum' => array_sum(array_map(static fn ($p) => $p->value ?? 0, $s->parcels)) / 100,
            'sender' => self::party($s->sender),
            'receiver' => self::party($s->recipient),
            'items' => array_map(static fn ($p) => ['name' => $s->option('description', '商品'), 'quantity' => 1, 'weight' => round(max(0.1, $p->weight / 1000), 2)], $s->parcels),
            'weight' => round(max(0.1, $s->weight() / 1000), 2),
            'remark' => mb_substr((string) $s->option('instructions', ''), 0, 100),
        ], static fn ($v) => null !== $v && '' !== $v));
        $number = (string) ($data['billCode'] ?? $data['mailNo'] ?? '');
        if ('' === $number) {
            throw new CarrierException('zto-express', 'ZTO issued no bill code.');
        }
        $print = isset($data['printData']) ? json_encode($data['printData'], \JSON_UNESCAPED_UNICODE) : null;
        $request->setResult(new Label('zto-express', $number, $print ?: null, 'application/json', null, 'https://www.zto.com/express/expressCheck.html?txtbill='.rawurlencode($number)));
    }

    private static function party(Address $a): array
    {
        return array_filter(['name' => $a->name, 'company' => $a->company, 'mobile' => $a->phone, 'phone' => $a->phone, 'province' => $a->line(2) ?: null, 'city' => $a->city, 'district' => $a->line(1) ?: null, 'address' => $a->line(0), 'zipCode' => $a->postcode], static fn ($v) => null !== $v && '' !== $v);
    }
}
