<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class InsightsClient
{
    private $httpClient;

    private $endpoint = 'http://localhost:3000';

    public function request($method, $path, $params = [], $headers = [])
    {
        $request = $method == 'GET' ? ['query' => $params] : ['form_params' => $params];

        $request['headers'] = $headers;

        return $this->client()->request($method, $this->endpoint . '/' . trim($path, '/'), [
            'query'   => $params,
            'headers' => $params,
        ]);

        return json_decode($response->getBody());
    }

    public function client()
    {
        if( ! $this->httpClient )
            $this->httpClient = new Client();

        return $this->httpClient;
    }

    public function session()
    {
        //
        //  Crate over http
        //
        $response = $this->request('POST', '/sessions');

        if( $response->getStatusCode() !== 201 )
            return null;

        return json_decode($response->getBody(), true);
    }

}