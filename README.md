# CampusConnect
**A PHP backend system for university peer matching and anonymous confessions.**

## Overview

CampusConnect helps university students connect with like-minded peers and share anonymous confessions safely. Built with PHP and MySQL.

## Features

- **Peer Matching**: Find study buddies and friends based on interests
- **Anonymous Confessions**: Share feelings safely with mood tags
- **Mentorship**: Connect with senior students
- **Admin Panel**: Moderate content and view analytics


## ️ Tech Stack

- PHP 8.0+
- MySQL 5.7+
- JWT Authentication
- RESTful API


## Quick Setup

### 1. Clone & Install

```shellscript
git clone https://github.com/yourusername/campusconnect.git
cd campusconnect
composer install
```

### 2. Database Setup

```shellscript
# Create database
mysql -u root -p -e "CREATE DATABASE campusconnect;"

# Import schema
mysql -u root -p campusconnect < database/schema.sql
```

### 3. Configuration

```shellscript
cp config.example.php config.php
```

Edit `config.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'campusconnect');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('JWT_SECRET', 'your_secret_key');
?>
```

### 4. Start Server

```shellscript
php -S localhost:8000
```

## API Endpoints

### Authentication

- `POST /api/register` - Register user
- `POST /api/login` - Login user
- `GET /api/me` - Get current user


### Profiles & Matching

- `POST /api/profile` - Update profile
- `GET /api/match` - Find matches
- `POST /api/friend-request` - Send friend request


### Confessions

- `POST /api/confessions` - Submit confession
- `GET /api/confessions` - Get confessions
- `POST /api/confessions/{id}/vote` - Vote on confession


### Admin

- `GET /api/admin/stats` - Dashboard stats
- `POST /api/admin/moderate` - Moderate content


## Usage Example

```shellscript
# Register
curl -X POST http://localhost:8000/api/register \
  -d '{"email":"student@uni.edu","password":"pass123","campus":"University"}'

# Login
curl -X POST http://localhost:8000/api/login \
  -d '{"email":"student@uni.edu","password":"pass123"}'

# Submit confession
curl -X POST http://localhost:8000/api/confessions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"content":"Feeling stressed about exams","mood":"anxious"}'
```

## Project Structure

```plaintext
campusconnect/
├── api/
│   ├── auth.php
│   ├── profile.php
│   ├── confessions.php
│   └── admin.php
├── includes/
│   ├── database.php
│   ├── auth.php
│   └── utils.php
├── database/
│   └── schema.sql
├── config.php
└── index.php
```

## Security Features

- Password hashing
- JWT authentication
- Input validation
- SQL injection prevention
- Rate limiting


## License

MIT License

---
