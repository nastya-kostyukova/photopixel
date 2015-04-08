<?php

namespace RaptorStore;

use Doctrine\DBAL\Connection;
class Image {
    const DATE_FORMAT = 'Y-m-d H:i:s';

    protected $id;
    protected $url;
    protected $title;
    protected $description;
    protected $author_id;
    protected $published_date;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function  getId(){
        return $this->id;
    }
    public function  getUrl(){
        return $this->url;
    }
    public function setUrl($url){
        $this->url = $url;
    }
    public function getTitle(){
        return $this->title;
    }
    public function setTitle($title){
        $this->title = $title;
    }
    public function getDescription(){
        return $this->description;
    }
    public function setDescription($description){
        $this->description = $description;
    }
    public function getAuthor_id(){
        return $this->author_id;
    }
    public function setAuthor_id($author_id){
        $this->$author_id = $author_id;
    }
    public function getPublished_date(){
    return $this->published_date;
}
    public function setPublished_date($published_date){
        $this->published_date = $published_date;
    }

    public function uploadImage($user, $dir)
    {
        $target_dir = $dir.$user;
        mkdir($target_dir, 0777);
        $message = '';
        if (!file_exists($target_dir))
        {
            if (!mkdir($target_dir, 0777))
                $message.= 'Error.Cannot make directory  '.$target_dir."   ";
            $uploadOk = 0;
        }
        $target_file = $target_dir . "/".basename($_FILES["fileToUpload"]["name"]);
        $uploadOk = 1;
        $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

        // Check if image file is a actual image or fake image
        if(isset($_POST["submit"])) {
            $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
            if ($check !== false) {
                $message = "File is an image - " . $check["mime"] . ".";
                $uploadOk = 1;
            } else {
                $message=  "File is not an image.";
                $uploadOk = 0;
            }
        }

        // Check file size
        if ($_FILES["fileToUpload"]["size"] > 500000) {
            $message .= "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
            $message .= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk != 0)
        {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
            {
                $url = basename( $_FILES["fileToUpload"]["name"]);
                $message .= "The file ". $url. " has been uploaded.";
            } else {
                $message .= "Sorry, there was an error uploading your file.";
            }
        }
        return array ('message' => $message, 'url' => $url);
    }

    public function saveImageInDB($user, $url ,$title, $description)
    {
        $sql = "SELECT * FROM users WHERE login = ?";
        $post = $this->conn->fetchAssoc($sql, array((string) $user));
        $this->author_id = $post['id'];
        $this->published_date = date('Y-m-d H:i:s');
        $this->conn->insert('images', array('url' => $url, 'title' => $title, 'description' => $description, 'id_author' => $post['id'], 'published_date' => date('Y-m-d H:i:s')));
    }

    public function getUserImages($id)
    {
        $sql = "SELECT * FROM images WHERE id_author = ?";
        $post = $this->conn->fetchAll($sql, array((int) $id));
        return $post;
    }

    public function getArrayUserImages($post)
    {
        $result = $this->getUserImages($post['id']);
        foreach ($result as  $value ){
            foreach ($value as $key => $type){
                if (('url' === $key) && ('' != $type))
                    $images[] = "upload/".$post['login']."/".$type;
            }
        }
        if (!isset($images))
        {
            $images['error'] = 'Not uploaded any photo :(';
        }
        return $images;
    }
}