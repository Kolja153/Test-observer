<?php

declare(strict_types=1);

/**
 * Abstract base class for in-memory representation of various business entities.  The only item
 * we have implemented at this point is InventoryItem (see below).
 */
abstract class Entity
{
    /**
     * @var string
     */
    protected $entityName = null;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var int|null
     */
    protected $id = null;

    /**
     * @return void
     * @throws Exception
     */
    public function init()
    {
        if ($this->id === null) {
            throw new Exception("Entity ID is not set.");
        }
    }

    /**
     * @return array
     */
    abstract public function getMembers(): array;

    /**
     * @return string
     */
    abstract public function getPrimary(): string;

    /**
     * Setter for properties and items in the underlying data array
     *
     * @param string $variableName
     * @param $value
     *
     * @return void
     * @throws Exception
     */
    public function __set(string $variableName, $value)
    {
        if (in_array($variableName, $this->getMembers())) {
            $newData = $this->getData();
            $newData[$variableName] = $value;
            $this->setData($newData);
        } else {
            if (property_exists($this, $variableName)) {
                $this->$variableName = $value;
            } else {
                throw new Exception("Set failed. Class " . get_class($this) .
                    " does not have a member named " . $variableName . ".");
            }
        }
    }

    /**
     * Getter for properties and items in the underlying data array
     *
     * @param string $variableName
     *
     * @return mixed
     * @throws Exception
     */
    public function __get(string $variableName)
    {
        if (in_array($variableName, $this->getMembers())) {
            $data = $this->getData();

            return $data[$variableName];
        } else {
            if (property_exists($this, $variableName)) {
                return $this->$variableName;
            } else {

                throw new Exception("Get failed. Class " . get_class($this) .
                    " does not have a member named " . $variableName . ".");
            }
        }
    }

    /**
     * @param string $entityName
     */
    public function setEntityName(string $entityName)
    {
        $this->entityName = $entityName;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return void
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return void
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }
}

class DataStore
{
    /**
     * @var string
     */
    protected $storePath;

    /**
     * @var int
     */
    protected $autoIncrement = 1;

    /**
     * @var array
     */
    protected $dataStore = [];

