<?php

namespace SigaClient;

use SigaClient\Exception\ContainerIdException;
use SigaClient\Exception\InvalidSigaParamException;
use SigaClient\Exception\SigaApiResponseException;
use SigaClient\Hashcode\HashcodeDataFile;
use SigaClient\Service\SigaApiClient;

/**
 * SiGa Client
 *
 */
class SigaClient
{
    const CONTAINER_TYPE_HASHCODE = 'HASHCODE';

    const CONTAINER_TYPE_ASIC = 'ASIC';

    /**
     * Container Id
     *
     * @var string
     */
    private $containerId;

    /**
     * SiGa adapter
     *
     * @var \SigaApiClient
     */
    private $sigaApiClient;

    /**
     * SiGa client is initiated
     */
    private function __construct(array $options = [])
    {
        $this->sigaApiClient = new SigaApiClient($options);
    }
    
    /**
     * Public factory method to create instance of Client.
     *
     * @param array $options Available properties: [
     *      'url' => 'xxx',
     *      'client' => 'xxx',
     *      'service' => 'xxx',
     *      'uuid' => 'xxx',
     *      'secret' => 'xxx',
     * ]
     *
     * @return SigaClient
     */
    public static function create(array $options = []) : SigaClient
    {
        return new self($options);
    }
    
    /**
     * Set container id
     *
     * @param string $containerId Container Id
     *
     * @return void
     */
    public function setContainerId(string $containerId) : void
    {
        $this->containerId = $containerId;
    }
    
    /**
     * Create SiGa container
     *
     * @param string $type Container type
     * @param array $files Files
     *
     * @throws InvalidSigaParamException
     *
     * @return string Container Id
     */
    public function createContainer(string $type, array $files) : string
    {
        if ($type === self::CONTAINER_TYPE_HASHCODE) {
            return $this->createHashcodeContainer($files);
        }
        
        throw new InvalidSigaParamException("Unknown container type");
    }

    /**
     * Create hascode container
     *
     * @param array $files Files
     *
     * @return string Container Id
     */
    private function createHashcodeContainer(array $files) : string
    {
        $body = [];
        foreach ($files as $file) {
            $body['dataFiles'][] = (new HashcodeDataFile($file['name'], $file['size'], $file['data']))->convert();
        }
        
        $requestResponse = $this->sigaApiClient->getClient()->request('POST', $this->sigaApiClient::HASHCODE_ENDPOINT, ['json' => $body]);
        
        $response = json_decode($requestResponse->getBody(), true);

        if ($requestResponse->getStatusCode() != 200) {
            throw new SigaApiResponseException($response['errorMessage']);
        }
        
        return $this->containerId = $response['containerId'];
    }
    
    public function prepareSigning(string $certicicateHex) : string
    {
        //TODO: Ask existing signatures(there was sample in SiGa java app)
        
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        $remoteSigningUri = $this->sigaApiClient->getSigaApiUri($this->sigaApiClient::HASHCODE_ENDPOINT, [$this->containerId, 'remotesigning']);

        $body = [
            'signingCertificate' => base64_encode(hex2bin($certicicateHex)),
            'signatureProfile' => $this->sigaApiClient::SIGNATURE_PROFILE_LT,
        ];

        $requestResponse = $this->sigaApiClient->getClient()->request('POST', $remoteSigningUri, ['json' => $body]);
       
        $response = json_decode($requestResponse->getBody(), true);
        
        if ($requestResponse->getStatusCode() != 200) {
            throw new SigaApiResponseException($response['errorMessage']);
        }
        
        return $this->sigaApiClient->returnJson([
            'dataToSign' => $response['dataToSign'],
            'dataToSignHash' => base64_encode(hash($response['digestAlgorithm'], base64_decode($response['dataToSign']), true)),
            'digestAlgorithm' => $response['digestAlgorithm'],
            'generatedSignatureId' => $response['generatedSignatureId'],
        ]);
    }
    
    public function finalizeSigning(string $signatureId, string $signatureHex)
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }
        
        $response = $this->sigaApiClient->finalizeContainerRemoteSigning($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId, $signatureId, $signatureHex);
        
        if ($response['result'] === $this->sigaApiClient::RESULT_OK) {
            $this->endContainerFlow();
        } else {
            throw new SigaApiResponseException($response['errorMessage']);
        }
    }
    
    private function endContainerFlow()
    {
        $this->doContainerValidation();
        
        //TODO: This response is going to be zip file with meta-inf etc
        $container = $this->sigaApiClient->getContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);

        //TODO: Remove tmp uploaded files
        $this->deleteContainer();
    }
    
    /**
     * Validate container
     *
     * @throws SigaApiResponseException
     *
     * @return void
     */
    private function doContainerValidation() : void
    {
        $response = $this->sigaApiClient->validateContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);

        if ($response['validationConclusion']['validSignaturesCount'] != $response['validationConclusion']['signaturesCount']) {
            throw new SigaApiResponseException('One of signatures is not valid!');
        }
    }
    
    /**
     * Delete SiGa containter
     *
     * @return void
     */
    private function deleteContainer()
    {
        $this->sigaApiClient->deleteContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);
    }
}
