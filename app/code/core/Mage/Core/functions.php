<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Object destructor
 *
 * @param mixed $object
 */
function destruct($object)
{
    if (is_array($object)) {
        foreach ($object as $obj) {
            destruct($obj);
        }
    }
    unset($object);
}

/**
 * Tiny function to enhance functionality of ucwords
 *
 * Will capitalize first letters and convert separators if needed
 *
 * @param string $str
 * @param string $destSep
 * @param string $srcSep
 * @return string
 */
function uc_words($str, $destSep = '_', $srcSep = '_')
{
    return str_replace(' ', $destSep, ucwords(str_replace($srcSep, ' ', $str)));
}

/**
 * Check whether sql date is empty
 *
 * @param string $date
 * @return bool
 */
function is_empty_date($date)
{
    return $date === null || preg_replace('#[ 0:-]#', '', $date) === '';
}

/**
 * Custom error handler
 *
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool|null
 */
function mageCoreErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (str_contains($errstr, 'DateTimeZone::__construct')) {
        // there's no way to distinguish between caught system exceptions and warnings
        return false;
    }

    // Ignore symlink "File exists" warnings from Symfony cache
    // @see https://github.com/MahoCommerce/maho/issues/269
    if ($errno === E_WARNING
        && str_contains($errstr, 'symlink(): File exists')
        && str_contains($errfile, 'FilesystemTagAwareAdapter.php')
    ) {
        return false;
    }

    $errno = $errno & error_reporting();
    if ($errno == 0) {
        return false;
    }

    // PEAR specific message handling
    if (stripos($errfile . $errstr, 'pear') !== false) {
        // ignore strict and deprecated notices
        if ((PHP_VERSION_ID < 80400 && $errno == E_STRICT) || ($errno == E_DEPRECATED)) {
            return true;
        }
        // ignore attempts to read system files when open_basedir is set
        if ($errno == E_WARNING && stripos($errstr, 'open_basedir') !== false) {
            return true;
        }
    }

    $errorMessage = '';

    match ($errno) {
        E_ERROR => $errorMessage .= 'Error',
        E_WARNING => $errorMessage .= 'Warning',
        E_PARSE => $errorMessage .= 'Parse Error',
        E_NOTICE => $errorMessage .= 'Notice',
        E_CORE_ERROR => $errorMessage .= 'Core Error',
        E_CORE_WARNING => $errorMessage .= 'Core Warning',
        E_COMPILE_ERROR => $errorMessage .= 'Compile Error',
        E_COMPILE_WARNING => $errorMessage .= 'Compile Warning',
        E_USER_ERROR => $errorMessage .= 'User Error',
        E_USER_WARNING => $errorMessage .= 'User Warning',
        E_USER_NOTICE => $errorMessage .= 'User Notice',
        // E_STRICT prior to PHP8.4
        2048 => $errorMessage .= 'Strict Notice',
        E_RECOVERABLE_ERROR => $errorMessage .= 'Recoverable Error',
        E_DEPRECATED => $errorMessage .= 'Deprecated functionality',
        default => $errorMessage .= "Unknown error ($errno)",
    };

    $errorMessage .= ": {$errstr}  in {$errfile} on line {$errline}";
    if (Mage::getIsDeveloperMode()) {
        throw new Exception($errorMessage);
    }
    Mage::log($errorMessage, Mage::LOG_ERROR);
    return null;
}

/**
 * @param bool $return
 * @param bool $html
 * @param bool $showFirst
 * @return string|null
 */
function mageDebugBacktrace($return = false, $html = true, $showFirst = false)
{
    $d = debug_backtrace();
    $out = '';
    if ($html) {
        $out .= '<pre>';
    }
    foreach ($d as $i => $r) {
        if (!$showFirst && $i == 0) {
            continue;
        }
        // sometimes there is undefined index 'file'
        @$out .= "[$i] {$r['file']}:{$r['line']}\n";
    }
    if ($html) {
        $out .= '</pre>';
    }
    if ($return) {
        return $out;
    }
    echo $out;
    return null;
}

function mageSendErrorHeader()
{
    return;
}

function mageSendErrorFooter()
{
    return;
}

/**
 * @param string $path
 */
function mageDelTree($path)
{
    if (is_dir($path)) {
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry != '.' && $entry != '..') {
                mageDelTree($path . DS . $entry);
            }
        }
        @rmdir($path);
    } else {
        @unlink($path);
    }
}

/**
 * @param string $string
 * @param string $delimiter
 * @param string $enclosure
 * @param string $escape
 * @return array
 */
function mageParseCsv($string, $delimiter = ',', $enclosure = '"', $escape = '\\')
{
    $elements = explode($delimiter, $string);
    for ($i = 0; $i < count($elements); $i++) {
        $nquotes = substr_count($elements[$i], $enclosure);
        if ($nquotes % 2 == 1) {
            for ($j = $i + 1; $j < count($elements); $j++) {
                if (substr_count($elements[$j], $enclosure) > 0) {
                    // Put the quoted string's pieces back together again
                    array_splice(
                        $elements,
                        $i,
                        $j - $i + 1,
                        implode($delimiter, array_slice($elements, $i, $j - $i + 1)),
                    );
                    break;
                }
            }
        }
        if ($nquotes > 0) {
            // Remove first and last quotes, then merge pairs of quotes
            $qstr = & $elements[$i];
            $qstr = substr_replace($qstr, '', strpos($qstr, $enclosure), 1);
            $qstr = substr_replace($qstr, '', strrpos($qstr, $enclosure), 1);
            $qstr = str_replace($enclosure . $enclosure, $enclosure, $qstr);
        }
    }
    return $elements;
}

function isDirWriteable(string $dir): bool
{
    if (is_dir($dir) && is_writable($dir)) {
        if (stripos(PHP_OS, 'win') === 0) {
            $dir    = ltrim($dir, DIRECTORY_SEPARATOR);
            $file   = $dir . DIRECTORY_SEPARATOR . uniqid(mt_rand()) . '.tmp';
            $exist  = file_exists($file);
            $fp     = @fopen($file, 'a');
            if ($fp === false) {
                return false;
            }
            fclose($fp);
            if (!$exist) {
                unlink($file);
            }
        }
        return true;
    }
    return false;
}
