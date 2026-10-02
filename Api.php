<?php

namespace Omnibus\ZtoExpress;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ZTO's open platform (japi.zto.com): one path per message type, the JSON
 * body signed with x-dataDigest = base64(md5(body + key)) and the company id
 * in x-companyId.
 */
final class Api
{
    public const LIVE = 'https://japi.zto.com';
    public const TEST = 'https://japi-test.zto.com';

    public function __construct(
        private readonly HttpClientInterface $http,
        public readonly string $companyId,
        private readonly string $key,
        public readonly ?string $partnerCode = null,
        public readonly bool $sandbox = false,
        private readonly int $timeout = 20,
    ) {
    }

    /** @return array<string, mixed> the result */
    public function call(string $msgType, array $data): array
    {
        $body = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        try {
            $response = $this->http->request('POST', ($this->sandbox ? self::TEST : self::LIVE).'/'.$msgType, [
                'headers' => ['Content-Type' => 'application/json;charset=UTF-8', 'x-companyId' => $this->companyId, 'x-dataDigest' => self::digest($body, $this->key)],
                'body' => $body,
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $envelope = json_decode($response->getContent(false), true);
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new CarrierException('zto_express', 'ZTO request failed: '.$e->getMessage(), null, $e);
        }
        if ($status >= 400 || !\is_array($envelope)) {
            throw new CarrierException('zto_express', sprintf('ZTO answered HTTP %d.', $status));
        }
        if (empty($envelope['status'])) {
            throw new CarrierException('zto_express', (string) ($envelope['message'] ?? $envelope['msg'] ?? 'ZTO refused the request.'), isset($envelope['statusCode']) ? (string) $envelope['statusCode'] : (isset($envelope['code']) ? (string) $envelope['code'] : null));
        }
        $result = $envelope['result'] ?? $envelope['data'] ?? [];

        return \is_array($result) ? $result : ['value' => $result];
    }

    public static function digest(string $body, string $key): string
    {
        return base64_encode(md5($body.$key, true));
    }
}
