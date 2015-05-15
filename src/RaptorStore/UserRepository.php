<?php

namespace RaptorStore;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

class UserRepository extends BaseRepository implements UserProviderInterface
{
    private $passwordEncoder;

    public function __construct(Connection $conn, PasswordEncoderInterface $passwordEncoder)
    {
        $this->conn = $conn;
        $this->passwordEncoder = $passwordEncoder;
        parent::__construct($conn);
    }
    public function getTableName()
    {
        return 'users';
    }

    public function createAdminUser($username, $password)
    {
        $user = new User();
        $user->username = $username;
        $user->plainPassword = $password;
        $user->roles = array('ROLE_ADMIN');

        $this->insert($user);

        return $user;
    }

    public function insert($user)
    {
        $this->encodePassword($user);

        parent::insert($user);
    }

    public function update($user)
    {
        $this->encodePassword($user);

        parent::insert($user);
    }

    /**
     * Turns a user into a flat array
     *
     * @todo - should really be its own service
     *
     * @param User $user
     * @return array
     */
    public function objectToArray($user)
    {
        return array(
            'id' => $user->id,
            'username' => $user->username,
            'password' => $user->password,
            'roles' => implode(',', $user->roles),
            'created_at' => $user->createdAt->format(self::DATE_FORMAT),
        );
    }

    public function check($login, $password)
    {
        if (strlen($login) < 3)
        {
            return array('status' => 'error', 'message' => 'Login is too short');
        }
        if (strlen($password) < 5)
        {
            return array ('status' => 'error', 'message' =>  'Password os too short');
        }
        if (preg_match('[a-Z]|([0-9][a-Z]|-|.)*', $login))
        {
            return array('status' => 'error', 'message' => 'Login should consist only of letter, digits and symbols . -t');
        }
        return array('status' => 'ok', 'message' => 'You are registered!');

    }
    public function uploadAvatar($user, $dir)
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
        $url = '';
        if ($uploadOk != 0)
        {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
            {
                $url = basename( $_FILES["fileToUpload"]["name"]);
                $message .= "The file ". $url. " has been uploaded.";
                rename($target_file, $target_dir.'/avatar');
                $url = 'avatar';
            } else {
                $message .= "Sorry, there was an error uploading your file.";
            }
        }
        return array ('message' => $message, 'url' => $url);
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
    /**
     * Turns an array of data into a User object
     *
     * @param array $userArr
     * @param User $user
     * @return User
     */
    public function arrayToObject(array $userArr, $user = null)
    {
        // create a User, unless one is given
        if (!$user) {
            $user = new User();

            // only hydrate in the id if we're creating a new User
            // this is used when we're grabbing something out of the database, for example
            // we should *not* do this otherwise, because we already have an id, and are just updating its data
            $user->id = isset($userArr['id']) ? $userArr['id'] : null;
        }

        $username = isset($userArr['username']) ? $userArr['username'] : null;
        $password = isset($userArr['password']) ? $userArr['password'] : null;
        $roles = isset($userArr['roles']) ? explode(',', $userArr['roles']) : array();
        $createdAt = isset($userArr['created_at']) ? \DateTime::createFromFormat(self::DATE_FORMAT, $userArr['created_at']) : null;

        if ($username) {
            $user->username = $username;
        }

        if ($password) {
            $user->password = $password;
        }

        if ($roles) {
            $user->roles = $roles;
        }

        if ($createdAt) {
            $user->createdAt = $createdAt;
        }

        return $user;
    }

    public function loadUserByUsername($username)
    {
        $stmt = $this->conn->executeQuery('SELECT * FROM user WHERE username = ?', array(strtolower($username)));

        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $this->arrayToObject($user);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'RaptorStore\User';
    }

    /**
     * Encodes the user's password if necessary
     *
     * @param User $user
     */
    private function encodePassword(User $user)
    {
        if ($user->plainPassword) {
            $user->password = $this->passwordEncoder->encodePassword($user->plainPassword, $user->getSalt());
        }
    }
}