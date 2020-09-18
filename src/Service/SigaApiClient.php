<?php

namespace SigaClient\Service;

use SigaClient\Exception\InvalidSigaParamException;

class SigaApiClient
{
    const ASIC_ENDPOINT = 'containers';
    const HASHCODE_ENDPOINT = 'hashcodecontainers';
    const RESULT_OK = 'OK';
    
    /**
     * Profile of the signature. Available values LT - TimeStamp based
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
    public function getClient() : SigaGuzzleClient
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
     * Return json header
     *
     * @param array $output
     *
     * @return string
     */
    public function returnJson(array $output = []) : string
    {
        header('Content-Type: application/json');

        return json_encode($output);
    }
}
