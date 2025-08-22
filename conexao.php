<?php
class BancoDeDados {
    private $host = "sql200.infinityfree.com";
    private $dbname = "if0_39533103_euro_banco";
    private $usuario = "if0_39533103";
    private $senha = "7uZMTnbzCohtJq";
    public $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};port=49170;dbname={$this->dbname}",
                $this->usuario,
                $this->senha,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Erro de conexão: ' . $e->getMessage());
        }
    }
}
?>