# AirX API 🏥⚡

> **Predictive Oxygen Demand Forecasting & Healthcare Logistics Management Platform**

AirX is a high-performance, lightweight REST API built to forecast hospital oxygen consumption, optimize medical gas supply chains, and deliver real-time analytics to healthcare administrators.

---

## 🚀 Tech Stack

* **Core Runtime**: PHP 8.x / 7.4+
* **API Framework**: [Slim Framework 3](https://www.slimframework.com/)
* **ORM & Database Abstraction**: [RedBeanPHP](https://redbeanphp.com/)
* **Database**: MySQL
* **Authentication & Security**: [Firebase PHP-JWT](https://github.com/firebase/php-jwt) (Bearer Token validation, `HS256`)
* **Environment Management**: [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
* **ML / Prediction Engine**: Native Linear Regression with meteorological normalization and moving averages (Future roadmap: `scikit-learn` Python microservice)

---

## 📁 Project Structure

```text
airx/
├── composer.json               # Dependencies and PSR-4 autoloading
├── .env.example                # Environment variable templates
├── .gitignore                  # Security rules & ignored directories
├── src/                        # PSR-4 Modular OOP Services & Configs
│   ├── Config/
│   │   └── Database.php        # Centralized DB setup & multi-schema routing
│   └── Services/
│       ├── AuthService.php     # JWT generation, token verification & password hashing
│       ├── PredictorService.php# Oxygen prediction algorithms & DB persistence
│       └── HospitalService.php # Dashboard metrics, order retrieval & hospital setup
├── include/
│   ├── dbsol/                  # RedBeanPHP connection and ORM core
│   ├── functions/              # Helper calculations, weather API & time utils
│   └── utlites/                # CORS headers, pricing rules & shared helpers
└── v1/                         # API Endpoints & Routes
    ├── index.php               # System status & API help
    ├── authentication/         # User login & registration endpoints
    ├── data/                   # Clinical telemetry & batch CSV ingestion
    ├── hospital/               # Hospital management, orders & support
    └── predictor/              # Oxygen requirement prediction engines
```

---

## 🛠️ Getting Started

### Prerequisites
* PHP >= 7.4 or PHP 8.x
* Composer
* MySQL Server (5.7+ or 8.0+)
* Apache (with `mod_rewrite` enabled) or Nginx

### Installation

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/lifebankng/Airx.git
   cd Airx
   ```

2. **Install Dependencies**:
   ```bash
   composer install
   ```

3. **Configure Environment Variables**:
   Copy `.env.example` to `.env` and fill in your database credentials and secret keys:
   ```bash
   cp .env.example .env
   ```

   Edit `.env`:
   ```env
   DB_HOST=localhost
   DB_USER=your_db_user
   DB_PASS=your_db_password
   DB_NAME=airx
   JWT_SECRET=your_super_secret_jwt_key
   AUTH_TOKEN=your_internal_api_token
   OPENWEATHER_API_KEY=your_openweather_api_key
   ```

4. **Run Locally**:
   Using the built-in PHP development server:
   ```bash
   php -S localhost:8000 -t .
   ```

---

## 📡 API Endpoints Overview

All protected endpoints require an `Authorization` header containing a valid Bearer JWT:
```http
Authorization: Bearer <your_jwt_token>
```

### 1. Authentication (`/v1/authentication`)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/v1/authentication/login` | Authenticate with email and password; returns JWT token. |
| `POST` | `/v1/authentication/add/user` | Register a new hospital staff account with secure password hashing. |
| `POST` | `/v1/authentication/supervisor/add/user` | Register an administrative/supervisor account. |

### 2. Predictor Engine (`/v1/predictor`)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/v1/predictor/run` | Predict oxygen demand (in $m^3$) using real-time patient counts. |
| `POST` | `/v1/predictor/run/supervisor` | Run supervisor-weighted linear prediction calculation. |
| `GET` | `/v1/predictor/hospitals` | Retrieve last 6 months of historical usage vs. prediction comparisons. |
| `GET` | `/v1/predictor/hospital/predict` | Predict next month's demand using time-series & meteorological trends. |

#### Example Prediction Request (`POST /v1/predictor/run`):
```json
{
  "hospitalid": 101,
  "peaditric": 14,
  "malaria": 8,
  "intensive": 5,
  "accident": 2,
  "theatre": 3,
  "materinity": 4,
  "typhoid": 1,
  "diabetes": 2
}
```

### 3. Hospital Operations & Orders (`/v1/hospital`)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/v1/hospital/dashboard` | Returns usage charts, upcoming deliveries, and stock estimates. |
| `GET` | `/v1/hospital/orders` | Retrieves recent oxygen orders filtered by status. |
| `POST` | `/v1/hospital/placeorder` | Place an oxygen order (Large, Medium, or Small cylinders). |
| `POST` | `/v1/hospital/support` | Submit a hospital support inquiry. |

### 4. Telemetry & Data Ingestion (`/v1/data`)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/v1/data/add` | Log single patient medical telemetry data point. |
| `POST` | `/v1/data/hospital/add` | Batch upload historical records via JSON template or CSV file. |

---

## 🔒 Security & Data Integrity

* **SQL Injection Prevention**: All queries use parameterized statements (`?` binding) with RedBeanPHP.
* **Password Hashing**: Implements PHP native `password_hash()` (Bcrypt/Argon2) with backward-compatible legacy verification.
* **Stateless Authentication**: JWT tokens signed with secure keys and configurable expiration.
* **Data Minimization**: Operates on anonymized statistical patient metrics rather than individual patient PII.

---

## 👥 Authors & Maintainers

* **LifeBank Tech Team** — [developer@lifebank.ng](mailto:developer@lifebank.ng)
* Website: [lifebank.ng](https://lifebank.ng)
