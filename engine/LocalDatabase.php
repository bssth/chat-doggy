<?php

/**
 * Class LocalDatabase
 */
class LocalDatabase
{
    /**
     * @var LocalDatabase
     */
    protected static $i;

    /**
     * @var MongoClient
     */
    protected $mongo;

    /**
     * @return LocalDatabase
     */
    public static function i()
    {
        if(!self::$i)
            self::$i = new LocalDatabase;

        return self::$i;
    }

    /**
     * LocalDatabase constructor.
     */
    public function __construct()
    {
        $this->mongo = (new MongoClient('127.0.0.1'))->vkbot;
    }

    /**
     * @param $table
     * @param $query
     * @return array|null
     */
    public function getAll($table, $query)
    {
        return iterator_to_array($this->mongo->{$table}->find($query));
    }

    /**
     * @param $table
     * @param $query
     * @return int
     */
    public function getCount($table, $query)
    {
        return $this->mongo->{$table}->find($query)->count();
    }

    /**
     * @param $table
     * @param $query
     * @return mixed|null
     */
    public function getOne($table, $query)
    {
        return $this->mongo->{$table}->findOne($query);
    }

    /**
     * @param $table
     * @param $array
     * @return null
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    public function insert($table, $array)
    {
        $this->mongo->{$table}->insert($array);
        return isset($array['_id']) ? $array['_id'] : null;
    }

    /**
     * @param $table
     * @param $find
     * @param $apply
     * @return bool
     * @throws MongoCursorException
     */
    public function update($table, $find, $apply)
    {
        return $this->mongo->{$table}->update($find, ['$set' => $apply], ['multiple']);
    }

    /**
     * @param $table
     * @param $query
     * @return array|bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function remove($table, $query)
    {
        return $this->mongo->{$table}->remove($query);
    }
}