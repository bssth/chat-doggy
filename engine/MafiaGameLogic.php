<?php

define('MAIN_MAFIA_CHAT', 1); // @TODO

/**
 * Class MafiaGameLogic
 */
class MafiaGameLogic
{
    /**
     * @var null|object
     */
    protected $game = null;

    /**
     * @var null|array
     */
    protected $start_members = null;
    
    /**
     * @var string
     */
    protected $filename = __DIR__ . '/mafia.json';

    /**
     * @param $k
     * @return null
     */
    public function __get($k) {
        return isset($this->game->{$k}) ? $this->game->{$k} : null;
    }

    /**
     * @param $k
     * @param $v
     */
    public function __set($k, $v) {
        $this->game->{$k} = $v;
    }

    /**
     * @param $uid
     * @param $message
     */
    public function handlePm($uid, $message)
    {
        global $commander;
        $text = trim(mb_strtolower($message['text']));

        if($text == 'мафия?')
        {
            $commander->sendPm($uid, $this->isProcess() ? 'Игра в процессе! :-D' : 'Нет активных игр');
            return;
        }

        if(!isset($this->game->members[$uid]))
        {
            $commander->sendPm($uid, 'Вы не в игре!');
            return;
        }

        if($text == 'мафия фолы')
        {
            $commander->sendPm($uid, 'Нарушений за эту партию: ' . isset($this->game->falls[$uid]) ? (int)$this->game->falls[$uid] : '0');
            return;
        }

        if($text == 'мафия суицид')
        {
            $this->suicide(MAIN_MAFIA_CHAT, $uid);
            return;
        }

        if($text == 'мафия убить')
        {
            if($this->game->roles[$uid] != 'mafia') {
                $commander->sendPm($uid, 'Только мафиози могут убивать');
                return;
            }

            if($this->game->night != true) {
                $commander->sendPm($uid, 'Убивать можно только ночью');
                return;
            }

            if(!isset($message['fwd_messages']) || !isset($message['fwd_messages'][0])) {
                $commander->sendPm($uid, 'Перешлите сообщение того, кого надо убить');
                return;
            }

            $killed = $message['fwd_messages'][0]['from_id'];

            if(!isset($this->game->roles[$killed])) {
                $commander->sendPm($uid, 'Этот пользователь не играет сейчас');
                return;
            }

            if($this->game->roles[$killed] == 'mafia') {
                $commander->sendPm($uid, 'Участник тоже мафиози. Нельзя убивать коллег!');
                return;
            }

            $this->game->killed = $killed;
            $commander->sendPm($uid, 'Жертва выбрана. Кто-то завтра не проснётся... 😈');
        }
    }

    /**
     * @param $chatId
     * @param $uid
     * @param $message
     * @param $admin
     * @return bool
     */
    public function handleMafiaChat($chatId, $uid, $message, $admin) {
        if($chatId != 1)
            return false;

        global $commander;

        if($this->isProcess() && $this->changeTime != 0 && $this->changeTime <= time()) {
            $this->night = !$this->night;
            $this->handleChangeTime($chatId);
            $this->changeTime = time()+60;
        }

        if($uid <= 0)
            return false;

        if($this->game != null)
            $this->saveGame();

        if($this->isProcess() && !isset($this->game->members[$uid]) && $admin < 1)
            return false;

        $text = trim(mb_strtolower($message['text']));
        
        if($text == 'играю')
        {
            if(isset($this->start_members[$uid])) {
                $commander->chatMessage($chatId, 'Подача ещё одной заявки не ускорит рассмотрение, камрад!');
                return true;
            }

            if(!$this->isActive() && !is_null($this->start_members)) {
                $this->start_members[$uid] = $uid;
                $commander->chatMessage($chatId, 'Добро пожаловать! ;-)' . PHP_EOL . 'Нас уже ' . count($this->start_members) . '!' . PHP_EOL . 'Модератору: по завершению набора нужно прописать \'играем\'');
            }
            else
                $commander->chatMessage($chatId, 'Нет активного набора');

            return true;
        }

        if(isset($this->game->members[$uid])) {
            if ($text == 'голос' && $this->game->night == false) {
                if (!isset($message['fwd_messages']) || !isset($message['fwd_messages'][0])) {
                    $commander->chatMessage($chatId, 'Перешлите сообщение того, кого подозреваете');
                    return true;
                }

                $voted = $message['fwd_messages'][0]['from_id'];

                if (!isset($this->game->members[$voted])) {
                    $commander->chatMessage($chatId, 'Вы указали на того, кто не участвует в этой партии. В чём подозрение? 😑');
                    return true;
                }

                $this->game->votes[$uid] = $voted;

                global $userManager;
                $commander->chatMessage($chatId, $userManager->printUser($uid) . ' голосует против ' . $userManager->printUser($voted));
                return true;
            }

            if ($text == 'суицид' && $this->isProcess()) {
                $this->suicide($chatId, $uid);
                return true;
            }
        }

        if($admin >= 1) {
            if ($text == 'старт') {
                if (!$this->isActive()) {
                    $this->start_members = [];
                    $commander->chatMessage($chatId, 'Скоро начнём! Кто хочет играть - напишите \'играю\'. ' .
                        ' Убедительная просьба написать мне в сообщения группы или разрешить их, иначе мы не сможем решать конфиденциальные вопросы (убивать кого-то и пр.)');
                } else
                    $commander->chatMessage($chatId, 'Игра уже в процессе, мой фюрер!');

                return true;
            }

            if ($text == 'исключить') {
                if ($this->isProcess()) {
                    if (!isset($message['fwd_messages']) || !isset($message['fwd_messages'][0]))
                        return true;

                    $uid2 = $message['fwd_messages'][0]['from_id'];
                    $this->removeFromGame($uid2);

                    global $userManager;
                    $commander->chatMessage($chatId, $userManager->printUser($uid2) . ' исключается из игры');
                } else
                    $commander->chatMessage($chatId, 'Нет активных игр!');

                return true;
            }

            if ($text == 'сброс') {
                $this->resetGame();
                $commander->chatMessage($chatId, 'Игра сброшена');
                return true;
            }

            if ($text == 'играем') {
                if (!$this->isProcess() && is_array($this->start_members)) {
                    $this->startNewGame($chatId);
                    $this->started = true;
                    $this->handleChangeTime($chatId);
                    $this->changeTime = time() + 30;
                }
                return true;
            }

            if ($text == 'время') {
                if ($this->isProcess() == true) {
                    $this->changeTime = 1;
                    $commander->chatMessage($chatId, 'Меняю время вручную');
                }
                return true;
            }

            if ($text == 'загрузить') {
                $this->loadGame($chatId);
                return true;
            }
        }

        if($this->isActive() && $this->night == true)
        {
            $this->setFall($uid, MAIN_MAFIA_CHAT, 'Нельзя общаться ночью');
            return true;
        }

        return false;
    }

