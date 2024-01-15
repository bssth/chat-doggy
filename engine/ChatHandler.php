<?php

/**
 * Class VkBotChatHandler
 */
class ChatHandler {

    /**
     * @var array
     */
    protected $cached_chats = [];

    /**
     * @param $message
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    function handle($message) {
        global $commander, $moderManager, $userManager;

        $chatId = normalizeChatId($message['peer_id']);
        $text = trim(mb_strtolower($message['text']));

        if(!isset($this->cached_chats[$chatId])) {
            $userManager->rebuildIfNeeded($chatId);
            $this->cached_chats[$chatId] = true;
        }

        $adminPriority = $moderManager->getPriority($chatId, $message['from_id']);

        if (in_array($text, ['кто здесь власть?', 'кто здесь власть', 'кто админы', 'кто модеры'])) {
            $this->printModers($chatId);
            return;
        }

        if (in_array($text, ['я здесь власть', 'я здесь власть!', 'кто я'])) {
            $this->printIamModer($message['from_id'], $chatId);
            return;
        }

        if(in_array($text, ['кто ты', 'кто ты?'])) {
            $this->whoAreYou($text, $message, $chatId);
            return;
        }

        if(normalizeChatId($message['peer_id']) == 4) {
            global $mafiaGame;

            if($mafiaGame->handleMafiaChat(normalizeChatId($message['peer_id']), $message['from_id'], $message, $adminPriority) == true)
                return;
        }

        if (in_array($text, array('лайк', 'спасибо, подрочил', 'класс', 'сас', 'у меня встал', 'насосала', 'f', 'плюс', "плюсик", 'sieg heil', "увожение", "уважение", "поддерживаю", '+', 'респект', 'моё увожение', 'мое увожение', 'моё уважение', 'мое уважение', 'meine respektierung'))) {
            $this->setLike($message['from_id'], $chatId, $message);
            return;
        }

        if ($text == 'бан-лист' || $text == 'бан лист') {
            $this->banList($chatId);
            return;
        }

        if ($adminPriority == -1)
            return;

        $passed = true;

        if ($text == 'чс' || startsWith($text, 'чс '))
            $this->banUser($text, normalizeChatId($message['peer_id']), $message, $adminPriority);
        else if ($text == 'вернуть' || $text == 'амнистия' || startsWith($text, 'вернуть '))
            $this->unbanUser($text, normalizeChatId($message['peer_id']), $message, $adminPriority);
         else if ($text == 'исключить' || $text == 'ухади' || $text == 'кто прочитал тот здохнет')
            $this->kickUser($text, normalizeChatId($message['peer_id']), $message, $adminPriority);
        else if ($text == 'причина' || startsWith($text, 'причина '))
            $this->getReason($text, normalizeChatId($message['peer_id']));
        else if ($text == 'рейтинг')
            $this->printRating($chatId);
        else
            $passed = false;

        if ($passed)
            return;

        if ($adminPriority < 2)
            return;
        else if (startsWith($text, 'добавить модера'))
            $this->moderAdd($message['from_id'], $text, $message);
        else if (startsWith($text, 'исключить модера'))
            $this->moderRemove($message['from_id'], $text, $message);
        else if (startsWith($text, 'понизить модера'))
            $this->moderDown($text, $message);
        else if (startsWith($text, 'повысить модера'))
            $this->moderUp($text, $message);
        else if (startsWith($text, 'закреп') || startsWith($text, 'закрепить'))
            $this->setPin($chatId, $message);
        else if (startsWith($text, 'кэш')) {
            if (!$this->isNeedRulesTimer())
                return;
            $this->updateRulesTimer();

            $userManager->rebuildUserMap($chatId);
            $commander->chatMessage($chatId, "Кэш беседы обновлен B-)");
        }
    }

    /**
     * @param $text
     * @param $chatId
     */
    function getReason($text, $chatId) {
        global $banManager, $commander;

        $info = $this->getUserInfoByMessage($text, null, 'причина ');
        if (!$info)
            return;

        $user = $banManager->getBanned($info['id'], $chatId);
        if (!$user) {
            $commander->chatMessage($chatId, 'Пользователь ' . $info['first_name'] . ' ' . $info['last_name'] . ' не забанен');
            return;
        }

        $commander->chatMessage($chatId, "Пользователь @id" . $info['id'] . '(' . $info['first_name'] . ' ' . $info['last_name'] . ") забанен\nдо " . $user['dateEnd']->format(GERMAN_DATETIME_FORMAT)
            . ($user['comment']? " Причина: " . $user['comment'] : ""));
    }

