<?php

namespace SigaClient\Hashcode;

class Hashcode
{
    public static $shaTypes = [
        'SHA-256' => [
            'name' => 'SHA256',
            'bits' => 256,
            'length' => 64,
        ],
        'SHA-384' => [
            'name' => 'SHA384',
            'bits' => 384,
            'length' => 96,
        ],
        'SHA-512' => [
            'name' => 'SHA512',
            'bits' => 512,
            'length' => 128,
        ]
    ];

    /**
     * Get hash type based value
     *
     * @param string $hash Hash
     *
     * @return string|null Hash type
     */
    public static function getHashType(string $hash) :?string
    {
        $length = strlen($hash);

        foreach (self::$shaTypes as $hashName => $hasRules) {
            if ($hasRules['length'] === $length && ctype_xdigit($hash)) {
                return $hashName;
            }
        }

        return null;
    }


    /**
     * Get hash type name based value
     *
     * @param string $type Hash
     *
     * @return string|null Hash type name
     */
    public static function getHashTypeName(string $type) :?string
    {
        if (!in_array($type, array_keys(self::$shaTypes))) {
            return null;
        }

        return self::$shaTypes[$type]['name'];
    }

    /**
     * Get hash lenght in bytes
     *
     * @param string $type Hash type
     *
     * @return integer|null Hash length
     */
    public static function getLengthInBytes(string $type) : ?int
    {
        if (!in_array($type, array_keys(self::$shaTypes))) {
            return null;
        }

        return self::$shaTypes[$type]['bits'] / 8;
    }
}
