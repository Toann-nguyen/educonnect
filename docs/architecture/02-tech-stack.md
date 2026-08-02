# Tech Stack & Packages

## Backend

| Category | Technology | Version | Purpose |
|----------|-----------|---------|---------|
| Framework | Laravel | ^10.10 | Core PHP framework |
| Language | PHP | ^8.1 | Server-side language |
| Database | MySQL | 8.0+ | Relational database |
| Cache & Queue | Redis | 7 | Caching, queue driver, rate limiting |
| Queue Monitoring | Laravel Horizon | ^5.46 | Queue dashboard |
| App Monitoring | Laravel Pulse | ^1.7 | Server & application monitoring |
| Auth | JWT Auth | ^2.3 | JSON Web Token authentication |
| API Auth | Laravel Sanctum | ^3.3 | API token + SPA auth |
| RBAC | Spatie Laravel Permission | ^6.25 | Role-based access control |
| OAuth | Laravel Socialite | ^5.27 | Social login (Google, GitHub, Facebook) |
| 2FA | pragmarx/google2fa-laravel | ^3.0 | TOTP two-factor authentication |
| SMS | Twilio SDK | ^8.11 | SMS gateway |
| Phone Validation | giggsey/libphonenumber-for-php | ^9.0 | Phone number validation & formatting |
| HTTP Client | Guzzle | ^7.2 | HTTP client for external APIs |
| QR Code | bacon/bacon-qr-code + simple-qrcode | ^2.0, ^4.2 | QR code generation |
| API Docs | Scribe + L5-Swagger | ^5.10, ^8.6 | API documentation |

## Frontend

| Category | Technology | Version | Purpose |
|----------|-----------|---------|---------|
| UI Framework | Vue 3 | ^3.4.0 | JavaScript UI framework |
| SPA Bridge | Inertia.js | ^1.0.0 | Laravel + Vue 3 integration |
| CSS | Tailwind CSS | ^3.2.1 | Utility-first CSS |
| Build Tool | Vite | ^8.0.12 | Frontend build tool |
| HTTP Client | Axios | ^1.6.4 | HTTP requests |
| Utilities | Lodash | ^4.18.1 | JS utility library |
| Route Helper | Ziggy | ^2.0 | Laravel routes in JS |
| Form Styles | @tailwindcss/forms | ^0.5.3 | Form reset styles |

## Development Tools

| Tool | Version | Purpose |
|------|---------|---------|
| PHPUnit | ^10.1 | Testing framework |
| Pest | (plugin) | Modern test runner |
| Laravel Pint | ^1.0 | PHP code style fixer |
| Laravel IDE Helper | ^3.1 | IDE auto-completion |
| Laravel Sail | ^1.45 | Docker dev environment |
| Laravel Breeze | ^1.29 | Auth scaffolding |
| Mockery | ^1.4.4 | Mocking framework |
| Faker | ^1.9.1 | Fake data generator |

## Infrastructure

| Component | Technology |
|-----------|-----------|
| Database | MySQL 8.0+ |
| Cache/Queue | Redis 7 |
| Mail | SMTP |
| Filesystem | Local |
| Container | Docker + Docker Compose |
| Web Server | Nginx |
| Queue Driver | Redis (sync in dev, redis in production) |