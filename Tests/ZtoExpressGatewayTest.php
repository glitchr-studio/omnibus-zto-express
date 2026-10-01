<?php

namespace Omnibus\ZtoExpress\Tests;

use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;
use Omnibus\ZtoExpress\Api;
use Omnibus\ZtoExpress\ZtoExpressGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ZtoExpressGatewayTest extends TestCase
{
    private array $calls = [];

    private static function shipment(): Shipment
    {
        return new Shipment(new Address('张三', ['深南大道1000号', '福田区', '广东省'], '518000', '深圳市', 'CN', phone: '13800000000'), new Address('李四', ['建国路88号', '朝阳区', '北京市'], '100022', '北京市', 'CN', phone: '13900000000'), [new Parcel(1500, 30, 20, 10, 20000, 'CNY')], reference: 'ORDER-1042');
    }

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertStringStartsWith('https://japi-test.zto.com/', $url);
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$path, (string) $options['body'], json_decode((string) $options['body'], true), $options['headers']];

            return match ($path) {
                '/zto.open.createOrder' => new MockResponse(json_encode(['status' => true, 'result' => ['orderCode' => 'ORDER-1042', 'billCode' => '75123456789012', 'printData' => ['bigMark' => '北京', 'mark' => '朝阳']]])),
                '/zto.open.getTraceInfo' => new MockResponse(json_encode(['status' => true, 'result' => [[['billCode' => '75123456789012', 'scanType' => '签收', 'scanDate' => '2026-10-02 11:30:00', 'scanSite' => '北京朝阳', 'desc' => '快件已签收'], ['billCode' => '75123456789012', 'scanType' => '收件', 'scanDate' => '2026-10-01 17:00:00', 'scanSite' => '深圳福田', 'desc' => '快件已收件']]]])),
                '/zto.open.cancelOrder' => new MockResponse(json_encode(['status' => true, 'result' => true])),
                default => new MockResponse(json_encode(['status' => false, 'statusCode' => 'S404', 'message' => 'unknown'])),
            };
        });

        return (new ZtoExpressGatewayFactory($http))->create(['company_id' => 'glitchr', 'key' => 'k', 'partner_code' => 'P001', 'sandbox' => true]);
    }

    public function testAnOrderIsCreatedWithItsBillCodeAndSigned(): void
    {
        $label = $this->gateway()->ship(self::shipment());
        self::assertSame('75123456789012', $label->trackingNumber);
        self::assertStringContainsString('北京', (string) $label->content);
        [, $body, $sent, $headers] = $this->calls[0];
        self::assertContains('x-companyId: glitchr', $headers);
        self::assertContains('x-dataDigest: '.Api::digest($body, 'k'), $headers);
        self::assertSame('P001', $sent['partner']);
        self::assertSame('广东省', $sent['sender']['province']);
        self::assertSame('朝阳区', $sent['receiver']['district']);
    }

    public function testTrackingAndCancel(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('75123456789012', 'zh');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame(TrackingStatus::IN_TRANSIT, $tracking->events[0]->status);
        self::assertSame('北京朝阳', $tracking->latest()->location);
        self::assertTrue($gateway->cancel('75123456789012'));
    }

    public function testARefusalIsRaisedWithItsCode(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['status' => false, 'statusCode' => 'S102', 'message' => '签名错误'])));
        try {
            (new ZtoExpressGatewayFactory($http))->create(['company_id' => 'a', 'key' => 'b'])->track('751');
            self::fail('raised');
        } catch (CarrierException $e) {
            self::assertSame('S102', $e->carrierCode);
        }
    }
}
