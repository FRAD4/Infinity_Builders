<?php
/**
 * Debug search filters
 */

require_once __DIR__ . '/../partials/init.php';

echo "<h1>Search Filters Debug</h1>";

// Check projects
echo "<h3>Projects sample:</h3>";
try {
    $stmt = $pdo->query("SELECT id, name, city, project_manager, status FROM projects LIMIT 10");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>Projects found: " . count($projects) . "</pre>";
    print_r($projects);
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check cities
echo "<h3>Cities in projects:</h3>";
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM projects WHERE city IS NOT NULL AND city != '' ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>Cities found: " . count($cities) . "</pre>";
    print_r($cities);
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check managers
echo "<h3>Project Managers in projects:</h3>";
try {
    $stmt = $pdo->query("SELECT DISTINCT project_manager FROM projects WHERE project_manager IS NOT NULL AND project_manager != '' ORDER BY project_manager");
    $managers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>Managers found: " . count($managers) . "</pre>";
    print_r($managers);
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check vendors
echo "<h3>Vendors:</h3>";
try {
    $stmt = $pdo->query("SELECT id, name FROM vendors ORDER BY name LIMIT 10");
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>Vendors found: " . count($vendors) . "</pre>";
    print_r($vendors);
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check permits
echo "<h3>Permits:</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM permits LIMIT 5");
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>Permits found: " . count($permits) . "</pre>";
    print_r($permits);
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../search.php'>Go to Search</a></p>";