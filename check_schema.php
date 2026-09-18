<?php

declare(strict_types=1);
require 'vendor/autoload.php';
$pdo = new PDO('sqlite:database/testing.sqlite');
$stmt = $pdo->query('PRAGMA table_info(stock_movements)');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
