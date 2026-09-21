# Architecture Documentation Index

This directory contains the architecture documentation for the EduConnect project, organized by topic.

## Files

| # | File | Description |
|---|------|-------------|
| 01 | `01-project-structure.md` | Project structure overview, domain layout, routes, DB connections, known issues |
| 02 | `02-tech-stack.md` | Complete tech stack and package listing (backend, frontend, dev tools, infrastructure) |
| 03 | `03-domain-architecture.md` | Domain-Driven Design breakdown: Identity, School, Finance domains with models, controllers, services |
| 04 | `04-api-routes.md` | All API endpoints organized by domain with middleware, parameters, and conventions |
| 05 | `05-database-connections.md` | Database connection configuration, model-to-connection mapping, migration organization |
| 06 | `06-enums.md` | Global enums (RoleEnum, PermissionEnum) with all cases, values, labels, and usage |
| 07 | `07-service-layer.md` | Service layer architecture, DI wiring, repository pattern, business logic flows |
| 08 | `08-known-issues.md` | Known issues, technical debt, architecture decisions, and priorities |

## Related Documentation

| File | Description |
|------|-------------|
| `../auth-token-flow.md` | JWT + refresh token flow, cookie configuration, theft detection |
| `../flow_login.md` | Login flow details |
| `../dual_sliding_window_nginx_rate_limit.md` | Rate limiting architecture |
| `../login_flow_evaluation.md` | Login flow evaluation |
| `../microservices/` | Microservice refactoring plans |
| `../tests/` | Test plans and strategies |

## Quick Reference

### Domains
- **Identity** (`app/Domains/Identity/`) — Auth, users, roles, permissions, 2FA, audit
- **School** (`app/Domains/School/`) — Students, classes, grades, schedules, discipline, library, events
- **Finance** (`app/Domains/Finance/`) — Invoices, payments, fee types (no HTTP layer yet)

### Key Technologies
- Laravel 10 + PHP 8.1
- JWT Auth (`php-open-source-saver/jwt-auth`)
- Spatie RBAC
- Redis (cache + queue + rate limiting)
- MySQL (3 connections: `identity`, `school`, `finance`)
- Vue 3 + Inertia.js (frontend)

### Enums
- `RoleEnum` — 8 roles (admin, principal, teacher, student, parent, accountant, librarian, red_scarf)
- `PermissionEnum` — 27 permissions across user, school, finance, library, discipline, event categories