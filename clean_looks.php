<?php
require_once 'config/db.php';

// Clean expired locks
$stmt = $pdo->prepare("UPDATE spot_locks SET is_active = FALSE WHERE expires_at < NOW()");
$stmt->execute();

echo "Cleaned " . $stmt->rowCount() . " expired locks\n";
?>