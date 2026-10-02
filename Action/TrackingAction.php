<?php

namespace Omnibus\ZtoExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;
use Omnibus\ZtoExpress\Api;

/** zto.open.getTraceInfo: the bill's traces, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('zto.open.getTraceInfo', ['billCodes' => [$request->trackingNumber]]);
        $traces = $data['value'] ?? $data;
        $traces = isset($traces[0]) && \is_array($traces[0]) && !isset($traces[0]['scanType']) ? $traces[0] : $traces;
        $events = [];
        foreach ($traces as $t) {
            if (!\is_array($t) || empty($t['scanDate'])) {
                continue;
            }
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) $t['scanDate'], new \DateTimeZone('Asia/Shanghai')), self::status($t['scanType'] ?? null, $t['desc'] ?? null), (string) ($t['desc'] ?? $t['scanType'] ?? ''), $t['scanSite'] ?? $t['scanCity'] ?? null, $t['scanType'] ?? null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $request->setResult(new TrackingModel('zto_express', $request->trackingNumber, $events ? $events[array_key_last($events)]->status : TrackingStatus::UNKNOWN, $events));
    }

    private static function status(?string $type, ?string $desc): TrackingStatus
    {
        $d = (string) $desc;

        return match (true) {
            '签收' === $type || str_contains($d, '已签收') || str_contains($d, 'delivered') => TrackingStatus::DELIVERED,
            '派件' === $type || str_contains($d, '派件') || str_contains($d, 'out for delivery') => TrackingStatus::OUT_FOR_DELIVERY,
            '退回' === $type || str_contains($d, '退回') || str_contains($d, 'return') => TrackingStatus::RETURNED,
            '问题件' === $type || str_contains($d, '问题件') || str_contains($d, '异常') || str_contains($d, 'exception') => TrackingStatus::EXCEPTION,
            '收件' === $type || str_contains($d, '已收件') || str_contains($d, 'collected') => TrackingStatus::IN_TRANSIT,
            \in_array($type, ['发件', '到件', '到达', '发出'], true) || '' !== $d => TrackingStatus::IN_TRANSIT,
            default => TrackingStatus::UNKNOWN,
        };
    }
}
