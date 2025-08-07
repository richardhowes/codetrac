# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is CodeTrac, a Laravel application with Vue 3 + Inertia.js frontend for tracking Claude Code development sessions. The application receives webhook data from Claude Code sessions and provides analytics dashboards to visualize development activity, costs, and productivity metrics.

## Key Architecture

### Backend (Laravel)
- **Models**: `ClaudeSession`, `Developer`, `Project`, `SessionCommand`, `SessionFile`, `SessionMetric` - Track development sessions and metrics
- **Services**:
  - `TranscriptParser`: Parses Claude Code session transcripts to extract metrics, files, and commands
  - `AnalyticsService`: Generates dashboard statistics and visualizations
- **API**: Webhook endpoint at `/api/transcripts` receives session data from Claude Code hooks

### Frontend (Vue 3 + Inertia.js)
- **UI Components**: Custom shadcn/ui-inspired components in `resources/js/components/ui/`
- **Pages**: Dashboard, authentication, and settings pages
- **State Management**: Uses Inertia.js for server-side state with Vue reactive frontend

## Development Commands

### Running the Application
```bash
# Start all services (server, queue, logs, vite)
composer run dev

# Start with SSR enabled
composer run dev:ssr

# Individual services
php artisan serve          # Start Laravel server
php artisan queue:listen   # Process queued jobs
php artisan pail           # Watch logs in real-time
npm run dev                # Start Vite dev server
```

### Build & Formatting
```bash
# Frontend
npm run build              # Build production assets
npm run build:ssr          # Build with SSR
npm run format             # Format with Prettier
npm run format:check       # Check formatting
npm run lint               # Run ESLint with auto-fix

# Backend
./vendor/bin/pint          # Format PHP code (Laravel Pint)
```

### Testing
```bash
# Run all tests
composer test
# OR
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/DashboardTest.php
```

### Database
```bash
php artisan migrate              # Run migrations
php artisan migrate:fresh        # Reset and re-run migrations
php artisan migrate:rollback     # Rollback last migration batch
```

### Common Artisan Commands
```bash
php artisan make:model ModelName -m     # Create model with migration
php artisan make:controller Name        # Create controller
php artisan make:migration name         # Create migration
php artisan make:request Name           # Create form request
php artisan make:service Name           # Create service class
php artisan tinker                      # Interactive REPL
```

## Project Structure

### API Webhook Flow
1. Claude Code sends transcript to `/api/transcripts` endpoint
2. `WebhookController` processes the request
3. `TranscriptParser` extracts metrics, files, and commands from transcript
4. Data is stored in database models
5. `AnalyticsService` aggregates data for dashboard display

### Database Schema
- `developers`: Tracks individual developers and their machines
- `projects`: Tracks projects by working directory path
- `claude_sessions`: Main session records with transcripts
- `session_metrics`: Token usage, costs, and productivity metrics
- `session_files`: Files read/written during sessions
- `session_commands`: Commands executed during sessions

### Frontend Architecture
- Inertia.js handles routing and page components
- Vue 3 Composition API for reactive components
- TypeScript for type safety
- Tailwind CSS with custom shadcn/ui components
- Vite for fast HMR and building

## Important Conventions

- Follow Laravel conventions for file organization and naming
- Use camelCase for Vue component names and props
- Use PascalCase for Vue component files
- Database migrations only contain `up()` methods (no down methods)
- API endpoints return JSON responses with appropriate status codes
- All timestamps are stored in UTC

## Environment Setup

The application uses SQLite by default. Key environment variables:
- `DB_CONNECTION=sqlite` - Database driver
- `SESSION_DRIVER=database` - Sessions stored in database
- `QUEUE_CONNECTION=database` - Queue jobs in database
- `CACHE_STORE=database` - Cache in database

## Webhook Integration

The application receives Claude Code session data via webhook. The codetrac.sh script in `/scripts/` shows how to configure Claude Code hooks to send session transcripts to this application.