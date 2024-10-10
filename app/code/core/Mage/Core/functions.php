<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
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
 * Simple sql format date
 *
 * @param bool $dayOnly
 * @return string
 * @deprecated use equivalent Varien method directly
 * @see Varien_Date::now()
 */
function now($dayOnly = false)
{
    return Varien_Date::now($dayOnly);
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
 * @param string $class
 * @deprecated 24.11.0
 * @return bool|string
 */
function mageFindClassFile($class)
{
    $loader = require BP . '/vendor/autoload.php';
    return $loader->findFile($class);
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

    $errno = $errno & error_reporting();
    if ($errno == 0) {
        return false;
    }

    // PEAR specific message handling
    if (stripos($errfile . $errstr, 'pear') !== false) {
        // ignore strict and deprecated notices
        if (($errno == E_STRICT) || ($errno == E_DEPRECATED)) {
            return true;
        }
        // ignore attempts to read system files when open_basedir is set
        if ($errno == E_WARNING && stripos($errstr, 'open_basedir') !== false) {
            return true;
        }
    }

    $errorMessage = '';

    switch ($errno) {
        case E_ERROR:
            $errorMessage .= 'Error';
            break;
        case E_WARNING:
            $errorMessage .= 'Warning';
            break;
        case E_PARSE:
            $errorMessage .= 'Parse Error';
            break;
        case E_NOTICE:
            $errorMessage .= 'Notice';
            break;
        case E_CORE_ERROR:
            $errorMessage .= 'Core Error';
            break;
        case E_CORE_WARNING:
            $errorMessage .= 'Core Warning';
            break;
        case E_COMPILE_ERROR:
            $errorMessage .= 'Compile Error';
            break;
        case E_COMPILE_WARNING:
            $errorMessage .= 'Compile Warning';
            break;
        case E_USER_ERROR:
            $errorMessage .= 'User Error';
            break;
        case E_USER_WARNING:
            $errorMessage .= 'User Warning';
            break;
        case E_USER_NOTICE:
            $errorMessage .= 'User Notice';
            break;
        case E_STRICT:
            $errorMessage .= 'Strict Notice';
            break;
        case E_RECOVERABLE_ERROR:
            $errorMessage .= 'Recoverable Error';
            break;
        case E_DEPRECATED:
            $errorMessage .= 'Deprecated functionality';
            break;
        default:
            $errorMessage .= "Unknown error ($errno)";
            break;
    }

    $errorMessage .= ": {$errstr}  in {$errfile} on line {$errline}";
    if (Mage::getIsDeveloperMode()) {
        throw new Exception($errorMessage);
    } else {
        Mage::log($errorMessage, Zend_Log::ERR);
        return null;
    }
}

/**
 * @param bool $return
 * @param bool $html
 * @param bool $showFirst
 * @return string|null
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
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
    } else {
        echo $out;
        return null;
    }
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
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
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
                        implode($delimiter, array_slice($elements, $i, $j - $i + 1))
                    );
                    break;
                }
            }
        }
        if ($nquotes > 0) {
            // Remove first and last quotes, then merge pairs of quotes
            $qstr =& $elements[$i];
            $qstr = substr_replace($qstr, '', strpos($qstr, $enclosure), 1);
            $qstr = substr_replace($qstr, '', strrpos($qstr, $enclosure), 1);
            $qstr = str_replace($enclosure . $enclosure, $enclosure, $qstr);
        }
    }
    return $elements;
}

/**
 * @param string $dir
 * @return bool
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
 */
function is_dir_writeable($dir)
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

function mahoFindFileInIncludePath(string $path): string|false
{
    $paths = [];
    $paths[] = BP;

    $modules = \Maho\MahoAutoload::getInstalledModules(BP);
    foreach ($modules as $module => $info) {
        if ($module === 'mahocommerce/maho') {
            continue;
        }
        $paths[] = $info['path'];
    }
    if ($modules['mahocommerce/maho']['isChildProject']) {
        $paths[] = $modules['mahocommerce/maho']['path'];
    }

    $relativePath = str_replace(array_reverse($paths), '', $path);
    $relativePath = ltrim($relativePath, '/');

    // Temporarily set include paths, then revert
    $oldPaths = get_include_path();
    set_include_path(implode(PS, $paths));
    $file = stream_resolve_include_path($relativePath);
    set_include_path($oldPaths);

    return $file;
}

function mahoListDirectories(string $path): array
{
    $results = [];
    $relativePath = str_replace(BP . '/', '', $path);

    foreach (glob("$path/*", GLOB_ONLYDIR) as $folder) {
        $results[] = basename($folder);
    }

    $modules = \Maho\MahoAutoload::getInstalledModules(BP);
    foreach ($modules as $module => $info) {
        foreach (glob($info['path'] . "/$relativePath/*", GLOB_ONLYDIR) as $folder) {
            $results[] = basename($folder);
        }
    }

    return array_unique($results);
}

function mahoErrorReport(array $reportData = [], int $httpResponseCode = 503): void
{
    $reportIdMessage = '';
    if ($reportData) {
        $reportId   = abs((int)(microtime(true) * random_int(100, 1000)));
        $reportIdMessage = "<p>Error log record number: {$reportId}</p>";
        $reportDir = Mage::getBaseDir('var') . '/report';
        if (!file_exists($reportDir)) {
            @mkdir($reportDir, 0750, true);
        }

        $reportFile = "{$reportDir}/$reportId";
        $reportData = array_map('strip_tags', $reportData);
        @file_put_contents($reportFile, serialize($reportData));
        @chmod($reportFile, 0640);
    }

    $description = match ($httpResponseCode) {
        404 => 'Not Found',
        503 => 'Service Unavailable',
        default => '',
    };
    header("HTTP/1.1 {$httpResponseCode} {$description}", true, $httpResponseCode);
    echo "<html><body><h1>There has been an error processing your request</h1>{$reportIdMessage}</body></html>";
}
