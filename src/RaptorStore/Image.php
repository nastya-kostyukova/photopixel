<?php

namespace RaptorStore;

use Doctrine\DBAL\Connection;
class Image {
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }


    public function saveImageInDB($user, $url ,$title, $description)
    {
        $sql = "SELECT * FROM users WHERE login = ?";
        $post = $this->conn->fetchAssoc($sql, array((string) $user));
        $this->conn->insert('images', array('url' => $url, 'title' => $title, 'description' => $description, 'id_author' => $post['id'], 'published_date' => date('Y-m-d H:i:s')));
    }

    public function getUserImages($id)
    {
        $sql = "SELECT url FROM images WHERE id_author = ?";
        $post = $this->conn->fetchAll($sql, array((int) $id));
        return $post;
    }

    public function getArrayUserImages($post)
    {
        $images = $this->getUserImages($post['id']);
        foreach ($images as  &$image ){
            $image['login'] = $post['login'];
        }
        if (!isset($images)) {
            $images['error'] = 'Not uploaded any photo :(';
        }
        return $images;
    }
    public  function  saveComment($login, $url, $comment, $id_author)
    {
        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $this->conn->fetchAssoc($sql, array((string) $login));
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $this->conn->fetchAssoc($sql, array((string) $url));

        $this->conn->insert('comments', array('id_author' => $id_author, 'id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'comment' => $comment, 'date' => date("Y-m-d H:i:s")));
    }

    public  function  saveLike($login, $url, $id_author)
    {
        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $this->conn->fetchAssoc($sql, array((string) $login));
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $this->conn->fetchAssoc($sql, array((string) $url));
        $sql = "SELECT idlikes FROM likes WHERE id_image=? && id_user=? && id_author=?";
        $post = $this->conn->fetchAll($sql, array( (int) $id_image['id'], (int) $id_user['id'], (int) $id_author));
        if (count($post) === 0)
            $this->conn->insert('likes', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
        else {
            $this->conn->delete('likes', array('id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'id_author' => $id_author));
        }
    }

    public  function  countLikes($login, $url, $id_author)
    {
        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $this->conn->fetchAssoc($sql, array((string) $login));
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $this->conn->fetchAssoc($sql, array((string) $url));
        $sql = "SELECT idlikes FROM likes WHERE id_image=? && id_user=? && id_author=?";
        $post = $this->conn->fetchAll($sql, array( (int) $id_image['id'], (int) $id_user['id'], (int) $id_author));
        return count($post);
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

        $sql = "SELECT idlikes FROM likes WHERE id_image=? && id_user=?";
        foreach($images as &$image) {
            $post = $this->conn->fetchAll($sql, array( (int) $image['id_image'], (int) $image['id']));
            $image['count_likes'] = count($post);
            $sqlLike = "SELECT idlikes FROM likes WHERE (id_image=?) && (id_user=?) && (id_author=?)";
            $post = $this->conn->fetchAll($sqlLike, array((int) $image['id_image'], (int) $image['id'], (int) $id_follower ));
            if (count($post) !== 0)
                $image['image_is_liked'] = 'TRUE';
            else
                $image['image_is_liked'] = 'FALSE';
        }
        return $images;
    }
}