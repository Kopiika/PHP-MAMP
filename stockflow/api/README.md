# StockFlow API

A stateless PHP REST API built with **Slim 4** and **Supabase** for the StockFlow inventory management application.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Slim 4 (PHP >= 8.1) |
| Auth & Database | Supabase (PostgreSQL + Auth) |
| PSR-7 HTTP | slim/psr7 |
| Env Config | vlucas/phpdotenv |
| AI (planned) | Google Gemini |

---

## Project Structure

```
stockflow/api/
├── public/
│   ├── index.php       # App entry point — bootstraps Slim, loads routes
│   └── .htaccess       # Rewrites all requests to index.php
├── src/
│   ├── Auth/
│   │   └── SupabaseAuth.php      # Stateless Supabase HTTP client
│   ├── Middleware/
│   │   └── AuthMiddleware.php    # Bearer token extraction middleware
│   ├── Routes/
│   │   ├── auth.php              # Auth endpoints
│   │   ├── products.php          # Products endpoints
│   │   ├── orders.php            # Orders endpoints
│   │   ├── notes.php             # Notes endpoints (placeholder)
│   │   └── ai.php                # AI endpoints (placeholder)
│   └── AI/
│       └── GeminiAI.php          # Gemini AI client (placeholder)
├── .env                # Environment variables (never commit this)
├── composer.json
└── Dockerfile
```

---

## Setup

### 1. Install dependencies

```bash
cd stockflow/api
composer install
```

### 2. Configure environment

Copy `.env.example` to `.env` and fill in your values:

```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SITE_URL=http://localhost:8005
CLIENT_URL=http://localhost:5173
GEMINI_API_KEY=your_gemini_key
```

### 3. Run locally

```bash
php -S localhost:8005 -t public
```

Or use Apache/Nginx with the `public/` folder as the document root. The `.htaccess` file handles URL rewriting automatically.

---

## API Endpoints

All protected endpoints require an `Authorization: Bearer <token>` header.

### Auth

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/auth/login-url` | Public | Returns the Google OAuth sign-in URL |
| GET | `/api/auth/user` | Protected | Returns the current authenticated user |

### Products

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/products` | Protected | Returns all products with their category, sorted by name |

### Orders

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/orders` | Protected | Returns all orders sorted by date (newest first) |

---

## How Authentication Works

1. The frontend redirects the user to the Google OAuth URL from `/api/auth/login-url`.
2. Supabase handles the OAuth flow and returns a JWT access token to the frontend.
3. The frontend stores the token and sends it as `Authorization: Bearer <token>` on every API request.
4. **`AuthMiddleware`** checks for the `Bearer` prefix and attaches the raw token to the request as an attribute.
5. Route handlers pass the token to **`SupabaseAuth::setToken()`**, which forwards it to Supabase on every cURL call.
6. Supabase validates the token and enforces Row Level Security (RLS) — the API never validates JWTs itself.

```
Client → Authorization: Bearer <token>
            ↓
        AuthMiddleware (checks header, extracts token)
            ↓
        Route Handler (calls SupabaseAuth::setToken())
            ↓
        SupabaseAuth (forwards token to Supabase as Bearer header)
            ↓
        Supabase (validates token, applies RLS, returns data)
```

---

## Key Design Decisions

- **Stateless** — no `$_SESSION`. The JWT token is passed per-request via the `Authorization` header.
- **Token validation is delegated to Supabase** — if a token is invalid or expired, Supabase returns a `401` which is passed through to the client.
- **CORS** is configured in `index.php` to allow requests from `CLIENT_URL` (defaults to `*` if not set).
- **`curl_close()` is omitted** — deprecated in PHP 8.0 and a no-op since PHP 8.5; handles close automatically when out of scope.

---

## Dependencies

```json
{
  "php": ">=8.1",
  "slim/slim": "^4.0",
  "slim/psr7": "^1.0",
  "vlucas/phpdotenv": "^5.0"
}
```
