<?php
namespace Formapro\Yadm\Tests;

use function Formapro\Values\add_value;
use function Formapro\Values\get_values;
use function Formapro\Values\set_value;
use function Formapro\Values\set_values;
use Formapro\Yadm\ChangesCollector;
use function Formapro\Yadm\set_object_id;
use Formapro\Yadm\Tests\Model\TestObject;
use MongoDB\BSON\ObjectID;
use PHPUnit\Framework\TestCase;

class ChangesCollectorTest extends TestCase
{
    public function testShouldTrackSetValue()
    {
        $obj = $this->createPersistedObject();

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', 'aVal');

        $changes = $collector->changes(get_values($obj), $collector->getOriginalValues($obj));
        $this->assertChangesEquals([
            '$set' => [
                'aKey' => 'aVal',
            ],
        ], $changes, json_encode($changes, JSON_PRETTY_PRINT));

        // 417025ae3572262667ac5686ce5242722228d7011c335d62e760b5337f48db09
    }

    public function testShouldTrackAddedValueToEmptyCollection()
    {
        $obj = $this->createPersistedObject();

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        add_value($obj, 'aKey', 'aVal');

        $this->assertChangesEquals([
            '$set' => [
                'aKey' => ['aVal'],
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldSkipMongoIdField()
    {
        $obj = $this->createPersistedObject();
        set_value($obj, '_id',123);

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, '_id',321);

        $this->assertChangesEquals([], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldUseWholeValuesIfNotRegistered()
    {
        $collector = new ChangesCollector();

        $obj = new TestObject();
        set_value($obj, 'foo','fooVal');
        set_value($obj, 'bar.baz','barVal');

        $this->assertChangesEquals([
            '$set' => [
                'foo' => 'fooVal',
                'bar' => ['baz' => 'barVal'],
            ],
        ], $collector->changes(get_values($obj), []));
    }

    public function testShouldTrackAddedValue()
    {
        $obj = $this->createPersistedObject();
        add_value($obj, 'aKey', 'anOldVal');

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        add_value($obj, 'aKey', 'aVal');

        $this->assertChangesEquals([
            '$push' => [
                'aKey' => [
                    '$each' => ['aVal'],
                ],
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldNotTrackSetValueAndUnsetLater()
    {
        $obj = $this->createPersistedObject();

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', 'aVal');
        set_value($obj, 'aKey', null);

        $this->assertChangesEquals([], $collector->changes(get_values($obj), []));
    }

    public function testShouldTrackUnsetValue()
    {
        $obj = $this->createPersistedObject(['aKey' => 'aVal']);
        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', null);

        $this->assertChangesEquals([
            '$unset' => [
                'aKey' => '',
            ]
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldTrackChangedValue()
    {
        $obj = $this->createPersistedObject(['aKey' => 'aVal']);

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', 'aNewVal');

        $this->assertChangesEquals([
            '$set' => [
                'aKey' => 'aNewVal',
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldTrackStringValueChangedToArrayValue()
    {
        $obj = $this->createPersistedObject(['aKey' => 'aVal']);

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey.fooKey', 'aFooVal');
        set_value($obj, 'aKey.barKey', 'aBarVal');

        $this->assertChangesEquals([
            '$set' => [
                'aKey' => [
                    'fooKey' => 'aFooVal',
                    'barKey' => 'aBarVal',
                ],
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldTrackArrayValueChangedToStringValue()
    {
        $obj = $this->createPersistedObject([
            'aKey' => [
                'fooKey' => 'aFooVal',
                'barKey' => 'aBarVal',
            ]
        ]);

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', 'aVal');

        $this->assertChangesEquals([
            '$set' => [
                'aKey' => 'aVal',
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    public function testShouldFoo()
    {
        $obj = $this->createPersistedObject([
            'aKey' => 'aVal',
        ]);

        $collector = new ChangesCollector();
        $collector->register($obj, get_values($obj));

        set_value($obj, 'aKey', null);
        set_value($obj, 'anotherKey', 'aVal');

        $this->assertChangesEquals([
            '$set' => [
                'anotherKey' => 'aVal',
            ],
            '$unset' => [
                'aKey' => '',
            ],
        ], $collector->changes(get_values($obj), $collector->getOriginalValues($obj)));
    }

    private function assertChangesEquals(array $expected, array $actual): void
    {
        self::assertEquals($expected, $actual, json_encode($actual, JSON_PRETTY_PRINT));
    }

    /**
     * @return object
     */
    private function createPersistedObject(array $values = [])
    {
        $obj = new TestObject();
        set_values($obj, $values);
        set_object_id($obj, new ObjectID());

        return $obj;
    }
}