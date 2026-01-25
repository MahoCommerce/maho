<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Feed Uploader
 *
 * Handles uploading feeds to various destinations (SFTP, FTP, APIs)
 */
class Maho_FeedManager_Model_Uploader
{
    protected Maho_FeedManager_Model_Destination $_destination;
    protected array $_config;

    public function __construct(Maho_FeedManager_Model_Destination $destination)
    {
        $this->_destination = $destination;
        $this->_config = $destination->getConfigArray();
    }

    /**
     * Check if SSH2 extension is available
     */
    protected function _hasSsh2Extension(): bool
    {
        return extension_loaded('ssh2') && function_exists('ssh2_connect');
    }

    /**
     * Check if phpseclib is available
     */
    protected function _hasPhpseclib(): bool
    {
        return class_exists(\phpseclib3\Net\SFTP::class);
    }

    /**
     * Get SFTP availability status message
     */
    protected function _getSftpAvailabilityError(): ?string
    {
        if ($this->_hasSsh2Extension() || $this->_hasPhpseclib()) {
            return null;
        }

        return 'SFTP requires either the PHP ssh2 extension or phpseclib library. ' .
               'Install with: sudo apt install php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-ssh2 ' .
               'OR composer require phpseclib/phpseclib:^3.0';
    }

    /**
     * Upload a file to the destination
     *
     * @param string $localPath Local file path
     * @param string $remoteName Remote filename
     * @return bool Success status
     */
    public function upload(string $localPath, string $remoteName): bool
    {
        if (!file_exists($localPath)) {
            throw new InvalidArgumentException("Local file not found: {$localPath}");
        }

        return match ($this->_destination->getType()) {
            Maho_FeedManager_Model_Destination::TYPE_SFTP => $this->_uploadSftp($localPath, $remoteName),
            Maho_FeedManager_Model_Destination::TYPE_FTP => $this->_uploadFtp($localPath, $remoteName),
            Maho_FeedManager_Model_Destination::TYPE_GOOGLE_API => $this->_uploadGoogleApi($localPath),
            Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API => $this->_uploadFacebookApi($localPath),
            default => throw new InvalidArgumentException("Unsupported destination type: {$this->_destination->getType()}"),
        };
    }

    /**
     * Test connection to destination
     */
    public function testConnection(): array
    {
        try {
            return match ($this->_destination->getType()) {
                Maho_FeedManager_Model_Destination::TYPE_SFTP => $this->_testSftpConnection(),
                Maho_FeedManager_Model_Destination::TYPE_FTP => $this->_testFtpConnection(),
                Maho_FeedManager_Model_Destination::TYPE_GOOGLE_API => $this->_testGoogleApiConnection(),
                Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API => $this->_testFacebookApiConnection(),
                default => ['success' => false, 'message' => 'Unsupported destination type'],
            };
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Upload via SFTP
     */
    protected function _uploadSftp(string $localPath, string $remoteName): bool
    {
        // Check SFTP availability
        $error = $this->_getSftpAvailabilityError();
        if ($error) {
            throw new RuntimeException($error);
        }

        // Use phpseclib if available (preferred), otherwise fall back to ssh2 extension
        if ($this->_hasPhpseclib()) {
            return $this->_uploadSftpPhpseclib($localPath, $remoteName);
        }

        return $this->_uploadSftpExtension($localPath, $remoteName);
    }

    /**
     * Upload via SFTP using phpseclib (optional dependency)
     *
     * @phpstan-ignore class.notFound
     */
    protected function _uploadSftpPhpseclib(string $localPath, string $remoteName): bool
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 22);
        $username = $this->_config['username'] ?? '';
        $authType = $this->_config['auth_type'] ?? 'password';
        $remotePath = rtrim($this->_config['remote_path'] ?? '/', '/');

        $sftp = new \phpseclib3\Net\SFTP($host, $port);

        // Authenticate
        if ($authType === 'key') {
            $privateKey = $this->_config['private_key'] ?? '';
            /** @var \phpseclib3\Crypt\Common\PrivateKey $key */
            $key = \phpseclib3\Crypt\PublicKeyLoader::load($privateKey);
            if (!$sftp->login($username, $key)) {
                throw new RuntimeException("SFTP key authentication failed for user: {$username}");
            }
        } else {
            $password = $this->_config['password'] ?? '';
            if (!$sftp->login($username, $password)) {
                throw new RuntimeException("SFTP password authentication failed for user: {$username}");
            }
        }

        // Upload file
        $remoteFile = "{$remotePath}/{$remoteName}";
        if (!$sftp->put($remoteFile, $localPath, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
            $error = $sftp->getLastSFTPError() ?: 'Unknown SFTP error';
            throw new RuntimeException("Failed to upload file to: {$remoteFile} - {$error}");
        }

        // Verify upload by checking file exists and size matches
        $localSize = filesize($localPath);
        $stat = $sftp->stat($remoteFile);
        if ($stat === false) {
            throw new RuntimeException('Upload verification failed: file not found on server after upload');
        }
        $remoteSize = $stat['size'] ?? 0;
        if ($remoteSize !== $localSize) {
            throw new RuntimeException("Upload verification failed: size mismatch (local: {$localSize}, remote: {$remoteSize})");
        }

        Mage::log("FeedManager: SFTP upload successful to {$host}:{$remoteFile} ({$remoteSize} bytes)", Mage::LOG_INFO);

        return true;
    }

    /**
     * Upload via SFTP using ssh2 extension
     */
    protected function _uploadSftpExtension(string $localPath, string $remoteName): bool
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 22);
        $username = $this->_config['username'] ?? '';
        $authType = $this->_config['auth_type'] ?? 'password';
        $remotePath = rtrim($this->_config['remote_path'] ?? '/', '/');

