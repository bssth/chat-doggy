<?php

/**
 * Class UserManager
 */
class UserManager
{
    /**
     * @var array
     */
    public $map = [];

    /**
     * Chat admins (VK-side)
     * @var array
     */
    public $admins = [];

    /**
     * @param array $userIds
     * @return array|null
     */
    public function addUserToMap($userIds = [])
    {
        global $commander;

        if(!is_array($userIds))
            $userIds = [$userIds];

        $items = $commander->getUsersInfo($userIds);
        $return = $item = [];

        if(!$items)
            return null;

        foreach($items as $k => $item) {
            if(!isset($item['id']))
                return null;

            $this->map[$item['id']] = $return[$item['id']] = $item;
        }

        return count($userIds)==1 ? $item : $return;
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getUsers($userIds = [])
    {
        $return = [];
        $update = [];

        foreach($userIds as $id) {
            if(isset($this->map[$id]))
                $return[$id] = $this->map[$id];
            else
                $update[] = $id;
        }

        return array_merge($return, $this->addUserToMap($update));
    }

    /**
     * @param $userId
     * @return mixed|null
     */
    public function getUser($userId)
    {
        if(isset($this->map[$userId]))
            return $this->map[$userId];

        return $this->addUserToMap($userId);
    }

    /**
     * @param $userId
     * @return string
     */
    public function printUser($userId, $link = true)
    {
        if($userId <= 0) {
            $fn = 'Сообщество';
            $ln = '';
        }
        else {
            $info = $this->getUser($userId);

            if (!isset($info['first_name']))
                $fn = 'ID';
            else
                $fn = $info['first_name'];

            if (!isset($info['last_name']))
                $ln = $userId;
            else
                $ln = $info['last_name'];
        }

        if($link == true && $userId > 0)
            return '@id' . $userId . '(' . $fn . ' ' . $ln . ')';
        elseif($link == true)
            return '@club' . abs($userId) . '(' . $fn . ' ' . $ln . ')';
        else
            return $fn . ' ' . $ln;
    }

    /**
     * @param $chatId
     * @return bool
     */
    public function rebuildIfNeeded($chatId)
    {
        if(isset($this->admins[$chatId]))
            return false;
        else
            return $this->rebuildUserMap($chatId);
    }

    /**
     * @param $chatId
     * @return bool
     */
    public function rebuildUserMap($chatId)
    {
        global $commander;
        $usr = $commander->getChatUsers($chatId);
        $map = $usr['profiles'];

        if(!is_array($map))
            return false;

        $this->admins[$chatId] = [];

        foreach($usr['items'] as $item) {
            if(isset($item['is_admin']) && $item['is_admin'] == true && $item['member_id'] > 0)
                $this->admins[$chatId][$item['member_id']] = $item['member_id'];
        }

        foreach($map as $k => $item) {
            if(!isset($item['id']))
                continue;

            $this->map[$item['id']] = $item;
        }

        return true;
    }
}