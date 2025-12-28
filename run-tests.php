#!/usr/bin/env php

<?php

/**
 * Test Runner Script
 * 
 * Usage examples:
 * php run-tests.php                    # Run all tests
 * php run-tests.php --unit              # Run unit tests only
 * php run-tests.php --feature           # Run feature tests only
 * php run-tests.php --coverage          # Run tests with coverage
 * php run-tests.php --filter="UserTest" # Run specific test
 */

$help = "
Laravel Test Runner

Usage:
    php run-tests.php [options]

Options:
    --unit              Run unit tests only
    --feature           Run feature tests only
    --integration       Run integration tests only
    --auth              Run authentication tests only
    --crud              Run CRUD tests only
    --services          Run service tests only
    --models            Run model tests only
    --coverage          Generate code coverage report
    --filter=FILTER     Filter tests by name
    --verbose           Show verbose output
    --stop-on-failure   Stop on first failure
    --parallel          Run tests in parallel (requires pcntl)
    --help              Show this help message

Examples:
    php run-tests.php
    php run-tests.php --unit --coverage
    php run-tests.php --filter=UserServiceTest
    php run-tests.php --auth --verbose
";

// Parse command line arguments
$options = getopt('uhv', [
    'unit',
    'feature',
    'integration',
    'auth',
    'crud',
    'services',
    'models',
    'coverage',
    'filter:',
    'verbose',
    'stop-on-failure',
    'parallel',
    'help'
]);

if (isset($options['help']) || isset($options['h'])) {
    echo $help;
    exit(0);
}

// Build phpunit command (cross-platform)
$phpunitCommand = DIRECTORY_SEPARATOR === '\\' ? 'vendor\\bin\\phpunit' : './vendor/bin/phpunit';
$command = $phpunitCommand;

// Add test suite filter
if (isset($options['unit'])) {
    $command .= ' --testsuite=Unit';
} elseif (isset($options['feature'])) {
    $command .= ' --testsuite=Feature';
} elseif (isset($options['integration'])) {
    $command .= ' --testsuite=Integration';
} elseif (isset($options['auth'])) {
    $command .= ' --testsuite=Authentication';
} elseif (isset($options['crud'])) {
    $command .= ' --testsuite=CRUD';
} elseif (isset($options['services'])) {
    $command .= ' --testsuite=Services';
} elseif (isset($options['models'])) {
    $command .= ' --testsuite=Models';
}

// Add coverage
if (isset($options['coverage'])) {
    $command .= ' --coverage-html=coverage --coverage-text=coverage.txt';
}

// Add filter
if (isset($options['filter'])) {
    $command .= ' --filter=' . escapeshellarg($options['filter']);
}

// Add verbose
if (isset($options['verbose']) || isset($options['v'])) {
    $command .= ' --verbose';
}

// Add stop on failure
if (isset($options['stop-on-failure'])) {
    $command .= ' --stop-on-failure';
}

// Add parallel execution
if (isset($options['parallel']) && extension_loaded('pcntl')) {
    $command .= ' --parallel';
}

echo "Running: $command\n";
echo str_repeat("=", 80) . "\n";

// Execute the command with Windows support
if (DIRECTORY_SEPARATOR === '\\') {
    // Windows
    $command = 'cmd /c "' . $command . '"';
}
passthru($command, $exitCode);

// Show summary
echo str_repeat("=", 80) . "\n";

if ($exitCode === 0) {
    echo "✅ All tests passed!\n";
} else {
    echo "❌ Tests failed with exit code: $exitCode\n";
}

exit($exitCode);