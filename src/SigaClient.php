<?php

namespace SigaClient;

use SigaClient\Hashcode\HashcodeDataFile;
use SigaClient\Exception\SigaException;
use SigaClient\Service\SigaApiClient;

/**
 * SiGa Client
 *
 */
class SigaClient
{
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
     * 		'uuid' => 'xxx',
     *      'secret' => 'xxx',
     * ]
     *
     * @return SigaClient
     */
    public static function create(array $options = [])
    {
        return new self($options);
    }

    public function createAsicContainer()
    {
        //TODO: Needs functionality
    }

    public function createHashcodeContainer($files)
    {
        $body = [];
        foreach ($files as $file) {
            $body['dataFiles'][] = (new HashcodeDataFile($file['name'], $file['size'], $file['data']))->convert();
        }
        
        $requestResponse = $this->sigaApiClient->getClient()->request('POST', 'hashcodecontainers', ['json' => $body]);
        
        $response = json_decode($requestResponse->getBody(), true);

        


        #dump($response);
        #dd($response);

        //TODO: Create functionality
    }
    

    public static function getContainerId()
    {
        if (!isset($_SESSION['containerId'])) {
            throw new SigaException('There is no container Id');
        }

        return $_SESSION['containerId'];
    }
}
