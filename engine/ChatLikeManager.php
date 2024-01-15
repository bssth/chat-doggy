<?php

/**
 * Class ChatLikeManager
 */
class ChatLikeManager {

    /**
     * @param $userId
     * @param $userTo
     * @param $chatId
     * @return mixed|null
     */
    public function getItem($userId, $userTo, $chatId) {
        return LocalDatabase::i()->getOne('likes', ['chat' => $chatId, 'from' => $userId, 'to' => $userTo]);
    }

    /**
     * @param $chatId
     * @return array|null
     */
    public function getByChat($chatId)
    {
        return LocalDatabase::i()->getAll('likes', ['chat' => $chatId]);
    }

    /**
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function clearExpired() {
        LocalDatabase::i()->remove('likes', ['del' => ['$lt' => time()]]);
    }

    /**
     * @param $marks
     * @return string
     */
    public function getRank($marks)
    {
        if($marks == 666)
            return 'САТАНА';

        if($marks >= 800)
            return 'Без личной жизни';

        if($marks >= 500)
            return 'Your Master';

        if($marks >= 300)
            return 'Богоподобный';

        if($marks >= 150)
            return 'Ветеран';

        if($marks >= 100)
            return 'Элитный';

        if($marks >= 50)
            return 'Бывалый';

        if($marks >= 15)
            return 'Участник';

        return 'Ньюфаг';
    }

    /**
     * @param $chatId
     * @param $userId
     * @return int
     */
    public function getCount($chatId, $userId)
    {
        return count(LocalDatabase::i()->getAll('likes', ['chat' => $chatId, 'to' => $userId]));
    }

    /**
     * @param $chatId
     * @param $from
     * @param $to
     * @return bool
     */
    public function canLike($chatId, $from, $to)
    {
        if($from == 0 || $from == BOT_ID)
            return true;

        return !count(LocalDatabase::i()->getAll('likes', ['chat' => $chatId, 'from' => $from, 'to' => $to, 'date' => ['$gt' => time()-86401]]));
    }

    /**
     * @param $chatId
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function clearChat($chatId) {
        LocalDatabase::i()->remove('likes', ['chat' => $chatId]);
    }

    /**
     * @param $userId
     * @param $userTo
     * @param $chatId
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
     public function increaseLike($userId, $userTo, $chatId) {
        LocalDatabase::i()->insert('likes', ['chat' => $chatId, 'from' => $userId, 'to' => $userTo, 'del' => time()+86400*7*8, 'date' => time()]);
     }
}