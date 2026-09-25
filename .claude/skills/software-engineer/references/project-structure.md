# Project Structure

## Structure Selection Guide

| Project Type | Recommended Structure | When |
|-------------|----------------------|------|
| Small-medium app | Feature-based monolith | 1-5 devs, single deployment |
| Large app, multiple teams | Monorepo with feature modules | 5-20 devs, shared code |
| Independent services | Polyrepo microservices | Multiple squads, independent deploys |
| Library/package | Flat module structure | Reusable code, published to registry |

---

## Frontend (React/Vue/Angular)

### Feature-Based (Recommended)

```
src/
├── app/                    # Application shell
│   ├── App.tsx             # Root component
│   ├── routes.tsx          # Route definitions
│   ├── providers.tsx       # Context providers (auth, theme, query)
│   └── layout/             # Layout components (header, sidebar, footer)
├── features/               # Feature modules (self-contained)
│   ├── auth/
│   │   ├── components/     # LoginForm, RegisterForm, ForgotPassword
│   │   ├── hooks/          # useAuth, useSession
│   │   ├── services/       # authApi.ts (API calls)
│   │   ├── stores/         # authStore.ts (state management)
│   │   ├── types/          # Auth-specific types
│   │   ├── utils/          # Token helpers, validators
│   │   └── __tests__/      # Feature tests
│   ├── dashboard/
│   │   ├── components/     # KPICard, ChartWidget, DataTable
│   │   ├── hooks/          # useDashboardData, useRealtimeMetrics
│   │   ├── services/       # dashboardApi.ts
│   │   └── __tests__/
│   └── users/
│       ├── components/     # UserList, UserProfile, UserForm
│       ├── hooks/          # useUsers, useUserDetail
│       ├── services/       # usersApi.ts
│       └── __tests__/
├── shared/                 # Cross-feature shared code
│   ├── components/         # Button, Input, Modal, Toast, DataTable
│   │   └── ui/             # Base UI primitives
│   ├── hooks/              # useDebounce, useLocalStorage, useMediaQuery
│   ├── utils/              # formatDate, formatCurrency, cn()
│   ├── types/              # Global types, API response types
│   ├── constants/          # App-wide constants
│   └── lib/                # Third-party library wrappers
├── assets/                 # Static assets (images, fonts, icons)
├── styles/                 # Global styles, theme, CSS variables
└── __tests__/              # E2E tests, test utilities
```

### Import Rules
```
features/auth/ can import from:
  ✓ shared/*
  ✓ features/auth/* (own feature)
  ✗ features/dashboard/* (cross-feature dependency)
  ✗ features/users/* (cross-feature dependency)

If feature A needs something from feature B:
  → Move shared logic to shared/
  → Or create a shared feature module
```

---

## Backend (Node.js / Python / Go)

### Clean Architecture Structure

```
src/
├── api/                    # Presentation layer
│   ├── routes/             # Route definitions
│   │   ├── users.routes.ts
│   │   ├── orders.routes.ts
│   │   └── index.ts        # Route aggregator
│   ├── controllers/        # Request handlers
│   │   ├── users.controller.ts
│   │   └── orders.controller.ts
│   ├── middleware/          # Auth, logging, error handling, rate limiting
│   │   ├── auth.middleware.ts
│   │   ├── error.middleware.ts
│   │   └── validation.middleware.ts
│   └── validators/         # Request schema validation (Zod, Joi)
│       ├── users.validator.ts
│       └── orders.validator.ts
├── application/            # Business logic layer
│   ├── use-cases/          # Application-specific business rules
│   │   ├── users/
│   │   │   ├── create-user.ts
│   │   │   ├── get-user.ts
│   │   │   └── update-user.ts
│   │   └── orders/
│   │       ├── create-order.ts
│   │       └── process-payment.ts
│   ├── dtos/               # Data transfer objects
│   └── interfaces/         # Port definitions (repository, service interfaces)
├── domain/                 # Enterprise business rules
│   ├── entities/           # Core business objects
│   │   ├── user.entity.ts
│   │   └── order.entity.ts
│   ├── value-objects/      # Immutable domain values
│   │   ├── email.vo.ts
│   │   └── money.vo.ts
│   ├── events/             # Domain events
│   └── errors/             # Domain-specific errors
├── infrastructure/         # External concerns
│   ├── database/
│   │   ├── prisma/         # Prisma schema, migrations
│   │   ├── repositories/   # Repository implementations
│   │   └── seeds/          # Database seeders
│   ├── cache/              # Redis client, caching logic
│   ├── queue/              # Message queue (BullMQ, SQS)
│   ├── email/              # Email service (SendGrid, SES)
│   ├── storage/            # File storage (S3, local)
│   └── external/           # Third-party API clients
├── config/                 # Configuration management
│   ├── env.ts              # Environment variable validation
│   ├── database.ts         # DB connection config
│   └── app.ts              # Application settings
├── shared/                 # Shared utilities
│   ├── utils/
│   ├── types/
│   └── constants/
└── server.ts               # Application entry point
```

