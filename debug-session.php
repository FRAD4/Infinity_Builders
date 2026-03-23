<?php
/**
 * Debug Session - Check what's in the session
 */
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .box { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .ok { color: green; }
        .fail { color: red; }
    </style>
</head>
<body>
    <h1>Session Debug</h1>";

echo "<div class='box'>";
echo "<h3>Session Contents:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>Role Check:</h3>";
$role = $_SESSION['user_role'] ?? 'NOT SET';
echo "user_role = '$role'<br>";

if ($role === 'admin') {
    echo "<span class='ok'>ADMIN ACCESS</span>";
} elseif ($role === 'user') {
    echo "<span class='fail'>USER ONLY</span>";
} else {
    echo "<span class='fail'>ROLE NOT SET</span>";
}
echo "</div>";

echo "<p><a href='index.php'>Logout</a></p>";
echo "</body></html>";
