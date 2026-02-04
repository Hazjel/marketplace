# Blue Marketplace 🛍️

**Blue Marketplace** is a robust and modern multi-vendor e-commerce platform built to connect buyers and sellers seamlessly. It features a responsive frontend, a powerful REST API backend, and real-time interaction capabilities.

## 🌟 Key Features

*   **👥 Multi-Role System**: Dedicated panels for **Buyers**, **Sellers (Stores)**, and **Admins** with specific permissions.
*   **🔐 Authentication**:
    *   Secure Email/Password Login.
    *   **Social Login** via Google OAuth.
*   **🛒 Shopping Experience**:
    *   Product Browsing & Search with History.
    *   Cart & Wishlist Management.
    *   Seamless Checkout Process with **Midtrans** Payment Gateway.
*   **🏪 Store Management**:
    *   Create and customize stores.
    *   Manage products, inventories, and orders.
    *   **Wallet System**: Track earnings and request withdrawals.
*   **💬 Real-Time Interaction**:
    *   **Live Chat**: Direct messaging between Buyers and Sellers using **Laravel Reverb**.
    *   **Notifications**: Real-time order and system updates.
    *   **Smart AI Assistant (Ri)**: An intelligent, empathetic chatbot powered by **Google Gemini**. 'Ri' can search the product database to give accurate recommendations and maintains a consistent, friendly persona.
*   **🎨 Modern UI/UX**:
    *   **Dark Mode (Beta)**: Fully integrated dark theme support across the platform.
    *   **Responsive Design**: Built with TailwindCSS for a premium mobile-first experience.
    *   **Admin Dashboard**: Comprehensive analytics (Charts, Stats) and management tools.
*   **✅ Trust & Verification**:
    *   Product Reviews with Image Uploads.
    *   Rating System.

---

## 🛠️ Tech Stack

### Backend (`api-blue`)
*   **Framework**: Laravel 12
*   **Auth**: Laravel Sanctum (API Tokens), Laravel Socialite (Google Auth)
*   **Real-time**: Laravel Reverb (WebSockets)
*   **Database**: MySQL
*   **Storage**: Local / S3 Compatible
*   **Roles**: Spatie Permission

### Frontend (`fe-blue`)
*   **Framework**: Vue.js 3 (Composition API)
*   **State Management**: Pinia
*   **Router**: Vue Router
*   **Styling**: TailwindCSS (Custom Design System)
*   **HTTP Client**: Axios
*   **Theme**: Light & Dark Mode (Beta)

### AI Service (`ai-service`)
*   **Language**: Python 3.x
*   **Framework**: FastAPI
*   **AI Model**: Google Gemini (Proprietary Persona 'Ri')
*   **Integration**: Direct MySQL Database Access for RAG (Retrieval-Augmented Generation) capabilities.

## 📚 Documentation

*   **[API Documentation](API_DOCUMENTATION.md)**: Detailed reference for all backend endpoints.

---

## 🚀 Installation & Setup

Follow these commands to set up the project locally.

### Prerequisites
*   PHP 8.2+
*   Composer
*   Node.js & NPM
*   MySQL
*   Python 3.10+

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

# Run Migrations & Seeders (Create Database Tables & Default Users)
php artisan migrate:fresh --seed

# Link Storage (For Images)
php artisan storage:link

# Start Server
php artisan serve
```
*Backend runs at: `http://localhost:8000`*

#### 2.1. Realtime Chat Setup (Reverb)
1.  **Start Reverb Server**:
    ```bash
    php artisan reverb:start
    ```
    Ensure `BROADCAST_CONNECTION=reverb` is set in your `.env`.

#### 2.2. Payment & Shipping Integration
*   **RajaOngkir**: Add `RAJAONGKIR_API_KEY` to `.env`.
*   **Midtrans**: Add `MIDTRANS_MERCHANT_ID`, `CLIENT_KEY`, and `SERVER_KEY` to `.env`.
*   **Ngrok** (Optional): Use `ngrok http 8000` for webhook testing.

#### 2.3. Queue Worker (Important)
Since the app uses queues for email/notifications, don't forget to run:
```bash
php artisan queue:work
```
Or for scheduled tasks:
```bash
php artisan schedule:work
```

#### 2.4. Running Tests
To ensure everything is working correctly:
```bash
php artisan test
```

---

### 3. Frontend Setup (`fe-blue`)
Setting up the Vue.js client.

```bash
# Return to root then go to frontend
cd ../fe-blue

# Install Node dependencies
npm install

# Environment Configuration
cp .env.example .env
```

**Configure `.env`:**
```ini
VITE_API_BASE_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=your_reverb_key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```

**Run Frontend:**
```bash
npm run dev
```
*Frontend runs at: `http://localhost:5173`*

---

### 4. AI Chatbot Setup (`ai-service`)
The platform includes an intelligent chatbot powered by **Google Gemini AI**.

1.  Navigate to the `ai-service` directory:
    ```bash
    cd ../ai-service
    ```
2.  **Create & Activate Virtual Environment**:
    ```bash
    # Windows
    python -m venv venv
    .\venv\Scripts\activate

    # Mac/Linux
    source venv/bin/activate
    ```
3.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```
4.  **Configure Environment**:
    Create a `.env` file in `ai-service/`:
    ```ini
    GEMINI_API_KEY=your_gemini_api_key
    DB_HOST=127.0.0.1
    DB_USER=root
    DB_PASSWORD=
    DB_NAME=blue_db
    ```
5.  **Run the Service**:
    ```bash
    uvicorn main:app --reload --port 8001
    ```
    *AI Service runs at: `http://localhost:8001`*

---

## 🔑 Login Credentials (Seeders)

If you ran database seeders, you can use these default accounts:

*   **Admin**: `admin@gmail.com` / `password`
*   **Store/Seller**: (Create via register or check seeders)
*   **Buyer**: (Create via register)

Happy Coding! 🚀
