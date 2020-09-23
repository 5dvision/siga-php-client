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
    /**
     * HASCODE container type
     *
     * @var string
     */
    const CONTAINER_TYPE_HASHCODE = 'HASHCODE';

    /**
     * ASIC container type
     *
     * @var string
     */
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
     * @throws SigaApiResponseException
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
    
    /**
     * Prepare signing process
     *
     * @param string $certicicateHex Certificate in hex format
     *
     * @throws ContainerIdException If containerId is missing
     * @throws SigaApiResponseException If remotesigning response is not with header 200
     *
     * @return string json encoded array
     */
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
    
    /**
     * Finalize signing process
     *
     * @param string $signatureId Signature Id
     * @param string $signatureHex Signature in hex format
     * @param array $files File names with paths
     *
     * @throws ContainerIdException If containerId is missing
     * @throws SigaApiResponseException If finalize response is content is not not self::RESULT_OK
     *
     * @return void
     */
    public function finalizeSigning(string $signatureId, string $signatureHex, array $files) : void
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }
        
        $response = $this->sigaApiClient->finalizeContainerRemoteSigning($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId, $signatureId, $signatureHex);
        
        if ($response['result'] === $this->sigaApiClient::RESULT_OK) {
            $this->endContainerFlow($files);
        } else {
            throw new SigaApiResponseException($response['errorMessage']);
        }
    }
    
    /**
     * End container flow
     *
     * @param array $files File names with paths
     *
     * @return void
     */
    private function endContainerFlow(array $files) : void
    {
        $this->doContainerValidation();
        
        $siGaContainer = $this->sigaApiClient->getContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);
        
        $this->createContainerWithFiles(base64_decode($siGaContainer['container']), $files);
        

        $this->deleteContainer();
    }
    
    /**
     * Create hascode container with files and save it to disk
     *
     * @param string $sigaZipContainer SiGa Zip container
     * @param array $files File names with paths
     *
     * @return void
     */
    private function createContainerWithFiles(string $sigaZipContainer, array $files) : void
    {
        $uploadDirectory = dirname(array_values($files)[0]);

        $containerName = $this->containerId.'.asice';
        $containerWithFullPath = $uploadDirectory . '/' . $containerName;
        
        //Letsa save file to disk
        file_put_contents($containerWithFullPath, $sigaZipContainer);
 
        $zip = new \ZipArchive();
        $zip->open($containerWithFullPath);
        foreach ($files as $filename => $path) {
            $zip->addFile($path, $filename);
        }
        $zip->close();
    }
    
    /**
     * Validate container
     *
     * @throws SigaApiResponseException If one of signatures is not valid
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
    private function deleteContainer() : void
    {
        $this->sigaApiClient->deleteContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);
    }
}
