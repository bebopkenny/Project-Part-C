<?php

require_once 'config.php';

$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;
$DB_NAME = DB_NAME;

try {
    $pdo = new PDO(
       "mysql:host=$DB_HOST;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASS,
[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false]);

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n"; 
}
?>