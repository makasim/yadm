<?php
namespace Formapro\Yadm\Tests\Functional;

use Formapro\Yadm\PessimisticLock;
use Formapro\Yadm\PessimisticLockException;

class PessimisticLockTest extends FunctionalTest
{
    public function setUp()
    {
        parent::setUp();

        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $lockCollection->drop();
        $this->database->createCollection('storage_lock_test');
    }

    public function testWaitForLockIsReleased()
    {
        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $pessimisticLock = new PessimisticLock($lockCollection);
        $pessimisticLock->createIndexes();

        $startTime = microtime(true);

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () use ($pessimisticLock) {
            $pessimisticLock->unlock('5669dd8f56c02c4628031635');
        });

        pcntl_alarm(3);

        $pessimisticLock->lock('5669dd8f56c02c4628031635');

        usleep(1);
        $anotherPessimisticLock = new PessimisticLock($lockCollection);
        $anotherPessimisticLock->lock('5669dd8f56c02c4628031635');

        $endTime = microtime(true);

        self::assertGreaterThanOrEqual(2, $endTime - $startTime);
        self::assertLessThan(4, $endTime - $startTime);
    }

    public function testShouldNotWaitForLockIfBlockingFalse()
    {
        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $pessimisticLock = new PessimisticLock($lockCollection);
        $pessimisticLock->createIndexes();

        $pessimisticLock->lock('5669dd8f56c02c4628031635');

        usleep(1);
        $anotherPessimisticLock = new PessimisticLock($lockCollection);

        $this->expectException(PessimisticLockException::class);
        $anotherPessimisticLock->lock('5669dd8f56c02c4628031635', false);
    }

    public function testShouldAllowMultipleLocksInOneProcess()
    {
        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $pessimisticLock = new PessimisticLock($lockCollection);
        $pessimisticLock->createIndexes();

        $pessimisticLock->lock('1');
        $pessimisticLock->lock('1', true, 2);

        $this->assertTrue(true);
    }

    /**
     * @expectedException \Formapro\Yadm\PessimisticLockException
     * @expectedExceptionMessage Cannot obtain the lock for id "2". Timeout after 2 seconds
     */
    public function testWaitForLockIsNotReleased()
    {
        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $pessimisticLock = new PessimisticLock($lockCollection);
        $pessimisticLock->createIndexes();
        $pessimisticLock->lock('2');

        usleep(200);

        $anotherPessimisticLock = new PessimisticLock($lockCollection);
        $anotherPessimisticLock->lock('2', true, 2);
    }
}