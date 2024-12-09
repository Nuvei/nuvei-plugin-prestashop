<?php

namespace NuveiCheckout\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Nuvei
 */
class NuveiApiClient
{
    private HttpClientInterface $httpClient;
    
    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }
    
    /**
     * Send a POST request to the Nuvei API.
     *
     * @param string $endpoint The API endpoint.
     * @param array $data The data to send.
     * @return array|null The response data or null on error.
     */
    public function post(string $endpoint, array $data): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'json' => $data,
            ]);

            return $response->toArray();
        }
        catch (\Exception $e) {
            // Handle exception (e.g., log it)
            return null;
        }
    }

}
