# Test Suite Documentation

This document provides comprehensive information about the test suite for the Laravel Multi-Tenant School Management System.

## Overview

The test suite covers all major functionality of the system including:
- Unit tests for models, services, and utilities
- Feature tests for API endpoints and CRUD operations
- Integration tests for multi-tenant functionality
- Authentication and authorization testing
- Database and migration testing

## Test Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── TenantTest.php
│   │   ├── UserTest.php
│   │   └── StudentTest.php
│   └── Services/
│       ├── ResultCalculationServiceTest.php
│       └── SMSServiceTest.php
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php
│   │   └── AuthorizationTest.php
│   ├── CRUD/
│   │   └── StudentCRUDTest.php
│   └── Integration/
│       └── MultiTenantTest.php
└── Helpers/
    ├── CreatesAuthenticationTokens.php
    ├── CreatesTenants.php
    └── HandlesFileUploads.php
```

## Running Tests

### Using the Test Runner Scripts

#### PHP Script (Cross-platform)
```bash
# Run all tests
php run-tests.php

# Run specific test suites
php run-tests.php --unit
php run-tests.php --feature
php run-tests.php --integration
php run-tests.php --auth
php run-tests.php --crud
php run-tests.php --services
php run-tests.php --models

# With coverage
php run-tests.php --coverage

# Filter specific tests
php run-tests.php --filter="UserServiceTest"

# Verbose output
php run-tests.php --verbose

# Show help
php run-tests.php --help
```

#### Shell Script (Linux/macOS)
```bash
# Make executable
chmod +x run-tests.sh

# Run all tests
./run-tests.sh

# Run specific test suites
./run-tests.sh unit
./run-tests.sh feature
./run-tests.sh integration
./run-tests.sh auth
./run-tests.sh crud
./run-tests.sh services
./run-tests.sh models

# With coverage
./run-tests.sh coverage

# Quick mode (faster)
./run-tests.sh quick

# Parallel execution
./run-tests.sh parallel

# Watch mode (continuous testing)
./run-tests.sh watch

# Verbose output
./run-tests.sh --verbose
```

### Using PHPUnit Directly

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/Models/UserTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html=coverage

# Filter tests
./vendor/bin/phpunit --filter="test_user_can_be_created"

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit
```

## Test Categories

### 1. Unit Tests

#### Model Tests
- **TenantTest**: Tests Tenant model relationships, database naming, and business logic
- **UserTest**: Tests User model authentication, roles, and validation
- **StudentTest**: Tests Student model relationships and data integrity

#### Service Tests
- **ResultCalculationServiceTest**: Tests GPA calculation, Nepal grading system, and result validation
- **SMSServiceTest**: Tests SMS service integration and error handling

### 2. Feature Tests

#### Authentication Tests
- **AuthenticationTest**: Tests login, logout, registration, and password management
- **AuthorizationTest**: Tests role-based access control for all user types

#### CRUD Tests
- **StudentCRUDTest**: Tests Create, Read, Update, Delete operations for students
- **TeacherCRUDTest**: Tests CRUD operations for teachers
- **ClassCRUDTest**: Tests CRUD operations for classes and subjects

#### Integration Tests
- **MultiTenantTest**: Tests tenant isolation, domain resolution, and multi-tenant architecture

### 3. Test Helpers

#### Traits
- **CreatesAuthenticationTokens**: Helper methods for creating authenticated users
- **CreatesTenants**: Helper methods for creating test tenants
- **HandlesFileUploads**: Helper methods for file upload testing

## Configuration

### PHPUnit Configuration (phpunit.xml)

The PHPUnit configuration includes:
- Separate test suites for different categories
- SQLite in-memory database for fast testing
- Code coverage configuration
- Logging and reporting
- Environment variables for testing

### Test Environment

The test environment uses:
- SQLite in-memory database
- Array cache driver
- Sync queue driver
- File session driver
- Testing-specific environment variables

## Coverage Reports

Coverage reports are generated in the `coverage/` directory when using the `--coverage` option.

