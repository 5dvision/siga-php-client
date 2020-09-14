<?php

namespace SigaClient\Hashcode;

class HashcodeDataFile
{
    /**
     * Filename.
     *
     * @var string
     */
    private string $fileName;
    
    /**
     * Filesize.
     *
     * @var int
     */
    private $fileSize;
    
    /**
     * File sha256 hash.
     *
     * @var string
     */
    private string $fileHash256;
    
    /**
     * File sha512 hash.
     *
     * @var string
     */
    private string $fileHash512;

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

        $hashToSign = new HashToSign($fileData);

        $this->fileHash256 = $hashToSign->calculateHash('sha256')->getHashInBase64();
        $this->fileHash512 = $hashToSign->calculateHash('sha512')->getHashInBase64();
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
