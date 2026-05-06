# Security Policy

## Reporting
Report vulnerabilities privately to repository maintainers through GitHub Security Advisories.

## Scope
- Admin routes
- Action authorization
- Token handling
- Data exposure in HTML/JSON/logs

## Guarantees
- No secret material should be rendered in UI.
- Default action authorizer is deny-all.
- Approval plain token is never re-rendered after issuance.
