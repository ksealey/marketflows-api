<?php 
namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use \App\Models\Company;
use \App\Models\Company\WebhookCall;
use Exception;

trait FiresWebhooks
{
    protected $httpClient;

    /**
     * Make an http request
     * 
     * @param string $method    The HTTP request method
     * @param string $url       The HTTP request url
     * @param array  $params    The HTTP request parameters
     * @param array  $headers   The HTTP request headers
     * 
     * @return void
     */
    public function request(string $method, string $url, array $params = [], array $headers = [])
    {
        if( ! $this->httpClient )
            $this->httpClient = new Client();

        $request = $method == 'GET' ? ['query' => $params] : ['form_params' => $params];

        $request['headers'] = $headers;

        return $this->httpClient->request($method, $url, $request);
    }

    /**
     * Fire a company's webhook
     * 
     * @param Company   $company        The company we're firing this webhook for
     * @param string    $webhookId      The webhook's identifier
    * @param int        $resourceId     The associated resource's id
     * @param callable  $data           A callable variable used to get the data that will be posted
     * @param mixed     $onSuccess      Callable to be executed on successful request
     * @param mixed     $onError        Callable to be executed on request error
     * 
     * @return boolean
     */
    public function fireWebhook(Company $company, string $webhookActionId, int $resourceId, callable $data, ?callable $onSuccess = null, ?callable $onError = null)
    {
        //  Determine if we should even call this
        $webhookActions = json_decode($company->webhook_actions, true);

        $webhookAction = $webhookActions[$webhookActionId];
        if( ! $webhookAction['url'] ){ // There is no URL so there's nothing to do
            if( $onSuccess )
                $onSuccess();
            return true;
        }

        //  Get request data
        $data       = $data($company);
        $error      = '';
        $statusCode = null;

        try{
            $response   = $this->request($webhookAction['method'], $webhookAction['url'], $data);
            $statusCode = $response->getStatusCode();
        }catch(RequestException $e){
            if( $e->hasResponse() ){
                $response   = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $error      = $response->getReasonPhrase();
            }else{
                $statusCode = 500;
                $error      = 'An uknown error has occurred';
            }
        }catch(Exception $e){
            $statusCode = 500;
            $error      = 'An uknown error has occurred';
        }

        $this->logWebhookCall(
            $company, 
            $webhookActionId, 
            $resourceId,
            $webhookAction['method'],
            $webhookAction['url'],
            $statusCode,
            $error
        );

        if( $error ){
            if( $onError )
                $onError($error, $statusCode);
            return false;
        }

        if( $onSuccess )
            $onSuccess();
        return true;
    }

    /**
     * Log a webhook call
     * 
     * @param Company   $company        The company we're firing this webhook for
     * @param string    $webhookId      The webhook's identifier
     * @param int       $resourceId     The associated resource's id
     * @param string    $method         The HTTP request method
     * @param string    $url            The HTTP request url
     * @param int       $statusCode     The HTTP response status code
     * @param string    $error          The HTTP response error
     * 
     * @return boolean 
     */
    public function logWebhookCall(Company $company, string $webhookActionId, int $resourceId, string $method, string $url, int $statusCode, $error = null)
    {
        return WebhookCall::create([
            'company_id'        => $company->id,
            'webhook_action_id' => $webhookActionId,
            'resource_id'       => $resourceId,
            'method'            => $method,
            'url'               => $url,
            'status_code'       => $statusCode,
            'error'             => $error
        ]);
    }
}   