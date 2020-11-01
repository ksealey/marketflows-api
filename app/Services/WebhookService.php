<?php 
namespace App\Services;

use App;
use Exception;

class WebhookService
{
    public function sendWebhook($method, $url, $data = [])
    {
        if( ! $this->isValidWebhookURL($url) ){
            return (object)[
                'ok'            => false,
                'status_code'   => 500,
                'error'         => 'Invalid url structure', 
            ];
        }
        
        $ok         = false;
        $statusCode = 0;
        $error      = null;
        
        $components = parse_url($url); 

        parse_str($components['query']??'', $params);

        $data = array_merge($params, $data);
        $url  = $components['scheme'] . '://' . $components['host'] . ($components['path']??'');

        try{
            $client      = App::make('HTTPClient');
            $fieldsKey   = $method == 'GET' ? 'query' : 'form_params';
            $contentType = $method == 'GET' ? 'application/text' : 'application/x-www-form-urlencoded';  
            $response    = $client->request($method, $url, [
                'headers' => [
                    'X-Sender'     => 'MarketFlows',
                    'Content-Type' => $contentType
                ],
                $fieldsKey         => $data,
                'connect_timeout' => 5
            ]);
            $ok         = true;
            $statusCode = $response->getStatusCode();
        }catch(Exception $e){
            $statusCode = 500;
            $error      = 'Unknown error';
            if( method_exists($e, 'getResponse') ){
                $response   = $e->getResponse();
                if( $response ){
                    $statusCode = $response->getStatusCode();
                    $error      = $e->getResponse()->getBody();
                }
            }
        }

        return (object)[
            'ok'            => $ok,
            'status_code'   => $statusCode,
            'error'         => $error, 
        ];
    }

    public function isValidWebhookURL($url)
    {
        return preg_match('/^http(s)?:\/\/([0-9A-z\-]+\.)?[0-9A-z\.\-]+\.[0-9A-z]{2,10}/i', $url);
    }
}