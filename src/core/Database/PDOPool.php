<?php
/**
 * This file is part of Swoole.
 *
 * @link     https://www.swoole.com
 * @contact  team@swoole.com
 * @license  https://github.com/swoole/library/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Swoole\Database;

use Exception;
use PDO;
use Swoole\ConnectionPool;

/**
 * @method PDO|PDOProxy get()
 * @method void put(PDO|PDOProxy $connection)
 */
class PDOPool extends ConnectionPool
{
    /** @var int */
    protected $size = 64;

    /** @var PDOConfig */
    protected $config;

    public function __construct(PDOConfig $config, int $size = self::DEFAULT_SIZE)
    {
        $this->config = $config;
        parent::__construct(function () {
            $driver = $this->config->getDriver();
            if ($driver === 'sqlite') {
                return new PDO($this->createDSN('sqlite'));
            }

            return new PDO($this->createDSN($driver), $this->config->getUsername(), $this->config->getPassword(), $this->config->getOptions());
        }, $size, PDOProxy::class);
    }

    /**
     * @purpose create DSN
     * @param string $driver
     * @return string
     * @throws Exception
     */
    private function createDSN(string $driver): string
    {
        switch ($driver) {
            case 'mysql':
                if ($this->config->hasUnixSocket()) {
                    $dsn = "mysql:unix_socket={$this->config->getUnixSocket()};dbname={$this->config->getDbname()};charset={$this->config->getCharset()}";
                } else {
                    $dsn = "mysql:host={$this->config->getHost()};port={$this->config->getPort()};dbname={$this->config->getDbname()};charset={$this->config->getCharset()}";
                }
                break;
            case 'pgsql':
                $dsn = 'pgsql:host=' . ($this->config->hasUnixSocket() ? $this->config->getUnixSocket() : $this->config->getHost()) . ";port={$this->config->getPort()};dbname={$this->config->getDbname()};";
                break;
            case 'oci':
                $dsn = 'oci:dbname='.($this->config->hasUnixSocket() ? $this->config->getUnixSocket() : $this->config->getHost()).':'.$this->config->getPort().'/'.$this->config->getDbname().';charset='.$this->config->getCharset();
                break;
            case 'sqlite':
                $dsn = 'sqlite:'.$this->config->getDbname();
                break;
            default:
                throw new Exception('Unknown Database Driver');
        }

        return $dsn;
    }
}
