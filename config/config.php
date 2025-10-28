<?php
// Database Configuration for PostgreSQL
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'zadostno_redovalnica_gv'); // Your database name
define('DB_USER', 'postgres');   // Your database username
define('DB_PASS', '828282'); // Your database password```

src/Database.php
```php
<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Pull connection details from the config file
        require_once __DIR__ . '/../config/config.php';

        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // In a real application, you would log this error, not display it
            die('Connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}