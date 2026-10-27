# 🔍 Smart Campus Lost & Found System

[![Vercel Deployment](https://img.shields.io/badge/Vercel-Deployed-black?style=flat-square&logo=vercel)](https://smart-campus-lost-and-found-ten.vercel.app)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Database](https://img.shields.io/badge/Database-PostgreSQL-336791?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![Architecture](https://img.shields.io/badge/Architecture-OOP%20%2F%20MVC-success?style=flat-square)](#-architecture--system-design)
[![Security](https://img.shields.io/badge/Security-Hardened-blue?style=flat-square)](#-security-architecture--hardening)
[![License](https://img.shields.io/badge/License-Academic%20Use-orange?style=flat-square)](#-license)

> **Live Production Platform**: [https://smart-campus-lost-and-found-ten.vercel.app](https://smart-campus-lost-and-found-ten.vercel.app)

A centralized, enterprise-grade digital platform designed for modern university campuses (**Cochin University College of Engineering Kuttanad - CUCEK**) to report, track, match, verify, and recover lost and found personal belongings.

Built from the ground up with **PHP 8+ Object-Oriented Programming (OOP)**, a clean **Model-View-Controller (MVC)** design pattern, serverless **PostgreSQL** (Neon / Vercel Postgres), and resilient security hardening.

---

## 📑 Table of Contents

- [System Architecture](#-system-architecture)
- [Key Features](#-key-features)
- [Security Architecture & Hardening](#-security-architecture--hardening)
- [Directory Structure](#-directory-structure)
- [Database Schema & Migrations](#-database-schema--migrations)
- [API Specification](#-api-specification)
- [Getting Started (Local Development)](#-getting-started-local-development)
- [Deployment (Vercel)](#-deployment-vercel)
- [Default Demonstration Accounts](#-default-demonstration-accounts)
- [Contributing & License](#-license)

---

## 🏛️ System Architecture

The application adopts an enterprise **Model-View-Controller (MVC)** architecture with strong separation of concerns, decoupling presentation, business routing, domain services, and database persistence.

```mermaid
flowchart TD
    Client["Client Single Page App<br/>(Vanilla ES6+ / HTML5 / CSS3)"]
    
    subgraph "Serverless PHP Runtime (Vercel)"
        FC["api/api.php<br/>(Front Controller & CORS Guard)"]
        Router["api/core/Router.php<br/>(Action Dispatcher)"]
        Auth["api/core/Auth.php<br/>(JWT HMAC-SHA256 & RBAC)"]
        
        subgraph "Controllers"
            AC["AuthController"]
            DC["DashboardController"]
            IC["ItemController"]
            CC["ClaimController"]
            AdminC["AdminController"]
        end
        
        subgraph "Domain Services"
            MS["MatchService<br/>(Tokenization & Proximity)"]
            IS["ImageService<br/>(Magic Bytes & MIME)"]
            NS["NotificationService<br/>(Email Queue)"]
        end
        
        subgraph "Data Models"
            BM["BaseModel<br/>(PostgreSQL CRUD & Sequence ID)"]
            UM["User / Admin"]
            IM["LostItem / FoundItem"]
            CLM["Claim"]
            MNM["MatchNotification"]
            ALM["AuditLog"]
        end
    end
    
    subgraph "Storage & Database"
        PG[("PostgreSQL Database<br/>(Neon / Vercel Postgres)")]
    end

    Client -->|HTTP / JSON + Bearer Token| FC
    FC --> Router
    Router --> Auth
    Auth --> AC & DC & IC & CC & AdminC
    IC & CC --> MS & IS
    AC & CC & AdminC --> NS
    Controllers --> BM
    BM --> UM & IM & CLM & MNM & ALM
    UM & IM & CLM & MNM & ALM -->|PDO pgsql with SNI Routing| PG
```

### Architectural Principles
* **Single Responsibility (SRP)**: Domain algorithms (scoring, image processing, notification queues) live in isolated Services rather than monolithic controllers.
* **Database Abstraction**: All database interactions flow through dedicated Models inheriting from `BaseModel`, utilizing parameterized statements and PostgreSQL sequences.
* **Stateless Autoloading**: A high-performance class loader (`api/core/Autoloader.php`) dynamically loads classes on demand without manual, brittle `require` trees.
* **Serverless Resiliency**: Built-in compatibility with Vercel Serverless Functions and Neon database proxy routing.

---

## ✨ Key Features

### 1. Lost & Found Management
* **Lost Item Reporting**: Students can register missing belongings with detailed descriptions, estimated date lost, campus location, keywords, and optional photographic proof.
* **Found Item Directory**: Public catalog of turned-in items with real-time status tracking (`Available` vs `Claimed`).
* **Multi-Filter Search**: Instant keyword filtering across item names, locations, categories, and descriptions.

### 2. Intelligent Auto-Matching Engine
* **Proximity & Token Analysis**: When a lost or found report is submitted, the `MatchService` algorithm decomposes item text into sanitized keyword tokens, filters English stop-words, and scores items using a weighted algorithm:
  * Category Exact Match: **+35 pts**
  * Keyword Overlap: **Up to +35 pts** (10 pts per matching token)
  * Location Match / Proximity: **Up to +25 pts**
  * Title Similarity: **+15 pts**
* **Instant Match Alerts**: Items scoring $\ge 35\%$ trigger real-time notifications directly on user dashboards with direct claim links.

### 3. Claim Verification Workflow
* **Structured Claim Proof**: To deter false claims, users must provide detailed identification criteria (e.g. unique scratches, serial numbers, wallpapers, contents) and optional verification images.
* **Race Condition Prevention**: Database-level validation prevents duplicate concurrent pending claims on the same item.

### 4. Administrative Control Center
* **Metrics Dashboard**: Overview of campus-wide recovery metrics, active lost items, unclaimed found items, and registered accounts.
* **Claim Adjudication**: Review submitted claims with side-by-side photographic comparison. Approving a claim automatically transitions the found item to `Claimed` and resolves linked lost reports.
* **Audit Trail**: Chronological, immutable logging of all critical system events (registrations, logins, removals, and status updates).

---

## 🛡️ Security Architecture & Hardening

The application is hardened against OWASP Top 10 vulnerabilities:

| Defense Vector | Implementation Mechanism |
| :--- | :--- |
| **Cross-Site Scripting (XSS)** | `script.js` sanitizes dynamic DOM insertions with full HTML entity replacement including single quotes (`&#039;`). Image URIs are strictly validated against `uploads/` or `data:image/` schemes. |
| **Arbitrary File Disclosure** | `api/uploads.php` enforces a strict extension whitelist (`jpg`, `jpeg`, `png`, `gif`, `webp`) preventing unauthorized retrieval of `.env`, `.php`, or configuration files. |
| **Malicious File Uploads** | `ImageService` performs deep binary inspection via `getimagesizefromstring()`, verifying magic bytes and valid image dimensions rather than trusting user-supplied headers. |
| **SQL Injection** | Exclusively uses PDO parameterized queries (`$st->prepare()` and `execute([$param])`). Table and column identifiers are validated via regex (`^[a-z_][a-z0-9_]*$`). |
| **Brute-Force Protection** | `AuthController` enforces a rolling 15-minute rate limit. Five consecutive failed login attempts trigger an automatic `429 Too Many Requests` lockout. |
| **Session Security** | Sessions rotate identifiers upon privilege changes (`session_regenerate_id(true)`) and cookies are dispatched with `HttpOnly`, `Secure`, and `SameSite=Lax`. |
| **Information Leakage** | Database exceptions are logged to internal server logs while client responses receive clean, standardized error messages without leaking table or column schemas. |
| **HTTP Security Headers** | Dispatches `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` (Clickjacking defense), `Referrer-Policy: strict-origin-when-cross-origin`, and restricted CORS. |

---

## 📂 Directory Structure

```
smart-campus-lost-and-found/
├── api/
│   ├── core/
│   │   ├── Auth.php                # JWT token creation, signature verification & session guards
│   │   ├── Autoloader.php          # Dynamic class loader for core, models, services & controllers
│   │   ├── Controller.php          # Abstract base controller providing request & response helpers
│   │   ├── Database.php            # PDO PostgreSQL singleton with Neon SNI routing failovers
│   │   ├── Request.php             # Request context (method, headers, body, query, bearer tokens)
│   │   ├── Response.php            # JSON response formatter & security header dispatcher
│   │   ├── Router.php              # Dispatcher mapping action queries to controller methods
│   │   └── Schema.php              # DDL schema migrations, column checks & default seeding
│   ├── models/
│   │   ├── Admin.php               # Administrator entity queries
│   │   ├── AuditLog.php            # Audit trail recording and retrieval
│   │   ├── BaseModel.php           # Abstract CRUD model with PostgreSQL sequence handling
│   │   ├── Claim.php               # Ownership claims and status transitions
│   │   ├── FoundItem.php           # Found item catalog and search filters
│   │   ├── LostItem.php            # Lost item reporting and status resolution
│   │   ├── MatchNotification.php   # Automated match recommendation alerts
│   │   └── User.php                # Student and faculty user accounts
│   ├── services/
│   │   ├── ImageService.php        # Deep binary validation, resizing, and storage
│   │   ├── MatchService.php        # Keyword tokenization and similarity scoring algorithm
│   │   └── NotificationService.php # Notification dispatcher and email queue manager
│   ├── controllers/
│   │   ├── AdminController.php     # Admin panel, user moderation, and claim adjudication
│   │   ├── AuthController.php      # Registration, login, session inspection, and logout
│   │   ├── ClaimController.php     # Claim verification, eligibility checks, and submissions
│   │   ├── DashboardController.php # Student dashboard, stats aggregation, and match alerts
│   │   └── ItemController.php      # Item browsing, reporting, and deletion
│   ├── api.php                     # Central Front Controller entry point
│   ├── db.php                      # Backward-compatible database connector
│   └── uploads.php                 # Hardened image serving proxy
├── index.html                      # Single Page Application interface
├── script.js                       # Frontend state management, SPA routing, and API interactions
├── style.css                       # Responsive CSS styles with CSS custom properties (Dark/Light)
├── database_postgres.sql           # Reference PostgreSQL schema DDL
├── vercel.json                     # Vercel deployment and serverless PHP runtime configuration
└── README.md                       # Documentation
```

---

## 🗄️ Database Schema & Migrations

The application manages database migrations automatically on application startup via `api/core/Schema.php`.

```mermaid
erDiagram
    users ||--o{ lost_items : "reports"
    users ||--o{ found_items : "turns in"
    users ||--o{ claims : "submits"
    users ||--o{ item_match_notifications : "receives"
    users ||--o{ audit_logs : "triggers"
    found_items ||--o{ claims : "claimed by"
    lost_items ||--o{ claims : "linked to"
    lost_items ||--o{ item_match_notifications : "matched with"
    found_items ||--o{ item_match_notifications : "matches"

    users {
        int id PK
        string name
        string email UK
        string password
        string uid UK
        string dept
        string role
        string status
        int is_admin
        timestamp created_at
    }

    admins {
        int id PK
        string name
        string email UK
        string password
        string uid UK
        string dept
        string role
        string status
        timestamp created_at
    }

    lost_items {
        int id PK
        int user_id FK
        string user_name
        string item_name
        string category
        text description
        string keywords
        text image_path
        date date_lost
        string location
        string status
        timestamp created_at
    }

    found_items {
        int id PK
        int user_id FK
        string user_name
        string item_name
        string category
        text description
        string keywords
        text image_path
        date date_found
        string location
        string status
        timestamp created_at
    }

    claims {
        int id PK
        int item_id FK
        int lost_item_id FK
        string item_name
        int user_id FK
        string user_name
        text details
        text image_path
        string status
        timestamp created_at
    }

    item_match_notifications {
        int id PK
        int user_id FK
        int lost_item_id FK
        int found_item_id FK
        int match_score
        string match_reason
        int is_read
        timestamp created_at
    }

    audit_logs {
        int id PK
        int user_id FK
        string action
        text description
        timestamp created_at
    }
```

---

## 🔌 API Specification

All endpoints receive and return JSON (`Content-Type: application/json; charset=utf-8`). Authenticated endpoints accept requests via the standard `Authorization: Bearer <token>` header or PHP session cookies.

| Endpoint Action | Method | Controller & Action | Auth Level | Description |
| :--- | :---: | :--- | :---: | :--- |
| `?action=session` | `POST` | `AuthController::session` | Public | Inspect current authentication status |
| `?action=login` | `POST` | `AuthController::login` | Public | Authenticate user or admin; returns JWT token |
| `?action=register` | `POST` | `AuthController::register` | Public | Register a new student or faculty account |
| `?action=logout` | `POST` | `AuthController::logout` | User | Terminate session and invalidate client state |
| `?action=dashboard` | `GET` | `DashboardController::dashboard` | User | Retrieve counts, alerts, and recent audit logs |
| `?action=browse` | `GET` | `ItemController::browse` | Public | Filter found items by category, location, keyword |
| `?action=submit_lost` | `POST` | `ItemController::submitLost` | User | File a lost report and trigger auto-matcher |
| `?action=submit_found` | `POST` | `ItemController::submitFound` | User | Register a found item and notify matching owners |
| `?action=my_reports` | `GET` | `ItemController::myReports` | User | Fetch lost reports, found items, and claim history |
| `?action=delete_lost` | `POST` | `ItemController::deleteLost` | User | Remove an active lost item report |
| `?action=claim_info` | `POST` | `ClaimController::claimInfo` | User | Inspect item details prior to claim submission |
| `?action=submit_claim` | `POST` | `ClaimController::submitClaim` | User | Submit ownership verification and image proof |
| `?action=admin_dashboard`| `GET` | `AdminController::dashboard` | Admin | Campus-wide metrics and recent item catalogs |
| `?action=admin_claims` | `GET` | `AdminController::claims` | Admin | Fetch full claim queue with attached proofs |
| `?action=approve_claim` | `POST` | `AdminController::approveClaim` | Admin | Approve claim and notify owner |
| `?action=reject_claim` | `POST` | `AdminController::rejectClaim` | Admin | Reject claim and log administrative rationale |
| `?action=admin_users` | `GET` | `AdminController::users` | Admin | View student and faculty directory |
| `?action=toggle_user_status`| `POST` | `AdminController::toggleUserStatus` | Admin | Activate or suspend user accounts |
| `?action=admin_logs` | `GET` | `AdminController::logs` | Admin | Access tamper-evident chronological audit logs |
| `?action=admin_delete_item`| `POST` | `AdminController::deleteItem` | Admin | Remove inappropriate or obsolete reports |

---

## 💻 Getting Started (Local Development)

### Prerequisites
* **PHP 8.2 or higher** with `pdo_pgsql` enabled.
* **PostgreSQL 14 or higher** (local instance, Docker container, or cloud database like Neon).
* **Git**.

### 1. Clone the Repository
```bash
git clone https://github.com/Sreayas7/smart-campus-lost-and-found.git
cd smart-campus-lost-and-found
```

### 2. Configure Environment Variables
Create a `.env` file in the project root:
```env
# PostgreSQL connection string
DATABASE_URL="postgres://username:password@localhost:5432/lost_found?sslmode=disable"

# Optional: Custom JWT Secret Key
JWT_SECRET="your-secure-random-secret-key-at-least-32-chars"
```

### 3. Start Local PHP Server
```bash
php -S localhost:8000
```

Visit **`http://localhost:8000`** in your browser. All tables, indexes, and default accounts will automatically initialize on the first page load.

---

## 🚀 Deployment (Vercel)

The project includes native configuration for serverless deployment using the `vercel-php` runtime.

### 1. Push to GitHub
Ensure your repository is up to date on GitHub:
```bash
git push origin main
```

### 2. Import into Vercel
1. Navigate to the [Vercel Dashboard](https://vercel.com/new) and import your repository.
2. Under **Storage**, create and attach a **Vercel Postgres (Neon)** instance to your project, OR configure the environment variable manually:
   * `POSTGRES_URL`: `postgres://[user]:[password]@[host]/[dbname]?sslmode=require`
   * *(Optional)* `JWT_SECRET`: A secure 32+ character string.
3. Click **Deploy**. Vercel will bundle the functions, install runtime dependencies, and deploy your site globally.

---

## 👥 Default Demonstration Accounts

The database seeds initial demonstration accounts on first boot:

| Role | Username / Email | Password | Identifier | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin@demo.com` | `admin123` | `ADMIN001` | Full administrative review, claim approval, and user moderation |
| **Demo Student** | `user@demo.com` | `password` | `24102535` | Standard student account for reporting and claiming items |

> 🔒 *Note: All default passwords use industry-standard bcrypt hashing (`password_hash`). If legacy plain text credentials exist, the application upgrades them to bcrypt hashes upon first login.*

---

## 📄 License

This project was developed for academic and campus operations at **Cochin University College of Engineering Kuttanad (CUCEK)**. Open-sourced under the MIT License for institutional and educational applications.