    /**
     * @param $chatId
     */
    function banList($chatId) {
        global $banManager, $commander, $userManager;

        $list = $banManager->banList($chatId);
        $text = 'Найдено '.count($list).' банов. Последние заблокированные:'.PHP_EOL.PHP_EOL;

        foreach($list as $k => $ban)
        {
            if($k >= 5)
                break;

            $text .= '⛔ '.$userManager->printUser($ban['id_user']).PHP_EOL;
        }

        $commander->chatMessage($chatId, $text);
    }

    /**
     * @param $chatId
     * @param $message
     * @return bool
     */
    function setPin($chatId, $message)
    {
        global $commander;

        if(!isset($message['fwd_messages']))
            return false;

        $commander->pinMessage($chatId, $message['fwd_messages'][0]['id']);
        return true;
    }

    /**
     * @param $text
     * @param $chatId
     * @param $message
     * @param $priority
     */
    function unbanUser($text, $chatId, $message, $priority) {
        global $banManager, $commander;

        $uid = null;

        if (isset($message['fwd_messages']))
            $uid = $message['fwd_messages'][0]['from_id'];

        $data = explode(' ', $text);
        $link = $data[sizeof($data) - 1];
        if (startsWith($link, 'https://')) {
            $userLink = getUserLink($link);
            if ($userLink) {
                $res = $commander->getUsersInfo(array(getUserLink($link)));
                if (sizeof($res) > 0) {
                    $uid = $res[0]['id'];
                }
            }
        }

        if (!$uid)
            return;

        if (sizeof($data) == 3 && mb_strtolower($data[1]) == 'везде') {
            $items = $banManager->getByUserId($uid);
            $size = sizeof($items) - 1;
            foreach ($items as $i => $it) {
                $this->unbanLocal($uid, $it['id_chat'], $priority);
                if ($i < $size)
                    sleep(1);
            }
        } else
            $this->unbanLocal($uid, $chatId, $priority);
    }

