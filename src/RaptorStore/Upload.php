<?php

namespace RaptorStore;

class Upload
{
    public function uploadImage($user, $dir)
    {
        $target_dir = $dir . $user;

        $message = '';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777);
            if (!mkdir($target_dir, 0777))
                $message .= 'Error.Cannot make directory  ' . $target_dir . "   ";
            $uploadOk = 0;
        }
        $target_file = $target_dir . "/" . basename($_FILES["fileToUpload"]["name"]);
        $uploadOk = 1;
        $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

        // Check if image file is a actual image or fake image
        if (isset($_POST["submit"])) {
            $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
            if ($check !== false) {
                $message = "File is an image - " . $check["mime"] . ".";
                $uploadOk = 1;
            } else {
                $message = "File is not an image.";
                $uploadOk = 0;
            }
        }

        // Check file size
        if ($_FILES["fileToUpload"]["size"] > 500000000) {
            $message .= "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif"
        ) {
            $message .= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        $url = '';
        if ($uploadOk != 0) {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                $url = basename($_FILES["fileToUpload"]["name"]);
                $message .= "The file " . $url . " has been uploaded.";
                $newName = hash_file('md5', $target_file);
                rename($target_file, $target_dir . '/' . $newName);
                $url = $newName;
            } else {
                $message .= "Sorry, there was an error uploading your file.";
            }
        }
        return array('message' => $message, 'url' => $url);
    }
}