    /**
     * @throws Exception
     */
    public function __construct(string $storePath)
    {
        $this->storePath = $storePath;

        try {
            $this->ensureFileExists();
            $this->checkPermissions();
            $this->loadData();
        } catch (Exception $e) {
            throw new Exception("Initialization failed: " . $e->getMessage());
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function ensureFileExists()
    {
        if (!file_exists($this->storePath)) {
            if (file_put_contents($this->storePath, '') === false) {
                $message = sprintf('Could not create data store file %s. Details: ', $this->storePath);
                throw new Exception($message . $this->getLastError());
            }
            if (!chmod($this->storePath, 0777)) {
                $message = sprintf('Could not set read/write on data store file %s. Details: ', $this->storePath);
                throw new Exception($message . $this->getLastError());
            }
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function checkPermissions()
    {
        if (!is_readable($this->storePath) || !is_writable($this->storePath)) {

            $message = sprintf('Data store file %s must be readable/writable. Details: ', $this->storePath);
            throw new Exception($message . $this->getLastError());
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadData()
    {
        $rawData = file_get_contents($this->storePath);

        if ($rawData === false) {
            $message = sprintf('Read of data store file %s failed. Details: ', $this->storePath);
            throw new Exception($message . $this->getLastError());
        }

        if (strlen($rawData) > 0) {
            $this->dataStore = @unserialize($rawData);
            if ($this->dataStore === false && $rawData !== 'b:0;') {
                $message = sprintf('Data store file %s appears to be corrupted. Details: ', $this->storePath);
                throw new Exception($message . $this->getLastError());
            }
        }
    }

    /**
     * @return string
     */
    private function getLastError(): string
    {
        $errorInfo = error_get_last();

        if ($errorInfo)
            $errorString = 'Error type ' . $errorInfo['type'] . ' ' . $errorInfo['message'] .
                ' on line ' . $errorInfo['line'] . ' of ' . $errorInfo['file'] . '.';
        else
            $errorString = 'No error information available.';

        return $errorString;
    }

    /**
     * @return integer
     */
    public function getNextAutoIncrement(): int
    {
        return $this->autoIncrement++;
    }

    /**
     * Update the store with information
     *
     * @param string $item
     * @param string $primary
     * @param array $data
     *
     * @return void
     */
    public function set(string $item, string $primary, array $data)
    {
        $this->dataStore[$item][$primary] = $data;
    }

    /**
     * Get information
     *
     * @param string $item
     * @param string $primary
     *
     * @return mixed|null
     */
    public function get(string $item, string $primary)
    {
        return $this->dataStore[$item][$primary] ?? null;
    }

    /**
     * Delete an item.
     *
     * @param string $item
     * @param string $primary
     *
     * @return void
     */
    public function delete(string $item, string $primary)
    {
        if (isset($this->dataStore[$item][$primary])) {
            unset($this->dataStore[$item][$primary]);
        }
    }

    /**
     * Save everything
     *
     * @return void
     * @throws Exception
     */
    public function save()
    {
        $result = file_put_contents($this->storePath, serialize($this->dataStore));
        if (!$result) {
            $message = sprintf('Write of data store file %s failed. Details: ', $this->storePath);
            throw new Exception($message . $this->getLastError());
        }
    }

    /**
     * Which types of items do we have stored
     *
     * @return array
     */
    public function getItemTypes(): array
    {
        return array_keys($this->dataStore);
    }

    /**
     * Get keys for an item-type, so we can loop over.
     *
     * @param string $itemType
     *
     * @return array
     */
    public function getItemKeys(string $itemType): array
    {
        return array_keys($this->dataStore[$itemType]);
    }
}

class EntityManager implements SplSubject
{
    /**
     * @var DataStore
     */
    protected $dataStore;

    /**
     * @var array
     */
    protected $entities = [];

    /**
     * @var array
     */
    protected $entityIdToPrimary = [];

    /**
     * @var array
     */
    protected $entityPrimaryToId = [];

    /**
     * @var array
     */
    protected $entitySaveList = [];

    /**
     * @var array
     */
    protected $observers = [];

    /**
     * @param string $storePath
     *
     * @throws Exception
     */
    public function __construct(string $storePath)
    {
        $this->dataStore = new DataStore($storePath);
        $itemTypes = $this->dataStore->getItemTypes();

        foreach ($itemTypes as $itemType) {
            $itemKeys = $this->dataStore->getItemKeys($itemType);
            foreach ($itemKeys as $itemKey) {
                $this->create($itemType, $this->dataStore->get($itemType, $itemKey));
            }
        }
    }

    /**
     * @param string $entityName
     * @param array $data
     *
     * @return Entity|mixed
     * @throws Exception
     */
    public function getEntity(string $entityName, array $data)
    {
        /** @var Entity $entity */
        $entity = new $entityName;
        $storageEntity = $this->findByPrimary($data[$entity->getPrimary()]);

        if ($storageEntity) {
            return $storageEntity;
        }

        $entity = $this->create($entityName, $data);
        $entity->init();

        return $entity;
    }

    /**
     * @param string $entityName
     * @param array $data
     *
     * @return Entity
     */
    public function create(string $entityName, array $data): Entity
    {
        /** @var Entity $entity */
        $entity = new $entityName;
        $entity->setEntityName($entityName);
        $id = $this->dataStore->getNextAutoIncrement();

        $entity->setData($data);
        $entity->setId($id);
        $this->entities[$id] = $entity;

        $primary = $data[$entity->getPrimary()];
        $this->entityIdToPrimary[$id] = $primary;
        $this->entityPrimaryToId[$primary] = $id;
        $this->entitySaveList[] = $id;

        return $entity;
    }

    /**
     * @param Entity $entity
     * @param array $newData
     *
     * @return Entity
     */
    public function update(Entity $entity, array $newData): Entity
    {
        if ($newData === $entity->getData()) {
            //Nothing to do
            return $entity;
        }

        $oldPrimary = $entity->{$entity->getPrimary()};
        $newPrimary = $newData[$entity->getPrimary()];
        if ($oldPrimary != $newPrimary) {
            $this->dataStore->delete(get_class($entity), $oldPrimary);
            unset($this->entityPrimaryToId[$oldPrimary]);
            $this->entityIdToPrimary[$entity->getId()] = $newPrimary;
            $this->entityPrimaryToId[$newPrimary] = $entity->getId();
        }

        $entity->setData($newData);

        return $entity;
    }

    /**
     * @param Entity $entity
     *
     * @return void
     * @throws Exception
     */
    public function delete(Entity $entity): void
    {
        $id = $entity->getId();
        $key = array_search($id, ($this->entitySaveList));

        if ($key === false) {
            throw new Exception('Cannot delete entity id ' . $id);
        }

        $primary = $entity->{$entity->getPrimary()};

        unset($this->entitySaveList[$key]);
        unset($this->entities[$id]);
        unset($this->entityIdToPrimary[$id]);
        unset($this->entityPrimaryToId[$primary]);

        $this->dataStore->delete(get_class($entity), $primary);
    }

    /**
     * @param string $primary
     * @return mixed|null
     */
    public function findByPrimary(string $primary)
    {
        if (isset($this->entityPrimaryToId[$primary])) {
            $id = $this->entityPrimaryToId[$primary];
            return $this->entities[$id];
        }

        return null;
    }

    /**
     * Update the datastore to update itself and save.
     *
     * @return void
     * @throws Exception
     */
    public function updateStore()
    {
        foreach ($this->entitySaveList as $id) {
            $entity = $this->entities[$id];

            $this->dataStore->set(get_class($entity), $entity->{$entity->getPrimary()}, $entity->data);
        }

        $this->notify();
        $this->dataStore->save();
    }

    /**
     * @param SplObserver $observer
     *
     * @return void
     */
    public function attach(SplObserver $observer)
    {
        if (!in_array($observer, $this->observers, true)) {
            $this->observers[] = $observer;
        }
    }

    /**
     * @param SplObserver $observer
     *
     * @return void
     */
    public function detach(SplObserver $observer)
    {
        foreach ($this->observers as $key => $obs) {
            if ($obs === $observer) {
                unset($this->observers[$key]);
            }
        }
    }

    /**
     * @return void
     */
    public function notify()
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * @return array
     */
    public function getEntitySaveList(): array
    {
        return $this->entitySaveList;
    }

    /**
     * @param int $id
     *
     * @return mixed|null
     */
    public function getEntityById(int $id)
    {
        return $this->entities[$id] ?? null;
    }
}

/**
 * @property int $qoh
 * @property $salePrice
 */
class InventoryItem extends Entity
{
    /**
     * Update the number of items, because we have shipped some.
     *
     * @param int $numberShipped
     *
     * @return void
     * @throws Exception
     */
    public function itemsHaveShipped(int $numberShipped)
    {
        $current = $this->qoh;
        $current -= $numberShipped;

        if ($current < 0) {
            throw new Exception('You cannot ship more items than are in stock');
        }

        $this->qoh = $current;
    }

    /**
     * We received new items, update the count.
     *
     * @param int $numberReceived
     *
     * @return void
     */
    public function itemsReceived(int $numberReceived)
    {
        $this->qoh += $numberReceived;
    }

    /**
     * @param float $salePrice
     *
     * @return void
     * @throws Exception
     */
    public function changeSalePrice(float $salePrice)
    {
        if ($salePrice < 0) {
            throw new Exception('Sale price cannot be negative.');
        }

        $this->salePrice = $salePrice;
    }

    /**
     * These are the field in the underlying data array
     *
     * @return array
     */
    public function getMembers(): array
    {
        return ['sku', 'qoh', 'cost', 'salePrice'];
    }

    /**
     * Which field constitutes the primary key in the storage class
     *
     * @return string
     */
    public function getPrimary(): string
    {
        return "sku";
    }
}

class FileLoggerObserver implements SplObserver
{
    /**
     * @var string
     */
    protected $logFile;

    /**
     * @param string $logFile
     */
    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * @param SplSubject $subject
     * @return void
     */
    public function update(SplSubject $subject)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $subject;
        foreach ($entityManager->getEntitySaveList() as $id) {
            /** @var Entity $entity */
            $entity = $entityManager->getEntityById($id);
            $data = $entity->getData();
            $currentTime = date("Y-m-d H:i:s");
            $logMessage = "[$currentTime] Entity updated: " . json_encode($data) . "\n";
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }
}

class EmailAlertObserver implements SplObserver
{
    const LIMIT_QOH = 5;

    /**
     * @var string
     */
    protected $email;

    /**
     * @param string $email
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * @param SplSubject $subject
     * @return void
     */
    public function update(SplSubject $subject)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $subject;
        foreach ($entityManager->getEntitySaveList() as $id) {
            /** @var Entity $entity */
            $entity = $entityManager->getEntityById($id);
            if (get_class($entity) == 'InventoryItem') {
                $data = $entity->getData();
                if ($data['qoh'] < self::LIMIT_QOH) {
                    $message = "Alert: Inventory item SKU {$data['sku']} has a QOH of {$data['qoh']}.";
                    mail($this->email, "Low Inventory Alert", $message);
                }
            }
        }
    }
}

/**
 * @return void
 * @throws Exception
 */
function driver()
{
    $dataStorePath = "data_store_file.data";
    $entityManager = new EntityManager($dataStorePath);
    $fileLogger = new FileLoggerObserver('log.txt');
    $emailAlert = new EmailAlertObserver('mykola.fedan@gmail.com');
    $entityManager->attach($fileLogger);
    $entityManager->attach($emailAlert);

    $item1 = $entityManager->getEntity('InventoryItem',
        ['sku' => 'abc-4589', 'qoh' => 0, 'cost' => '5.67', 'salePrice' => '7.27']);

    $item2 = $entityManager->getEntity('InventoryItem',
        ['sku' => 'hjg-3821', 'qoh' => 0, 'cost' => '7.89', 'salePrice' => '12.00']);
    $item3 = $entityManager->getEntity('InventoryItem',
        ['sku' => 'xrf-3827', 'qoh' => 0, 'cost' => '15.27', 'salePrice' => '19.99']);
    $item4 = $entityManager->getEntity('InventoryItem',
        ['sku' => 'eer-4521', 'qoh' => 0, 'cost' => '8.45', 'salePrice' => '1.03']);
    $item5 = $entityManager->getEntity('InventoryItem',
        ['sku' => 'qws-6783', 'qoh' => 0, 'cost' => '3.00', 'salePrice' => '4.97']);

    $item1->itemsReceived(4);
    $item2->itemsReceived(2);
    $item3->itemsReceived(12);
    $item4->itemsReceived(20);
    $item5->itemsReceived(1);

    $item3->itemsHaveShipped(5);
    $item4->itemsHaveShipped(16);

    $item4->changeSalePrice(0.87);

    $entityManager->updateStore();
}

driver();
