<?php

/**
 * Class ModeratorManager
 */
class ModeratorManager {

    /**
     * @param $chatId
     * @param $userId
     * @return mixed|null
     */
    function getByChatIdUserId($chatId, $userId) {
        return LocalDatabase::i()->getOne('moderators', ['chat' => (int)$chatId, 'user' => (int)$userId]);
    }

    /**
     * @param $chatId
     * @param $userId
     * @return array|bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function removeByChatIdUserId($chatId, $userId) {
        return LocalDatabase::i()->remove('moderators', ['chat' => (int)$chatId, 'user' => (int)$userId]);
    }

    /**
     * @param $chatId
     * @param $userId
     * @return bool
     */
    public function isModer($chatId, $userId) {
        return ($this->getPriority($chatId, $userId) >= 0);
    }

    /**
     * @param $chatId
     * @param $userId
     * @return int
     */
    public function getPriority($chatId, $userId) {
        global $ADMINS, $TOP_ADMINS, $userManager;

        if (in_array($userId, $TOP_ADMINS)) {
            return 3;
        } else if (in_array($userId, $ADMINS) || isset($userManager->admins[$chatId][$userId])) {
            return 2;
        }

        if(!is_array($info = $this->getByChatIdUserId($chatId, $userId)))
            return -1;

        return $info['priority'];
    }

    /**
     * @param $chatId
     * @param $userId
     * @param $change
     * @return bool
     * @throws MongoCursorException
     */
    public function changePriority($chatId, $userId, $change) {
        return LocalDatabase::i()->update('moderators', ['chat' => (int)$chatId, 'user' => (int)$userId], ['priority' => (int)$change]);
    }

    /**
     * @param $chatId
     * @param $userId
     * @return null
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    public function add($chatId, $userId) {
        return LocalDatabase::i()->insert('moderators', ['chat' => (int)$chatId, 'user' => (int)$userId, 'priority' => 0]);
    }

    /**
     * @param $chatId
     * @param $userId
     * @param $priority
     * @throws MongoCursorException
     */
    public function setPriority($chatId, $userId, $priority) {
        $this->changePriority($chatId, $userId, $priority);
    }

    /**
     * @param $chatId
     * @return mixed|null
     */
    public function getByChat($chatId) {
        global $userManager;
        $return = LocalDatabase::i()->getAll('moderators', ['chat' => (int)$chatId]);

        foreach($userManager->admins[$chatId] as $adm) {
            $return[] = [
                'chat' => $chatId,
                'user' => $adm,
                'priority' => 2
            ];
        }

        return $return;
    }

    /**
     * @param $query
     * @return mixed|null
     */
    public function get($query) {
        return LocalDatabase::i()->getAll('moderators', $query);
    }

    /**
     * @return mixed|null
     */
    public function getAllOrdered() {
        return LocalDatabase::i()->getAll('moderators', []);
    }

}