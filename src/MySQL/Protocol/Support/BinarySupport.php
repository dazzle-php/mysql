<?php

namespace Dazzle\MySQL\Protocol\Support;

use Exception;

class BinarySupport
{
    /**
     * Build structure of labels.
     *
     * @param string $q dot-separated labels list.
     * @return string
     */
    public static function labels($q)
    {
        $e = explode('.', $q);
        $r = '';
        for ($i = 0, $s = sizeof($e); $i < $s; ++$i)
        {
            $r .= chr(strlen($e[$i])) . $e[$i];
        }
        if (static::binarySubstr($r, -1) !== "\x00") {
            $r .= "\x00";
        }
        return $r;
    }

    /**
     * Parse structure of labels.
     *
     * @param string $data
     * @param string $orig
     * @return string Dot-separated labels list.
     */
    public static function parseLabels(&$data, $orig = null)
    {
        $str = '';
        while (strlen($data) > 0)
        {
            $l = ord($data[0]);

            if ($l >= 192) {
                $pos  = static::bytes2int(chr($l - 192) . static::binarySubstr($data, 1, 1));
                $data = static::binarySubstr($data, 2);
                $ref  = static::binarySubstr($orig, $pos);

                return $str . static::parseLabels($ref);
            }

            $p = substr($data, 1, $l);
            $str .= $p . (($l !== 0) ? '.' : '');
            $data = substr($data, $l + 1);
            if ($l === 0) {
                break;
            }
        }
        return $str;
    }

    /**
     * Build length-value binary snippet.
     *
     * @param string $str Data.
     * @param int $len Number of bytes to encode length, defaults to 1.
     * @return string
     */
    public static function LV($str, $len = 1, $lrev = false)
    {
        $l = static::i2b($len, strlen($str));
        if ($lrev) {
            $l = strrev($l);
        }
        return $l . $str;
    }

    /**
     * Build null-terminated string, with 2-byte of length.
     *
     * @param string $str Data.
     * @return string
     */
    public static function LVnull($str)
    {
        return static::LV($str . "\x00", 2, true);
    }

    /**
     * Build byte.
     *
     * @param int $int
     * @return string
     */
    public static function byte($int)
    {
        return chr($int);
    }

    /**
     * Build word (2 bytes) big-endian.
     *
     * @param int $int
     * @return string
     */
    public static function word($int)
    {
        return static::i2b(2, $int);
    }

    /**
     * Build word (2 bytes) little-endian.
     *
     * @param int $int
     * @return string
     */
    public static function wordl($int)
    {
        return strrev(static::word($int));
    }

    /**
     * Build double word (4 bytes) big-endian.
     *
     * @param int $int
     * @return string
     */
    public static function dword($int)
    {
        return static::i2b(4, $int);
    }

    /**
     * Build double word (4 bytes) little endian.
     *
     * @param int $int
     * @return string
     */
    public static function dwordl($int)
    {
        return strrev(static::dword($int));
    }

    /**
     * Build quadro word (8 bytes) big endian.
     *
     * @param int $int
     * @return string
     */
    public static function qword($int)
    {
        return static::i2b(8, $int);
    }

    /**
     * Build quadro word (8 bytes) little endian.
     * 
     * @param int $int
     * @return string
     */
    public static function qwordl($int)
    {
        return strrev(static::qword($int));
    }

    /**
     * Parse byte, and remove it.
     *
     * @param &string $p Data
     * @return int
     */
    public static function getByte(&$p)
    {
        $r = static::bytes2int($p{0});
        $p = static::binarySubstr($p, 1);

        return (int) $r;
    }

    /**
     * Get single-byte character.
     *
     * @param &string $p Data
     * @return string
     */
    public static function getChar(&$p)
    {
        $r = $p{0};
        $p = static::binarySubstr($p, 1);

        return $r;
    }

    /**
     * Parse word (2 bytes)
     *
     * @param &string $p Data.
     * @param bool $l Little-endian, defaults to false.
     * @return int
     */
    public static function getWord(&$p, $l = false)
    {
        $r = static::bytes2int(static::binarySubstr($p, 0, 2), !!$l);
        $p = static::binarySubstr($p, 2);

        return intval($r);
    }

    /**
     * Get word (2 bytes).
     *
     * @param &string $p Data
     * @param bool $l Little-endian, defaults to false.
     * @return string
     */
    public static function getStrWord(&$p, $l = false)
    {
        $r = static::binarySubstr($p, 0, 2);
        $p = static::binarySubstr($p, 2);
        if ($l) {
            $r = strrev($r);
        }
        return $r;
    }

    /**
     * Get double word (4 bytes).
     *
     * @param &string $p Data.
     * @param bool $l Little-endian, defaults to false.
     * @return int
     */
    public static function getDWord(&$p, $l = false)
    {
        $r = static::bytes2int(static::binarySubstr($p, 0, 4), !!$l);
        $p = static::binarySubstr($p, 4);

        return intval($r);
    }

    /**
     * Parse quadro word (8 bytes).
     *
     * @param &string $p Data
     * @param bool $l Little-endian, defaults to false.
     * @return int
     */
    public static function getQword(&$p, $l = false)
    {
        $r = static::bytes2int(static::binarySubstr($p, 0, 8), !!$l);
        $p = static::binarySubstr($p, 8);

        return intval($r);
    }