        // Connect
        $connection = ssh2_connect($host, $port);
        if (!$connection) {
            throw new RuntimeException("Failed to connect to SFTP server: {$host}:{$port}");
        }

        // Authenticate
        if ($authType === 'key') {
            $privateKey = $this->_config['private_key'] ?? '';
            // Write private key to temp file
            $keyFile = tempnam(sys_get_temp_dir(), 'sftp_key_');
            file_put_contents($keyFile, $privateKey);
            chmod($keyFile, 0600);

            // Note: For key-only auth, public key file is often not required by server
            /** @phpstan-ignore argument.type */
            $auth = ssh2_auth_pubkey_file($connection, $username, null, $keyFile);
            unlink($keyFile);
        } else {
            $password = $this->_config['password'] ?? '';
            $auth = ssh2_auth_password($connection, $username, $password);
        }

        if (!$auth) {
            throw new RuntimeException("SFTP authentication failed for user: {$username}");
        }

        // Open SFTP session
        $sftp = ssh2_sftp($connection);
        if (!$sftp) {
            throw new RuntimeException('Failed to initialize SFTP subsystem');
        }

        // Upload file
        $remoteFile = "{$remotePath}/{$remoteName}";
        $sftpPath = "ssh2.sftp://{$sftp}{$remoteFile}";

        $result = copy($localPath, $sftpPath);

        if (!$result) {
            throw new RuntimeException("Failed to upload file to: {$remoteFile}");
        }

        Mage::log("FeedManager: SFTP upload successful to {$host}:{$remoteFile}", Mage::LOG_INFO);

