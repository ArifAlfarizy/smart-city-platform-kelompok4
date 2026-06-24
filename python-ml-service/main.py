import os
import threading
import logging
import joblib
import numpy as np
from datetime import datetime, timezone
from typing import Optional, List

from fastapi import FastAPI, HTTPException, Depends, Request
from fastapi.responses import JSONResponse
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel, Field
from dotenv import load_dotenv
from prometheus_fastapi_instrumentator import Instrumentator

import mysql.connector
from mysql.connector import Error as MySQLError

import jwt as pyjwt

load_dotenv()

# Logging 
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)
logger = logging.getLogger(__name__)

# App Setup
app = FastAPI(
    title="Smart City ML Service",
    version="1.0.0",
    description="Traffic prediction, Air Quality classification & Anomaly detection",
)

Instrumentator().instrument(app).expose(app, endpoint="/metrics")

security = HTTPBearer()

# Load Models
MODEL_PATH = os.getenv("MODEL_PATH", "models/smartcity_models.pkl")

try:
    BUNDLE = joblib.load(MODEL_PATH)
    logger.info(f"  Models loaded from {MODEL_PATH}")
    logger.info(f"  Available: {list(BUNDLE.keys())}")
except FileNotFoundError:
    logger.warning(f"  Model file not found: {MODEL_PATH}")
    logger.warning("   Jalankan: python train_models.py")
    BUNDLE = {}

# RabbitMQ Consumers (background threads)
from consumers.anomaly_publisher import AnomalyPublisher
from consumers.traffic_consumer  import start_traffic_consumer
from consumers.air_consumer      import start_air_consumer
from consumers.citizen_consumer  import start_citizen_consumer

anomaly_pub = AnomalyPublisher()

def _start_consumers():
    import time
    time.sleep(5)  # Tunggu RabbitMQ siap

    threads = [
        threading.Thread(
            target=start_traffic_consumer,
            args=(BUNDLE, anomaly_pub),
            daemon=True, name="traffic-consumer"
        ),
        threading.Thread(
            target=start_air_consumer,
            args=(BUNDLE, anomaly_pub),
            daemon=True, name="air-consumer"
        ),
        threading.Thread(
            target=start_citizen_consumer,
            args=(BUNDLE, anomaly_pub),
            daemon=True, name="citizen-consumer"
        ),
    ]

    for t in threads:
        t.start()
        logger.info(f" Started consumer thread: {t.name}")


consumer_thread = threading.Thread(
    target=_start_consumers, daemon=True, name="consumer-starter"
)
consumer_thread.start()

# DB Helper
def get_db():
    try:
        conn = mysql.connector.connect(
            host=os.getenv("DB_HOST", "mysql"),
            port=int(os.getenv("DB_PORT", 3306)),
            database=os.getenv("DB_NAME", "smartcity"),
            user=os.getenv("DB_USER", "root"),
            password=os.getenv("DB_PASS", ""),
        )
        return conn
    except MySQLError:
        return None


def save_prediction(model_type: str, zone: Optional[str],
                    input_data: dict, result: dict,
                    confidence: Optional[float] = None):
    import json
    conn = get_db()
    if not conn:
        return
    try:
        cur = conn.cursor()
        cur.execute(
            (model_type, zone,
             json.dumps(input_data), json.dumps(result),
             confidence)
        )
        conn.commit()
    except MySQLError:
        pass
    finally:
        conn.close()


# JWT Auth
JWT_SECRET    = os.getenv("JWT_SECRET", "accessrahasia")
JWT_ALGORITHM = "HS256"


def verify_jwt(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict:
    token = credentials.credentials
    try:
        payload = pyjwt.decode(token, JWT_SECRET, algorithms=[JWT_ALGORITHM])
        return payload
    except pyjwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail=response_body(
            "error", 401, None, "Token sudah expired"
        ))
    except pyjwt.InvalidTokenError:
        raise HTTPException(status_code=401, detail=response_body(
            "error", 401, None, "Token tidak valid"
        ))


def require_operator(payload: dict = Depends(verify_jwt)) -> dict:
    if payload.get("role") != "operator":
        raise HTTPException(status_code=403, detail=response_body(
            "error", 403, None, "Akses ditolak — hanya operator"
        ))
    return payload


# Response Helper
def response_body(status: str, code: int, data, message: str) -> dict:
    return {
        "status":    status,
        "code":      code,
        "data":      data,
        "message":   message,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "service":   "python-ml",
    }


def success(data, message: str = "OK", code: int = 200):
    return JSONResponse(status_code=code,
                        content=response_body("success", code, data, message))


def error(code: int, message: str):
    return JSONResponse(status_code=code,
                        content=response_body("error", code, None, message))


# Pydantic Schemas
class TrafficIn(BaseModel):
    zone:          str
    vehicle_count: float = Field(..., ge=0)
    avg_speed:     float = Field(..., ge=0)
    hour:          int   = Field(..., ge=0, le=23)
    day_of_week:   int   = Field(..., ge=0, le=6)
    incident:      int   = Field(0, ge=0, le=1)


