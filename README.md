# StockFlow — PHP Course Project

A hands-on inventory management system used to teach PHP REST API development.
Students build the backend step by step through 8 exercises while the frontend and database are already provided.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | >= 8.1 |
| Composer | >= 2.x |
| Node.js | >= 18.x |
| npm | >= 9.x |

Accounts needed: **Supabase** (free tier) · **Google AI Studio** (free Gemini API key)

---

## Project Structure

```
stockflow/
├── api/                        # PHP Slim 4 REST API (students edit this)
│   ├── public/
│   │   └── index.php           # Entry point
│   ├── src/
│   │   ├── Auth/SupabaseAuth.php
│   │   ├── Middleware/AuthMiddleware.php
│   │   ├── AI/GeminiAI.php
│   │   └── Routes/
│   │       ├── auth.php
│   │       ├── products.php    # Exercises 1, 2, 4, 5
│   │       ├── orders.php      # Exercises 3, 6
│   │       ├── dashboard.php   # Exercise 7
│   │       ├── ai.php          # Exercise 8
│   │       └── stock.php
│   ├── .env                    # API keys (not committed)
│   └── composer.json
├── client/                     # React + Vite frontend (pre-built, read-only)
│   └── @/src/
│       ├── components/         # UI components
│       └── services/api.js     # HTTP client
├── TASKS.md                    # All 8 exercises with hints
└── ARCHITECTURE.md             # System design overview
```

---

## Setup

### 1. Install API dependencies
```bash
cd api
composer install
```

### 2. Configure environment
Create `api/.env` with the following values:
```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SITE_URL=http://localhost:8005
CLIENT_URL=http://localhost:5173
GEMINI_API_KEY=your_gemini_api_key
```

- **Supabase** credentials: Project Settings → API in your Supabase dashboard
- **Gemini** key: [aistudio.google.com/apikey](https://aistudio.google.com/apikey)

### 3. Set up the database
Run the SQL from the Setup section in `TASKS.md` in the Supabase SQL editor to create tables and RLS policies.

### 4. Start the API server
```bash
cd api
php -S localhost:8005 -t public
```

### 5. Install and start the frontend
```bash
cd client/@
npm install
npm run dev
```

Open [http://localhost:5173](http://localhost:5173)

---

## Exercises Overview

All exercises are in `TASKS.md` with full instructions and hints. Students edit only files inside `api/src/Routes/`.

| # | Topic | File | Key concepts |
|---|-------|------|--------------|
| 1 | Post-processing data | `products.php` | `array_map`, data transformation |
| 2 | Search & filtering | `products.php` | Query params, Supabase filters |
| 3 | Date & time handling | `orders.php` | `strtotime`, `date()`, relative time |
| 4 | CRUD — Products | `products.php` | GET/POST/PUT/DELETE, validation |
| 5 | Image upload | `products.php` | File upload, Supabase Storage |
| 6 | CRUD — Orders | `orders.php` | Multi-step insert, state machine |
| 7 | Dashboard analytics | `dashboard.php` | Aggregation, `array_filter/sum` |
| 8 | AI integration | `ai.php` | Gemini API, prompt engineering |

Each route file contains stub routes with `TODO` comments and hints. Students replace each stub with a working implementation.

---

## Tech Stack

- **Backend:** PHP 8.1 · Slim 4 · phpdotenv
- **Frontend:** React 19 · Vite · React Router
- **Database & Auth:** Supabase (PostgreSQL + RLS)
- **Storage:** Supabase Storage
- **AI:** Google Gemini 2.0 Flash