    /**
     * @param int $role
     * @return string
     */
    public function roleText($role = 0)
    {
        switch($role)
        {
            case 'mafia':
                return 'мафиози';

            case 'user':
                return 'мирный житель';

            default:
                return 'НЛО О_О';
        }
    }

    /**
     * @param $userId
     * @param int $chatId
     * @return bool
     */
    public function removeFromGame($userId, $chatId = MAIN_MAFIA_CHAT)
    {
        if(!isset($this->game->members[$userId]))
            return false;

        unset($this->game->falls[$userId]);
        unset($this->game->roles[$userId]);
        unset($this->game->members[$userId]);
        return true;
    }

    /**
     * @param $userId
     * @param int $chatId
     * @param string $reason
     * @return mixed
     */
    public function setFall($userId, $chatId = MAIN_MAFIA_CHAT, $reason = '')
    {
        global $userManager, $commander;
        $add = '';

        if(!isset($this->game->falls[$userId])) {
            $this->game->falls[$userId] = 0;
            $add = 'В первый раз нарушение не засчитывается, впредь будьте хорошим!';
        }
        else {
            $this->game->falls[$userId]++;

            if ($this->game->falls[$userId] >= 1)
                $add = 'Я же просил 😔';
            if ($this->game->falls[$userId] >= 2)
                $add = 'Опять! Ещё одно нарушение - и вас придётся исключить! 😓';

            if ($this->game->falls[$userId] >= 3) {
                $add = 'Вы нарушили правила 3 раза и исключены из этой партии >(';
                $this->removeFromGame($userId, $chatId);
            }
        }

        $commander->chatMessage($chatId, 'ФОЛ! '.$userManager->printUser($userId).' ['.$this->game->falls[$userId].'/3]: '.$reason.'. '.$add);
        return isset($this->game->falls[$userId]) ? $this->game->falls[$userId] : null;
    }

    /**
     * @param $chatId
     */
    public function handleChangeTime($chatId) {
        global $commander;

        if($this->night == true) {
            if(isset($this->game->votes) && is_array($this->game->votes) && count($this->game->votes)) {
                $votes = [];
                $kilee = false;

                foreach($this->game->votes as $from => $to) {
                    if(isset($votes[$to]))
                        $votes[$to]++;
                    else
                        $votes[$to] = 1;
                }

                if(count($votes))
                    $kilee = array_search(max($votes), $votes);

                if($kilee != false) {
                    global $userManager;
                    $commander->chatMessage($chatId, 'Мы приняли своё решение. '.$userManager->printUser($kilee).' предаётся смертной казни :-(');
                    $this->removeFromGame($kilee);
                }
            }

            $this->killed = null;
            $this->game->votes = null;
            $commander->chatMessage($chatId, 'Наступает ночь, город засыпает. А тем временем мафия выходит на охоту...');
        }
        elseif($this->night == false) {
            if(!is_null($this->killed)) {
                global $userManager;
                $startWith = 'Мафиози хорошо постарались. Этой ночью нас покинул(а) ' . $userManager->printUser($this->game->killed) . ' ('.$this->roleText($this->game->roles[$this->game->killed]).') :-( '.PHP_EOL;
                $this->removeFromGame($this->game->killed);
                $this->killed = null;
            }
            else
                $startWith = 'Доброе утро! Этой ночью, к счастью, все проснулись живыми. ';

            $this->game->votes = [];

            $mem = [];
            foreach($this->game->members as $v) {
                $mem[] = $v['first_name'] . ' ' . $v['last_name'];
            }

            $roleinfo = '';
            $roles = [];
            foreach($this->game->roles as $id => $role) {
                isset($roles[$role]) ? ($roles[$role]++) : ($roles[$role] = 1);
            }

            foreach($roles as $role => $cnt)
                $roleinfo .= $cnt . ' ' . $this->roleText($role) . ', ';

            $commander->chatMessage($chatId, $startWith .
                'Пришло время высказать догадки, кто из присутствующих причастен к убийствам всяких личностей. ' .
                'Когда будете готовы проголосовать, перешлите сообщение подозреваемого и введите команду \'голос\'' . PHP_EOL .
                'Живые участники: ' . implode(', ', $mem) . PHP_EOL .
                'Положение дел: ' . $roleinfo . ' бессмертный ведущий'
            );
        }
        else {
            $commander->chatMessage($chatId, 'Игра запоролась по техническим причинам. Пробую починить... :((');
            $this->night = false;
            $this->handleChangeTime($chatId);
        }

        $this->handleWin($chatId);
    }