    /**
     * Get quadro word (8 bytes).
     *
     * @param &string $p Data.
     * @param bool $l Little-endian, defaults to false.
     * @return string
     */
    public static function getStrQWord(&$p, $l = false)
    {
        $r = static::binarySubstr($p, 0, 8);
        if ($l) {
            $r = strrev($r);
        }
        $p = static::binarySubstr($p, 8);

        return $r;
    }

    /**
     * Parse null-terminated string.
     *
     * @param &string $str
     * @return string
     */
    public static function getString(&$str)
    {
        $p = strpos($str, "\x00");
        if ($p === false) {
            return '';
        }
        $r   = static::binarySubstr($str, 0, $p);
        $str = static::binarySubstr($str, $p + 1);

        return $r;
    }

    /**
     * Parse length-value structure.
     *
     * @param &string $p
     * @param int $l number of length bytes.
     * @param bool $nul Null-terminated, defaults to false.
     * @param bool $lrev Little-endian, default to false.
     * @return string
     */
    public static function getLV(&$p, $l = 1, $nul = false, $lrev = false)
    {
        $s = static::b2i(static::binarySubstr($p, 0, $l), !!$lrev);
        $p = static::binarySubstr($p, $l);

        if ($s == 0) {
            return '';
        }

        $r = '';

        if (strlen($p) < $s) {
            echo("getLV error: buf length (" . strlen($p) . "): " . Debug::exportBytes($p) . ", must be >= string length (" . $s . ")\n");

        } else if ($nul) {
            if ($p{$s - 1} != "\x00") {
                echo("getLV error: Wrong end of NUL-string (" . Debug::exportBytes($p{$s - 1}) . "), len " . $s . "\n");
            } else {
                $d = $s - 1;
                if ($d < 0)
                {
                    $d = 0;
                }
                $r = static::binarySubstr($p, 0, $d);
                $p = static::binarySubstr($p, $s);
            }

        } else {
            $r = static::binarySubstr($p, 0, $s);
            $p = static::binarySubstr($p, $s);
        }

        return $r;
    }

    /**
     * Converts integer to binary string.
     *
     * @param int $len
     * @param int $int
     * @param bool $l Little-endian, defaults to false.
     * @return string
     */
    public static function int2bytes($len, $int = 0, $l = false)
    {
        $hexstr = dechex($int);

        if ($len === null) {
            if (strlen($hexstr) % 2) {
                $hexstr = "0" . $hexstr;
            }
        } else {
            $hexstr = str_repeat('0', $len * 2 - strlen($hexstr)) . $hexstr;
        }

        $bytes = strlen($hexstr) / 2;
        $bin   = '';

        for ($i = 0; $i < $bytes; ++$i)
        {
            $bin .= chr(hexdec(substr($hexstr, $i * 2, 2)));
        }

        return $l ? strrev($bin) : $bin;
    }

    /**
     * Convert array of flags into bit array.
     *
     * @param array $flags
     * @param int $len defaults to 4.
     * @return string
     */
    public static function flags2bitarray($flags, $len = 4)
    {
        $ret = 0;
        foreach ($flags as $v)
        {
            $ret |= $v;
        }
        return static::i2b($len, $ret);
    }

    /**
     * @see BinarySupport::int2bytes
     */
    public static function i2b($bytes, $int = 0, $l = false)
    {
        return static::int2bytes($bytes, $int, $l);
    }

    /**
     * Convert bytes into integer.
     *
     * @param string $str
     * @param bool $l little-endian encoding, defaults to false
     * @return int
     */
    public static function bytes2int($str, $l = false)
    {
        if ($l) {
            $str = strrev($str);
        }

        $dec = 0;
        $len = strlen($str);

        for ($i = 0; $i < $len; ++$i)
        {
            $dec += ord(static::binarySubstr($str, $i, 1)) * pow(0x100, $len - $i - 1);
        }

        return $dec;
    }

    /**
     * @see BinarySupport::bytes2int
     */
    public static function b2i($hex = 0, $l = false)
    {
        return static::bytes2int($hex, $l);
    }

    /**
     * Convert bitmap into bytes.
     *
     * @param string $bitmap
     * @param int $checkLen
     * @return string
     * @throws Exception
     */
    public static function bitmap2bytes($bitmap, $checkLen = 0)
    {
        $r = '';
        $bitmap = str_pad($bitmap, ceil(strlen($bitmap) / 8) * 8, '0', STR_PAD_LEFT);

        for ($i = 0, $n = strlen($bitmap) / 8; $i < $n; ++$i)
        {
            $r .= chr((int) bindec(static::binarySubstr($bitmap, $i * 8, 8)));
        }
        if ($checkLen && (strlen($r) != $checkLen)) {
            throw new Exception('Warning! Bitmap incorrect.');
        }

        return $r;
    }

    /**
     * Get bitmap.
     *
     * @param string $byte
     * @return string
     */
    public static function getBitmap($byte)
    {
        return sprintf('%08b', $byte);
    }

    /**
     * Binary Substring.
     *
     * @param string $s
     * @param string $p
     * @param int|null $len
     * @return string
     */
    protected static function binarySubstr($s, $p, $len = null)
    {
        if ($len === null) {
            $ret = substr($s, $p);
        } else {
            $ret = substr($s, $p, $len);
        }

        if ($ret === false) {
            $ret = '';
        }

        return $ret;
    }
}
