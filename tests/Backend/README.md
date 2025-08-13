# Backend Tests

## Structure

This directory contains backend tests organized to mirror Maho's module architecture:

```
Backend/
├── Unit/                               # Unit tests
│   ├── Core/
│   │   └── Helper/
│   │       └── ValidationTest.php     # Core validation helper methods
│   ├── Admin/
│   │   └── Model/
│   │       ├── BlockTest.php          # Admin block model validation
│   │       ├── UserTest.php           # Admin user model validation
│   │       └── VariableTest.php       # Admin variable model validation
│   └── Adminhtml/
│       └── Model/
│           └── Email/
│               └── PathValidatorTest.php  # Email path validator
└── BootstrapTest.php                   # Bootstrap/integration test

```

## Running Tests

### All backend tests:
```bash
vendor/bin/pest --testsuite=Backend
```

### Specific module tests:
```bash
vendor/bin/pest tests/Backend/Unit/Core/        # Core module tests
vendor/bin/pest tests/Backend/Unit/Admin/       # Admin module tests
vendor/bin/pest tests/Backend/Unit/Adminhtml/   # Adminhtml module tests
```

### Single test file:
```bash
vendor/bin/pest tests/Backend/Unit/Core/Helper/ValidationTest.php
```

## Test Organization Principles

- **Mirror source structure**: Tests follow `app/code/core/Mage/` module organization
- **Separate concerns**: Unit tests grouped separately from integration tests  
- **Clean naming**: Test files named after the class they test (without "Validation" suffix)
- **Logical grouping**: Related functionality grouped in appropriate module directories