    /**
     * @param $text
     * @param $chatId
     * @param $message
     * @param $adminPriority
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    function banUser($text, $chatId, $message, $adminPriority) {
        global $commander;

        $uid = null;
        if (isset($message['fwd_messages']) && count($message['fwd_messages']))
            $uid = $message['fwd_messages'][0]['from_id'];

        $text = explode("\n", $text);
        $data = explode(' ', $text[0]);
        $link = $data[sizeof($data) - 1];
        if (startsWith($link, 'https://')) {
            $userLink = getUserLink($link);
            if ($userLink) {
                $res = $commander->getUsersInfo(array(getUserLink($link)));
                if (sizeof($res) > 0) {
                    $uid = $res[0]['id'];
                }
            }
        } else
            $link = null;

        if (!$uid)
            return;

        $targetPriority = (new ModeratorManager)->getPriority($chatId, $uid);

        if (($uid == BOT_ID || $adminPriority < $targetPriority) && $message['from_id'] != $uid) {
            $commander->chatMessage($chatId, 'Ранг модератора недостаточен, чтобы заблокировать старшего модератора');
            return;
        }

        $paramsSize = sizeof($data) - ($link !== null) - 1;
        $banEverywhere = false;
        if ($paramsSize ) {
            $param = $data[1];
            $type = self::convertBanType($param);

            if (!$type) {
                $commander->chatMessage($chatId, "Непонятная команда.\nФормат \"чс [остыть|час|сутки|неделя|месяц|год|прощай]\" ссылка");
                return;
            }

            if ($paramsSize >= 2) {
                $banEverywhere = $data[2] == 'везде';
            }
        } else
            $type = VK_BOT_BAN_FOREVER;
        if (sizeof($text) > 1) {
            $comment = substr($message['text'], strlen($text[0] . "\n"));
        } else
            $comment = null;

        global $banManager, $userManager;

        $typeText = $this->banTypeText($type);
        if ($banEverywhere) {
            $commander->chatMessage($chatId, 'Схема \'везде\' более не поддерживается');
        } else {
            $res = $banManager->addBan($uid, $chatId, $comment, true, $type, $link, $adminPriority);
            if ($res && $message['from_id'] != $uid)
                $commander->chatMessage($chatId, $userManager->printUser($uid) .', бан на ' . $typeText . ($comment? "\nПричина: " . $comment : ''));
            else if($res && $message['from_id'] == $uid)
                $commander->chatMessage($chatId, $userManager->printUser($uid) . ', самобан на ' . $typeText . ($comment? "\nПричина: " . $comment : ''));
            else
                $commander->chatMessage($chatId, 'Пользователь уже забанен. Для перебана вашего ранга недостаточно');

            $commander->chatBan($uid, $chatId);
        }

    }

    /**
     * @param $text
     * @param $chatId
     * @param $message
     * @param $adminPriority
     * @param bool $force
     */
    function kickUser($text, $chatId, $message, $adminPriority, $force = false) {
        $uid = null;
        if (isset($message['fwd_messages']))
            $uid = $message['fwd_messages'][0]['from_id'];

        $text = explode("\n", $text);
        $data = explode(' ', $text[0]);
        $link = $data[sizeof($data) - 1];

        global $commander;

        if (startsWith($link, 'https://')) {
            $userLink = getUserLink($link);
            if ($userLink) {
                $res = $commander->getUsersInfo(array(getUserLink($link)));
                if (sizeof($res) > 0) {
                    $uid = $res[0]['id'];
                }
            }
        } else
            $link = null;



        if (!$uid)
            return;
        if ($message['from_id'] == $uid)
            return;

        $targetPriority = (new ModeratorManager)->getPriority($chatId, $uid);

        if ($force == false && ($targetPriority == TOP_ADMIN_PRIOROTY || $adminPriority < $targetPriority)) {
            $commander->chatMessage($chatId, 'Ранг модератора недостаточен, чтобы исключить старшего модератора');
            return;
        }

        global $userManager;
        $commander->chatMessage($chatId, $userManager->printUser($uid) . ' исключен из беседы');
        $commander->removeChatUsers($chatId, $uid);
    }

    /**
     * @param $type
     * @return string
     */
    function banTypeText($type) {
        switch ($type) {
            case VK_BOT_BAN_WHILE: return '10 минут';
            case VK_BOT_BAN_HOUR: return 'час';
            case VK_BOT_BAN_DAY: return 'сутки';
            case VK_BOT_BAN_WEEK: return 'неделю';
            case VK_BOT_BAN_MONTH: return 'месяц';
            case VK_BOT_BAN_FOREVER: return 'веки вечные';
            case VK_BOT_BAN_YEAR: return 'год';

        }
        return 'неопределённый период';
    }

    /**
     * @param $type
     * @return int
     */
    static function convertBanType($type) {
        if ($type == 'остыть' || $type == 'остынь')
            return VK_BOT_BAN_WHILE;
        if ($type == 'час')
            return VK_BOT_BAN_HOUR;
        if ($type == 'день' || $type == 'сутки')
            return VK_BOT_BAN_DAY;
        if ($type == 'неделя')
            return VK_BOT_BAN_WEEK;
        if ($type == 'месяц')
            return VK_BOT_BAN_MONTH;
        if ($type == 'год')
            return VK_BOT_BAN_YEAR;
        if ($type == 'прощай')
            return VK_BOT_BAN_FOREVER;
        return 0;
    }

    var $rulesTimer;

