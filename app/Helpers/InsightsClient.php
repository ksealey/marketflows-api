<?php
namespace App\Helpers;

class InsightsClient
{
    private $httpClient;

    private $endpoint = 'insights.marketflows.io';

    public function request($method, $path, $params = [], $headers = [])
    {
        $request = $method == 'GET' ? ['query' => $params] : ['form_params' => $params];

        $request['headers'] = $headers;

        $this->httpClient->request($method, $this->endpoint . '/' . trim($path, '/'), [
            'query'   => $params,
            'headers' => $params,
        ]);
    }

    public function client()
    {
        if( ! $this->httpClient )
            $this->httpClient = new Client();

        return $this->httpClient;
    }

    public function session($params)
    {
        //
        //  Store over http
        //  ...
        //  
        
        return $params;
    }

}