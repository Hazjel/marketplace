# Blue Marketplace 🛍️

**Blue Marketplace** is a robust and modern multi-vendor e-commerce platform built to connect buyers and sellers seamlessly. It features a responsive frontend, a powerful REST API backend, and real-time interaction capabilities.

## 🌟 Key Features

*   **Multi-Role System**: Dedicated panels for **Buyers**, **Sellers (Stores)**, and **Admins**.
*   **Authentication**:
    *   Secure Email/Password Login.
    *   **Social Login** via Google OAuth.
*   **Shopping Experience**:
    *   Product Browsing & Search.
    *   Cart & Wishlist Management.
    *   Seamless Checkout Process.
*   **Store Management**:
    *   Create and customize stores.
    *   Manage products, inventories, and orders.
    *   **Wallet System**: Track earnings and request withdrawals.
*   **Real-Time Interaction**:
    *   **Live Chat**: Direct messaging between Buyers and Sellers using WebSockets.
    *   Notifications.
*   **Trust & Verification**:
    *   Product Reviews with Image Uploads.
    *   Rating System.

## 🛠️ Tech Stack

### Backend (`api-blue`)
*   **Framework**: Laravel 12
*   **Auth**: Laravel Sanctum (API Tokens), Laravel Socialite (Google Auth)
*   **Database**: MySQL
*   **Real-time**: Pusher / Laravel Echo
*   **Roles**: Spatie Permission

### Frontend (`fe-blue`)
*   **Framework**: Vue.js 3 (Composition API)
*   **State Management**: Pinia
*   **Router**: Vue Router
*   **Styling**: TailwindCSS
*   **HTTP Client**: Axios

---

## 🚀 Installation & Setup

Follow these commands to set up the project locally.

### Prerequisites
*   PHP 8.2+
*   Composer
*   Node.js & NPM
*   MySQL

### 1. Clone the Repository
```bash
git clone https://github.com/your-username/blue-marketplace.git
cd blue-marketplace
```

### 2. Backend Setup (`api-blue`)
Setting up the Laravel API server.

```bash
cd api-blue

# Install PHP dependencies
composer install

# Environment Configuration
cp .env.example .env
```

**Configure your `.env` file:**
Open `.env` and update your database and Google Auth credentials:
```ini
DB_DATABASE=blue_db
DB_USERNAME=root
DB_PASSWORD=

# Google Auth (Required for Social Login)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=http://localhost:8000/api/auth/google/callback
FRONTEND_URL=http://localhost:5173
```

**Finalize Backend:**
```bash
# Generate App Key
php artisan key:generate

# Run Migrations (Create Database Tables)
php artisan migrate

# Link Storage (For Images)
php artisan storage:link

# Start Server
php artisan serve
```
*Backend runs at: `http://localhost:8000`*

### 3. Frontend Setup (`fe-blue`)
Setting up the Vue.js client.

```bash
# Return to root then go to frontend
cd ../fe-blue

# Install Node dependencies
npm install

# Environment Configuration
cp .env.example .env 
# Or manually create .env with:
# VITE_API_BASE_URL=http://localhost:8000/api

#### 4. AI Chatbot Setup (`ai-service`)
The platform includes an intelligent chatbot powered by **Google Gemini AI**.

1.  Navigate to the `ai-service` directory:
    ```bash
    cd ai-service
    ```
2.  **Create Virtual Environment**:
    To avoid path issues (like `pyvenv.cfg` errors), always create a local virtual environment:
    ```bash
    python -m venv venv
    ```
3.  **Activate Virtual Environment**:
    *   **Windows**:
        ```bash
        .\venv\Scripts\activate
        ```
    *   **Mac/Linux**:
        ```bash
        source venv/bin/activate
        ```
4.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```
5.  **Configure Environment**:
    Create a `.env` file in `ai-service/`:
    ```ini
    GEMINI_API_KEY=your_gemini_api_key
    DB_HOST=127.0.0.1
    DB_USER=root
    DB_PASSWORD=
    DB_NAME=blue_db
    ```
6.  **Run the Service**:
    ```bash
    uvicorn main:app --reload --port 8001
    ```
    *AI Service runs at: `http://localhost:8001`*
*Frontend runs at: `http://localhost:5173`*

---

## 🔑 Login Credentials (Seeders)

If you ran database seeders, you can use these default accounts:

*   **Admin**: `admin@example.com` / `password`
*   **Buyer**: `buyer@example.com` / `password`
*   **Seller**: `seller@example.com` / `password`

Happy Coding! 🚀
