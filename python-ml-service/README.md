## 📁 Struktur Folder

```text
python-ml-service/
├── main.py
├── train_models.py
├── generate_dataset.py
├── recommendation_engine.py
├── test_integration.py
├── requirements.txt
├── .env.example
├── Dockerfile
├── consumers/
│   ├── traffic_consumer.py
│   ├── environment_consumer.py
│   ├── incident_consumer.py
│   └── recommendation_publisher.py
├── models/
└── data/
```

---

## Setup & Instalasi

### Virtual Environment

```bash
python -m venv venv

# Linux/Mac
source venv/bin/activate

# Windows
venv\Scripts\activate
```

### Install Dependencies

```bash
pip install --upgrade pip
pip install -r requirements.txt
```

### Environment Variables

```bash
cp .env.example .env
```

Contoh:

```env
APP_ENV=development
APP_PORT=5000

RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASS=guest
RABBITMQ_EXCHANGE=city.events

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartcity
DB_USER=root
DB_PASS=rootpass

JWT_SECRET=accessrahasia

MODEL_PATH=models/smartcity_models.pkl
```
---

## Generate Dataset & Train Model

Generate dataset:

```bash
python generate_dataset.py
```

Train model:

```bash
python train_models.py
```

Output yang diharapkan:

```text
✓ Model saved → models/smartcity_models.pkl

Accuracy : 0.9xxx
CV Acc   : 0.9xxx ± 0.0xxx
```

---

## Menjalankan Service

### Jalankan RabbitMQ

```bash
docker run -d \
  --name rabbitmq-test \
  -p 5672:5672 \
  -p 15672:15672 \
  rabbitmq:3.12-management
```

Management UI:

```text
http://localhost:15672
```

Login:

```text
username: guest
password: guest
```

### Jalankan ML Service

```bash
uvicorn main:app --reload --port 5000
```

Output:

```text
✓ Model loaded: ['congestion']

✓ Started: traffic-consumer
✓ Started: environment-consumer
✓ Started: incident-consumer
```

Swagger:

```text
http://localhost:5000/docs
```

---

## Testing

Pastikan RabbitMQ dan ML Service sudah berjalan sebelum menjalankan testing.

### Terminal 1

```bash
uvicorn main:app --reload --port 5000
```

### Terminal 2

Subscribe recommendation:

```bash
python test_integration.py
```

Pilih:

```text
4
```

### Terminal 3

Publish event:

```bash
python test_integration.py
```

Pilihan:

```text
1 → traffic.updated
2 → environment.updated
3 → incident.created
5 → ALL TESTS
```

---

## 🌐 API Endpoints

| Method | Endpoint                         | Auth     |
| ------ | -------------------------------- | -------- |
| GET    | /health                          | No       |
| GET    | /metrics                         | No       |
| POST   | /api/ml/analyze                  | JWT      |
| GET    | /api/ml/status                   | JWT      |
| GET    | /api/ml/recommendations/latest   | Operator |
| GET    | /api/ml/model/feature-importance | JWT      |
| POST   | /api/ml/analyze/batch            | JWT      |

### Generate Token
Beberapa endpoint memerlukan JWT Token dengan role `operator`.

Jalankan perintah berikut untuk membuat token sementara yang berlaku selama 1 jam:

```bash
python3 -c "
import jwt, datetime
print(jwt.encode(
    {
        'id': 1,
        'email': 'operator@test.com',
        'role': 'operator',
        'exp': datetime.datetime.utcnow() + datetime.timedelta(hours=1)
    },
    'accessrahasia',
    algorithm='HS256'
))
"
```

Contoh output:

```text
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Gunakan token tersebut pada request yang memerlukan autentikasi:

```bash
curl -X GET \
http://localhost:5000/api/ml/status \
-H "Authorization: Bearer <TOKEN>"
```

### Menggunakan Swagger

1. Buka:

```text
http://localhost:5000/docs
```

2. Klik tombol **Authorize** di kanan atas.

3. Masukkan token dengan format:

```text
Bearer <TOKEN>
```

Contoh:

```text
Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

4. Klik **Authorize** lalu **Close**.

Semua endpoint yang membutuhkan autentikasi sekarang bisa dicoba langsung dari Swagger UI.

> Pastikan nilai secret (`accessrahasia`) sama dengan `JWT_SECRET` yang digunakan oleh service.


### Example Request

```bash
curl -X POST http://localhost:5000/api/ml/analyze \
-H "Authorization: Bearer TOKEN" \
-H "Content-Type: application/json" \
-d '{
  "vehicle_count": 1200,
  "average_speed": 18.5,
  "rainfall": 35,
  "water_level": 420,
  "incident_count": 1
}'
```

---

## \RabbitMQ Events

### Consumed Events

| Routing Key         |
| ------------------- |
| traffic.updated     |
| environment.updated |
| incident.created    |

### Published Events

| Routing Key              |
| ------------------------ |
| recommendation.generated |

---

## Docker

Build image:

```bash
docker build -t smartcity/python-ml:latest .
```

Run:

```bash
docker run -d \
--name python-ml \
-p 5000:5000 \
--env-file .env \
smartcity/python-ml:latest
```

Full stack:

```bash
docker-compose up -d --build
```

---

##  Troubleshooting

### Model not found

```bash
python generate_dataset.py
python train_models.py
```

### RabbitMQ connection error

```bash
docker ps
```

Pastikan container RabbitMQ berjalan.

### Missing module

```bash
pip install -r requirements.txt
```

### JWT Invalid

Pastikan `JWT_SECRET` sama dengan Auth Service.

### Port 5000 already in use

Linux/Mac:

```bash
lsof -i :5000
```

Windows:

```powershell
netstat -ano | findstr :5000
```