    /**
     * @return bool
     */
    private function isNeedRulesTimer() {
        return ($this->rulesTimer + 30 <= time());
    }

    private function updateRulesTimer() {
        $this->rulesTimer = time();
    }

    /**
     * @param $adminId
     * @param $text
     * @param $message
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    private function moderAdd($adminId, $text, $message) {
        global $moderManager, $commander, $userManager;

        $uid = $this->getUserIdByMessage($text, $message, 'добавить модера ');

        if (!$uid)
            return;
        $chatId = normalizeChatId($message['peer_id']);

        $item = $moderManager->isModer($chatId, $uid);
        if ($item) {
            $commander->chatMessage($chatId, 'Модератор уже был назначен');
            return;
        }

        $moderManager->add($chatId, $uid);

        $commander->chatMessage($chatId, 'Модератор '.$userManager->printUser($uid).' назначен');
    }

    /**
     * @param $adminId
     * @param $text
     * @param $message
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    private function moderRemove($adminId, $text, $message) {
        global $moderManager, $commander, $userManager;

        $uid = $this->getUserIdByMessage($text, $message, 'исключить модера ');

        if (!$uid)
            return;
        $chatId = normalizeChatId($message['peer_id']);

        if(!$moderManager->isModer($chatId, $uid)) {
            $commander->chatMessage($chatId, 'Он и не был модером');
            return;
        }

        $moderManager->removeByChatIdUserId($chatId, $uid);

        $commander->chatMessage($chatId, 'Модератор '.$userManager->printUser($uid).' исключён');
    }

    /**
     * @param $text
     * @param $message
     * @throws MongoCursorException
     */
    private function moderUp($text, $message) {
        global $moderManager, $commander, $userManager;
        $uid = $this->getUserIdByMessage($text, $message, 'повысить модера ');
        if (!$uid)
            return;

        $chatId = normalizeChatId($message['peer_id']);

        if (!$moderManager->isModer($chatId, $uid)) {
            $commander->chatMessage($chatId, 'Это не модератор!');
            return;
        }

        $moderManager->setPriority($chatId, $uid, 1);
        $commander->chatMessage($chatId, 'Модератору '.$userManager->printUser($uid).' поднят приоритет');
    }

    /**
     * @param $text
     * @param $message
     * @throws MongoCursorException
     */
    private function moderDown($text, $message) {
        global $moderManager, $commander, $userManager;

        $uid = $this->getUserIdByMessage($text, $message, 'понизить модера ');
        if (!$uid)
            return;
        $chatId = normalizeChatId($message['peer_id']);

        if (!$moderManager->isModer($chatId, $uid)) {
            $commander->chatMessage($chatId, 'Это не модератор!');
            return;
        }

        $moderManager->setPriority($chatId, $uid, 0);

        $commander->chatMessage($chatId, 'Модератору '.$userManager->printUser($uid).' снижен приоритет');
    }

