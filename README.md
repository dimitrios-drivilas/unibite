# 🍔 UniBite - University Dining & Food Sharing Platform

UniBite is a comprehensive web-based platform designed to connect university students, campus cooks, and administrators, facilitating meal requests, surplus food sharing, and campus dining management.

## 🚀 Key Features

- **Consumer Module:** Browse available meals, make food requests, track order status, earn loyalty points, and submit ratings.
- **Cook Module:** Create and manage meal offers, handle incoming consumer requests, view statistics, and track cooking metrics.
- **Admin Module:** Manage users, monitor platform activity, view leaderboards, inspect system statistics, and oversee system moderation.
- **Shared Authentication & Dashboard:** Secure login/register system with role-based access control and unified dashboards.
- **Automated Cron Jobs:** Background handlers for automatic offer expiration and status updates.

## 📂 Project Structure

```tree
├── Admin/              # Administrator dashboard & management APIs
├── Consumer/           # Student/consumer interface & request workflows
├── Cook/               # Campus cook dashboard & offer management
├── Shared/             # Shared authentication, CSS styles, & auth guards
├── config/             # Database connection & configuration
├── cron/               # Automated background tasks (e.g., auto-expire)
├── database/           # SQL schema and initial database setup
├── index.php           # Main landing / entry point
├── reset_db.php        # Database reset & testing utility scripts
└── upatras_locations.json # University campus location data
```

## 🛠️ Tech Stack

- **Backend:** PHP (Native / Custom MVC-like structure)
- **Database:** MySQL / MariaDB (Schema included in `database/schema.sql` and `unibite_database.sql`)
- **Frontend:** HTML5, CSS3 (Custom & responsive UI), JavaScript (AJAX / Fetch API)
- **Server Environment:** Apache / Nginx with PHP support

## ⚙️ Installation & Setup

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/dimitrios-drivilas/unibite.git
   cd unibite
   ```
2. **Database Setup:**
   - Import `database/schema.sql` or `unibite_database.sql` into your MySQL server.
   - Configure your database credentials in `config/db.php`.
3. **Run the Project:**
   - Host the directory on a local PHP development server (e.g., XAMPP, MAMP, or built-in PHP server):
     ```bash
     php -S localhost:8000
     ```
   - Open `http://localhost:8000` in your browser.

---
*Developed for University of Patras community dining & food sharing.*
