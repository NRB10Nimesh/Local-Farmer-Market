<?php
// Migration tool: safely add `description` column to farmer if absent
// Usage: open in browser -> http://localhost/LocalFarmerMarket2/tools/run_migration.php

require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=utf-8');

$check = $conn->query("SHOW COLUMNS FROM `farmer` LIKE 'description'");
if ($check === false) {
    echo "ERROR: cannot inspect table `farmer` - " . $conn->error . "\n";
    exit(1);
}

if ($check->num_rows > 0) {
    echo "OK: column `description` already exists on `farmer`. Nothing to do.\n";
    exit(0);
}

$sql = "ALTER TABLE `farmer` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `address`";
if ($conn->query($sql) === TRUE) {
    echo "SUCCESS: column `description` added to `farmer`.\n";
} else {
    echo "FAILED: could not add column - " . $conn->error . "\n";
    exit(1);
}