    /**
     * @param $text
     * @param $message
     * @param $commandPart
     * @return bool|null|string
     */
    private function getUserIdByMessage($text, $message, $commandPart) {
        global $commander;

        $uid = null;
        if ($message && isset($message['fwd_messages']) && count($message['fwd_messages'])) {
            return $message['fwd_messages'][0]['from_id'];
        } else {
            $link = trim(mb_substr($text, mb_strlen($commandPart)));
            if (startsWith($link, 'https://')) {
                $userLink = getUserLink($link);
                if ($userLink) {
                    if (startsWith($userLink, 'id'))
                        return substr($userLink, 2);
                    $res = $commander->getUsersInfo(array($userLink));
                    if (sizeof($res) > 0) {
                        return $res[0]['id'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param $text
     * @param $message
     * @param $commandPart
     * @return null
     */
    private function getUserInfoByMessage($text, $message, $commandPart) {
        global $commander;

        $uid = null;
        if ($message && isset($message['fwd_messages'])) {
            $uid = $message['fwd_messages'][0]['from_id'];
        } else {
            $link = trim(mb_substr($text, mb_strlen($commandPart)));
            if (startsWith($link, 'https://')) {
                $userLink = getUserLink($link);
                if ($userLink) {
                    if (startsWith($userLink, 'id'))
                        $uid = substr($userLink, 2);
                    else
                        $uid = $userLink;
                }
            }
        }
        $res = $commander->getUsersInfo(array($uid));
        if (sizeof($res) > 0)
            return $res[0];
        return null;
    }

    /**
     * @param $chatId
     */
    private function printModers($chatId) {
        global $commander, $TOP_ADMINS, $userManager, $moderManager;

        $moderIds = $moderManager->getByChat($chatId);

        if (sizeof($moderIds))
            $moderIds = array_values($moderIds);
        else
            $moderIds = [];

        $message = '';

        if (sizeof($moderIds)) {
            $message .= "☑️ МОДЕРАТОРЫ:\n";

            foreach($moderIds as $k => $v) {
                if($v['priority'] >= 2)
                    continue;

                $user = $userManager->getUser($v['user']);
                $older = ($v['priority'] >= 1) ? '(старший)' : '';
                $message .= '👉 '. $user['first_name'] . ' ' . $user['last_name'] . " {$older} https://vk.com/" . $user['domain'] . "\n";
            }

            $message .= "\n";
        }

        if (sizeof($TOP_ADMINS)) {
            $message .= "\n☑️ АДМИНИСТРАТОРЫ:\n";

            foreach($moderIds as $k => $v) {
                if($v['priority'] < 2 || $v['user'] == 483377226 ||$v['user'] == BOT_ID || $v['user'] == -GROUP_ID)
                    continue;

                $user = $userManager->getUser($v['user']);
                $message .= '👉 '. $user['first_name'] . ' ' . $user['last_name'] . " https://vk.com/" . $user['domain'] . "\n";
            }
        }

        $commander->chatMessage($chatId, $message);
    }

    /**
     * @param $userId
     * @param $chatId
     */
    private function printIamModer($userId, $chatId) {
        global $commander, $likeMgr;

        $adminPriority = (new ModeratorManager)->getPriority($chatId, $userId);
        $message = '';

        if($sp = $this->getSpecial($userId))
            $message .= $sp . ' | ';

        switch ($adminPriority) {
            case -1 :
                $message .= 'Ты здесь просто участник';
                break;
            case 0 :
                $message .= 'Ты модератор беседы ⭐';
                break;
            case 1 :
                $message .= 'Ты старший модератор беседы ⭐⭐';
                break;
            case 2 :
                $message .= 'Ты сверхмодератор ⭐⭐⭐';
                break;
            case 3 :
                $message .= 'Ты одмен ⭐⭐⭐⭐';
                break;
            default :
                $message .= 'Я не знаю, кто ты такой';
        }

        $likes = $likeMgr->getCount($chatId, $userId);
        $rank = $likeMgr->getRank($likes);
        $message .= PHP_EOL.PHP_EOL."Ранг: {$rank} ({$likes} лайков)";

        $commander->chatMessage($chatId, $message);
    }

    var $answersTimer = [];

    /**
     * @param $uid
     * @return bool
     */
    private function isNeedAnswer($uid) {
        if (!isset($this->answersTimer[$uid])) return true;
        return ($this->answersTimer[$uid] + 5 <= time());
    }

    /**
     * @param $uid
     */
    private function updateAnswer($uid) {
        $this->answersTimer[$uid] = time();
    }

    /**
     * @param $text
     * @param $message
     * @param $chatId
     */
    private function whoAreYou($text, $message, $chatId) {
        global $likeMgr;

        if (!isset($message['fwd_messages']))
            return;

        $userId = $message['fwd_messages'][0]['from_id'];
        $adminPriority = (new ModeratorManager)->getPriority($chatId, $userId);
        $message = '';

        if($sp = $this->getSpecial($userId))
            $message .= $sp . ' | ';

        switch ($adminPriority) {
            case -1 :
                $message .= 'Это просто участник беседки';
                break;
            case 0 :
                $message .= 'Это модератор беседы ⭐';
                break;
            case 1 :
                $message .= 'Это старший модератор беседы ⭐⭐';
                break;
            case 2 :
                $message .= 'Это сверхмодератор ⭐⭐⭐';
                break;
            case 3 :
                $message .= 'Это одмен ⭐⭐⭐⭐';
                break;
            default:
                $message .= 'Я не знаю, кто это :-(';
        }

        $likes = $likeMgr->getCount($chatId, $userId);
        $rank = $likeMgr->getRank($likes);
        $message .= PHP_EOL.PHP_EOL."Ранг: {$rank} ({$likes} лайков)";

        global $commander;
        $commander->chatMessage($chatId, $message);
    }

    /**
     * @param $userId
     * @param $chatId
     * @param $priority
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     */
    private function unbanLocal($userId, $chatId, $priority)
    {
        global $userManager, $banManager, $commander;

        $done = $banManager->removeFromBan($userId, $chatId, $priority);

        if ($done) {
            $commander->sendPm($userId, 'Вы были разблокированы в чате и можете вновь присоединиться по ссылке (или можете попросить кого-нибудь вас добавить)');
            $commander->chatMessage($chatId, 'Блокировка с '.$userManager->printUser($userId).' снята');
            $commander->addChatUser($chatId, $userId);
        } else {
            $item = $banManager->getBanned($userId, $chatId);

            if ($item)
                $commander->chatMessage($chatId, 'Уровень модератора не достаточен для разбана. Обратитесь к старшим модерам.');
        }
    }
    
    /**
     * @param $uid
     * @return null
     */
    private function getSpecial($uid)
    {
        $specials = parse_ini_file(__DIR__ . '/special_names.ini');
        return isset($specials[$uid]) ? $specials[$uid] : null;
    }

    /**
     * @param $userId
     * @param $chatId
     * @param $message
     * @throws MongoCursorException
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     */
    private function setLike($userId, $chatId, $message) {
        global $commander, $likeMgr;

        if (!isset($message['fwd_messages']) || !count($message['fwd_messages']) || !isset($message['fwd_messages'][0]['from_id']))
            return;

        $userTo = $message['fwd_messages'][0]['from_id'];

        if($userTo < 0) {
            $commander->chatMessage($chatId, 'Нельзя лайкать сообщества');
            return;
        }

        if($userId == $userTo) {
            $commander->chatMessage($chatId, 'Самолайк - залог успеха :-D');
            return;
        }

        if(!$likeMgr->canLike($chatId, $userId, $userTo)) {
            $commander->chatMessage($chatId, 'Вы уже лайкали этого участника сегодня. Подождите сутки');
            return;
        }

        $likeMgr->increaseLike($userId, $userTo, $chatId);
        $commander->chatMessage($chatId, 'Репутация повышена');
    }

    /**
     * @param $chatId
     */
    private function printRating($chatId) {
        global $commander, $likeMgr, $userManager;

        $likes = $likeMgr->getByChat($chatId);
        $usrs = [];

        foreach($likes as $num => $like) {
            if(!isset($like['to']))
                continue;

            if(!isset($usrs[$like['to']]))
                $usrs[$like['to']] = 1;
            else
                $usrs[$like['to']]++;
        }

        arsort($usrs);

        $message = "ТОП-15 САМЫХ УВАЖАЕМЫХ УЧАСТНИКОВ:".PHP_EOL.PHP_EOL;

        if(!count($usrs))
            $message .= "Пока пусто :-(";
        else {
            $index = 0;

            foreach($usrs as $usr => $cnt) {
                if($index == 0)
                    $message .= "🙏 ";
                elseif($index == 1)
                    $message .= "👼🏻 ";
                elseif($index == 2)
                    $message .= "B-) ";
                elseif($index > 15)
                    break;

                $message .= "[".$likeMgr->getRank($cnt)."] ".$userManager->printUser($usr, false) . " | {$cnt} лайков".PHP_EOL;
                $index++;
            }
        }

        $commander->chatMessage($chatId, $message);
    }

}
