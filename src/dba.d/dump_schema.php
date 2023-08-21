<?php
extract(json_decode(file_get_contents(__DIR__ . "/../conf.d/sql.json"), true));

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Get the list of tables in the database
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Dump the structure of each table to a .sql file
$filename = __DIR__ . "/../../install/ai.sql";
$file = fopen($filename, "w");

foreach ($tables as $table) {
    $result = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_ASSOC);
    $createTableStmt = $result['Create Table'];
    fwrite($file, $createTableStmt . ";\n");
}

fclose($file);

echo "Database structure dumped to $filename successfully!";
