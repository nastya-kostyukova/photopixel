<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2.6.15
 * Time: 17.09
 */

namespace RaptorStore;

use Doctrine\DBAL\Connection;
class Admin {
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function deleteUser($id) {

        $this->conn->delete('users', array('id' => $id));
        $this->conn->delete('images', array('id_author' => $id));
        $this->conn->delete('followers', array('id_follower' => $id));
        $this->conn->delete('followers', array('id_following' => $id));
        $this->conn->delete('user_profile', array('id' => $id));
        $this->conn->delete('favorites', array('id_author' => $id));
        $this->conn->delete('likes', array('id_author' => $id));
        $this->conn->delete('comments', array('id_author' => $id));
    }

    public function showImages($id) {
        file_put_contents('data.txt', ' '.$id.' ', FILE_APPEND);
        $sql="SELECT * FROM images WHERE id_author=?";
        $images = $this->conn->fetchAll($sql, array((int) $id));

        if (count($images) == 0) return $images= '';

        $sql="SELECT login FROM users WHERE id=?";
        foreach($images as &$image) {
            $login = $this->conn->fetchAssoc($sql, array((int) $image['id_author']));

            $image['login'] = $login['login'];
        }
        return $images;
    }
    public function deleteImage($id) {
        $this->conn->delete('images', array('id' => $id));
    }

    public function deleteAllImages($id_author) {
        $this->conn->delete('images', array('id_author' => $id_author));
    }

    public function deleteComments() {

    }
}