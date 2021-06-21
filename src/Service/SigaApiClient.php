<?php

namespace SigaClient\Service;

use SigaClient\Exception\InvalidSigaParamException;
use SigaClient\Exception\SigaApiResponseException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

class SigaApiClient
{
    /**
     * ASIC container endpoint.
     *
     * @var string
     */
    const ASIC_ENDPOINT = 'containers';
    
    /**
     * HASCODE container endpoint.
     *
     * @var string
     */
    const HASHCODE_ENDPOINT = 'hashcodecontainers';
    
    
    const RESULT_OK = 'OK';
    
    /**
     * Profile of the signature.
     *
     * LT - TimeStamp based
     * LT_TM - TimeMark based
     *
     * @var string
     */
    const SIGNATURE_PROFILE_LT = 'LT';
    
    /**
     * SiGa endpoint API url.
     *
     * @var string
     */
    private $url;

    /**
     * SiGa Client name.
     *
     * @var string
     */
    private $name;

    /**
     * SiGa Service name.
     *
     * @var string
     */
    private $service;

    /**
     * SiGa UUID.
     *
     * @var string
     */
    private $uuid;

    /**
     * SiGa secret key.
     *
     * @var string
     */
    private $secret;
    
    /**
     * SiGa Guzzle client
     *
     * @var \SigaGuzzleClient
     */
    private $client;
    
    /**
     * Last guzzle response
     *
     * @var \Response|null
     */
    private $lastResponse = null;
    
    /**
     * @param array $sigaOptions Siga configuration settings.
     *
     * @return void
     */
    public function __construct(array $sigaOptions)
    {
        $this->validateOptions($sigaOptions);
        $this->prepareOptions($sigaOptions);
        
        $this->client = new SigaGuzzleClient($sigaOptions);
    }
    
    /**
     * Validate options
     *
     * @param array $options
     *
     * @return void
     *
     * @throws InvalidSigaParamException If some required params are missing
     */
    private function validateOptions(array $options)
    {
        if (empty($options['url'])) {
            throw new InvalidSigaParamException('SiGa endpoint url is missing');
        }
        if (empty($options['name'])) {
            throw new InvalidSigaParamException('SiGa client name is missing');
        }
        if (empty($options['service'])) {
            throw new InvalidSigaParamException('SiGa service name is missing');
        }
        if (empty($options['uuid'])) {
            throw new InvalidSigaParamException('SiGa UUID is missing');
        }
        if (empty($options['secret'])) {
            throw new InvalidSigaParamException('SiGa secret key is missing');
        }
    }
    
    /**
     * Read options from array and set as object property
     *
     * @param array $options
     *
     * @return void
     */
    private function prepareOptions(array $options)
    {
        $this->url = (string) $options['url'];
        $this->name = (string) $options['name'];
        $this->service = (string) $options['service'];
        $this->uuid = (string) $options['uuid'];
        $this->secret = (string) $options['secret'];
    }
    
    /**
     * Get Siga Guzzle client
     *
     * @return SigaGuzzleClient
     */
    public function guzzle() : SigaGuzzleClient
    {
        return $this->client;
    }
    
    /**
     * Generate Siga Api Uri
     *
     * @param string $containerEndpoint
     * @param array $pathSegments
     *
     * @return string Api uri
     */
    public function getSigaApiUri(string $containerEndpoint, array $pathSegments = []) : string
    {
        return  $this->url.'/'.$containerEndpoint.'/'.implode('/', $pathSegments);
    }
    
