# 📧 Temp Mail Server

A self-hosted temporary email service built with **Laravel 12**, featuring real-time email notifications via **Laravel Reverb** WebSockets.

## ✨ Features

- **Disposable Email Addresses** - Generate temporary inboxes instantly
- **Real-time Updates** - Receive emails instantly via WebSocket (Laravel Reverb)
- **Guest & Authenticated Modes**:
  - 🕐 **Guest**: Inbox expires in **1 hour**
  - ⭐ **Authenticated**: Inbox expires in **1 week**
- **OTP-based Login** - Secure two-factor authentication via email
- **Email Verification** - Link-based registration verification
- **Attachment Support** - Download email attachments
- **Custom SMTP Server** - Receive emails on any domain

---

## 📋 Prerequisites

- PHP >= 8.2
- Composer
- Node.js >= 18 & npm
- Redis (for queue and real-time features)
- SQLite (default) or MySQL/PostgreSQL

---

## 🚀 Installation

### 1. Clone the repository

```bash
git clone <repository-url> temp-mail-server
cd temp-mail-server
```

### 2. Run setup script

This installs dependencies, copies `.env`, generates app key, runs migrations, and builds assets:

```bash
composer run setup
```

### 3. Configure environment

Edit `.env` file with your settings:

```bash
# Database (SQLite is default, create the file if needed)
touch database/database.sqlite

# Mail configuration (for sending OTP/verification emails)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Temp Mail Domain (emails will be received at *@this-domain)
TEMP_MAIL_DOMAIN=tempmail.local
```

### 4. Configure Redis (recommended)

```bash
# .env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 🖥️ Running the Application

### Development Mode (Recommended)

This starts all services concurrently (web server, queue, Reverb, Vite):

```bash
composer run dev
```

This runs:
- Laravel development server at `http://localhost:8000`
- Queue worker for background jobs
- Laravel Pail for real-time logs
- Vite for frontend assets

### Individual Services

If you prefer to run services separately:

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:listen

# Terminal 3: Reverb WebSocket server
php artisan reverb:start

# Terminal 4: Vite dev server
npm run dev

# Terminal 5: SMTP server (to receive emails)
php artisan smtp:serve
```

### Production

```bash
# Build assets
npm run build

# Start with process manager (PM2, Supervisor, etc.)
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --daemon
php artisan reverb:start
php artisan smtp:serve
```

---

## 📧 Using as Local SMTP Server

You can use this project as a local SMTP server to test email sending from your other applications.

### 1. Start the Server
The SMTP server starts automatically with `composer run dev`. It listens on `127.0.0.1:2525`.

### 2. Configure Your Other App
Update the `.env` of your other project:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

### 3. Send & View
Send an email from your other app to any active `@tempmail.local` address (e.g., `test@tempmail.local`). It will appear instantly in the Temp Mail inbox!

---

## 🔧 Configuration

### Key Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `TEMP_MAIL_DOMAIN` | Domain for receiving emails | `tempmail.local` |
| `SMTP_SERVER_PORT` | SMTP server listening port | `2525` |
| `REVERB_HOST` | WebSocket host | `localhost` |
| `REVERB_PORT` | WebSocket port | `8080` |
| `QUEUE_CONNECTION` | Queue driver | `redis` |

### Reverb (WebSocket) Configuration

```bash
REVERB_APP_ID=temp-mail-app
REVERB_APP_KEY=temp-mail-key
REVERB_APP_SECRET=temp-mail-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

---

## 👤 User Modes

### Guest Mode (No Registration)
- Access immediately without registration
- Inbox expires in **1 hour**
- No email verification required

### Authenticated Mode
- Register with name, email, password
- Verify email via **verification link**
- Login with email/password + **OTP verification**
- Inbox expires in **1 week**

---

## 🧪 Testing

```bash
composer run test
```

---

## 📁 Project Structure

```
├── app/
│   ├── Console/Commands/     # Artisan commands (SMTP server, cleanup)
│   ├── Http/Controllers/     # Web & API controllers
│   ├── Mail/                 # Mailable classes (OTP, verification)
│   ├── Models/               # Eloquent models
│   └── Events/               # Broadcasting events
├── resources/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript
│   └── views/
│       ├── layouts/          # Blade layouts
│       ├── auth/             # Authentication views
│       ├── partials/         # Reusable components
│       └── emails/           # Email templates
├── routes/
│   ├── web.php               # Web routes
│   ├── api.php               # API routes
│   └── channels.php          # Broadcast channels
└── database/
    └── migrations/           # Database migrations
```

---

## 📡 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/inbox` | Create new inbox |
| GET | `/api/inbox/{token}` | Get inbox details |
| GET | `/api/inbox/{token}/emails` | List emails |
| GET | `/api/inbox/{token}/emails/{id}` | Get email details |
| DELETE | `/api/inbox/{token}/emails/{id}` | Delete email |
| GET | `/api/inbox/{token}/emails/{id}/attachments/{aid}` | Download attachment |

---

## 📄 License

This project is open-source and free to use.
