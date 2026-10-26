# 🔍 Smart Campus Lost & Found System

A modern, centralized web application designed for campus communities (CUCEK - Cochin University College of Engineering Kuttanad) to report, track, claim, and recover lost and found belongings.

---

## 🚀 Key Features

* **Lost Item Reporting**: Submit detailed lost item reports with images, categories, keywords, date, and location.
* **Found Item Directory**: Public catalog of discovered items with real-time status tracking (`Available` vs `Claimed`).
* **Intelligent Auto-Matching**: Automated matching engine that pairs lost reports with turned-in items based on weighted token analysis, name similarity, category, and location proximity.
* **Claim Verification Workflow**: Formal claim submission with proof description and optional verification image uploads.
* **Admin Oversight & Dashboard**: Full administrative dashboard to review and approve/reject claims, deactivate accounts, and inspect tamper-evident audit logs.
* **Dual Database Support**: Works with **PostgreSQL** (Vercel Postgres, Neon) and **MySQL** (Railway, TiDB Cloud, Local XAMPP/Docker).
* **Stateless Token Authentication**: Built-in HMAC-SHA256 signed token authentication ensuring seamless session persistence across serverless cold starts.
* **Light / Dark Mode**: Integrated theme toggle persisted to local storage.

---

## 🛠️ Tech Stack

* **Frontend**: Vanilla JavaScript (ES6+), HTML5 Single Page Application, CSS3 Custom Properties (Dark/Light themes).
* **Backend**: PHP REST-like API (`api/api.php`) with PDO prepared statements and stateless token auth.
* **Database**: MySQL & PostgreSQL dual-driver architecture (`api/core/Database.php` & `api/core/Schema.php`).
* **Deployment**: Configured for Vercel Serverless (`vercel.json`) with `vercel-php@0.9.0`.

---

## ⚙️ Environment Variables & Database Configuration

The application automatically detects whether you are using **PostgreSQL** or **MySQL** based on environment variables:

### Option 1: PostgreSQL (Vercel Postgres / Neon)
Set any of the following in your hosting provider's environment variables:
```env
POSTGRES_URL="postgres://user:password@host:5432/dbname?sslmode=require"
# Or
DATABASE_URL="postgres://user:password@host:5432/dbname?sslmode=require"
```

### Option 2: MySQL (Railway / TiDB / Local)
Set the standard MySQL connection variables:
```env
MYSQLHOST=your-mysql-host.com
MYSQLPORT=3306
MYSQLDATABASE=lost_found
MYSQLUSER=root
MYSQLPASSWORD=yourpassword
# Or
DATABASE_URL="mysql://root:yourpassword@your-mysql-host.com:3306/lost_found"
```

### Optional Authentication & Mail Settings
```env
AUTH_SECRET="your-random-secret-key"
```

---

## 📦 Deployment to Vercel

1. Fork or push this repository to your GitHub account: `https://github.com/Sreayas7/smart-campus-lost-and-found.git`
2. Import the project into [Vercel](https://vercel.com/new).
3. Connect either **Vercel Postgres (Neon)** or add your database environment variables under **Settings $\rightarrow$ Environment Variables**.
4. Deploy! The database tables and default admin account will automatically initialize on the first request.

---

## 👤 Default Accounts (Initialized Automatically)

| Role | Email | Password | UID |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@demo.com` | `admin123` | `ADMIN001` |
| **Demo Student** | `user@demo.com` | `password` | `24102535` |

> *Note: Plaintext passwords are automatically upgraded to bcrypt hashes upon first login.*

---

## 📜 License
Developed for academic and campus use at **CUCEK**.
