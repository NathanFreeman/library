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

use PDO;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Tests\HookFlagsTrait;

/**
 * Class PDOPoolTest
 *
 * @internal
 * @coversNothing
 */
class PDOPoolTest extends TestCase
{
    use HookFlagsTrait;

    public function testPutWhenErrorHappens()
    {
        self::saveHookFlags();
        self::setHookFlags(SWOOLE_HOOK_ALL);
        $expect = ['0', '1', '2', '3', '4'];
        $actual = [];
        Coroutine\run(function () use (&$actual) {
            $config = (new PDOConfig())
                ->withHost(MYSQL_SERVER_HOST)
                ->withPort(MYSQL_SERVER_PORT)
                ->withDbName(MYSQL_SERVER_DB)
                ->withCharset('utf8mb4')
                ->withUsername(MYSQL_SERVER_USER)
                ->withPassword(MYSQL_SERVER_PWD);

            $pool = new PDOPool($config, 2);
            for ($n = 5; $n--;) {
                Coroutine::create(function () use ($pool, $n, &$actual) {
                    $pdo = $pool->get();
                    try {
                        $statement = $pdo->prepare('SELECT :n as n');
                        $statement->execute([':n' => $n]);
                        $row = $statement->fetch(PDO::FETCH_ASSOC);
                        // simulate error happens
                        $statement = $pdo->prepare('KILL CONNECTION_ID()');
                        $statement->execute();
                    } catch (\PDOException $th) {
                        // do nothing
                    }
                    $pdo = null;
                    $pool->put(null);

                    $actual[] = $row['n'];
                });
            }
        });
        sort($actual);
        $this->assertEquals($expect, $actual);
        self::restoreHookFlags();
    }

    public function testPostgresPool()
    {
        self::saveHookFlags();
        self::setHookFlags(SWOOLE_HOOK_ALL);
        $expect = ['0', '1', '2', '3', '4'];
        $actual = [];
        Coroutine\run(function () use (&$actual) {
            $config = (new PDOConfig())
                ->withHost('pgsql')
                ->withHost(PGSQL_SERVER_HOST)
                ->withPort(PGSQL_SERVER_PORT)
                ->withDbName(PGSQL_SERVER_DB)
                ->withUsername(PGSQL_SERVER_USER)
                ->withPassword(PGSQL_SERVER_PWD);
            $pool = new PDOPool($config, 2);

            $pdo = $pool->get();
            $pdo->query(
                <<<EOF
CREATE TABLE test (
    id INTEGER
);
EOF
            );

            for ($n = 5; $n--;) {
                Coroutine::create(function () use ($pool, $n, &$actual) {
                    $pdo = $pool->get();
                    $statement = $pdo->prepare('INSERT INTO test values(?, ?)');
                    $statement->execute([$n]);
                    $statement = $pdo->prepare('SELECT id FROM test where id = ?');
                    $statement->execute([$n]);
                    $row = $statement->fetch(PDO::FETCH_ASSOC);
                    $actual[] = $row['id'];
                    $pool->put($pdo);
                });
            }
        });
        sort($actual);
        $this->assertEquals($expect, $actual);
        self::restoreHookFlags();
    }
}
