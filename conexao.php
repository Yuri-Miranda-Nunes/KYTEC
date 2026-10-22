<?php
class BancoDeDados {
    public $pdo;

    public function __construct() {
        $configFile = __DIR__ . '/config.local.php';
        $localConfig = is_file($configFile) ? require $configFile : [];
        if (!is_array($localConfig)) {
            throw new RuntimeException('A configuração local do banco de dados deve retornar um array.');
        }

        $host = getenv('DB_HOST') ?: ($localConfig['host'] ?? '');
        $port = getenv('DB_PORT') ?: ($localConfig['port'] ?? '3306');
        $dbname = getenv('DB_NAME') ?: ($localConfig['dbname'] ?? '');
        $usuario = getenv('DB_USER') ?: ($localConfig['user'] ?? '');
        $senha = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');

        foreach ([
            'DB_HOST' => $host,
            'DB_NAME' => $dbname,
            'DB_USER' => $usuario,
            'DB_PASSWORD' => $senha,
        ] as $name => $value) {
            if ($value === '') {
                error_log("Missing database configuration: {$name}");
                throw new RuntimeException('O banco de dados não está configurado.');
            }
        }

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                $usuario,
                $senha,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $e);
        }
    }
}
?>