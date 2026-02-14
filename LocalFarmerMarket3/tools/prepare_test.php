<?php
require_once __DIR__ . '/../db.php';

$sql = "UPDATE Farmer SET name=?, contact=?, location=?, description=? WHERE farmer_id=?";
$stmt = $conn->prepare($sql);
var_export([ 'prepare_result' => $stmt !== false, 'conn_error' => $conn->error, 'errno' => $conn->errno ]);
if ($stmt !== false) {
    var_export(['bind_ok' => $stmt->bind_param("ssssi", $a="x", $b="y", $c="z", $d="desc", $e=1), 'stmt_error' => $stmt->error]);
    $stmt->close();
}

echo "\n";