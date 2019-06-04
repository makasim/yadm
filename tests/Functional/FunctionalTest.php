<?php
namespace Formapro\Yadm\Tests\Functional;

use Formapro\Yadm\ClientProvider;
use Formapro\Yadm\CollectionFactory;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\TestCase;

abstract class FunctionalTest extends TestCase
{
    /**
     * @var Database
     */
    protected $database;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @before
     */
    protected function setUpMongoClient()
    {
        $uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1/yadm_test';

        $this->client = new Client($uri);
        $this->database = $this->client->selectDatabase('yadm_test');

        foreach ($this->database->listCollections() as $collectionInfo) {
            if ('system.indexes' == $collectionInfo->getName()) {
                continue;
            }

            $this->database->dropCollection($collectionInfo->getName());
        }
    }

    protected function getCollectionFactory(): CollectionFactory
    {
        $uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1/yadm_test';

        return new CollectionFactory(new ClientProvider($uri), $uri);
    }
}
