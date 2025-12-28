#!/bin/bash

# Laravel Test Suite Runner
# 
# This script provides convenient shortcuts for running different test suites

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_usage() {
    echo "Laravel Test Suite Runner"
    echo ""
    echo "Usage: ./run-tests.sh [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  all           Run all tests (default)"
    echo "  unit          Run unit tests only"
    echo "  feature       Run feature tests only"
    echo "  integration   Run integration tests only"
    echo "  auth          Run authentication tests only"
    echo "  crud          Run CRUD tests only"
    echo "  services      Run service tests only"
    echo "  models        Run model tests only"
    echo "  coverage      Run tests with coverage report"
    echo "  quick         Run tests without coverage (faster)"
    echo "  parallel      Run tests in parallel (requires pcntl)"
    echo "  watch         Run tests in watch mode (requires fswatch)"
    echo ""
    echo "Options:"
    echo "  -v, --verbose Verbose output"
    echo "  -h, --help    Show this help message"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}================================${NC}"
}

run_command() {
    echo -e "${YELLOW}Running: $1${NC}"
    echo ""
    
    if eval $1; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $3${NC}"
        exit 1
    fi
}

# Parse command
COMMAND=${1:-all}
VERBOSE=""
EXTRA_ARGS=""

for arg in "$@"; do
    case $arg in
        -v|--verbose)
            VERBOSE="--verbose"
            shift
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        --stop-on-failure)
            EXTRA_ARGS="$EXTRA_ARGS --stop-on-failure"
            shift
            ;;
    esac
done

# Check if vendor/bin/phpunit exists
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}Error: vendor/bin/phpunit not found. Please run 'composer install' first.${NC}"
    exit 1
fi

# Create coverage directory if it doesn't exist
mkdir -p coverage

case $COMMAND in
    all)
        print_header "Running All Tests"
        run_command "php run-tests.php $VERBOSE $EXTRA_ARGS" "All tests passed!" "All tests failed!"
        ;;
    unit)
        print_header "Running Unit Tests"
        run_command "php run-tests.php --unit $VERBOSE $EXTRA_ARGS" "Unit tests passed!" "Unit tests failed!"
        ;;
    feature)
        print_header "Running Feature Tests"
        run_command "php run-tests.php --feature $VERBOSE $EXTRA_ARGS" "Feature tests passed!" "Feature tests failed!"
        ;;
    integration)
        print_header "Running Integration Tests"
        run_command "php run-tests.php --integration $VERBOSE $EXTRA_ARGS" "Integration tests passed!" "Integration tests failed!"
        ;;
    auth)
        print_header "Running Authentication Tests"
        run_command "php run-tests.php --auth $VERBOSE $EXTRA_ARGS" "Authentication tests passed!" "Authentication tests failed!"
        ;;
    crud)
        print_header "Running CRUD Tests"
        run_command "php run-tests.php --crud $VERBOSE $EXTRA_ARGS" "CRUD tests passed!" "CRUD tests failed!"
        ;;
    services)
        print_header "Running Service Tests"
        run_command "php run-tests.php --services $VERBOSE $EXTRA_ARGS" "Service tests passed!" "Service tests failed!"
        ;;
    models)
        print_header "Running Model Tests"
        run_command "php run-tests.php --models $VERBOSE $EXTRA_ARGS" "Model tests passed!" "Model tests failed!"
        ;;
    coverage)
        print_header "Running Tests with Coverage"
        run_command "php run-tests.php --coverage $VERBOSE $EXTRA_ARGS" "Tests with coverage completed!" "Tests with coverage failed!"
        echo -e "${BLUE}Coverage report generated in coverage/ directory${NC}"
        ;;
    quick)
        print_header "Running Tests (Quick Mode)"
        run_command "php run-tests.php $VERBOSE --stop-on-failure" "Quick tests completed!" "Quick tests failed!"
        ;;
    parallel)
        print_header "Running Tests in Parallel"
        if ! php -m | grep -q pcntl; then
            echo -e "${RED}Error: pcntl extension is not installed. Please install it to run tests in parallel.${NC}"
            exit 1
        fi
        run_command "php run-tests.php --parallel $VERBOSE $EXTRA_ARGS" "Parallel tests completed!" "Parallel tests failed!"
        ;;
    watch)
        print_header "Running Tests in Watch Mode"
        if ! command -v fswatch &> /dev/null; then
            echo -e "${RED}Error: fswatch is not installed. Please install it to use watch mode.${NC}"
            echo "On macOS: brew install fswatch"
            echo "On Ubuntu: sudo apt-get install fswatch"
            exit 1
        fi
        
        echo -e "${YELLOW}Watching for file changes... (Ctrl+C to stop)${NC}"
        fswatch -o app/ tests/ | xargs -n1 -I{} sh -c 'echo "Running tests..." && php run-tests.php --quick'
        ;;
    *)
        echo -e "${RED}Error: Unknown command '$COMMAND'${NC}"
        echo ""
        print_usage
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}Test execution completed successfully!${NC}"