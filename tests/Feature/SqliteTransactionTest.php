<?php

namespace Tests\Feature;

use App\Support\Database\WriteConflict;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class SqliteTransactionTest extends TestCase
{
    private string $databaseFile;

    private Connection $first;

    private Connection $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseFile = tempnam(sys_get_temp_dir(), 'clbk-sqlite-');
        $settings = array_replace(config('database.connections.sqlite'), [
            'url' => null,
            'database' => $this->databaseFile,
            'busy_timeout' => 50,
        ]);

        config([
            'database.connections.lock_first' => $settings,
            'database.connections.lock_second' => $settings,
        ]);

        $this->first = DB::connection('lock_first');
        $this->second = DB::connection('lock_second');
        $this->first->statement('CREATE TABLE balances (id INTEGER PRIMARY KEY, amount INTEGER NOT NULL)');
        $this->first->table('balances')->insert(['id' => 1, 'amount' => 1000]);
        // Kedua koneksi sudah terbuka sebelum mencoba write lock.
        $this->second->getPdo();
    }

    protected function tearDown(): void
    {
        foreach (['lock_first', 'lock_second'] as $name) {
            $connection = DB::connection($name);
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::purge($name);
        }

        foreach (['', '-wal', '-shm'] as $suffix) {
            $file = $this->databaseFile.$suffix;
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_sqlite_safety_settings_are_applied(): void
    {
        $this->assertSame(1, (int) $this->first->selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertSame('wal', $this->first->selectOne('PRAGMA journal_mode')->journal_mode);
        $this->assertSame(2, (int) $this->first->selectOne('PRAGMA synchronous')->synchronous);
        $this->assertSame(50, (int) $this->first->selectOne('PRAGMA busy_timeout')->timeout);
        $this->assertSame(5000, config('database.connections.sqlite.busy_timeout'));
    }

    public function test_write_lock_is_acquired_before_reading_and_next_writer_sees_committed_balance(): void
    {
        $this->first->beginTransaction();
        $blocked = false;

        try {
            $this->second->beginTransaction();
        } catch (PDOException $exception) {
            $blocked = str_contains($exception->getMessage(), 'database is locked');
        }

        $this->assertTrue($blocked, 'BEGIN IMMEDIATE must block the second writer before either reads a balance.');
        $this->assertSame(0, $this->second->transactionLevel());
        $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
        $this->first->commit();

        $this->second->transaction(function () {
            $this->assertSame(600, $this->second->table('balances')->where('id', 1)->value('amount'));
            $this->second->table('balances')->where('id', 1)->decrement('amount', 200);
        }, 3);

        $this->assertSame(400, $this->first->table('balances')->where('id', 1)->value('amount'));
    }

    public function test_failed_transaction_rolls_back_and_releases_the_lock(): void
    {
        try {
            $this->first->transaction(function () {
                $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
                throw new RuntimeException('simulate failure');
            }, 3);
        } catch (RuntimeException $exception) {
            $this->assertSame('simulate failure', $exception->getMessage());
        }

        $this->second->transaction(function () {
            $this->assertSame(1000, $this->second->table('balances')->where('id', 1)->value('amount'));
        });
        $this->assertSame(0, $this->first->transactionLevel());
    }

    public function test_nested_rollback_and_after_commit_integrate_with_laravel(): void
    {
        $called = false;
        $this->first->beginTransaction();
        $this->first->table('balances')->where('id', 1)->decrement('amount', 100);
        $this->first->beginTransaction();
        $this->first->table('balances')->where('id', 1)->decrement('amount', 200);
        $this->first->rollBack();
        $this->first->afterCommit(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->first->commit();
        $this->assertTrue($called);
        $this->assertSame(900, $this->second->table('balances')->where('id', 1)->value('amount'));
    }

    public function test_transaction_retries_after_a_busy_lock_and_reads_again(): void
    {
        $this->first->beginTransaction();
        $attempts = 0;
        $this->second->beforeStartingTransaction(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 2) {
                $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
                $this->first->commit();
            }
        });

        app(WriteTransaction::class)->run(function () {
            $this->assertSame(600, $this->second->table('balances')->where('id', 1)->value('amount'));
        }, 'lock_second');

        $this->assertSame(2, $attempts);
    }

    public function test_busy_retry_limit_never_runs_callback_or_changes_balance(): void
    {
        $this->first->beginTransaction();
        $called = false;
        try {
            app(WriteTransaction::class)->run(function () use (&$called) {
                $called = true;
                $this->second->table('balances')->where('id', 1)->decrement('amount', 400);
            }, 'lock_second', 2);
            $this->fail('A busy database must return a conflict after the retry limit.');
        } catch (WriteConflict $exception) {
            $this->assertNotNull($exception->getPrevious());
        }

        $this->assertFalse($called);
        $this->assertSame(0, $this->second->transactionLevel());
        $this->first->commit();
        $this->assertSame(1000, $this->second->table('balances')->where('id', 1)->value('amount'));
    }

    public function test_after_commit_failure_never_repeats_the_committed_change(): void
    {
        $calls = 0;
        try {
            app(WriteTransaction::class)->run(function () use (&$calls) {
                $calls++;
                $this->first->table('balances')->where('id', 1)->decrement('amount', 100);
                $this->first->afterCommit(function () {
                    $this->second->beginTransaction();
                    // Simulasikan efek setelah commit yang bertabrakan dengan penulis lain.
                    $this->first->beginTransaction();
                });
            }, 'lock_first');
            $this->fail('The afterCommit failure must propagate without retrying the change.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('database is locked', $exception->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->second->rollBack();
        $this->assertSame(900, $this->first->table('balances')->where('id', 1)->value('amount'));
    }
}
