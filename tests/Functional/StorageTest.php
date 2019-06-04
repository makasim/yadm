<?php
namespace Formapro\Yadm\Tests\Functional;

use function Formapro\Yadm\get_object_id;
use Formapro\Yadm\Hydrator;
use Formapro\Yadm\PessimisticLock;
use Formapro\Yadm\Storage;
use MongoDB\BSON\ObjectID;
use MongoDB\InsertOneResult;

class StorageTest extends FunctionalTest
{
    public function testCreateModel()
    {
        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator);

        $model = $storage->create();

        self::assertInstanceOf(Model::class, $model);
        self::assertEquals([], $model->values);
    }

    public function testInsertModel()
    {
        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator);

        $model = new Model();
        $model->values = ['foo' => 'fooVal', 'bar' => 'barVal', 'ololo' => ['foo', 'foo' => 'fooVal']];

        $result = $storage->insert($model);

        self::assertInstanceOf(InsertOneResult::class, $result);
        self::assertTrue($result->isAcknowledged());

        self::assertArrayNotHasKey('_id', $model->values);
        self::assertInstanceOf(ObjectID::class, get_object_id($model, true));

        $foundModel = $storage->findOne(['_id' => get_object_id($model)]);

        self::assertInstanceOf(Model::class, $foundModel);
        self::assertEquals($model->values, $foundModel->values);
    }

    public function testUpdateModel()
    {
        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator);

        $model = new Model();
        $model->values = ['foo' => 'fooVal', 'bar' => 'barVal'];

        $result = $storage->insert($model);

        //guard
        self::assertTrue($result->isAcknowledged());

        $model->values['ololo'] = 'ololoVal';

        $result = $storage->update($model);

        //guard
        self::assertTrue($result->isAcknowledged());

        $foundModel = $storage->findOne(['_id' => get_object_id($model)]);

        self::assertInstanceOf(Model::class, $foundModel);
        self::assertEquals($model->values, $foundModel->values);
    }

    public function testDeleteModel()
    {
        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator);

        $model = new Model();
        $model->values = ['foo' => 'fooVal', 'bar' => 'barVal'];

        $result = $storage->insert($model);

        //guard
        self::assertTrue($result->isAcknowledged());

        $result = $storage->delete($model);

        //guard
        self::assertTrue($result->isAcknowledged());

        self::assertNull($storage->findOne(['_id' => get_object_id($model)]));
    }

    public function testUpdateModelPessimisticLock()
    {
        $lockCollection = $this->database->selectCollection('storage_lock_test');
        $pessimisticLock = new PessimisticLock($lockCollection);
        $pessimisticLock->createIndexes();

        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator, null, $pessimisticLock);

        $model = new Model();
        $model->values = ['foo' => 'fooVal', 'bar' => 'barVal'];

        $result = $storage->insert($model);

        //guard
        self::assertTrue($result->isAcknowledged());

        $storage->lock(get_object_id($model), function($lockedModel, $storage) use ($model) {
            self::assertInstanceOf(Model::class, $lockedModel);
            self::assertEquals($model->values, $lockedModel->values);

            self::assertInstanceOf(Storage::class, $storage);

            $model->values['ololo'] = 'ololoVal';

            $result = $storage->update($model);

            //guard
            self::assertTrue($result->isAcknowledged());
        });

        $foundModel = $storage->findOne(['_id' => get_object_id($model)]);

        self::assertInstanceOf(Model::class, $foundModel);
        self::assertEquals($model->values, $foundModel->values);
    }

    public function testFindModels()
    {
        $hydrator = new Hydrator(Model::class);

        $storage = new Storage('storage_test', $this->getCollectionFactory(), $hydrator);

        $result = $storage->find([]);

        self::assertInstanceOf(\Traversable::class, $result);
        self::assertCount(0, iterator_to_array($result));

        $storage->insert(new Model());
        $storage->insert(new Model());
        $storage->insert(new Model());

        $result = $storage->find([]);

        self::assertInstanceOf(\Traversable::class, $result);
        $data = iterator_to_array($result);

        self::assertCount(3, $data);
        self::assertContainsOnly(Model::class, $data);
    }
}

class Model
{
    public $values = [];
    public $hookId;
}