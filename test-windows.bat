@echo off
REM Simple Windows Test Runner

echo Checking for PHPUnit...
if not exist "vendor\bin\phpunit" (
    echo Error: vendor\bin\phpunit not found.
    echo Please run: composer install
    pause
    exit /b 1
)

echo Running all tests...
echo ========================================

vendor\bin\phpunit

if %ERRORLEVEL% neq 0 (
    echo.
    echo Tests failed!
    pause
    exit /b 1
) else (
    echo.
    echo All tests passed!
    pause
)