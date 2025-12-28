# Laravel Test Suite Runner for PowerShell (Windows)
# 
# Usage: .\run-tests.ps1 [COMMAND]

param(
    [string]$Command = "all",
    [switch]$Verbose,
    [switch]$Help
)

function Show-Help {
    Write-Host "Laravel Test Suite Runner for PowerShell" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage: .\run-tests.ps1 [COMMAND] [OPTIONS]"
    Write-Host ""
    Write-Host "Commands:" -ForegroundColor Yellow
    Write-Host "  all           Run all tests (default)"
    Write-Host "  unit          Run unit tests only"
    Write-Host "  feature       Run feature tests only"
    Write-Host "  integration   Run integration tests only"
    Write-Host "  auth          Run authentication tests only"
    Write-Host "  crud          Run CRUD tests only"
    Write-Host "  services      Run service tests only"
    Write-Host "  models        Run model tests only"
    Write-Host "  coverage      Run tests with coverage report"
    Write-Host "  quick         Run tests without coverage (faster)"
    Write-Host "  parallel      Run tests in parallel (requires pcntl)"
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "  -Verbose      Verbose output"
    Write-Host "  -Help         Show this help message"
}

function Write-Header {
    param([string]$Text)
    Write-Host "================================" -ForegroundColor Blue
    Write-Host $Text -ForegroundColor Blue
    Write-Host "================================" -ForegroundColor Blue
}

function Run-TestCommand {
    param(
        [string]$TestCommand,
        [string]$SuccessMessage,
        [string]$FailureMessage
    )
    
    Write-Host "Running: $TestCommand" -ForegroundColor Yellow
    Write-Host ""
    
    $exitCode = Start-Process -FilePath "php" -ArgumentList $TestCommand -Wait -PassThru -NoNewWindow
    
    if ($exitCode.ExitCode -eq 0) {
        Write-Host "✅ $SuccessMessage" -ForegroundColor Green
    } else {
        Write-Host "❌ $FailureMessage" -ForegroundColor Red
        exit $exitCode.ExitCode
    }
}

# Show help if requested
if ($Help) {
    Show-Help
    exit 0
}

# Check if vendor/bin/phpunit exists
if (-not (Test-Path "vendor\bin\phpunit")) {
    Write-Host "Error: vendor\bin\phpunit not found. Please run 'composer install' first." -ForegroundColor Red
    exit 1
}

# Create coverage directory if it doesn't exist
if (-not (Test-Path "coverage")) {
    New-Item -ItemType Directory -Path "coverage" | Out-Null
}

# Build verbose argument
$verboseArg = ""
if ($Verbose) {
    $verboseArg = "--verbose"
}

# Execute command based on parameter
switch ($Command.ToLower()) {
    "all" {
        Write-Header "Running All Tests"
        Run-TestCommand "run-tests.php $verboseArg" "All tests passed!" "All tests failed!"
    }
    "unit" {
        Write-Header "Running Unit Tests"
        Run-TestCommand "run-tests.php --unit $verboseArg" "Unit tests passed!" "Unit tests failed!"
    }
    "feature" {
        Write-Header "Running Feature Tests"
        Run-TestCommand "run-tests.php --feature $verboseArg" "Feature tests passed!" "Feature tests failed!"
    }
    "integration" {
        Write-Header "Running Integration Tests"
        Run-TestCommand "run-tests.php --integration $verboseArg" "Integration tests passed!" "Integration tests failed!"
    }
    "auth" {
        Write-Header "Running Authentication Tests"
        Run-TestCommand "run-tests.php --auth $verboseArg" "Authentication tests passed!" "Authentication tests failed!"
    }
    "crud" {
        Write-Header "Running CRUD Tests"
        Run-TestCommand "run-tests.php --crud $verboseArg" "CRUD tests passed!" "CRUD tests failed!"
    }
    "services" {
        Write-Header "Running Service Tests"
        Run-TestCommand "run-tests.php --services $verboseArg" "Service tests passed!" "Service tests failed!"
    }
    "models" {
        Write-Header "Running Model Tests"
        Run-TestCommand "run-tests.php --models $verboseArg" "Model tests passed!" "Model tests failed!"
    }
    "coverage" {
        Write-Header "Running Tests with Coverage"
        Run-TestCommand "run-tests.php --coverage $verboseArg" "Tests with coverage completed!" "Tests with coverage failed!"
        Write-Host "Coverage report generated in coverage\ directory" -ForegroundColor Blue
    }
    "quick" {
        Write-Header "Running Tests (Quick Mode)"
        Run-TestCommand "run-tests.php $verboseArg --stop-on-failure" "Quick tests completed!" "Quick tests failed!"
    }
    "parallel" {
        Write-Header "Running Tests in Parallel"
        # Check if pcntl is available
        $pcntlCheck = php -m | findstr pcntl
        if (-not $pcntlCheck) {
            Write-Host "Error: pcntl extension is not installed. Please install it to run tests in parallel." -ForegroundColor Red
            exit 1
        }
        Run-TestCommand "run-tests.php --parallel $verboseArg" "Parallel tests completed!" "Parallel tests failed!"
    }
    default {
        Write-Host "Error: Unknown command '$Command'" -ForegroundColor Red
        Write-Host ""
        Show-Help
        exit 1
    }
}

Write-Host ""
Write-Host "Test execution completed successfully!" -ForegroundColor Green