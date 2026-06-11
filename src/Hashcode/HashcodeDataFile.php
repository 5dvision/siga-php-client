<?php

namespace SigaClient\Hashcode;

class HashcodeDataFile
{
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
    public function __construct(
        private readonly string $fileName,
        private readonly int $fileSize,
        private readonly string $fileData
    ) {
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
     * Get file sha256 hash
     *
     * @return string File hash
     */
    public function getHashSha256() : string
    {
        return $this->fileHash256;
    }

    /**
     * Get file sha512 hash
     *
     * @return string File hash
     */
    public function getHashSha512() : string
    {
        return $this->fileHash512;
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
