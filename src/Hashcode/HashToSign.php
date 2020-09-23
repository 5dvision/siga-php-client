<?php

namespace SigaClient\Hashcode;

class HashToSign
{
    /**
     * Hash
     *
     * @var string
     */
    private $hash;
    
    /**
     * Content from to create hash
     *
     * @var string
     */
    private $content;


    /**
     * Class constructor
     *
     * @param string $content String to create hash from
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Get Base 64encoded result from hash
     *
     * @return string
     */
    public function getHashInBase64() : string
    {
        return base64_encode($this->hash);
    }
    
    /**
     * Calculate hash
     *
     * @param string $algo Name of selected hashing algorithm
     *
     * @return HashToSign
     */
    public function calculateHash(string $algo) : HashToSign
    {
        $this->hash = hash($algo, $this->content, true);

        return $this;
    }
    

    /**
     * Get hashed string
     *
     * @param string $algo Name of selected hashing algorithm
     *
     * @return string Content hash
     */
    public function getHash(string $algo) : string
    {
        $this->calculateHash($algo);
        
        return $this->hash;
    }
}
