### Laravel Keycloak Guard Development Skill

This skill provides context for developing, testing, and maintaining the `laravel-keycloak-guard` package.

#### 1. Development Environment & Testing
- **Local Testing**: Use `orchestra/testbench` for package testing.
- **Running Tests**: Run tests from the package root:
  ```bash
  vendor/bin/phpunit
  ```
- **Mocking**: Use `Mockery` to mock Keycloak server responses and JWT validation.

#### 2. Key Components
- `KeycloakGuard`: The main guard implementation (`src/KeycloakGuard.php`).
- `TokenService`: Handles JWT decoding and validation (`src/Services/TokenService.php`).
- `JwksService`: Manages public key retrieval and caching (`src/Services/JwksService.php`).
- `KeycloakOrg`: Facade for organization-related logic (`src/Facades/KeycloakOrg.php`).

#### 3. Adding New Features
- **Middleware**: Register new middleware in `KeycloakGuardServiceProvider`.
- **Configuration**: Update `config/keycloak.php` and ensure the `KeycloakGuardServiceProvider` properly merges the configuration.
- **Commands**: Add diagnostic or utility commands like `KeycloakDoctorCommand`.

#### 4. Coding Standards
- Follow PSR-12 coding standards.
- Use strict typing for all method parameters and return types.
- Ensure all new features are covered by unit or feature tests.

#### 5. Documentation
- Update `README.md` for user-facing changes.
- Update `.ai/guidelines/package-skills.md` to inform AI agents of new capabilities.

#### 6. Keycloak 26 Compatibility
- Ensure organization features align with Keycloak 26's organization mappers and claims.
- Validate `iss` and `aud` claims by default for enhanced security.
