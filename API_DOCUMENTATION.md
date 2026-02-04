# Blue Marketplace API Documentation

This document provides a reference for the REST API endpoints available in the Blue Marketplace backend (`api-blue`).

**Base URL**: `http://localhost:8000/api`

---

## 🔐 Authentication & Profile

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/register` | Register a new user account | No |
| `POST` | `/login` | Log in and receive API token | No |
| `GET` | `/auth/google/redirect` | Redirect to Google OAuth | No |
| `GET` | `/auth/google/callback` | Callback from Google OAuth | No |
| `POST` | `/logout` | Invalidate current token | **Yes** |
| `GET` | `/me` | Get current logged-in user details | **Yes** |
| `PUT` | `/profile` | Update user profile information | **Yes** |
| `GET` | `/email/verify/{id}/{hash}` | Verify email address (Signed URL) | No |
| `POST` | `/email/resend` | Resend verification email | **Yes** |

---

## 🏪 Store Management

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/store` | List all stores | No |
| `GET` | `/store/username/{username}` | Get public store details by username | No |
| `GET` | `/store/username/{username}/categories` | Get product categories of a store | No |
| `GET` | `/store/username/{username}/reviews` | Get reviews for a store's products | No |
| `POST` | `/register-store` | Register a new store (Upgrade account) | **Yes** |
| `GET` | `/my-store` | Get current user's store details | **Yes** |
| `PUT` | `/store/{id}` | Update store details | **Yes** |
| `POST` | `/store/{id}/verified` | (Admin) Update store verification status | **Yes** |
| `POST` | `/store/{id}/follow` | Follow a store | **Yes** |
| `POST` | `/store/{id}/unfollow` | Unfollow a store | **Yes** |
| `GET` | `/store/{id}/follow-status` | Check if user follows a store | **Yes** |

---

## 🛍️ Products & Categories

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/product` | List all products (with filters) | No |
| `GET` | `/product/slug/{slug}` | Get product details by slug | No |
| `POST` | `/product` | Create a new product | **Yes** |
| `PUT` | `/product/{id}` | Update a product | **Yes** |
| `DELETE` | `/product/{id}` | Delete a product | **Yes** |
| `GET` | `/product-category` | List product categories | No |
| `POST` | `/product-review` | Submit a review for a product | **Yes** |

---

## 🧾 Transactions & Orders

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/transaction` | Create a new transaction (Checkout) | **Yes** |
| `GET` | `/transaction` | List user transactions | **Yes** |
| `GET` | `/transaction/code/{code}` | Get transaction by unique code | **Yes** |
| `POST` | `/transaction/{id}/check-status` | Check Midtrans payment status | **Yes** |
| `POST` | `/transaction/{id}/complete` | Mark transaction as completed (Received) | **Yes** |
| `POST` | `/midtrans-callback` | Webhook for payment status updates | No |

---

## 💰 Wallet & Withdrawals

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/my-store-balance` | Get current store balance | **Yes** |
| `GET` | `/store-balance-history` | Get balance history/logs | **Yes** |
| `GET` | `/withdrawal` | List withdrawal requests | **Yes** |
| `POST` | `/withdrawal` | Request a new withdrawal | **Yes** |
| `POST` | `/withdrawal/{id}/approve` | (Admin) Approve withdrawal | **Yes** |

---

## 💬 Real-Time Chat

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/chat/contacts` | Get list of chat contacts | **Yes** |
| `GET` | `/chat/{user}` | Get messages with specific user | **Yes** |
| `POST` | `/chat/send` | Send a message | **Yes** |

---

## ❤️ Wishlist

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/wishlist` | Get user's wishlist | **Yes** |
| `POST` | `/wishlist` | Add/Remove product from wishlist | **Yes** |

---

## 📍 Logistics

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/store/locations` | Get available store locations (Cities) | No |
| `GET` | `/address` | User shipping addresses | **Yes** |

---

## Error Codes

*   `200`: Success
*   `201`: Created
*   `401`: Unauthorized (Invalid or missing token)
*   `403`: Forbidden (Insufficient permissions)
*   `404`: Not Found
*   `422`: Validation Error
*   `500`: Server Error
