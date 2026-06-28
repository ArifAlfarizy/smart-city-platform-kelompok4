# Citizen Service

Citizen Service is a microservice in the Smart Traffic Decision Support System responsible for managing citizen data and incident reports. This service allows citizens to submit incident reports, manage their profiles, view their reports and notifications, and publish events to RabbitMQ for inter-service communication.

---

## Overview

### Features

* Authenticate users using JWT issued by the Auth Service
* View citizen profile
* Update citizen profile
* Create incident reports
* View personal reports
* View all reports (Operator)
* Update report status (Operator)
* View citizen notifications
* Publish `incident.created` events to RabbitMQ when a report is successfully created

---

## Technology Stack

* CodeIgniter 4
* PHP 8+
* MySQL
* JWT Authentication
* RabbitMQ
* php-amqplib/php-amqplib

---

## Database Structure

### `citizens`

Stores citizen information associated with users from the Auth Service.

| Field        | Description                    |
| ------------ | ------------------------------ |
| `id`         | Primary key                    |
| `user_id`    | User ID from Auth Service      |
| `nik`        | National Identification Number |
| `name`       | Citizen name                   |
| `phone`      | Phone number                   |
| `created_at` | Record creation timestamp      |

### `reports`

Stores incident reports submitted by citizens.

| Field         | Description               |
| ------------- | ------------------------- |
| `id`          | Primary key               |
| `citizen_id`  | Related citizen           |
| `road_name`   | Road name                 |
| `category`    | Incident category         |
| `description` | Incident description      |
| `status`      | Report status             |
| `created_at`  | Record creation timestamp |

#### Report Categories

* `accident`
* `broken_vehicle`
* `fallen_tree`
* `flood`
* `road_obstacle`
* `traffic_light_damage`

### `notifications`

Stores notifications sent to citizens.

| Field        | Description               |
| ------------ | ------------------------- |
| `id`         | Primary key               |
| `citizen_id` | Related citizen           |
| `title`      | Notification title        |
| `message`    | Notification content      |
| `is_read`    | Read status               |
| `created_at` | Record creation timestamp |

---

## API Endpoints

### Citizen

| Method | Endpoint                | Description              |
| ------ | ----------------------- | ------------------------ |
| GET    | `/api/citizens/profile` | Retrieve citizen profile |
| PUT    | `/api/citizens/profile` | Update citizen profile   |

### Reports

| Method | Endpoint                            | Description                                         |
| ------ | ----------------------------------- | --------------------------------------------------- |
| POST   | `/api/citizens/reports`             | Create a new incident report                        |
| GET    | `/api/citizens/reports`             | Retrieve reports owned by the authenticated citizen |
| GET    | `/api/citizens/reports/all`         | Retrieve all reports (Operator only)                |
| PUT    | `/api/citizens/reports/{id}/status` | Update report status (Operator only)                |

### Notifications

| Method | Endpoint                      | Description                    |
| ------ | ----------------------------- | ------------------------------ |
| GET    | `/api/citizens/notifications` | Retrieve citizen notifications |

---

## Request Examples

### Create Report

**POST** `/api/citizens/reports`

```json
{
  "road_name": "MT Haryono",
  "category": "accident",
  "description": "Kecelakaan di simpang Cawang"
}
```

### Update Profile

**PUT** `/api/citizens/profile`

```json
{
  "name": "Budi Santoso",
  "phone": "08123456789"
}
```

---

## Authentication

All endpoints require JWT authentication.

Include the token in the request header:

```http
Authorization: Bearer <token>
```

The JWT is obtained from the Auth Service.

---

## RabbitMQ Integration

Whenever a citizen successfully creates a report, Citizen Service publishes an event to RabbitMQ.

**Exchange**

```text
city.events
```

**Routing Key**

```text
incident.created
```

**Payload Example**

```json
{
  "incident_id": 1,
  "category": "accident",
  "road_name": "MT Haryono",
  "description": "Kecelakaan di simpang Cawang"
}
```

If RabbitMQ is unavailable, the report is still saved to the database. Only the event publishing process is skipped.

---

## Local Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Copy the environment file.

```bash
cp .env.example .env
```

Update the following configuration values in `.env`:

* Database connection
* JWT configuration
* RabbitMQ configuration

### 3. Import Database

Import the Citizen Service database schema and seed data.

### 4. Run the Application

```bash
php spark serve
```

By default, the application will run at:

```text
http://localhost:8080
```

### 5. Test the API

Use Postman or another API client with the following header:

```http
Authorization: Bearer <JWT_TOKEN>
```

---

## Notes

* Citizens can only access their own reports.
* Operators can view all reports and update report statuses.
* RabbitMQ is used for communication between microservices.
* Authentication is handled using JWT issued by the Auth Service.
* Report creation does not depend on RabbitMQ availability; reports are always stored in the database.
