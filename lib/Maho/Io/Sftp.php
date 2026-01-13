<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Io;

use phpseclib\Net\SFTP as PhpSecLibSftp;

class Sftp extends \Maho\Io
{
    public const REMOTE_TIMEOUT = 10;
    public const SSH2_PORT = 22;

    /**
     * @var PhpSecLibSftp $_connection
     */
    protected $_connection = null; // @phpstan-ignore class.notFound

    /**
     * Open a SFTP connection to a remote site.
     *
     * @param array{host?: mixed, username?: mixed, password?: mixed, timeout?: int} $args Connection arguments
     * @throws \Exception
     */
    #[\Override]
    public function open(array $args = [])
    {
        if (!isset($args['timeout'])) {
            $args['timeout'] = self::REMOTE_TIMEOUT;
        }
        if (str_contains($args['host'], ':')) {
            [$host, $port] = explode(':', $args['host'], 2);
        } else {
            $host = $args['host'];
            $port = self::SSH2_PORT;
        }
        $this->_connection = new PhpSecLibSftp($host, $port, $args['timeout']); // @phpstan-ignore class.notFound
        if (!$this->_connection->login($args['username'], $args['password'])) { // @phpstan-ignore class.notFound
            throw new \Exception(sprintf('Unable to open SFTP connection as %s@%s', $args['username'], $args['host']));
        }
        return true;
    }

    /**
     * Close a connection
     */
    #[\Override]
    public function close(): void
    {
        $this->_connection->disconnect(); // @phpstan-ignore class.notFound
    }

    /**
     * Create a directory
     *
     * @param $mode Ignored here; uses logged-in user's umask
     * @param $recursive Analogous to mkdir -p
     *
     * Note: if $recursive is true and an error occurs mid-execution,
     * false is returned and some part of the hierarchy might be created.
     * No rollback is performed.
     */
    #[\Override]
    public function mkdir($dir, $mode = 0777, $recursive = true)
    {
        if ($recursive) {
            $no_errors = true;
            $dirlist = explode('/', $dir);
            reset($dirlist);
            $cwd = $this->_connection->pwd(); // @phpstan-ignore class.notFound
            while ($no_errors && ($dir_item = next($dirlist))) {
                $no_errors = ($this->_connection->mkdir($dir_item) && $this->_connection->chdir($dir_item)); // @phpstan-ignore class.notFound, class.notFound
            }
            $this->_connection->chdir($cwd); // @phpstan-ignore class.notFound
            return $no_errors;
        }
        return $this->_connection->mkdir($dir); // @phpstan-ignore class.notFound
    }

    /**
     * Delete a directory
     */
    #[\Override]
    public function rmdir($dir, $recursive = false)
    {
        if ($recursive) {
            $no_errors = true;
            $cwd = $this->_connection->pwd(); // @phpstan-ignore class.notFound
            if (!$this->_connection->chdir($dir)) { // @phpstan-ignore class.notFound
                throw new \Exception("chdir(): $dir: Not a directory");
            }
            $list = $this->_connection->nlist(); // @phpstan-ignore class.notFound
            if (!count($list)) {
                // Go back
                $this->_connection->chdir($cwd); // @phpstan-ignore class.notFound
                return $this->rmdir($dir, false);
            }
            foreach ($list as $filename) {
                if ($this->_connection->chdir($filename)) { // This is a directory @phpstan-ignore class.notFound
                    $this->_connection->chdir('..'); // @phpstan-ignore class.notFound
                    $no_errors = $no_errors && $this->rmdir($filename, $recursive);
                } else {
                    $no_errors = $no_errors && $this->rm($filename);
                }
            }
            $no_errors = $no_errors && ($this->_connection->chdir($cwd) && $this->_connection->rmdir($dir)); // @phpstan-ignore class.notFound, class.notFound
            return $no_errors;
        }
        return $this->_connection->rmdir($dir); // @phpstan-ignore class.notFound
    }

    /**
     * Get current working directory
     */
    #[\Override]
    public function pwd()
    {
        return $this->_connection->pwd(); // @phpstan-ignore class.notFound
    }

    /**
     * Change current working directory
     */
    #[\Override]
    public function cd($dir)
    {
        return $this->_connection->chdir($dir); // @phpstan-ignore class.notFound
    }

    /**
     * Read a file
     */
    #[\Override]
    public function read($filename, $dest = null)
    {
        if (is_null($dest)) {
            $dest = false;
        }
        return $this->_connection->get($filename, $dest); // @phpstan-ignore class.notFound
    }

    /**
     * Write a file
     * @param $src Must be a local file name
     */
    #[\Override]
    public function write($filename, $src, $mode = null)
    {
        return $this->_connection->put($filename, $src); // @phpstan-ignore class.notFound
    }

    /**
     * Delete a file
     */
    #[\Override]
    public function rm($filename)
    {
        return $this->_connection->delete($filename); // @phpstan-ignore class.notFound
    }

    /**
     * Rename or move a directory or a file
     */
    #[\Override]
    public function mv($src, $dest)
    {
        return $this->_connection->rename($src, $dest); // @phpstan-ignore class.notFound
    }

    /**
     * Chamge mode of a directory or a file
     */
    #[\Override]
    public function chmod($filename, $mode)
    {
        return $this->_connection->chmod($mode, $filename); // @phpstan-ignore class.notFound
    }

    /**
     * Get list of cwd subdirectories and files
     */
    #[\Override]
    public function ls($grep = null)
    {
        $list = $this->_connection->nlist(); // @phpstan-ignore class.notFound
        $pwd = $this->pwd();
        $result = [];
        foreach ($list as $name) {
            $result[] = [
                'text' => $name,
                'id' => "{$pwd}{$name}",
            ];
        }
        return $result;
    }

    public function rawls()
    {
        $list = $this->_connection->rawlist(); // @phpstan-ignore class.notFound
        return $list;
    }

    /**
     * Write a file
     * @param  string $filename remote filename
     * @param  string $src local filename
     * @return boolean
     */
    public function writeFile($filename, $src)
    {
        return $this->_connection->put($filename, $src, PhpSecLibSftp::SOURCE_LOCAL_FILE); // @phpstan-ignore class.notFound,class.notFound
    }
}