class AQIIn(BaseModel):
    zone:        str
    aqi:         float = Field(..., ge=0)
    temperature: float
    humidity:    float = Field(..., ge=0, le=100)


class AnomalyIn(BaseModel):
    sensor_type: str
    value:       float
    zone:        str


class BatchItem(BaseModel):
    model_type: str   # "traffic" | "aqi" | "anomaly"
    payload:    dict


class BatchIn(BaseModel):
    predictions: List[BatchItem]

# ENDPOINTS
# GET /health
@app.get("/health")
def health():
    db_ok = get_db() is not None
    return success({
        "service":       "python-ml",
        "models_loaded": list(BUNDLE.keys()),
        "db_connected":  db_ok,
    }, "Service healthy")


# POST /api/ml/predict/traffic
@app.post("/api/ml/predict/traffic")
def predict_traffic(body: TrafficIn, _=Depends(verify_jwt)):
    if "traffic" not in BUNDLE:
        return error(503, "Traffic model belum tersedia")

    b = BUNDLE["traffic"]

    zone_map = {"A": 0, "B": 1, "C": 2, "D": 3,
                "zone1": 0, "zone2": 1, "zone3": 2, "zone4": 3}
    location_enc = zone_map.get(str(body.zone).upper(),
                                zone_map.get(body.zone, 0))

    try:
        X = b["scaler"].transform([[
            body.hour,
            body.day_of_week,
            0,                  # weather_code default cerah
            body.vehicle_count, # prev_density
            location_enc,
        ]])
        density = float(b["model"].predict(X)[0])
        density = max(0.0, density)
    except Exception as e:
        return error(500, f"Prediksi gagal: {str(e)}")

    level = "Padat" if density > 80 else "Sedang" if density > 40 else "Lancar"

    tree_preds = np.array([t.predict(X)[0] for t in b["model"].estimators_])
    confidence = float(1 - (tree_preds.std() / (tree_preds.mean() + 1e-6)))
    confidence = max(0.0, min(1.0, confidence))

    result = {
        "predicted_density": round(density, 1),
        "congestion_level":  level,
        "confidence":        round(confidence, 4),
        "zone":              body.zone,
    }

    save_prediction("traffic", body.zone, body.model_dump(), result, confidence)
    return success(result, "Prediksi traffic berhasil")


# POST /api/ml/predict/aqi
@app.post("/api/ml/predict/aqi")
def predict_aqi(body: AQIIn, _=Depends(verify_jwt)):
    if "air" not in BUNDLE:
        return error(503, "AQI model belum tersedia")

    b       = BUNDLE["air"]
    aqi_val = body.aqi
    pm25    = aqi_val * 0.8
    pm10    = pm25 * 1.5
    no2     = aqi_val * 0.3
    co      = aqi_val * 0.02
    o3      = max(0, 80 - aqi_val * 0.2)

    try:
        X     = b["scaler"].transform([[
            pm25, pm10, no2, co, o3,
            body.temperature, body.humidity
        ]])
        pred  = b["model"].predict(X)[0]
        proba = b["model"].predict_proba(X)[0]
        label = b["le_aqi"].inverse_transform([pred])[0]
    except Exception as e:
        return error(500, f"Prediksi gagal: {str(e)}")

    confidence    = round(float(proba.max()), 4)
    probabilities = dict(zip(
        b["classes"],
        [round(float(p), 4) for p in proba]
    ))

    result = {
        "aqi_category":  label,
        "confidence":    confidence,
        "probabilities": probabilities,
        "zone":          body.zone,
    }

    save_prediction("aqi", body.zone, body.model_dump(), result, confidence)
    return success(result, "Prediksi AQI berhasil")


# POST /api/ml/detect/anomaly
@app.post("/api/ml/detect/anomaly")
def detect_anomaly(body: AnomalyIn, _=Depends(verify_jwt)):
    if "anomaly" not in BUNDLE:
        return error(503, "Anomaly model belum tersedia")

    b            = BUNDLE["anomaly"]
    hour         = datetime.now().hour
    rolling_mean = body.value
    z_score      = 0.0

    try:
        X     = b["scaler"].transform([[
            body.value, hour, rolling_mean, z_score
        ]])
        score = float(b["model"].score_samples(X)[0])
    except Exception as e:
        return error(500, f"Deteksi gagal: {str(e)}")

    is_anomaly = score < -0.1
    severity   = "Kritis" if score < -0.3 else "Peringatan" if is_anomaly else "Normal"

    result = {
        "is_anomaly":    is_anomaly,
        "anomaly_score": round(-score, 4),
        "severity":      severity,
        "sensor_type":   body.sensor_type,
        "zone":          body.zone,
    }

    save_prediction("anomaly", body.zone, body.model_dump(), result)
    return success(result, "Deteksi anomali berhasil")


