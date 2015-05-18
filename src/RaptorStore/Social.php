<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 15.5.15
 * Time: 23.47
 */

namespace RaptorStore;

use Doctrine\DBAL\Connection;
class Social {
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public  function  saveComment($login, $url, $comment, $id_author)
    {
        $sql = "SELECT login FROM users WHERE id=?";
        $post = $this->conn->fetchAssoc($sql, array((string) $id_author));
        $res = false;
        if (('' != $comment) && (isset($post))) {
            $sql = "SELECT id FROM users WHERE login = ?";
            $id_user = $this->conn->fetchAssoc($sql, array((string)$login));
            $sql = "SELECT id FROM images WHERE url = ?";
            $id_image = $this->conn->fetchAssoc($sql, array((string)$url));

            $res = $this->conn->insert('comments', array('id_author' => $id_author, 'id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'comment' => $comment, 'date' => date("Y-m-d H:i:s")));
            $res = true;
        }
        return $res;
    }

    public  function  saveLike($login, $url, $id_author)
    {
        $res = true;
        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $this->conn->fetchAssoc($sql, array((string) $login));
        if (!isset($id_user)) {
            $res = false;
        }
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $this->conn->fetchAssoc($sql, array((string) $url));
        if (!isset($id_image)) {
            $res = false;
        }
        $sql = "SELECT idlikes FROM likes WHERE id_image=? && id_user=? && id_author=?";
        $post = $this->conn->fetchAll($sql, array( (int) $id_image['id'], (int) $id_user['id'], (int) $id_author));
        if ($res) {
            if (count($post) === 0) {
                $this->conn->insert('likes', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
            }
            if (count($post) > 0)
                 $this->conn->delete('likes', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
        }
        return $res;
    }

    public function saveFavorites($login, $url, $id_author)
    {
        $res = true;
        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $this->conn->fetchAssoc($sql, array((string) $login));
        if (!isset($id_user)) {
            $res = false;
        }
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $this->conn->fetchAssoc($sql, array((string) $url));
        if (!isset($id_image)) {
            $res = false;
        }
        $sql = "SELECT idfavorites FROM favorites WHERE id_image=? && id_user=? && id_author=?";
        $post = $this->conn->fetchAll($sql, array( (int) $id_image['id'], (int) $id_user['id'], (int) $id_author));
        if ($res) {
            if (count($post) === 0) {
                $this->conn->insert('favorites', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
            }
            if (count($post) > 0)
                $this->conn->delete('favorites', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
        }
        return $res;
    }

    public function  loadFeed($id_follower)
    {
        $sql = "SELECT u.login, u.id, i.url, i.title, i.description, i.published_date, i.id as id_image
FROM users u INNER JOIN followers f JOIN images i ON (f.id_following = u.id) && (i.id_author = u.id) WHERE f.id_follower=? ORDER BY i.published_date DESC ";

        $images = $this->conn->fetchAll($sql, array( (int) $id_follower));

        $sql = "SELECT DISTINCT u.login, c.comment, c.id_user, c.date, c.id_image FROM comments c INNER JOIN users u JOIN images i JOIN followers f
ON (f.id_following = u.id) && (c.id_author = u.id) && (i.id = c.id_image) && (u.id = c.id_author)";
        $comments = $this->conn->fetchAll($sql);

        $i = 0;
        foreach($images as &$image) {
            $image['comments']='';
            foreach($comments as  &$comment) {
                $comment['comment'] = nl2br($comment['comment']);
                if (($image['id_image'] === $comment['id_image']) && ($image['id'] === $comment['id_user'])) {
                    foreach ($comment as $key => $value) {
                        $image['comments'][$i][$key] = $value;
                    }
                    $i++;
                }
            }
        }

        $sqlLike = "SELECT idlikes FROM likes WHERE id_image=? && id_user=?";
        $sqlLikes = "SELECT idlikes FROM likes WHERE (id_image=?) && (id_user=?) && (id_author=?)";
        $sqlFavorit = "SELECT idfavorites FROM favorites WHERE id_image=? && id_user=?";
        $sqlFavorited = "SELECT idfavorites FROM favorites WHERE (id_image=?) && (id_user=?) && (id_author=?)";

        foreach($images as &$image) {
            $post = $this->conn->fetchAll($sqlLike, array( (int) $image['id_image'], (int) $image['id']));
            $image['count_likes'] = count($post);
            $post = $this->conn->fetchAll($sqlFavorit, array( (int) $image['id_image'], (int) $image['id']));
            $image['count_favorites'] = count($post);

            $post = $this->conn->fetchAll($sqlLikes, array((int) $image['id_image'], (int) $image['id'], (int) $id_follower ));
            if (count($post) !== 0)
                $image['image_is_liked'] = 'TRUE';
            else
                $image['image_is_liked'] = 'FALSE';

            $post = $this->conn->fetchAll($sqlFavorited, array((int) $image['id_image'], (int) $image['id'], (int) $id_follower ));
            if (count($post) !== 0)
                $image['image_is_favorit'] = 'TRUE';
            else
                $image['image_is_favorit'] = 'FALSE';
        }

        return $images;
    }

    public function loadFavorites($id_author)
    {
        $sql = "SELECT i.url, u.login FROM favorites f INNER JOIN images i JOIN users u ON (i.id_author = u.id) && (f.id_image = i.id) WHERE f.id_author=?";
        $image = $this->conn->fetchAll($sql, array( (int) $id_author));
        return $image;
    }

    public function countFollower($id_following)
    {
        $sql = "SELECT id_follower FROM followers WHERE id_following=?";
        $post = $this->conn->fetchAll($sql, array((int) $id_following));
        $count = count($post);
        return $count;
    }

    public  function countFollowing($id_follower)
    {
        $sql = "SELECT id_following FROM followers WHERE id_follower=?";
        $post = $this->conn->fetchAll($sql, array((int) $id_follower));
        $count = count($post);
        return $count;
    }

    public  function  countImages($user)
    {
        $sql = "SELECT i.id FROM images i INNER JOIN users u ON (u.id = i.id_author) WHERE u.login=?";
        $post = $this->conn->fetchAll($sql, array((string) $user));
        $count = count($post);
        return $count;
    }
    public function userIsFollowed($user, $userSession, $id_following)
    {
        if  ($user === $userSession['login']) {
            $user_is_followed = '';
        }else {
            $id_follower = $userSession['id'];
            $sql= "SELECT id_follower, id_following FROM followers WHERE id_follower=? AND id_following=?";
            $post = $this->conn->fetchAssoc($sql, array((int) $id_follower, (int) $id_following));
            if (isset($post['id_follower'])){
                $user_is_followed = 'TRUE';
            }else{
                $user_is_followed = 'FALSE';
            }
        }
        return $user_is_followed;
    }
}