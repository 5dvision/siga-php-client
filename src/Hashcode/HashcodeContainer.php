<?php

namespace SigaClient\Hashcode;

use SigaClient\Exception\SigaException;

class HashcodeContainer
{
    /**
     * Filename.
     *
     * @var string
     */
    private $filename;
    
    /**
     * Container data.
     *
     * @var string
     */
    private $containerData;

    /**
     * Container files.
     *
     * @var array
     */
    private $files = [];

    /**
     * Class contructor
     *
     * @param string $filename Filename with full path
     *
     * @return HashcodeContainer
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
        
        return $this;
    }
    
    /**
     * Register hashcode container
     *
     * @param string $container
     *
     * @return HashcodeContainer
     */
    public function addContainer(string $container) : HashcodeContainer
    {
        $this->containerData = $container;

        return $this;
    }

    /**
     * Get container datafiles list
     *
     * @return array
     */
    public function getDataFiles() : array
    {
        $zip = new \ZipArchive();
        $zip->open($this->filename);
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            if ($this->isDataFile($filename)) {
                $stat = $zip->statName($filename);

                $this->files[] = new HashcodeDataFile($filename, $stat['size'], $zip->getFromName($filename));
            }
        }

        $zip->close();

        return $this->files;
    }

    /**
     * Check if file is data file
     *
     * @param string $filename Filename
     *
     * @return boolean Is or not
     */
    private function isDataFile(string $filename) : bool
    {
        return $filename !== 'mimetype' && strpos($filename, 'META-INF/') !== 0;
    }
    
    /**
     * Extract data files from container
     *
     * @param string $outputDir Directory where to put temp files
     *
     * @return array Files list of HashcodeDataFile objects
     */
    public function extractDataFiles(string $outputDir) : array
    {
        $datafiles = [];
        
        if (!is_dir($outputDir)) {
            return $datafiles;
        }

        $zip = new \ZipArchive();
        $zip->open($this->filename);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            if ($this->isDataFile($filename)) {
                $stat = $zip->statName($filename);

                $file = new HashcodeDataFile($filename, $stat['size'], $zip->getFromName($filename));
                
                if ($zip->extractTo($outputDir)) {
                    $datafiles[] = $outputDir . DIRECTORY_SEPARATOR . $file->getName();
                }
            }
        }
        $zip->close();

        return $datafiles;
    }
    
    /**
     * Add files to container
     *
     * @param array $files Files array to add to container
     *
     * @return HashcodeContainer
     */
    public function addFiles(array $files) : HashcodeContainer
    {
        foreach ($files as $file) {
            $this->files[] = new HashcodeDataFile($file['name'], $file['size'], $file['data']);
        }

        return $this;
    }

    /**
     * Save container to file system
     *
     * @return bool Was successful
     */
    public function saveContainer() : bool
    {
        //Not sure if directory creation is nessesary
        $dir = dirname($this->filename);

        //Lets create dir if it does not exist
        if (!is_dir($dir)) {
            mkdir($dir, true);
        }

        return file_put_contents($this->filename, $this->containerData);
    }

    /**
     * Save container with files to disk
     *
     * @return bool Was successful or not
     */
    public function saveContainerWithFiles() :bool
    {
        if (!$this->saveContainer()) {
            throw new SigaException("Could not save container to filesystem");
        }

        $zip = new \ZipArchive();
        $zip->open($this->filename);
        
        foreach ($this->files as $hashcodeFile) {
            $zip->addFromString($hashcodeFile->getName(), $hashcodeFile->getContent());
        }
        $return = $zip->close();

        if (!$return) {
            unlink($this->filename);
        }

        return $return;
    }
}
