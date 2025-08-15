<?php
class BancoDeDados {
    private $host = "localhost:49170";
    private $dbname = "euro_banco";
    private $usuario = "root";
    private $senha = "";
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
