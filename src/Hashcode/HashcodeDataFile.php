<?php

namespace SigaClient\Hashcode;

class HashcodeDataFile
{
    /**
     * Filename.
     *
     * @var string
     */
    private $fileName;
    
    /**
     * Filesize.
     *
     * @var int
     */
    private $fileSize;
    
    /**
     * Filedata.
     *
     * @var string
     */
    private $fileData;
    
    
    /**
     * File sha256 hash.
     *
     * @var string
     */
    private $fileHash256;
    
    /**
     * File sha512 hash.
     *
     * @var string
     */
    private $fileHash512;

    /**
     * Class constructor
     *
     * @param string $fileName Filename
     * @param integer $fileSize File size in bytes
     * @param string $fileData File content
     *
     * @return void
     */
    public function __construct(string $fileName, int $fileSize, string $fileData)
    {
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->fileData = $fileData;

        $hashToSign = new HashToSign($this->fileData);

        $this->fileHash256 = $hashToSign->calculateHash('sha256')->getHashInBase64();
        $this->fileHash512 = $hashToSign->calculateHash('sha512')->getHashInBase64();
    }
    
    /**
     * Get filename
     *
     * @return string
     */
    public function getName() :string
    {
        return $this->fileName;
    }

    /**
     * Get file size
     *
     * @return int
     */
    public function getSize() : int
    {
        return $this->fileSize;
    }
    
    /**
     * Get file content
     *
     * @return string
     */
    public function getContent() : string
    {
        return $this->fileData;
    }

    /**
     * Convert File hashcode to SiGa format
     *
     * @return array Siga file prepare format
     */
    public function convert() : array
    {
        return [
            'fileName' => $this->fileName,
            'fileHashSha512' => $this->fileHash512,
            'fileSize' => $this->fileSize,
            'fileHashSha256' => $this->fileHash256,
        ];
    }
}
