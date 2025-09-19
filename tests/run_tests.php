<?php
/*
 * Test Runner for WeChat Integration Tests
 * 
 * This script runs all unit tests for the WeChat integration module
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if PHPUnit is available
if (!class_exists('PHPUnit\Framework\TestCase')) {
    echo "PHPUnit is not available. Please install PHPUnit to run tests." . PHP_EOL;
    echo "You can install it via Composer: composer require --dev phpunit/phpunit" . PHP_EOL;
    exit(1);
}

// Set working directory
chdir(__DIR__);

// Include bootstrap
require_once 'bootstrap.php';

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "  WeChat Integration Unit Tests" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo PHP_EOL;

// Test configuration
$testSuites = [
    'WeComAPITest' => 'WeComTests/WeComAPITest.php',
    'WeComSyncTest' => 'WeComTests/WeComSyncTest.php',
    'WeComNotificationTest' => 'WeComTests/WeComNotificationTest.php',
    'WeComIntegrationTest' => 'WeComTests/WeComIntegrationTest.php'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$errors = [];

// Run each test suite
foreach ($testSuites as $suiteName => $suiteFile) {
    echo "Running {$suiteName}..." . PHP_EOL;
    
    if (!file_exists($suiteFile)) {
        echo "  ✗ Test file not found: {$suiteFile}" . PHP_EOL;
        $errors[] = "Test file not found: {$suiteFile}";
        continue;
    }
    
    // Include test file
    require_once $suiteFile;
    
    // Create test instance
    if (!class_exists($suiteName)) {
        echo "  ✗ Test class not found: {$suiteName}" . PHP_EOL;
        $errors[] = "Test class not found: {$suiteName}";
        continue;
    }
    
    $testInstance = new $suiteName();
    
    // Get test methods
    $reflection = new ReflectionClass($suiteName);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $suiteTests = 0;
    $suitePassed = 0;
    $suiteFailed = 0;
    
    foreach ($methods as $method) {
        $methodName = $method->getName();
        
        // Skip non-test methods
        if (!str_starts_with($methodName, 'test')) {
            continue;
        }
        
        $suiteTests++;
        $totalTests++;
        
        try {
            // Setup
            if (method_exists($testInstance, 'setUp')) {
                $testInstance->setUp();
            }
            
            // Run test
            $testInstance->$methodName();
            
            // Teardown
            if (method_exists($testInstance, 'tearDown')) {
                $testInstance->tearDown();
            }
            
            echo "  ✓ {$methodName}" . PHP_EOL;
            $suitePassed++;
            $passedTests++;
            
        } catch (Exception $e) {
            echo "  ✗ {$methodName}: " . $e->getMessage() . PHP_EOL;
            $errors[] = "{$suiteName}::{$methodName}: " . $e->getMessage();
            $suiteFailed++;
            $failedTests++;
            
            // Teardown even on failure
            try {
                if (method_exists($testInstance, 'tearDown')) {
                    $testInstance->tearDown();
                }
            } catch (Exception $teardownException) {
                // Ignore teardown exceptions
            }
        }
    }
    
    echo "  Suite Summary: {$suitePassed} passed, {$suiteFailed} failed" . PHP_EOL;
    echo PHP_EOL;
}

// Print final results
echo "========================================" . PHP_EOL;
echo "  Test Results Summary" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo "Total Tests: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;

if ($failedTests > 0) {
    echo PHP_EOL;
    echo "Failures:" . PHP_EOL;
    foreach ($errors as $error) {
        echo "  - {$error}" . PHP_EOL;
    }
}

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Success Rate: {$successRate}%" . PHP_EOL;
echo PHP_EOL;

if ($failedTests === 0) {
    echo "🎉 All tests passed!" . PHP_EOL;
    exit(0);
} else {
    echo "❌ Some tests failed. Please check the errors above." . PHP_EOL;
    exit(1);
}

// Helper function for PHP 8 compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}
