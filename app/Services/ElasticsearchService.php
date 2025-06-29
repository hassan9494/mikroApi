<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ElasticsearchService
{
    protected $client;
    protected $baseUri;
    protected $auth;

    public function __construct()
    {

        $this->baseUri = config('scout.elasticsearch.host');
        $this->auth = [
            config('scout.elasticsearch.user'),
            config('scout.elasticsearch.password')
        ];

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'auth' => $this->auth,
            'verify' => false, // Disable SSL verification
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    public function indexDocument($index, $id, array $document)
    {
        $uri = "/{$index}/_doc/{$id}";

        try {
            $response = $this->client->put($uri, [
                'json' => $document
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? (string) $e->getResponse()->getBody() : null
            ];
        }
    }

    public function search($index, array $query)
    {
        $uri = "/{$index}/_search";

        try {
            $response = $this->client->get($uri, [
                'json' => $query
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? (string) $e->getResponse()->getBody() : null
            ];
        }
    }
}