    public function handleWin($chatId)
    {

    }

    /**
     * @return bool
     */
    public function isProcess() {
        return ($this->isActive() && $this->started == true);
    }

    /**
     * @param int $chatId
     * @param int $uid
     * @return bool
     */
    public function suicide($chatId = MAIN_MAFIA_CHAT, $uid = 0)
    {
        if(!isset($this->roles[$uid]))
            return false;

        global $userManager, $commander;
        $commander->chatMessage($chatId, 'Суицид :О' . PHP_EOL . 'От собственного ножа страдает ' . $userManager->printUser($uid) . ' ('.$this->roleText($this->game->roles[$uid]).')');
        $this->removeFromGame($uid);
        return true;
    }

    /**
     * @return bool
     */
    public function isActive() {
        return ($this->game != null && is_object($this->game));
    }

    public function resetGame() {
        $this->game = null;

        if(file_exists($this->filename))
            unlink($this->filename);
    }

    /**
     * @param null $chatId
     * @param bool $silent
     */
    public function loadGame($chatId = null, $silent = false) {
        global $commander;

        if(file_exists($this->filename)) {
            $this->game = unserialize(file_get_contents($this->filename));

            if(is_numeric($chatId) && $silent == false)
                $commander->chatMessage($chatId, 'Старая игра загружена B-)');
        }
        elseif(is_numeric($chatId) && $silent == false)
            $commander->chatMessage($chatId, 'Нет сохранённых игр :-(');
    }

    /**
     * @param integer $chatId
     */
    public function saveGame($chatId = null) {
        global $commander;

        file_put_contents($this->filename, serialize($this->game));

        if(is_numeric($chatId))
            $commander->chatMessage($chatId, 'Игра сохранена B-)');
    }

    /**
     * @param integer $chatId
     */
    public function startNewGame($chatId = null) {
        global $commander, $userManager;

        if(!is_array($this->start_members) || !count($this->start_members)) {
            $commander->chatMessage($chatId, 'Нет участников для игры :-(');
            return;
        }

        $usrs = $userManager->getUsers($this->start_members);

        if(!is_array($usrs)) {
            $commander->chatMessage($chatId, 'Не могу получить список участников');
            return;
        }

        $members = [];
        foreach ($usrs as $user)  {
            if(!isset($user['online']) || $user['online'] == 0)
                $members[$user['id']] = $user;
        }

        shuffle($usrs);

        $ans = 'Игра начинается! Список участников:' . PHP_EOL;
        $roles = [];
        $roles_available = ['mafia', 'mafia'];

        if($usrs <= count($roles_available)) {
            $commander->chatMessage($chatId, 'Маловато потенциальных игроков... надо хотя бы '.count($roles_available).' + мирные');
            return;
        }

        foreach($usrs as $k => $v) {
            $ans .= '👉 '.$v['first_name'].' '.$v['last_name'].' '. ($v['id'] == BOT_ID ? '(ведущий)' : '') .' ' . PHP_EOL;

            if($v['id'] == BOT_ID)
                continue;

            $members[$v['id']] = $v;

            if(!count($roles_available)) {
                $roles[$v['id']] = 'user';
                continue;
            }

            $rol_key = rand(0, count($roles_available)-1);
            $roles[$v['id']] = $roles_available[$rol_key];
            unset($roles_available[$rol_key]);
            shuffle($roles_available);

            $commander->sendPm($v['id'], 'Ваша роль в этой партии: ' . $this->roleText($roles[$v['id']]));
        }

        $this->game = (object)[
            'creator' => BOT_ID,
            'members' => $members,
            'roles' => $roles,
            'night' => true,
            'falls' => [],
            'started' => false,
            'changeTime' => 0
        ];
        $this->start_members = null;

        $commander->chatMessage($chatId, $ans);
    }
}