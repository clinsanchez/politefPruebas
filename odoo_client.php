<?php
require_once 'ripcord.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

class OdooClient {
    private $url;
    private $db;
    private $username;
    private $password;
    private $uid;
    private $models;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $this->url = $_ENV['ODOO_URL'];
        $this->db = $_ENV['ODOO_DB'];
        $this->username = $_ENV['ODOO_USER'];
        $this->password = $_ENV['ODOO_PASS'];

        $common = ripcord::client("{$this->url}common");
        $this->uid = $common->authenticate($this->db, $this->username, $this->password, []);

        if (!$this->uid) {
            throw new Exception("Error al autenticar en Odoo.");
        }

        $this->models = ripcord::client("{$this->url}object");
    }

    public function search_read($model, $domain, $options = []) {
        return $this->models->execute_kw($this->db, $this->uid, $this->password,
            $model, 'search_read', [$domain], $options);
    }

    public function read($model, $ids, $fields) {
        return $this->models->execute_kw($this->db, $this->uid, $this->password,
            $model, 'read', [$ids], ['fields' => $fields]);
    }
}
