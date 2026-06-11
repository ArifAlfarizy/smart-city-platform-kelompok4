"""
main.py
-------
FastAPI Smart City ML Service
Port  : 5000
Models: Traffic Predictor, Air Quality Classifier, Anomaly Detector

Endpoints:
  GET  /health
  GET  /metrics
  POST /api/ml/predict/traffic
  POST /api/ml/predict/aqi
  POST /api/ml/detect/anomaly
  GET  /api/ml/model/feature-importance
  GET  /api/ml/predictions
  POST /predict/batch
"""

import os
import time
import joblib
import numpy as np
from datetime import datetime, timezone
from typing import Optional, List

from fastapi import FastAPI, HTTPException, Depends, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field
from dotenv import load_dotenv
from prometheus_fastapi_instrumentator import Instrumentator
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials

# Database
import mysql.connector
from mysql.connector import Error as MySQLError

# JWT
import jwt as pyjwt

load_dotenv()

# App Setup
app = FastAPI(
    title="Smart City ML Service",
    version="1.0.0",
    description="Traffic prediction, Air Quality classification & Anomaly detection",
)

security = HTTPBearer()

# Prometheus metrics
Instrumentator().instrument(app).expose(app, endpoint="/metrics")

# Load Models
MODEL_PATH = os.getenv("MODEL_PATH", "models/smartcity_models.pkl")

try:
    BUNDLE = joblib.load(MODEL_PATH)
    print(f"✓ Models loaded from {MODEL_PATH}")
    print(f"  Available: {list(BUNDLE.keys())}")
except FileNotFoundError:
    print(f"  Model file not found: {MODEL_PATH}")
    print("   Jalankan: python train_models.py")
    BUNDLE = {}


# DB Helper
def get_db():
    try:
        conn = mysql.connector.connect(
            host=os.getenv("DB_HOST", "mysql"),
            port=int(os.getenv("DB_PORT", 3306)),
            database=os.getenv("DB_NAME", "smartcity"),
            user=os.getenv("DB_USER", "ml_user"),
            password=os.getenv("DB_PASS", "ml_password"),
        )
        return conn
    except MySQLError:
        return None


