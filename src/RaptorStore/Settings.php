<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.5.15
 * Time: 21.50
 */

namespace RaptorStore;

use Doctrine\DBAL\Connection;
class Settings {
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function saveSettings($id, $forename, $surname, $gender, $birthdayDay, $birthdayMonth, $birthdayYear, $country, $city, $description)
    {
        $sql = "SELECT * FROM user_profile WHERE id=?";
        $post = $this->conn->fetchAssoc($sql, array((int)$id));
        if (isset($post)) {
            return $this->conn->update('user_profile', array('forename' => $forename, 'surname' => $surname,
                'gender' => $gender, 'birthday_day' => $birthdayDay, 'birthday_month' => $birthdayMonth, 'birthday_year' => $birthdayYear,
                'country' => $country, 'city' => $city, 'description' => $description), array('id' => $id));
        } else {
            return $this->conn->insert('user_profile', array('id' => $id, 'forename' => $forename, 'surname' => $surname,
                'gender' => $gender, 'birthday_day' => $birthdayDay, 'birthday_month' => $birthdayMonth, 'birthday_year' => $birthdayYear,
                'country' => $country, 'city' => $city, 'description' => $description));
        }

    }

    public function loadSettings($id)
    {
        $sql = "SELECT * FROM user_profile WHERE id=?";
        $post = $this->conn->fetchAssoc($sql, array((int) $id));
        if ($post)
            return $post;
        else {
            //$this->saveSettings();
        }
    }
}