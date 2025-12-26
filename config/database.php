<?php
/**
 * FL REPAROS - Configuração de Banco (HOSPEDAGEM)
 * SEM ERROS DE SINTAXE
 */

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    
    public function __construct() {
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Verificar se existe .env
        $envFile = __DIR__ . '/../.env';
        
        if (file_exists($envFile)) {
            // Produção - ler .env
            $env = parse_ini_file($envFile);
            $this->host = $env['DB_HOST'];
            $this->username = $env['DB_USERNAME'];
            $this->password = $env['DB_PASSWORD'];
            $this->database = $env['DB_DATABASE'];
        } else {
            // Desenvolvimento - valores padrão
            $this->host = 'localhost';
            $this->username = 'root';
            $this->password = '';
            $this->database = 'fl_reparos';
        }
    }
    
    public function connect() {
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            return $this->connection;
            
        } catch (PDOException $e) {
            die('Erro de conexão com banco de dados: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        if ($this->connection === null) {
            return $this->connect();
        }
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Erro no banco de dados: ' . $e->getMessage());
        }
    }
}

// Criar instância global
try {
    $database = new Database();
    $conn = $database->connect();
    $pdo = $conn; // Alias para compatibilidade
} catch (Exception $e) {
    die('ERRO: ' . $e->getMessage());
}
?>