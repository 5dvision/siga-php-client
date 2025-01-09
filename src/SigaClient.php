<?php

namespace SigaClient;

use SigaClient\Exception\ContainerIdException;
use SigaClient\Exception\InvalidSigaParamException;
use SigaClient\Exception\SigaApiResponseException;
//use SigaClient\Hashcode\HashcodeContainer;
use SigaClient\Hashcode\HashcodeDataFile;
use SigaClient\Service\SigaApiClient;
use ZipArchive;

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
     * @var SigaApiClient
     */
    private $sigaApiClient;

    /**
     * Container extension bdoc or asice
     *
     * @var string
     */
    private $extension = 'asice';

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

    public function setExtension($ext)
    {
        $this->extension = $ext;
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
     * @return string Container Id
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidSigaParamException
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
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function createHashcodeContainer(array $files) : string
    {
        $body = [];
        foreach ($files as $file) {
            $body['dataFiles'][] = (new HashcodeDataFile($file['name'], $file['size'], $file['data']))->convert();
        }

        $response = $this->sigaApiClient->createHascodeContainer($body);

        return $this->containerId = $response['containerId'];
    }

    /**
     * Get base 64 decoded container content
     *
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getContainer() : string
    {
        $response = $this->sigaApiClient->getContainer($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId);

        return base64_decode($response['container']);
    }

    /**
     * Prepare signing process
     *
     * @param string $certicicateHex Certificate in hex format
     *
     * @return array Prepared parts
     *
     * @throws ContainerIdException If containerId is missing
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function prepareSigning(string $certicicateHex) : array
    {
        //TODO: Ask existing signatures(there was sample in SiGa java app)

        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        $response = $this->sigaApiClient->startSigning($this->containerId, $certicicateHex);

        return [
            'dataToSign' => $response['dataToSign'],
            'dataToSignHash' => base64_encode(hash($response['digestAlgorithm'], base64_decode($response['dataToSign']), true)),
            'digestAlgorithm' => $response['digestAlgorithm'],
            'generatedSignatureId' => $response['generatedSignatureId'],
        ];
    }

    /**
     * Start mobile signing process
     *
     * @link @https://github.com/open-eid/SiGa/wiki/Hashcode-API-description#start-mobile-id-signing
     *
     * @param array $requestParams Request params
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function prepareMobileSigning(array $requestParams) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->startMobileSigning($this->containerId, $requestParams);
    }

    /**
     * Get mobile ID signing status
     *
     * @param string $signatureId Signature Id
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMobileSigningStatus(string $signatureId) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->getMobileSigningStatus($this->containerId, $signatureId);
    }

    /**
     * Finalize signing process
     *
     * @param string $signatureId Signature Id
     * @param string $signatureHex Signature in hex format
     *
     * @return array
     *
     * @throws ContainerIdException If containerId is missing
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function finalizeSigning(string $signatureId, string $signatureHex) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->finalizeContainerRemoteSigning($this->sigaApiClient::HASHCODE_ENDPOINT, $this->containerId, $signatureId, $signatureHex);
    }

    /**
     * End container flow
     *
     * @param array $files File names with paths
     *
     * @return void
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function endContainerFlow(array $files) : void
    {
        $this->doContainerValidation();

        /* Trying elegant way to add files to container
        $hashcodeContainer =
            (new HashcodeContainer(sys_get_temp_dir().'/'.$this->containerId.'.asice'))
            ->addFilesToContainer($this->getContainer(), $files)
            ->saveContainerTo();
        */

        $this->createContainerWithFiles($this->getContainer(), $files);

        $this->deleteContainer();
    }

    /**
     * Create hascode container with files and save it to disk
     *
     * TODO: deprecated and should not be used in furure. Create more elegant way to do it
     *
     * @param string $sigaZipContainer SiGa Zip container
     * @param array $files File names with paths
     *
     * @return void
     */
    public function createContainerWithFiles(string $sigaZipContainer, array $files) : void
    {
        $uploadDirectory = dirname(array_values($files)[0]);

        $containerName = $this->containerId . '.' . $this->extension;
        $containerWithFullPath = $uploadDirectory . '/' . $containerName;

        //Lets save file to disk
        file_put_contents($containerWithFullPath, $sigaZipContainer);

        $zip = new ZipArchive();
        $zip->open($containerWithFullPath);
        foreach ($files as $filename => $path) {
            $zip->addFile($path, $filename);
        }
        $zip->close();
    }

    /**
     * Validate container
     *
     * @return void
     *
     * @throws SigaApiResponseException If one of signatures is not valid
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function doContainerValidation() : void
    {
        $response = $this->getContainerValidation();

        if ($response['validSignaturesCount'] != $response['signaturesCount']) {
            throw new SigaApiResponseException('One of signatures is not valid!');
        }
    }

    /**
     * Get SIVA container validation report
     *
     *
     * @return array
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getContainerValidation() : array
    {
        $response = $this->sigaApiClient->getContainerValidation($this->containerId);

        return $response['validationConclusion'];
    }

    /**
     * Delete SiGa container
     *
     * @return void
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function deleteContainer() : void
    {
        $this->sigaApiClient->deleteContainer($this->containerId);
    }

    /**
     * Upload hashcode container
     *
     * @param string $fileContent Container data
     *
     * @return string Container Id
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadHashcodeContainer(string $fileContent) : string
    {
        $response = $this->sigaApiClient->uploadContainer(base64_encode($fileContent));

        return $this->containerId = $response['containerId'];
    }

    /**
     * Get container data files
     *
     * @return array Files list
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDataFilesList() : array
    {
        $response = $this->sigaApiClient->getContainerFiles($this->containerId);

        return $response['dataFiles'];
    }

    /**
     * Get container signatures
     *
     * @return array Signatures list
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSignaturesList()
    {
        $response = $this->sigaApiClient->getContainerSignatures($this->containerId);

        return $response['signatures'];
    }

    /**
     * Get signature info
     *
     * @return array Signature info
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSignatureInfo(string $signatureId)
    {
        return $this->sigaApiClient->getSignatureInfo($this->containerId, $signatureId);
    }

    /**
     * Get Smart-ID certificate choice
     *
     * @param array $requestParams Request params
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSmartIdCertificateChoice(array $requestParams) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->getSmartIdCertificateChoice($this->containerId, $requestParams);
    }

    /**
     * Get Smart ID certificate choice status
     *
     * @param string $certificate Certificate
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSmartIdCertificateStatus(string $certificate): array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->getSmartIdCertificateChoiceStatus($this->containerId, $certificate);
    }

    /**
     * Start Smart-ID signing process
     *
     * @link @https://github.com/open-eid/SiGa/wiki/Hashcode-API-description#smart-id-signing
     *
     * @param array $requestParams Request params
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function prepareSmartIdSigning(array $requestParams) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->startSmartIdSigning($this->containerId, $requestParams);
    }

    /**
     * Get Smart-ID signing status
     *
     * @param string $signatureId Signature Id
     *
     * @return array Response
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSmartIdSigningStatus(string $signatureId) : array
    {
        if (!$this->containerId) {
            throw new ContainerIdException();
        }

        return $this->sigaApiClient->getSmartIdSigningStatus($this->containerId, $signatureId);
    }
}
