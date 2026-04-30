<?php
class BancoDeDados {
    private $host = "mysql.railway.internal";
    private $dbname = "railway";
    private $usuario = "root";
    private $senha = "yAIrUScHfWtuioDPCVWLrEzuLjcTHKBK";
    public $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname}",
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