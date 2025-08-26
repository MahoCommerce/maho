# Maho JSON-RPC API Testing Framework

This directory contains the JSON-RPC API testing framework for Maho, built on top of Pest PHP testing framework.

## Structure

```
tests/Api/
├── Client/
│   ├── JsonRpcClient.php         # JSON-RPC client implementation
│   └── Response/
│       └── JsonRpcResponse.php   # Response wrapper with validation
├── JsonRpc/
│   └── BlogPostApiTest.php       # Example Blog API tests
└── README.md                     # This file
```

## Key Components

### JsonRpcClient
- **Purpose**: HTTP client specifically designed for JSON-RPC 2.0 communication
- **Features**: 
  - Session management and authentication
  - Multi-call batch operations
  - HTTP Basic Auth support
  - Configurable timeouts

### JsonRpcResponse  
- **Purpose**: Wraps and validates JSON-RPC responses
- **Features**:
  - JSON-RPC 2.0 spec validation
  - Error/success state detection
  - Type-safe result extraction

### MahoApiTestCase (Base Class)
- **Purpose**: Base test class for all API tests
- **Features**:
  - Automatic session management
  - Environment-based configuration
  - Common assertion helpers
  - Test data cleanup utilities

## Configuration

API tests use sensible defaults and can be configured via environment variables if needed:

### Default Configuration
- **API URL**: Auto-detected from Maho's base URL configuration
- **Username**: `test_api_user`
- **Password**: `test_api_password_123`
- **Timeout**: `30` seconds

### Environment Variable Overrides (Optional)
```bash
API_BASE_URL=http://custom.test/api.php  # Override auto-detected URL
API_USERNAME=custom_api_user             # Override default username
API_PASSWORD=custom_password             # Override default password
API_TIMEOUT=60                           # Override default timeout
```

### Automatic URL Detection

The framework automatically detects the API base URL using multiple methods:

1. **Environment Variable**: If `API_BASE_URL` is set, it takes precedence
2. **Maho Store Configuration**: Uses `$store->getBaseUrl()` from current store  
3. **Database Configuration**: Falls back to `web/unsecure/base_url` or `web/secure/base_url`
4. **Server Environment**: Detects from `$_SERVER['HTTP_HOST']` for local development
5. **Fallback**: Uses `http://localhost/api.php` as last resort

### Zero Configuration Setup

In most cases, no configuration is needed! The framework will:
- Automatically detect your API endpoint
- Use the default test API user created by the setup script
- Work out of the box in development and CI environments

## Writing Tests

### Basic Test Structure

```php
use Tests\MahoApiTestCase;

uses(MahoApiTestCase::class);

describe('My API Resource', function () {
    it('can perform some operation', function () {
        $response = $this->authenticatedCall('resource.method', [
            'param1' => 'value1'
        ]);
        
        $this->assertSuccessfulResponse($response);
        expect($response->getResult())->toBeArray();
    });
});
```

### Available Helper Methods

#### Authentication
- `getAuthenticatedSessionId()` - Get session ID for authenticated calls
- `authenticatedCall(method, params)` - Make authenticated API call

#### Assertions  
- `assertSuccessfulResponse(response)` - Assert response is successful
- `assertErrorResponse(response, expectedMessage?)` - Assert response contains error
- `assertResponseStructure(response, structure)` - Validate response structure

#### Utilities
- `skipIfApiNotAvailable()` - Skip test if API is unreachable
- `createTestData()` - Override to create test data
- `cleanupTestData(data)` - Override to cleanup test data

## GitHub Actions Integration

The framework is integrated with GitHub Actions workflow:

1. **API User Setup**: `tests/setup-api-user.php` creates API user and role
2. **Environment Variables**: Set in workflow for test execution
3. **Automatic Cleanup**: Sessions are automatically closed after tests

## Running Tests Locally

1. **Setup API User**:
   ```bash
   php tests/setup-api-user.php
   ```

2. **Run API Tests**:
   ```bash
   ./vendor/bin/pest tests/Api/
   ```

That's it! No environment variables needed - everything uses sensible defaults.

### Optional: Custom Configuration
If you need different settings, you can set environment variables:
```bash
export API_USERNAME=my_api_user
export API_PASSWORD=my_password
./vendor/bin/pest tests/Api/
```

## Example: Blog Post API Tests

The `BlogPostApiTest.php` demonstrates:
- Authentication testing
- Full CRUD operations (Create, Read, Update, Delete)
- Validation and error handling
- Filtering and search operations
- Batch operations with multiCall
- Data cleanup patterns

## Best Practices

1. **Always clean up test data** - Use `afterEach()` hooks to delete created resources
2. **Use unique identifiers** - Include timestamps in test data to avoid conflicts
3. **Test both success and error cases** - Validate proper error handling
4. **Verify response structure** - Use `assertResponseStructure()` for type safety
5. **Skip unavailable APIs** - Use `skipIfApiNotAvailable()` for optional features

## Extending the Framework

To add new API tests:

1. Create test file in appropriate subdirectory (e.g., `tests/Api/JsonRpc/`)
2. Extend `MahoApiTestCase` 
3. Use existing patterns for authentication and cleanup
4. Add resource-specific helper methods as needed

The framework is designed to be extensible while maintaining consistency across all API tests.