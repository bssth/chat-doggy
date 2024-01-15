<?php

define('VK_BOT_BAN_HOUR', 1);
define('VK_BOT_BAN_DAY', 2);
define('VK_BOT_BAN_WEEK', 3);
define('VK_BOT_BAN_MONTH', 4);
define('VK_BOT_BAN_YEAR', 5);
define('VK_BOT_BAN_WHILE', 6);
define('VK_BOT_BAN_FOREVER', 7);

/**
 * Class BanManager
 */
class BanManager {
    /**
     * @return array|null
     */
    function getUsersToUnban() {
        return LocalDatabase::i()->getAll('bans', ['dateEnd' => ['$lt' => time()]]);
    }

    /**
     * @return bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    function checkReturnUnbans() {
        global $commander, $userManager;

        $bans = $this->getUsersToUnban();

        if(!$bans) {
            return false;
        }

        foreach($bans as $ban) {
            if($ban['needReturn'] != 1)
                continue;

            $userId = $ban['id_user'];
            $chatId = $ban['id_chat'];

            $done = $this->removeFromBan($userId, $chatId, TOP_ADMIN_PRIOROTY);

            if ($done) {
                $commander->sendPm($userId, 'Вы были разблокированы в чате и можете вновь присоединиться');
                $commander->chatMessage($chatId, $userManager->printUser($userId) . ' разблокирован по таймеру');
                $commander->addChatUser($chatId, $userId);
            }
        }

        return true;
    }

    /**
     * @return array|bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    function freeUsersToUnban() {
        return LocalDatabase::i()->remove('bans', ['dateEnd' => ['$lt' => time()]]);
    }

    /**
     * @param $idUser
     * @param $idChat
     * @param null $comment
     * @param bool $needReturn
     * @param null $periodType
     * @param null $link
     * @param int $adminPriority
     * @return bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    function addBan($idUser, $idChat, $comment = null, $needReturn = false, $periodType = null, $link = null, $adminPriority = 0) {
        switch ($periodType) {
            case VK_BOT_BAN_WHILE: $period = 600; break;
            case VK_BOT_BAN_HOUR: $period = 3600; break;
            case VK_BOT_BAN_DAY: $period = 86400; break;
            case VK_BOT_BAN_YEAR: $period = 1036800; break;
            case VK_BOT_BAN_FOREVER: $period = 10368000; break;
            case VK_BOT_BAN_MONTH: $period = 2592000; break;
            case VK_BOT_BAN_WEEK: $period = 604800; break;
            default: $period = $periodType;
        }

        $item = $this->getBanned($idUser, $idChat);
        if ($item) {
            if ($item['priority'] <= $adminPriority) {
                $item['dateEnd'] = time() + $period;
                $item['comment'] = htmlspecialchars($comment);
                $item['dateAdd'] = time();
                $item['priority'] = $adminPriority;
                LocalDatabase::i()->update('bans', ['_id' => $item['_id']], $item);
            }
            else
                return false;
        } else {
            $item = [];
            $item['dateEnd'] = time()+$period;
            $item['comment'] = htmlspecialchars($comment);
            $item['dateAdd'] = time();
            $item['priority'] = $adminPriority;
            $item['link'] = $link;
            $item['needReturn'] = $needReturn;
            $item['id_user'] = (int)$idUser;
            $item['id_chat'] = (int)$idChat;

            LocalDatabase::i()->insert('bans', $item);
        }
        return true;
    }

    /**
     * @param $ids
     * @return array|null
     */
    public function getByIdList($ids) {
        return LocalDatabase::i()->getAll('bans', ['id_user' => ['$in' => $ids]]);
    }

    /**
     * @param $userId
     * @param $chatId
     * @param int $priority
     * @return array|bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function removeFromBan($userId, $chatId, $priority = 0) {
        return LocalDatabase::i()->remove('bans', ['id_user' => $userId, 'id_chat' => $chatId]);
    }

    /**
     * @param $idUser
     * @return array|bool
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    public function clearBan($idUser) {
        return LocalDatabase::i()->remove('bans', ['id_user' => $idUser]);
    }

    /**
     * @param $idUser
     * @param $idChat
     * @return mixed|null
     */
    public function getBanned($idUser, $idChat) {
        return LocalDatabase::i()->getOne('bans', ['id_user' => $idUser, 'id_chat' => $idChat]);
    }

    /**
     * @param $idChat
     * @return mixed|null
     */
    public function banList($idChat) {
        return LocalDatabase::i()->getAll('bans', ['id_chat' => $idChat]);
    }

    /**
     * @throws Exception
     */
    public function getByInfoList() {
        throw new Exception('Deprecated getByInfoList');
    }

    /**
     * @param $uid
     * @return array|null
     */
    public function getByUserId($uid) {
        return LocalDatabase::i()->getAll('bans', ['id_user' => $uid]);
    }
}