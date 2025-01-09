<?php

namespace SigaClient\Hashcode;

use DOMDocument;
use SigaClient\Exception\SigaException;
use ZipArchive;

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
     * Checks if given container is hashcode container or not
     *
     * @return boolean
     */
    public function isHashcodeContainer() : bool
    {
        $zip = new ZipArchive();
        $zip->open($this->filename);

        $isHashcode = $zip->locateName('META-INF/hashcodes-sha256.xml') !== false;

        $zip->close();

        return $isHashcode;
    }

    /**
     * Get hashcode container without data files
     *
     * @return string
     */
    public function getContainerWithoutFiles() : String
    {
        $tempZipFile = tempnam(sys_get_temp_dir(), $this->filename);
        copy($this->filename, $tempZipFile);

        $zip = new ZipArchive();
        $zip->open($tempZipFile);

        foreach ($this->files as $file) {
            $zip->deleteName($file->getName());
        }

        $zip->close();

        return file_get_contents($tempZipFile);
    }

    /**
     * Get container datafiles list
     *
     * @return array
     */
    public function getDataFiles() : array
    {
        $this->loadFilesFromContainer();

        return $this->files;
    }

    /**
     * Load container datafiles to files variable
     *
     * @return HashcodeContainer
     */
    public function loadFilesFromContainer() : HashcodeContainer
    {
        $zip = new ZipArchive();
        $zip->open($this->filename);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if ($this->isDataFile($filename)) {
                $stat = $zip->statName($filename);

                $this->files[] = new HashcodeDataFile($filename, $stat['size'], $zip->getFromName($filename));
            }
        }

        $zip->close();

        return $this;
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

        $zip = new ZipArchive();
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
     * Add hascode datafiles to container
     *
     * @param array $files Files array to add to container
     *
     * @return HashcodeContainer
     */
    public function addDataFiles(array $files) : HashcodeContainer
    {
        foreach ($files as $file) {
            $this->files[] = $file;
        }

        return $this;
    }

    /**
     * Get files
     *
     * @return array Files
     */
    public function getFiles() : array
    {
        return $this->files;
    }

    /**
     * Add hascode files to container
     *
     * @return HashcodeContainer
     *
     * @throws \DOMException
     */
    public function addHashcodeFiles() : HashcodeContainer
    {
        $zip = new ZipArchive();
        $zip->open($this->filename);

        $zip->addFromString('META-INF/hashcodes-sha256.xml', $this->createHashcodeXml(256));
        $zip->addFromString('META-INF/hashcodes-sha512.xml', $this->createHashcodeXml(512));

        $zip->close();

        return $this;
    }

    /**
     * Create hashcode XML contents
     *
     * @param integer $hashFormat Hascode format 256 or 512
     *
     * @return string XML file content
     *
     * @throws \DOMException
     */
    private function createHashcodeXml(int $hashFormat) : string
    {
        $xml = new DOMDocument("1.0", "UTF-8");

        $hashcodes = $xml->createElement('hashcodes');
        $hashcodes = $xml->appendChild($hashcodes);

        foreach ($this->files as $file) {
            $entry = $xml->createElement('file-entry');

            $entry->setAttribute('full-path', $file->getName());
            $entry->setAttribute('size', $file->getSize());

            if ($hashFormat == 256) {
                $entry->setAttribute('hash', $file->getHashSha256());
            } elseif ($hashFormat == 512) {
                $entry->setAttribute('hash', $file->getHashSha512());
            }
            $hashcodes->appendChild($entry);
        }

        return $xml->saveXML();
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
    public function saveContainerWithFiles() : bool
    {
        if (!$this->saveContainer()) {
            throw new SigaException("Could not save container to filesystem");
        }

        $zip = new ZipArchive();
        $zip->open($this->filename);

        foreach ($this->files as $hashcodeFile) {
            $zip->addFromString($hashcodeFile->getName(), $hashcodeFile->getContent());
        }

        $zip->deleteName('META-INF/hashcodes-sha256.xml');
        $zip->deleteName('META-INF/hashcodes-sha512.xml');

        $return = $zip->close();

        if (!$return) {
            unlink($this->filename);
        }

        return $return;
    }

    /**
     * Convert datafile container to hashcode container
     *
     * @return bool
     *
     * @throws \DOMException
     */
    public function convertToHashcodeContainer() : bool
    {
        $this->loadFilesFromContainer()
            ->addHashcodeFiles()
        ;

        return true;
    }
}