---

## Full-Stack Monorepo

```
project-root/
├── apps/
│   ├── web/                # Frontend application
│   │   ├── src/
│   │   ├── package.json
│   │   └── vite.config.ts
│   ├── api/                # Backend API
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   └── admin/              # Admin dashboard (optional)
│       └── ...
├── packages/               # Shared packages
│   ├── ui/                 # Shared UI component library
│   │   ├── src/
│   │   └── package.json
│   ├── shared/             # Shared types, utils, constants
│   │   ├── src/
│   │   │   ├── types/      # Shared TypeScript types
│   │   │   ├── utils/      # Shared utility functions
│   │   │   └── constants/  # Shared constants
│   │   └── package.json
│   └── config/             # Shared configs (ESLint, TSConfig, Tailwind)
│       ├── eslint/
│       ├── typescript/
│       └── tailwind/
├── infrastructure/         # IaC (Terraform, Docker)
│   ├── docker/
│   └── terraform/
├── package.json            # Root workspace config
├── turbo.json              # Turborepo config (or nx.json for Nx)
└── pnpm-workspace.yaml     # Workspace definition
```

### Monorepo Tools

| Tool | Best For |
|------|----------|
| **Turborepo** | Simple setup, caching, TypeScript-first |
| **Nx** | Enterprise, plugin ecosystem, generators |
| **pnpm workspaces** | Package management, strict dependency isolation |
| **Lerna** | Publishing multiple packages (legacy) |

---

## Configuration Files (Root)

```
project-root/
├── .github/
│   ├── workflows/          # CI/CD pipelines
│   │   ├── ci.yml
│   │   └── deploy.yml
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── CODEOWNERS
├── .husky/                 # Git hooks
│   ├── pre-commit          # lint-staged
│   └── commit-msg          # commitlint
├── .vscode/                # Editor settings (optional, shared)
│   ├── settings.json
│   └── extensions.json
├── .env.example            # Environment variable template
├── .gitignore
├── .eslintrc.js            # Linting config
├── .prettierrc             # Formatting config
├── tsconfig.json           # TypeScript config
├── docker-compose.yml      # Local development
└── Makefile                # Common commands (make dev, make test, make build)
```

---

## Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Files (components) | PascalCase | `UserProfile.tsx` |
| Files (utilities) | camelCase or kebab-case | `formatDate.ts` |
| Files (styles) | kebab-case | `user-profile.module.css` |
| Files (tests) | Match source + `.test` | `UserProfile.test.tsx` |
| Directories | kebab-case | `user-management/` |
| Components | PascalCase | `<UserProfile />` |
| Functions | camelCase | `calculateTotal()` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRY_COUNT` |
| Types/Interfaces | PascalCase | `UserProfile`, `OrderStatus` |
| Environment vars | UPPER_SNAKE_CASE | `DATABASE_URL` |

---

## Sources
- Bulletproof React: https://github.com/alan2207/bulletproof-react
- Node.js Best Practices: https://github.com/goldbergyoni/nodebestpractices
- Clean Architecture (Robert C. Martin)
- Turborepo Documentation: https://turbo.build/repo/docs
