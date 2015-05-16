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

}