<?php

namespace SigaClient\Service;

use Psr\Http\Message\RequestInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Middleware;

/**
 * Siga client service for GuzzleHttp client
 */
class SigaGuzzleClient extends Guzzle
{

    /**
     * @param array $sigaOptions Siga configuration settings.
     * @param array $config Client configuration settings.
     *
     * @return void
     */
    public function __construct(array $sigaOptions, array $config = []) : void
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('X-Authorization-Timestamp', time());
        }));
        
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($sigaOptions) {
            return $request->withHeader('X-Authorization-ServiceUUID', $sigaOptions['uuid']);
        }));

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($sigaOptions) {
            $headers = $request->getHeaders();

            $signature  = $headers['X-Authorization-ServiceUUID'][0].':';
            $signature .= $headers['X-Authorization-Timestamp'][0].':';
            $signature .= (string)$request->getMethod().':';
            $signature .= (string)$request->getUri()->getPath().':';
            $signature .= (string)$request->getBody();
            
            return $request->withHeader('X-Authorization-Signature', hash_hmac('sha256', $signature, $sigaOptions['secret']));
        }));

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('X-Authorization-Hmac-Algorithm', 'HmacSHA256');
        }));

        parent::__construct([
            'base_uri' => $sigaOptions['url'],
            'handler' => $stack,
        ]);
    }
}