### Viewing Coverage Reports

```bash
# Generate coverage report
php run-tests.php --coverage

# Open in browser
open coverage/index.html  # macOS
xdg-open coverage/index.html  # Linux
```

## Test Data

### Factories

The test suite uses factories to generate realistic test data:
- **UserFactory**: Creates users with different roles
- **TenantFactory**: Creates tenant instances with domains
- **StudentFactory**: Creates student records with realistic data
- **TeacherFactory**: Creates teacher records
- **SchoolClassFactory**: Creates class instances
- **ResultFactory**: Creates result records with calculated values

### Relationships

All models maintain proper relationships:
- User ↔ Student/Teacher/Parent
- Tenant ↔ Domain
- Student ↔ Class ↔ Results
- Results ↔ Subjects/Terms

## Multi-Tenant Testing

### Tenant Isolation

The test suite verifies:
- Data isolation between tenants
- Domain-based tenant resolution
- Separate database connections per tenant
- Cross-tenant access prevention

### Domain Testing

Tests verify:
- Domain uniqueness
- Domain-to-tenant mapping
- Subdomain resolution
- Multiple domains per tenant

## Performance Considerations

### Database Testing
- Uses SQLite in-memory for speed
- Proper transaction rollback
- Efficient factory usage
- Minimal data generation

### Parallel Testing
- Supports parallel execution with pcntl
- Isolated test databases
- Concurrent test execution

## Continuous Integration

### GitHub Actions (Example)

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.2
        extensions: mbstring, pdo, sqlite, pcntl
    - name: Install dependencies
      run: composer install
    - name: Run tests
      run: php run-tests.php --coverage
    - name: Upload coverage
      uses: codecov/codecov-action@v1
```

## Best Practices

### Writing Tests
1. **Arrange-Act-Assert**: Structure tests clearly
2. **Descriptive Names**: Use descriptive test method names
3. **One Assertion Per Test**: Focus on single behavior
4. **Test Data**: Use factories for realistic data
5. **Clean State**: Use RefreshDatabase trait

### Test Organization
1. **Group Related Tests**: Organize tests by functionality
2. **Use Helpers**: Reuse common test logic
3. **Documentation**: Document complex test scenarios
4. **Coverage**: Aim for high code coverage
5. **Performance**: Keep tests fast and efficient

### Debugging Tests
1. **Verbose Output**: Use `--verbose` for detailed output
2. **Stop on Failure**: Use `--stop-on-failure` to stop on first error
3. **Filtering**: Use `--filter` to run specific tests
4. **Database State**: Use `--debug` to see database state
5. **Log Output**: Check test logs for debugging information

## Troubleshooting

### Common Issues

1. **Memory Errors**: Increase PHP memory limit
2. **Database Errors**: Check migrations and seeds
3. **Authentication Issues**: Verify token generation
4. **File Upload Errors**: Check file storage configuration
5. **Timeout Errors**: Increase test timeout values

### Solutions

1. **Clear Cache**: `php artisan config:clear && php artisan cache:clear`
2. **Fresh Database**: `php artisan migrate:fresh --seed`
3. **Dependencies**: `composer install --no-dev`
4. **Permissions**: Check file and directory permissions
5. **Environment**: Verify `.env.testing` configuration

## Extending the Test Suite

### Adding New Tests

1. **Create Test File**: Add to appropriate directory
2. **Use Base Classes**: Extend TestCase
3. **Use Traits**: Include helper traits
4. **Follow Patterns**: Use existing test patterns
5. **Documentation**: Document test scenarios

### Testing New Features

1. **Unit Tests**: Test individual components
2. **Feature Tests**: Test API endpoints
3. **Integration Tests**: Test system integration
4. **Edge Cases**: Test error conditions
5. **Performance**: Test performance impact

## Conclusion

This comprehensive test suite ensures the reliability, security, and performance of the Laravel Multi-Tenant School Management System. Regular execution of these tests helps maintain code quality and prevents regressions.

For any questions or issues with the test suite, please refer to this documentation or contact the development team.