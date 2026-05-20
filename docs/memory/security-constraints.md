

## SEC-04: Strict Type Comparison for Access Checks

Membership checks for access control decisions MUST use strict comparison (`===`/`in_array(..., true)`), not loose comparison (`==`/`in_array(..., false)`). This prevents type coercion attacks.

**Vulnerability Prevented**: Type coercion allows integers to match strings. Example: `0 == 'admin'` is `true` in PHP (loose), but `0 === 'admin'` is `false` (strict).

**Pattern**:
```php
// CORRECT
return in_array( $slug, $protected_slugs, true );  // strict=true

// WRONG
return in_array( $slug, $protected_slugs );  // default strict=false
```

**When to Apply**: All array membership checks for access control, all authorization comparisons, all identity checks in security-sensitive code.

**Reference**: Feature 005 (`AcrossAI_Protected_Abilities::is_protected()`, line 69), security review (SECURITY-REVIEW.md "Type Safety" section).

