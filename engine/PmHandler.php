<?php

/**
 * Class VkBotPmHandler
 */
class PmHandler {

    protected $help = '';

    /**
     * PmHandler constructor.
     */
    function __construct() {
        $this->help = file_get_contents(__DIR__ . '/../hom/help.txt');
    }

    /**
     * @param $message
     */
    function handle($message) {
        global $commander;
        
        if(!isset($message['from_id']))
            return;

        $uid = $message['from_id'];

        $text = trim(mb_strtolower($message['text']));

        if(startsWith($text, 'мафия')) {
            global $mafiaGame;
            $mafiaGame->handlePm($uid, $message);
        }
        else if($text == 'стоп')
            $this->stopWorking($uid);
        //else if($text == 'бд')
        //    $this->getStats($uid);
        else if($text == 'умри')
            $this->stopWorking($uid, true);
        else
            $commander->sendPm($uid, "Я воспринимаю только команды :-( \n\n Дополнительная информация находится в группе");
    }

    /**
     * @param $userId
     * @param bool $full
     */
    function stopWorking($userId, $full = false) {
        global $ADMINS, $commander;

        if (!in_array($userId, $ADMINS)) {
            $commander->sendPm($userId, 'Нет прав на остановку');
            return;
        }

        if($full == true) {
            $commander->sendPm($userId, 'Умираю... :_(');
            die;
        }

        $commander->sendPm($userId, 'До скорых встреч =)');
        restartBot();
    }

    /**
     * @param $userId
     */
    function getStats($userId) {
        global $ADMINS, $commander;

        if (!in_array($userId, $ADMINS)) {
            $commander->sendPm($userId, 'Нет прав на проверку базы');
            return;
        }

        $bans = LocalDatabase::i()->getCount('bans', []);
        $likes = LocalDatabase::i()->getCount('likes', []);
        $moders = LocalDatabase::i()->getCount('moderators', []);
        global $userManager;
        $cache = count($userManager->map);
        $admins = count($userManager->admins);

        $commander->sendPm($userId, "СТАТИСТИКА БАЗЫ ДАННЫХ\n\n" .
            "Банов: {$bans} :-( \n" .
            "Лайков: {$likes} <3 \n" .
            "Модеров: {$moders} B-) \n" .
            "Закэшировано {$cache} пользователей и {$admins} админов"
        );
    }
}