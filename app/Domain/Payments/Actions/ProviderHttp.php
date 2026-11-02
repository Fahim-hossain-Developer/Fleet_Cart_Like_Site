<?php

namespace App\Domain\Payments\Actions;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ProviderHttp
{
    public function request(): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(35)->withoutRedirecting()->acceptJson();
    }

    /**
     * @param callable(PendingRequest):Response $operation
     * @return array<string,mixed> */
    public function json(string $driver, callable $operation): array
    {
        try {
            $response = $operation($this->request());
        } catch (\Throwable $e) {
            throw new IntegrationFailure($driver, 'transport_unknown');
        }
        if (! $response->successful()) {
            throw new IntegrationFailure($driver, $response->status() >= 500 ? 'provider_unavailable' : 'provider_rejected');
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new IntegrationFailure($driver, 'invalid_response');
        }

        return $data;
    }
}
