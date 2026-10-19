<?php
/**
 * Automated Smoke Test & Verification Suite
 * Used by CI/CD pipeline and local environment checks.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$errors = [];
$successCount = 0;

function runTest(string $description, callable $callback): void {
    global $errors, $successCount;
    echo "Running: $description... ";
    try {
        $result = $callback();
        if ($result === false) {
            throw new Exception("Assertion failed");
        }
        echo "[PASSED]\n";
        $successCount++;
    } catch (Throwable $e) {
        echo "[FAILED]: " . $e->getMessage() . "\n";
        $errors[] = "$description: " . $e->getMessage();
    }
}

echo "\n============================================\n";
echo " Document Request System - Test Suite\n";
echo "============================================\n\n";

// 1. PHP Syntax Check across all project PHP files
runTest("PHP Syntax Linting across all PHP files", function() use ($projectRoot) {
    $directory = new RecursiveDirectoryIterator($projectRoot);
    $iterator = new RecursiveIteratorIterator($directory);
    $phpFiles = [];

    foreach ($iterator as $info) {
        if ($info->isFile() && $info->getExtension() === 'php') {
            $path = $info->getRealPath();
            // Skip vendor or git if any
            if (str_contains($path, DIRECTORY_SEPARATOR . '.git')) {
                continue;
            }
            $phpFiles[] = $path;
        }
    }

    foreach ($phpFiles as $file) {
        $output = [];
        $returnVar = 0;
        exec(escapeshellcmd("php -l " . escapeshellarg($file)), $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }
    return count($phpFiles) > 0;
});

// 2. Database Connection
runTest("Database Connection via config/database.php", function() use ($projectRoot) {
    require $projectRoot . '/config/database.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('$pdo PDO instance not found.');
    }
    return true;
});

// 3. Database Schema / Tables Validation
runTest("Database Tables Existence Check", function() use ($projectRoot) {
    require $projectRoot . '/config/database.php';
    $requiredTables = ['users', 'document_types', 'document_requests', 'request_status_history'];

    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($requiredTables as $table) {
        if (!in_array($table, $existingTables, true)) {
            throw new Exception("Required table '$table' not found in database.");
        }
    }
    return true;
});

// 4. Seed Data Verification (Admin & Users)
runTest("Seed Data: Admin user exists and password verifies", function() use ($projectRoot) {
    require $projectRoot . '/config/database.php';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception("Admin user 'admin@example.com' not found.");
    }
    if ($admin['role'] !== 'admin') {
        throw new Exception("User 'admin@example.com' does not have 'admin' role.");
    }
    if (!password_verify('password', $admin['password'])) {
        throw new Exception("Admin password verification failed.");
    }
    return true;
});

// 5. Seed Data Verification (Document Types)
runTest("Seed Data: Document types present", function() use ($projectRoot) {
    require $projectRoot . '/config/database.php';
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM document_types");
    $row = $stmt->fetch();
    if ((int)$row['count'] < 1) {
        throw new Exception("No document types found.");
    }
    return true;
});

// 6. Transactional Request Creation and Tracking Test
runTest("Transactional Request Creation & Reference Number Generation", function() use ($projectRoot) {
    require $projectRoot . '/config/database.php';

    // Start a transaction so tests leave zero trace
    $pdo->beginTransaction();
    try {
        $stmtUser = $pdo->query("SELECT id FROM users WHERE role = 'user' LIMIT 1");
        $user = $stmtUser->fetch();
        $userId = $user ? (int)$user['id'] : 1;

        $stmtDoc = $pdo->query("SELECT id FROM document_types LIMIT 1");
        $doc = $stmtDoc->fetch();
        $docId = $doc ? (int)$doc['id'] : 1;

        $refNo = 'TEST-' . strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');

        // Insert request
        $insert = $pdo->prepare("INSERT INTO document_requests 
            (reference_no, user_id, document_type_id, purpose, quantity, status, requested_at, updated_at) 
            VALUES (?, ?, ?, ?, 1, 'Pending', ?, ?)");
        $insert->execute([$refNo, $userId, $docId, 'Automated CI/CD Verification', $now, $now]);
        $requestId = (int)$pdo->lastInsertId();

        // Check request retrieval
        $check = $pdo->prepare("SELECT * FROM document_requests WHERE id = ?");
        $check->execute([$requestId]);
        $request = $check->fetch();

        if (!$request || $request['reference_no'] !== $refNo) {
            throw new Exception("Failed to retrieve created test request.");
        }

        // Insert status history
        $history = $pdo->prepare("INSERT INTO request_status_history (request_id, status, remarks, changed_by) VALUES (?, 'Pending', 'Test note', ?)");
        $history->execute([$requestId, $userId]);

        // Rollback so the database remains unchanged
        $pdo->rollBack();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
});

echo "\n============================================\n";
echo " Test Results: $successCount passed, " . count($errors) . " failed\n";
echo "============================================\n";

if (count($errors) > 0) {
    echo "\nFailures:\n";
    foreach ($errors as $err) {
        echo " - $err\n";
    }
    exit(1);
}

echo "All smoke tests completed successfully!\n\n";
exit(0);
