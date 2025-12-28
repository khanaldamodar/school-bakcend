@echo off
REM Laravel Test Suite Runner for Windows
REM 
REM This script provides convenient shortcuts for running different test suites on Windows

setlocal enabledelayedexpansion

REM Set default command
set COMMAND=all
set VERBOSE=

REM Parse arguments
for %%x in (%*) do (
    if "%%x"=="-v" set VERBOSE=--verbose
    if "%%x"=="--verbose" set VERBOSE=--verbose
    if "%%x"=="--help" goto :show_help
    if "%%x"=="-h" goto :show_help
    if "%%x"=="all" set COMMAND=all
    if "%%x"=="unit" set COMMAND=unit
    if "%%x"=="feature" set COMMAND=feature
    if "%%x"=="integration" set COMMAND=integration
    if "%%x"=="auth" set COMMAND=auth
    if "%%x"=="crud" set COMMAND=crud
    if "%%x"=="services" set COMMAND=services
    if "%%x"=="models" set COMMAND=models
    if "%%x"=="coverage" set COMMAND=coverage
    if "%%x"=="quick" set COMMAND=quick
    if "%%x"=="parallel" set COMMAND=parallel
)

REM Check if vendor/bin/phpunit exists
if not exist "vendor\bin\phpunit" (
    echo Error: vendor\bin\phpunit not found. Please run 'composer install' first.
    exit /b 1
)

REM Create coverage directory if it doesn't exist
if not exist "coverage" mkdir coverage

goto :run_command

:show_help
echo Laravel Test Suite Runner for Windows
echo.
echo Usage: run-tests.bat [COMMAND]
echo.
echo Commands:
echo   all           Run all tests (default)
echo   unit          Run unit tests only
echo   feature       Run feature tests only
echo   integration   Run integration tests only
echo   auth          Run authentication tests only
echo   crud          Run CRUD tests only
echo   services      Run service tests only
echo   models        Run model tests only
echo   coverage      Run tests with coverage report
echo   quick         Run tests without coverage (faster)
echo   parallel      Run tests in parallel (requires pcntl)
echo.
echo Options:
echo   -v, --verbose Verbose output
echo   -h, --help    Show this help message
goto :end

:run_command
if "%COMMAND%"=="all" goto :run_all
if "%COMMAND%"=="unit" goto :run_unit
if "%COMMAND%"=="feature" goto :run_feature
if "%COMMAND%"=="integration" goto :run_integration
if "%COMMAND%"=="auth" goto :run_auth
if "%COMMAND%"=="crud" goto :run_crud
if "%COMMAND%"=="services" goto :run_services
if "%COMMAND%"=="models" goto :run_models
if "%COMMAND%"=="coverage" goto :run_coverage
if "%COMMAND%"=="quick" goto :run_quick
if "%COMMAND%"=="parallel" goto :run_parallel

:run_all
echo ========================================
echo Running All Tests
echo ========================================
php run-tests.php %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo All tests failed!
    exit /b 1
) else (
    echo All tests passed!
    goto :end
)

:run_unit
echo ========================================
echo Running Unit Tests
echo ========================================
php run-tests.php --unit %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Unit tests failed!
    exit /b 1
) else (
    echo Unit tests passed!
    goto :end
)

:run_feature
echo ========================================
echo Running Feature Tests
echo ========================================
php run-tests.php --feature %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Feature tests failed!
    exit /b 1
) else (
    echo Feature tests passed!
    goto :end
)

:run_integration
echo ========================================
echo Running Integration Tests
echo ========================================
php run-tests.php --integration %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Integration tests failed!
    exit /b 1
) else (
    echo Integration tests passed!
    goto :end
)

:run_auth
echo ========================================
echo Running Authentication Tests
echo ========================================
php run-tests.php --auth %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Authentication tests failed!
    exit /b 1
) else (
    echo Authentication tests passed!
    goto :end
)

:run_crud
echo ========================================
echo Running CRUD Tests
echo ========================================
php run-tests.php --crud %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo CRUD tests failed!
    exit /b 1
) else (
    echo CRUD tests passed!
    goto :end
)

:run_services
echo ========================================
echo Running Service Tests
echo ========================================
php run-tests.php --services %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Service tests failed!
    exit /b 1
) else (
    echo Service tests passed!
    goto :end
)

:run_models
echo ========================================
echo Running Model Tests
echo ========================================
php run-tests.php --models %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Model tests failed!
    exit /b 1
) else (
    echo Model tests passed!
    goto :end
)

:run_coverage
echo ========================================
echo Running Tests with Coverage
echo ========================================
php run-tests.php --coverage %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Tests with coverage failed!
    exit /b 1
) else (
    echo Tests with coverage completed!
    echo Coverage report generated in coverage\ directory
    goto :end
)

:run_quick
echo ========================================
echo Running Tests (Quick Mode)
echo ========================================
php run-tests.php %VERBOSE% --stop-on-failure
if %ERRORLEVEL% neq 0 (
    echo Quick tests failed!
    exit /b 1
) else (
    echo Quick tests completed!
    goto :end
)

:run_parallel
echo ========================================
echo Running Tests in Parallel
echo php -m | findstr pcntl > nul
if %ERRORLEVEL% neq 0 (
    echo Error: pcntl extension is not installed. Please install it to run tests in parallel.
    exit /b 1
)
php run-tests.php --parallel %VERBOSE%
if %ERRORLEVEL% neq 0 (
    echo Parallel tests failed!
    exit /b 1
) else (
    echo Parallel tests completed!
    goto :end
)

:end
echo.
echo Test execution completed successfully!
pause