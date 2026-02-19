<?php
/**
 * ============================================================
 * Education Hub - Database Configuration (database.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Establishes a connection to the MySQL database using MySQLi.
 *   This file is included by functions.php and used globally.
 * 
 * HOW IT WORKS:
 *   1. Defines database constants (host, user, password, database name)
 *   2. Creates a Database class that wraps MySQLi connection
 *   3. Provides helper methods: query(), prepare(), escape(), lastInsertId()
 *   4. Starts PHP session if not already started (needed for login system)
 *   5. Creates global $db and $conn objects for use in all pages
 * 
 * TECHNOLOGY:
 *   - MySQLi (MySQL Improved) for database operations
 *   - UTF-8 character encoding for multilingual support
 *   - PHP sessions for user authentication state
 * 
 * CONFIGURATION:
 *   Change DB_HOST, DB_USER, DB_PASS, DB_NAME if your XAMPP
 *   setup uses different credentials.
 * ============================================================
 */

/* --- Database Connection Constants --- */
/* These must match your XAMPP MySQL settings */
define('DB_HOST', 'localhost');   // MySQL server address (localhost for XAMPP)
define('DB_USER', 'root');       // MySQL username (root is XAMPP default)
define('DB_PASS', '');           // MySQL password (empty is XAMPP default)
define('DB_NAME', 'education_hub'); // Name of the database we created

/**
 * Database Class
 * 
 * Wraps MySQLi connection into a reusable object.
 * Used throughout the app via $db and $conn variables.
 * 
 * METHODS:
 *   - getConnection(): Returns the raw MySQLi connection object
 *   - query($sql):     Executes a SQL query string and returns result
 *   - prepare($sql):   Creates a prepared statement (prevents SQL injection)
 *   - escape($string): Escapes special characters in a string
 *   - lastInsertId():  Returns the ID of the last inserted row
 *   - close():         Closes the database connection
 */
class Database {
    private $connection; // Holds the MySQLi connection object

    /**
     * Constructor: automatically connects when Database object is created
     */
    public function __construct() {
        $this->connect();
    }

    /**
     * connect() - Establishes the MySQLi connection
     * 
     * LOGIC:
     *   1. Creates new MySQLi connection using the defined constants
     *   2. If connection fails, stops execution and shows error
     *   3. Sets character encoding to utf8mb4 (supports emojis & special chars)
     */
    private function connect() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // Check if connection was successful
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }

        // Set UTF-8 encoding for proper character handling
        $this->connection->set_charset("utf8mb4");
    }

    /** Returns the raw MySQLi connection object */
    public function getConnection() {
        return $this->connection;
    }

    /** Executes a SQL query and returns the result set */
    public function query($sql) {
        return $this->connection->query($sql);
    }

    /** Creates a prepared statement for safe parameterized queries */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /** Escapes a string to prevent SQL injection */
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    /** Returns the auto-increment ID of the last INSERT */
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    /** Closes the database connection */
    public function close() {
        $this->connection->close();
    }
}

/* --- Start PHP Session --- */
/* Sessions store login state (user_id, user_role, etc.) across pages */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --- Create Global Database Instance --- */
/* $db = Database object, $conn = raw MySQLi connection */
$db = new Database();
$conn = $db->getConnection();
?>
