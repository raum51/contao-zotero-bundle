
<?php

namespace raum51\ContaoZoteroBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ZoteroApiClient
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    private function baseUrl(string $type, string $libraryId): string
    {
        // $type: 'user' or 'group'
        return sprintf('https://api.zotero.org/%ss/%s', $type, $libraryId);
    }

    public function fetchItems(string $type, string $libraryId, string $apiKey, array $params = []): array
    {
        $url = $this->baseUrl($type, $libraryId) . '/items';
        $headers = [
            'Zotero-API-Key' => $apiKey,
            'Accept' => 'application/json'
        ];
        $response = $this->httpClient->request('GET', $url, ['headers' => $headers, 'query' => $params]);
        return $response->toArray(false);
    }

    public function fetchCollections(string $type, string $libraryId, string $apiKey, array $params = []): array
    {
        $url = $this->baseUrl($type, $libraryId) . '/collections';
        $headers = [
            'Zotero-API-Key' => $apiKey,
            'Accept' => 'application/json'
        ];
        $response = $this->httpClient->request('GET', $url, ['headers' => $headers, 'query' => $params]);
        return $response->toArray(false);
    }
}
