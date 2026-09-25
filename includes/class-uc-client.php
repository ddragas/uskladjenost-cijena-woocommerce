<?php

declare(strict_types=1);

/** The API over the WordPress HTTP layer (wp_remote_request); no Composer needed at runtime. */
final class UC_Client
{
    public function __construct(private readonly string $token, private readonly string $baseUrl = 'https://uskladjenost-cijena.com', private readonly int $timeout = 15) {}

    public static function fromSettings(): ?self
    {
        $token = (string) get_option('uc_api_token', '');
        if ($token === '') {
            return null;
        }

        return new self($token, (string) (get_option('uc_base_url', '') ?: 'https://uskladjenost-cijena.com'));
    }

    /** @return array<string, mixed> */
    public function ping(): array
    {
        return $this->request('GET', '/api/v1/ping');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function upsertItem(string $externalId, array $data): array
    {
        return $this->request('PUT', '/api/v1/items/'.rawurlencode($externalId), $data);
    }

    /** @param list<array<string, mixed>> $items @return array<string, mixed> */
    public function bulkItems(array $items): array
    {
        return $this->request('POST', '/api/v1/items/bulk', ['items' => array_values($items)]);
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    public function recordPriceByExternal(string $externalId, array $event, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/api/v1/prices/by-external/'.rawurlencode($externalId), $event, $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey]);
    }

    /** @param list<array<string, mixed>> $events @return array<string, mixed> */
    public function bulkPrices(array $events): array
    {
        return $this->request('POST', '/api/v1/price-events/bulk', ['events' => array_values($events)]);
    }

    /** @param array<string, mixed> $scope @return array<string, mixed> */
    public function complianceByExternal(string $externalId, array $scope = []): array
    {
        return $this->request('GET', '/api/v1/compliance/by-external/'.rawurlencode($externalId).'?'.http_build_query(array_filter($scope)));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, string>  $headers
     * @return array<string, mixed> the decoded body; on refusal ['error' => ['code', 'message', 'status', 'details']]
     */
    public function request(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers + [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'uskladjenost-cijena-woocommerce/'.UC_WC_VERSION,
            ],
        ];
        if ($json !== null) {
            $args['body'] = wp_json_encode($json);
        }
        $response = wp_remote_request(rtrim($this->baseUrl, '/').$path, $args);
        if (is_wp_error($response)) {
            return ['error' => ['code' => 'transport', 'message' => $response->get_error_message(), 'status' => 0]];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = is_array($data) ? $data : [];
        if ($status >= 400) {
            $error = is_array($data['error'] ?? null) ? $data['error'] : [];

            return ['error' => ['code' => (string) ($error['code'] ?? 'http_'.$status), 'message' => (string) ($error['message'] ?? 'HTTP '.$status), 'status' => $status, 'details' => $error['details'] ?? []]];
        }
        $data['_status'] = $status;

        return $data;
    }
}