def save_prediction(model_type: str, zone: Optional[str],
                    input_data: dict, result: dict,
                    confidence: Optional[float] = None):
    """Simpan hasil prediksi ke tabel ml_predictions."""
    import json
    conn = get_db()
    if not conn:
        return  # DB tidak tersedia — skip, jangan crash
    try:
        cur = conn.cursor()
        cur.execute(
            """INSERT INTO ml_predictions
               (model_type, zone, input_data, result, confidence_score)
               VALUES (%s, %s, %s, %s, %s)""",
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
JWT_SECRET    = os.getenv("JWT_SECRET", "dev_secret")
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
    """Dependency — hanya role operator yang boleh akses."""
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
    zone:         str
    vehicle_count: float = Field(..., ge=0)
    avg_speed:    float  = Field(..., ge=0)
    hour:         int    = Field(..., ge=0, le=23)
    day_of_week:  int    = Field(..., ge=0, le=6)
    incident:     int    = Field(0, ge=0, le=1)


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
    """Status service dan daftar model yang ter-load."""
    db_ok = get_db() is not None
    return success({
        "service":      "python-ml",
        "models_loaded": list(BUNDLE.keys()),
        "db_connected": db_ok,
    }, "Service healthy")


# POST /api/ml/predict/traffic
@app.post("/api/ml/predict/traffic")
def predict_traffic(body: TrafficIn, _=Depends(verify_jwt)):
    """
    Prediksi kepadatan lalu lintas berdasarkan kondisi saat ini.
    Input  : zone, vehicle_count, avg_speed, hour, day_of_week, incident
    Output : predicted_density, congestion_level, confidence
    """
    if "traffic" not in BUNDLE:
        return error(503, "Traffic model belum tersedia")

    b = BUNDLE["traffic"]

    # Map zone ke location_enc
    zone_map = {"A": 0, "B": 1, "C": 2, "D": 3,
                "zone1": 0, "zone2": 1, "zone3": 2, "zone4": 3}
    location_enc = zone_map.get(str(body.zone).upper(),
                                zone_map.get(body.zone, 0))

    # Gunakan vehicle_count sebagai prev_density (kondisi terkini)
    weather_code = 0  # default cerah

    try:
        X = b["scaler"].transform([[
            body.hour,
            body.day_of_week,
            weather_code,
            body.vehicle_count,   # prev_density
            location_enc,
        ]])
        density = float(b["model"].predict(X)[0])
        density = max(0.0, density)
    except Exception as e:
        return error(500, f"Prediksi gagal: {str(e)}")

    if density > 80:
        level = "Padat"
    elif density > 40:
        level = "Sedang"
    else:
        level = "Lancar"

    # Estimasi confidence dari variance antar tree
    tree_preds = np.array([t.predict(X)[0] for t in b["model"].estimators_])
    confidence = round(float(1 - (tree_preds.std() / (tree_preds.mean() + 1e-6))), 4)
    confidence = max(0.0, min(1.0, confidence))

    result = {
        "predicted_density": round(density, 1),
        "congestion_level":  level,
        "confidence":        confidence,
        "zone":              body.zone,
    }

    save_prediction("traffic", body.zone,
                    body.model_dump(), result, confidence)

    return success(result, "Prediksi traffic berhasil")


# POST /api/ml/predict/aqi
@app.post("/api/ml/predict/aqi")
def predict_aqi(body: AQIIn, _=Depends(verify_jwt)):
    """
    Klasifikasi kategori kualitas udara.
    Input  : zone, aqi, temperature, humidity
    Output : aqi_category, confidence, probabilities
    """
    if "air" not in BUNDLE:
        return error(503, "AQI model belum tersedia")

    b = BUNDLE["air"]

    # Derive fitur dari AQI value (pendekatan inverse)
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

    confidence   = round(float(proba.max()), 4)
    probabilities = dict(zip(
        b["classes"],
        [round(float(p), 4) for p in proba]
    ))

    result = {
        "aqi_category":   label,
        "confidence":     confidence,
        "probabilities":  probabilities,
        "zone":           body.zone,
    }

    save_prediction("aqi", body.zone,
                    body.model_dump(), result, confidence)

    return success(result, "Prediksi AQI berhasil")


# POST /api/ml/detect/anomaly
@app.post("/api/ml/detect/anomaly")
def detect_anomaly(body: AnomalyIn, _=Depends(verify_jwt)):
    """
    Deteksi anomali pembacaan sensor.
    Input  : sensor_type, value, zone
    Output : is_anomaly, anomaly_score, severity
    """
    if "anomaly" not in BUNDLE:
        return error(503, "Anomaly model belum tersedia")

    b    = BUNDLE["anomaly"]
    hour = datetime.now().hour

    # Rolling mean & z_score — estimasi sederhana
    rolling_mean = body.value  # tanpa histori, gunakan nilai itu sendiri sebagai baseline
    z_score      = 0.0         # akan diupdate saat ada histori

    try:
        X     = b["scaler"].transform([[
            body.value, hour, rolling_mean, z_score
        ]])
        score = float(b["model"].score_samples(X)[0])
    except Exception as e:
        return error(500, f"Deteksi gagal: {str(e)}")

    is_anomaly = score < -0.1

    if score < -0.3:
        severity = "Kritis"
    elif is_anomaly:
        severity = "Peringatan"
    else:
        severity = "Normal"

    result = {
        "is_anomaly":    is_anomaly,
        "anomaly_score": round(-score, 4),
        "severity":      severity,
        "sensor_type":   body.sensor_type,
        "zone":          body.zone,
    }

    save_prediction("anomaly", body.zone,
                    body.model_dump(), result)

    return success(result, "Deteksi anomali berhasil")


# GET /api/ml/model/feature-importance
@app.get("/api/ml/model/feature-importance")
def feature_importance(_=Depends(verify_jwt)):
    """
    Bobot fitur penting ketiga model.
    [GAP PRD — endpoint ini wajib ada]
    """
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
    """
    Riwayat hasil prediksi (operator only).
    Query params: model_type (opsional), limit (default 50)
    """
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

        # Parse JSON fields
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
    """
    Batch prediction — array input, array output.
    Setiap item di array bisa punya model_type berbeda.
    """
    results = []

    for i, item in enumerate(body.predictions):
        try:
            if item.model_type == "traffic":
                traffic_body = TrafficIn(**item.payload)
                resp = predict_traffic.__wrapped__(traffic_body) \
                    if hasattr(predict_traffic, "__wrapped__") \
                    else _predict_traffic_logic(item.payload)
                results.append({"index": i, "model_type": "traffic",
                                 "status": "success", "data": resp})

            elif item.model_type == "aqi":
                resp = _predict_aqi_logic(item.payload)
                results.append({"index": i, "model_type": "aqi",
                                 "status": "success", "data": resp})

            elif item.model_type == "anomaly":
                resp = _detect_anomaly_logic(item.payload)
                results.append({"index": i, "model_type": "anomaly",
                                 "status": "success", "data": resp})

            else:
                results.append({"index": i, "model_type": item.model_type,
                                 "status": "error",
                                 "message": f"model_type tidak dikenal: {item.model_type}"})
        except Exception as e:
            results.append({"index": i, "model_type": item.model_type,
                             "status": "error", "message": str(e)})

    return success({"results": results, "count": len(results)},
                   f"Batch prediction selesai: {len(results)} item")


# Internal helpers untuk batch
def _predict_traffic_logic(payload: dict) -> dict:
    b    = BUNDLE["traffic"]
    zone = payload.get("zone", "A")
    zone_map = {"A": 0, "B": 1, "C": 2, "D": 3}
    loc  = zone_map.get(str(zone).upper(), 0)
    X    = b["scaler"].transform([[
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