# GET /api/ml/model/feature-importance
@app.get("/api/ml/model/feature-importance")
def feature_importance(_=Depends(verify_jwt)):
    if not BUNDLE:
        return error(503, "Models belum tersedia")

    data = {}

    if "traffic" in BUNDLE:
        data["traffic"] = {
            "model":    "Random Forest Regressor",
            "features": BUNDLE["traffic"].get("feature_importance", {}),
        }
    if "air" in BUNDLE:
        data["air"] = {
            "model":    "Gradient Boosting Classifier",
            "features": BUNDLE["air"].get("feature_importance", {}),
        }
    if "anomaly" in BUNDLE:
        data["anomaly"] = {
            "model":    "Isolation Forest",
            "features": BUNDLE["anomaly"].get("feature_importance", {}),
        }

    return success(data, "Feature importance berhasil diambil")


# GET /api/ml/predictions
@app.get("/api/ml/predictions")
def get_predictions(
    model_type: Optional[str] = None,
    limit: int = 50,
    _=Depends(require_operator),
):
    import json
    conn = get_db()
    if not conn:
        return error(503, "Database tidak tersedia")

    try:
        cur = conn.cursor(dictionary=True)
        if model_type:
            cur.execute(
                "SELECT * FROM ml_predictions WHERE model_type = %s "
                "ORDER BY created_at DESC LIMIT %s",
                (model_type, limit)
            )
        else:
            cur.execute(
                "SELECT * FROM ml_predictions "
                "ORDER BY created_at DESC LIMIT %s",
                (limit,)
            )
        rows = cur.fetchall()

        for row in rows:
            if isinstance(row.get("input_data"), str):
                row["input_data"] = json.loads(row["input_data"])
            if isinstance(row.get("result"), str):
                row["result"] = json.loads(row["result"])
            if row.get("created_at"):
                row["created_at"] = str(row["created_at"])

        return success({"predictions": rows, "count": len(rows)},
                       f"Berhasil mengambil {len(rows)} prediksi")
    except MySQLError as e:
        return error(500, f"Database error: {str(e)}")
    finally:
        conn.close()


# POST /predict/batch
@app.post("/predict/batch")
def predict_batch(body: BatchIn, _=Depends(verify_jwt)):
    results = []

    for i, item in enumerate(body.predictions):
        try:
            if item.model_type == "traffic":
                resp = _predict_traffic_logic(item.payload)
            elif item.model_type == "aqi":
                resp = _predict_aqi_logic(item.payload)
            elif item.model_type == "anomaly":
                resp = _detect_anomaly_logic(item.payload)
            else:
                results.append({
                    "index": i, "model_type": item.model_type,
                    "status": "error",
                    "message": f"model_type tidak dikenal: {item.model_type}"
                })
                continue

            results.append({
                "index": i, "model_type": item.model_type,
                "status": "success", "data": resp
            })
        except Exception as e:
            results.append({
                "index": i, "model_type": item.model_type,
                "status": "error", "message": str(e)
            })

    return success({"results": results, "count": len(results)},
                   f"Batch prediction selesai: {len(results)} item")


# Internal helpers untuk batch
def _predict_traffic_logic(payload: dict) -> dict:
    b        = BUNDLE["traffic"]
    zone     = payload.get("zone", "A")
    zone_map = {"A": 0, "B": 1, "C": 2, "D": 3}
    loc      = zone_map.get(str(zone).upper(), 0)
    X        = b["scaler"].transform([[
        payload.get("hour", 8),
        payload.get("day_of_week", 1),
        0,
        payload.get("vehicle_count", 30),
        loc,
    ]])
    density = float(b["model"].predict(X)[0])
    level   = "Padat" if density > 80 else "Sedang" if density > 40 else "Lancar"
    return {"predicted_density": round(density, 1), "congestion_level": level}


def _predict_aqi_logic(payload: dict) -> dict:
    b       = BUNDLE["air"]
    aqi_val = payload.get("aqi", 50)
    pm25    = aqi_val * 0.8
    pm10    = pm25 * 1.5
    X       = b["scaler"].transform([[
        pm25, pm10,
        aqi_val * 0.3, aqi_val * 0.02,
        max(0, 80 - aqi_val * 0.2),
        payload.get("temperature", 28),
        payload.get("humidity", 65),
    ]])
    pred  = b["model"].predict(X)[0]
    label = b["le_aqi"].inverse_transform([pred])[0]
    proba = b["model"].predict_proba(X)[0]
    return {"aqi_category": label, "confidence": round(float(proba.max()), 4)}


def _detect_anomaly_logic(payload: dict) -> dict:
    b     = BUNDLE["anomaly"]
    value = payload.get("value", 0)
    X     = b["scaler"].transform([[value, datetime.now().hour, value, 0.0]])
    score = float(b["model"].score_samples(X)[0])
    is_a  = score < -0.1
    sev   = "Kritis" if score < -0.3 else "Peringatan" if is_a else "Normal"
    return {"is_anomaly": is_a, "anomaly_score": round(-score, 4), "severity": sev}