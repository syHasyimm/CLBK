<?php

namespace Tests\Feature;

use App\Support\Database\ReadSnapshot;
use App\Support\Database\WriteConflict;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class MySqlTransactionTest extends TestCase
{
    use DatabaseMigrations;

    private Connection $first;

    private Connection $second;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.lock_first' => config('database.connections.mysql'),
            'database.connections.lock_second' => config('database.connections.mysql')]);
        $this->first = DB::connection('lock_first');
        $this->second = DB::connection('lock_second');
        $this->first->statement('CREATE TABLE balances (id BIGINT PRIMARY KEY, amount BIGINT NOT NULL) ENGINE=InnoDB');
        $this->first->table('balances')->insert(['id' => 1, 'amount' => 1000]);
        $this->second->statement('SET SESSION innodb_lock_wait_timeout=1');
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
        parent::tearDown();
    }

    private function holdWriteLock(): void
    {
        $this->first->beginTransaction();
        $this->first->table('database_write_locks')->where('id', 1)->lockForUpdate()->first();
    }

    public function test_report_snapshot_stays_consistent_while_another_connection_commits(): void
    {
        app(ReadSnapshot::class)->run(function () {
            $this->assertSame(1000, $this->first->table('balances')->value('amount'));
            $this->second->transaction(fn () => $this->second->table('balances')->where('id', 1)->decrement('amount', 400));
            $this->assertSame(1000, $this->first->table('balances')->value('amount'));
        }, 'lock_first');
        $this->assertSame(600, $this->first->table('balances')->value('amount'));
        $this->assertFalse($this->first->getPdo()->inTransaction());
    }

    public function test_next_writer_reads_the_committed_balance_after_a_lock_conflict(): void
    {
        $this->holdWriteLock();
        try {
            app(WriteTransaction::class)->run(fn () => $this->fail('Blocked writer entered callback.'), 'lock_second', 1);
            $this->fail('Expected lock conflict.');
        } catch (WriteConflict) {
            $this->assertSame(0, $this->second->transactionLevel());
        }
        $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
        $this->first->commit();
        app(WriteTransaction::class)->run(function () {
            $this->assertSame(600, $this->second->table('balances')->value('amount'));
            $this->second->table('balances')->where('id', 1)->decrement('amount', 200);
        }, 'lock_second');
        $this->assertSame(400, $this->first->table('balances')->value('amount'));
    }

    public function test_failed_business_transaction_rolls_back_and_releases_its_lock(): void
    {
        try {
            app(WriteTransaction::class)->run(function () {
                $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
                throw new RuntimeException('simulate failure');
            }, 'lock_first');
        } catch (RuntimeException $error) {
            $this->assertSame('simulate failure', $error->getMessage());
        }
        app(WriteTransaction::class)->run(fn () => $this->assertSame(1000, $this->second->table('balances')->value('amount')), 'lock_second');
        $this->assertSame(0, $this->first->transactionLevel());
    }

    public function test_nested_savepoint_rollback_and_after_commit_integrate_with_laravel(): void
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
        $this->assertSame(900, $this->second->table('balances')->value('amount'));
    }

    public function test_transaction_retries_a_lock_timeout_and_reads_the_latest_balance(): void
    {
        $this->holdWriteLock();
        $attempts = 0;
        $this->second->beforeStartingTransaction(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 2) {
                $this->first->table('balances')->where('id', 1)->decrement('amount', 400);
                $this->first->commit();
            }
        });
        app(WriteTransaction::class)->run(fn () => $this->assertSame(600, $this->second->table('balances')->value('amount')), 'lock_second');
        $this->assertSame(2, $attempts);
    }

    public function test_retry_limit_never_runs_the_callback_or_changes_the_balance(): void
    {
        $this->holdWriteLock();
        $called = false;
        try {
            app(WriteTransaction::class)->run(function () use (&$called) {
                $called = true;
            }, 'lock_second', 2);
            $this->fail('Expected lock conflict.');
        } catch (WriteConflict $error) {
            $this->assertNotNull($error->getPrevious());
        }
        $this->assertFalse($called);
        $this->assertSame(0, $this->second->transactionLevel());
        $this->first->commit();
        $this->assertSame(1000, $this->second->table('balances')->value('amount'));
    }

    public function test_after_commit_failure_never_repeats_the_committed_change(): void
    {
        $calls = 0;
        try {
            app(WriteTransaction::class)->run(function () use (&$calls) {
                $calls++;
                $this->first->table('balances')->where('id', 1)->decrement('amount', 100);
                $this->first->afterCommit(function () {
                    $error = new PDOException('Simulated afterCommit failure.');
                    $error->errorInfo = ['HY000', 1205, 'Lock wait timeout'];
                    throw $error;
                });
            }, 'lock_first');
            $this->fail('Expected afterCommit failure.');
        } catch (PDOException $error) {
            $this->assertSame(1205, $error->errorInfo[1]);
        }
        $this->assertSame(1, $calls);
        $this->assertSame(900, $this->second->table('balances')->value('amount'));
    }
}