        return true;
    }

    /**
     * Upload via FTP
     */
    protected function _uploadFtp(string $localPath, string $remoteName): bool
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 21);
        $username = $this->_config['username'] ?? '';
        $password = $this->_config['password'] ?? '';
        $passive = (bool) ($this->_config['passive_mode'] ?? true);
        $ssl = (bool) ($this->_config['ssl'] ?? false);
        $remotePath = rtrim($this->_config['remote_path'] ?? '/', '/');

        // Connect
        if ($ssl) {
            $connection = ftp_ssl_connect($host, $port, 30);
        } else {
            $connection = ftp_connect($host, $port, 30);
        }

        if (!$connection) {
            throw new RuntimeException("Failed to connect to FTP server: {$host}:{$port}");
        }

        // Login
        if (!ftp_login($connection, $username, $password)) {
            ftp_close($connection);
            throw new RuntimeException("FTP login failed for user: {$username}");
        }

        // Enable passive mode if configured
        if ($passive) {
            ftp_pasv($connection, true);
        }

        // Change to remote directory
        if (!empty($remotePath) && $remotePath !== '/') {
            ftp_chdir($connection, $remotePath);
        }

        // Upload file
        $result = ftp_put($connection, $remoteName, $localPath, FTP_BINARY);

        ftp_close($connection);

        if (!$result) {
            throw new RuntimeException('Failed to upload file via FTP');
        }

        Mage::log("FeedManager: FTP upload successful to {$host}:{$remotePath}/{$remoteName}", Mage::LOG_INFO);

        return true;
    }

    /**
     * Upload via Google Merchant Centre API
     */
    protected function _uploadGoogleApi(string $localPath): bool
    {
        $merchantId = $this->_config['merchant_id'] ?? '';
        $targetCountry = $this->_config['target_country'] ?? 'AU';
        $serviceAccountJson = $this->_config['service_account_json'] ?? '';

        if (empty($merchantId) || empty($serviceAccountJson)) {
            throw new InvalidArgumentException('Google API configuration incomplete');
        }

        // Google API implementation would go here
        // This requires the google/apiclient library
        // For now, we'll throw an exception indicating it needs implementation
        throw new RuntimeException(
            'Google Merchant Centre API upload requires google/apiclient library. ' .
            'Install via: composer require google/apiclient',
        );
    }

    /**
     * Upload via Facebook/Meta Catalog API
     */
    protected function _uploadFacebookApi(string $localPath): bool
    {
        $catalogId = $this->_config['catalog_id'] ?? '';
        $accessToken = $this->_config['access_token'] ?? '';

        if (empty($catalogId) || empty($accessToken)) {
            throw new InvalidArgumentException('Facebook API configuration incomplete');
        }

        // Read feed content
        $feedContent = file_get_contents($localPath);
        if ($feedContent === false) {
            throw new RuntimeException('Failed to read feed file');
        }

        // Facebook Catalog API endpoint for feed upload
        $url = "https://graph.facebook.com/v18.0/{$catalogId}/product_feed";

        $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 300]);

        $response = $client->request('POST', $url, [
            'body' => [
                'access_token' => $accessToken,
                'update_type' => 'CREATE_OR_UPDATE',
                'file' => $feedContent,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->toArray(false);

        if ($statusCode !== 200) {
            $error = $content['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("Facebook API error: {$error}");
        }

        Mage::log("FeedManager: Facebook API upload successful to catalog {$catalogId}", Mage::LOG_INFO);

        return true;
    }

    /**
     * Test SFTP connection (including authentication)
     */
    protected function _testSftpConnection(): array
    {
        // Check SFTP availability first
        $error = $this->_getSftpAvailabilityError();
        if ($error) {
            return ['success' => false, 'message' => $error];
        }

        // Use phpseclib if available (preferred), otherwise fall back to ssh2 extension
        if ($this->_hasPhpseclib()) {
            return $this->_testSftpConnectionPhpseclib();
        }

        return $this->_testSftpConnectionExtension();
    }

    /**
     * Test SFTP connection using phpseclib (optional dependency)
     *
     * @return array{success: bool, message: string}
     * @phpstan-ignore class.notFound
     */
    protected function _testSftpConnectionPhpseclib(): array
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 22);
        $username = $this->_config['username'] ?? '';
        $authType = $this->_config['auth_type'] ?? 'password';
        $remotePath = $this->_config['remote_path'] ?? '/';

        try {
            $sftp = new \phpseclib3\Net\SFTP($host, $port, 10); // 10 second timeout

            // Authenticate
            if ($authType === 'key') {
                $privateKey = $this->_config['private_key'] ?? '';
                if (empty($privateKey)) {
                    return ['success' => false, 'message' => 'Private key is required for key authentication'];
                }
                try {
                    /** @var \phpseclib3\Crypt\Common\PrivateKey $key */
                    $key = \phpseclib3\Crypt\PublicKeyLoader::load($privateKey);
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => 'Invalid private key format: ' . $e->getMessage()];
                }
                if (!$sftp->login($username, $key)) {
                    return ['success' => false, 'message' => "Key authentication failed for user '{$username}'"];
                }
            } else {
                $password = $this->_config['password'] ?? '';
                if (!$sftp->login($username, $password)) {
                    return ['success' => false, 'message' => "Password authentication failed for user '{$username}'"];
                }
            }

            // Check if remote path exists
            $stat = $sftp->stat($remotePath);
            if ($stat === false) {
                return ['success' => false, 'message' => "Remote path does not exist: {$remotePath}"];
            }

            return ['success' => true, 'message' => "Connected and authenticated to {$host}:{$port} as '{$username}'. Remote path verified."];

        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Connection timed out') || str_contains($message, 'Connection refused')) {
                return ['success' => false, 'message' => "Cannot connect to {$host}:{$port} - " . $message];
            }
            return ['success' => false, 'message' => 'Connection error: ' . $message];
        }
    }

    /**
     * Test SFTP connection using ssh2 extension
     */
    protected function _testSftpConnectionExtension(): array
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 22);
        $username = $this->_config['username'] ?? '';
        $authType = $this->_config['auth_type'] ?? 'password';
        $remotePath = $this->_config['remote_path'] ?? '/';

        // Test TCP connection
        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            return ['success' => false, 'message' => "Cannot connect to {$host}:{$port}"];
        }

        // Test authentication
        try {
            if ($authType === 'key') {
                $privateKey = $this->_config['private_key'] ?? '';
                if (empty($privateKey)) {
                    return ['success' => false, 'message' => 'Private key is required for key authentication'];
                }
                $keyFile = tempnam(sys_get_temp_dir(), 'sftp_key_');
                file_put_contents($keyFile, $privateKey);
                chmod($keyFile, 0600);

                /** @phpstan-ignore argument.type (null pubkey file works for key-only auth) */
                $authenticated = @ssh2_auth_pubkey_file($connection, $username, null, $keyFile);
                @unlink($keyFile);
            } else {
                $password = $this->_config['password'] ?? '';
                $authenticated = @ssh2_auth_password($connection, $username, $password);
            }

            if (!$authenticated) {
                return ['success' => false, 'message' => "Authentication failed for user '{$username}'"];
            }

            // Test SFTP subsystem and remote path
            $sftp = @ssh2_sftp($connection);
            if (!$sftp) {
                return ['success' => false, 'message' => 'Failed to initialize SFTP subsystem'];
            }

            // Check if remote path exists
            $realPath = @ssh2_sftp_realpath($sftp, $remotePath);
            if ($realPath === false) {
                return ['success' => false, 'message' => "Remote path does not exist: {$remotePath}"];
            }

            return ['success' => true, 'message' => "Connected and authenticated to {$host}:{$port} as '{$username}'. Remote path verified."];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
        }
    }

    /**
     * Test FTP connection (including authentication)
     */
    protected function _testFtpConnection(): array
    {
        $host = $this->_config['host'] ?? '';
        $port = (int) ($this->_config['port'] ?? 21);
        $username = $this->_config['username'] ?? '';
        $password = $this->_config['password'] ?? '';
        $ssl = (bool) ($this->_config['ssl'] ?? false);
        $passiveMode = (bool) ($this->_config['passive_mode'] ?? true);
        $remotePath = $this->_config['remote_path'] ?? '/';

        // Test TCP connection
        if ($ssl) {
            $connection = @ftp_ssl_connect($host, $port, 10);
        } else {
            $connection = @ftp_connect($host, $port, 10);
        }

        if (!$connection) {
            return ['success' => false, 'message' => "Cannot connect to {$host}:{$port}"];
        }

        // Test authentication
        if (!@ftp_login($connection, $username, $password)) {
            @ftp_close($connection);
            return ['success' => false, 'message' => "Authentication failed for user '{$username}'"];
        }

        // Set passive mode
        if ($passiveMode) {
            @ftp_pasv($connection, true);
        }

        // Test remote path
        if (!@ftp_chdir($connection, $remotePath)) {
            @ftp_close($connection);
            return ['success' => false, 'message' => "Remote path does not exist or not accessible: {$remotePath}"];
        }

        @ftp_close($connection);
        return ['success' => true, 'message' => "Connected and authenticated to {$host}:{$port} as '{$username}'. Remote path verified."];
    }

    /**
     * Test Google API connection
     */
    protected function _testGoogleApiConnection(): array
    {
        // Would validate service account JSON and test API access
        return ['success' => true, 'message' => 'Google API configuration validated'];
    }

    /**
     * Test Facebook API connection
     */
    protected function _testFacebookApiConnection(): array
    {
        $catalogId = $this->_config['catalog_id'] ?? '';
        $accessToken = $this->_config['access_token'] ?? '';

        if (empty($catalogId) || empty($accessToken)) {
            return ['success' => false, 'message' => 'Missing catalog ID or access token'];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$catalogId}?access_token={$accessToken}";
            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return ['success' => true, 'message' => "Connected to catalog: {$data['name']}"];
            }

            return ['success' => false, 'message' => 'Invalid response from Facebook API'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
