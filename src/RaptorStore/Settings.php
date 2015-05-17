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

}