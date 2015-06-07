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
        $sql="SELECT * FROM images WHERE id_author=?";
        $images = $this->conn->fetchAll($sql, array((int) $id));

        if (count($images) == 0) return $images= '';

        $sql="SELECT login FROM users WHERE id=?";
        foreach($images as &$image) {
            $login = $this->conn->fetchAssoc($sql, array((int) $image['id_author']));

            $image['login'] = $login['login'];
        }

        $sql = "SELECT DISTINCT u.login, c.comment, c.id_user, c.date, c.id_image FROM comments c INNER JOIN users u JOIN images i ON
(c.id_author = u.id) && (i.id = c.id_image) ";
        $comments = $this->conn->fetchAll($sql);
        file_put_contents('data.txt', '');

        file_put_contents('data.txt', '                         11111111111111111                    ', FILE_APPEND);
file_put_contents('data.txt', var_export($comments, true), FILE_APPEND);

        $i = 0;
        foreach($images as &$image) {
            $image['comments']='';
            foreach($comments as  &$comment) {
                $comment['comment'] = nl2br($comment['comment']);
                if (($image['id'] === $comment['id_image']) && ($image['id_author'] === $comment['id_user'])) {
                    foreach ($comment as $key => $value) {
                        $image['comments'][$i][$key] = $value;
                    }
                    $i++;
                }
            }
        }
        file_put_contents('data.txt', var_export($images, true), FILE_APPEND);
        return $images;
    }

    public function  giveAdmin($id) {
        $this->conn->update('users', array('ROLE' => 1), array('id' => $id));
    }

    public function deleteAllImages($id_author) {
        $this->conn->delete('images', array('id_author' => $id_author));
    }

    public function deleteComments() {

    }
}