    /**
     * Create hascode container
     *
     * @param array $body Request body params
     *
     * @return array Response
     */
    public function createHascodeContainer(array $body) : array
    {
        $requestResponse = $this->client->post(self::HASHCODE_ENDPOINT, ['json' => $body]);
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Upload container
     *
     * @param string $file Base 64 encoded file content
     *
     * @return array Response
     */
    public function uploadContainer(string $file) : array
    {
        $uri = $this->getSigaApiUri('upload', [self::HASHCODE_ENDPOINT]);
        
        $body = [
            'container' => $file,
        ];

        $requestResponse = $this->client->post($uri, ['json' => $body]);
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Start remote signing process
     *
     * @param string $containerId Container Id
     * @param string $certicicateHex Certificate in hex format
     *
     * @return string Prepared parts
     */
    public function startSigning(string $containerId, string $certicicateHex) : array
    {
        $uri = $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'remotesigning']);

        $body = [
            'signingCertificate' => base64_encode(hex2bin($certicicateHex)),
            'signatureProfile' => self::SIGNATURE_PROFILE_LT,
        ];

        $requestResponse = $this->client->post($uri, ['json' => $body]);
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Start mobile signing process
     *
     * @link @https://github.com/open-eid/SiGa/wiki/Hashcode-API-description#start-mobile-id-signing
     *
     * @param string $containerId Container Id
     * @param array $requestParams Mobile Id request params.
     *
     * @return array Response
     */
    public function startMobileSigning(string $containerId, array $requestParams) : array
    {
        $requestParams['signatureProfile'] = self::SIGNATURE_PROFILE_LT;
        
        $requestResponse = $this->client->post(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'mobileidsigning']),
            ['json' => $requestParams]
        );
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Get mobile ID signing status
     *
     * @param string $containerId Container Id
     * @param string $signatureId Signature Id
     *
     * @return array Response
     */
    public function getMobileSigningStatus(string $containerId, string $signatureId) : array
    {
        $requestResponse = $this->client->get(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'mobileidsigning', $signatureId, 'status'])
        );
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Finalize container remote signing
     *
     * @param string $containerEndpoint Container endpoint
     * @param string $containerId Container Id
     * @param string $signatureId Signature Id
     * @param string $signatureHex Signature Hex
     *
     * @return array Finalization request response
     */
    public function finalizeContainerRemoteSigning(string $containerEndpoint, string $containerId, string $signatureId, string $signatureHex) : array
    {
        $body = [
            'signatureValue' => base64_encode(hex2bin($signatureHex)),
        ];

        $requestResponse = $this->client->put(
            $this->getSigaApiUri($containerEndpoint, [$containerId, 'remotesigning', $signatureId]),
            ['json' => $body]
        );
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Validate container
     *
     * @param string $containerId Container Id
     *
     * @return array Validation response
     */
    public function getContainerValidation(string $containerId) : array
    {
        $requestResponse = $this->client->get($this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'validationreport']));
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Delete container
     *
     * @param string $containerId Container Id
     *
     * @return ResponseInterface
     */
    public function deleteContainer(string $containerId) : ResponseInterface
    {
        return $this->client->delete($this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId]));
    }
    
    /**
     * Get signed container
     *
     * @param string $containerEndpoint Container endpoint
     * @param string $containerId Container Id
     *
     * @return array Base64 encoded container
     */
    public function getContainer(string $containerEndpoint, string $containerId) : array
    {
        $requestResponse = $this->client->get($this->getSigaApiUri($containerEndpoint, [$containerId]));
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Get signed container data files list
     *
     * @param string $containerId Container Id
     *
     * @return array Files list
     */
    public function getContainerFiles(string $containerId) : array
    {
        $requestResponse = $this->client->get($this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'datafiles']));
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Get signed container signatures list
     *
     * @param string $containerId Container Id
     *
     * @return array Signatures list
     */
    public function getContainerSignatures(string $containerId) : array
    {
        $requestResponse = $this->client->get($this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'signatures']));
        
        return $this->decodeResponse($requestResponse);
    }
    
    /**
     * Get signed container signature
     *
     * @param string $containerId Container Id
     * @param string $signatureId Signature Id
     *
     * @return array Signature info
     */
    public function getSignatureInfo(string $containerId, string $signatureId) : array
    {
        $requestResponse = $this->client->get($this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'signatures', $signatureId]));
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Json decode guzzle request
     *
     * @param Response $response
     *
     * @throws SigaApiResponseException If there is errorMessage
     *
     * @return array Query response
     */
    private function decodeResponse(Response $response) : array
    {
        $this->lastResponse = $response;

        $responseBody = json_decode($this->lastResponse->getBody(), true);
        
        if (isset($responseBody['errorMessage'])) {
            throw new SigaApiResponseException($responseBody['errorMessage'], $this->lastResponse->getStatusCode());
        }

        return $responseBody;
    }
    
    /**
     * Get ast guzzle request response
     *
     * @return Response|null
     */
    public function getLastResponse() : ?Response
    {
        return $this->lastResponse;
    }

    /**
     * Get Smart-ID certificate choice
     *
     * @param string $containerId Container Id
     * @param array $requestParams Smart-ID request params.
     *
     * @return array Response
     */
    public function getSmartIdCertificateChoice(string $containerId, array $requestParams): array
    {
        $requestResponse = $this->client->post(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'smartidsigning', 'certificatechoice']),
            ['json' => $requestParams]
        );
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Get Smart ID certificate choice status
     *
     * @param string $containerId Container Id
     * @param string $certificate Certificate
     *
     * @return array Response
     */
    public function getSmartIdCertificateChoiceStatus(string $containerId, string $certificate): array
    {
        $requestResponse = $this->client->get(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'smartidsigning', 'certificatechoice', $certificate, 'status'])
        );
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Start Smart-ID signing process
     *
     * @link @https://github.com/open-eid/SiGa/wiki/Hashcode-API-description#smart-id-signing
     *
     * @param string $containerId Container Id
     * @param array $requestParams Mobile Id request params.
     *
     * @return array Response
     */
    public function startSmartIdSigning(string $containerId, array $requestParams) : array
    {
        $requestParams['signatureProfile'] = self::SIGNATURE_PROFILE_LT;
        
        $requestResponse = $this->client->post(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'smartidsigning']),
            ['json' => $requestParams]
        );
        
        return $this->decodeResponse($requestResponse);
    }

    /**
     * Get Smart-ID signing status
     *
     * @param string $containerId Container Id
     * @param string $signatureId Signature Id
     *
     * @return array Response
     */
    public function getSmartIdSigningStatus(string $containerId, string $signatureId) : array
    {
        $requestResponse = $this->client->get(
            $this->getSigaApiUri(self::HASHCODE_ENDPOINT, [$containerId, 'smartidsigning', $signatureId, 'status'])
        );
        
        return $this->decodeResponse($requestResponse);
    }
}
