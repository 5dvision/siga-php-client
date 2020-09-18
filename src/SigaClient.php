<?php

namespace SigaClient;

use SigaClient\Hashcode\HashcodeDataFile;
use SigaClient\Exception\SigaApiResponseException;
use SigaClient\Service\SigaApiClient;

/**
 * SiGa Client
 *
 */
class SigaClient
{

    /**
     * Container Id
     *
     * @var string
     */
    public $containerId;

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
     * create hashcode container id
     *
     * @param array $files Files to send to create fontainer from
     *
     * @return void
     */
    public function createHashcodeContainerId(array $files) : void
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
        
        $this->containerId = $response['containerId'];
    }

    /**
     * Create hascode container
     *
     * @param array $files Files
     *
     * @return string Container Id
     */
    public function createHashcodeContainer(array $files) :string
    {
        //in separate function, since not sure if I should put separately to some other object.
        $this->createHashcodeContainerId($files);

        return $this->containerId;
    }
    
    public function prepareSigning(string $containerId, string $certicicateHex) : string
    {
        //TODO: Ask existing signatures(there was sample in SiGa java app)

        $remoteSigningUri = $this->sigaApiClient->getSigaApiUri($this->sigaApiClient::HASHCODE_ENDPOINT, [$containerId, 'remotesigning']);

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
            'digestAlgorithm' => $response['digestAlgorithm'],
            'generatedSignatureId' => $response['generatedSignatureId'],
        ]);
    }
}
