import os
import threading
import logging
import joblib
import numpy as np
from datetime import datetime, timezone
from typing import Optional, List
from fastapi import FastAPI, Depends, HTTPException
from fastapi.responses import JSONResponse
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel, Field
from dotenv import load_dotenv
from prometheus_fastapi_instrumentator import Instrumentator
import mysql.connector
from mysql.connector import Error as MySQLError
import jwt as pyjwt
from recommendation_engine import generate_recommendations

load_dotenv()

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)
logger = logging.getLogger(__name__)

# App
app = FastAPI(
    title="Smart Traffic DSS — ML Service",
    version="2.0.0",
    description="Decision Support System untuk Rekayasa Lalu Lintas MT Haryono Jakarta",
)

Instrumentator().instrument(app).expose(app, endpoint="/metrics")
security = HTTPBearer()

# Load Model
MODEL_PATH = os.getenv("MODEL_PATH", "models/smartcity_models.pkl")
try:
    BUNDLE = joblib.load(MODEL_PATH)
    logger.info(f"✓ Model loaded: {list(BUNDLE.keys())}")
except FileNotFoundError:
    logger.warning("⚠️ Model not found. Run: python train_models.py")
    BUNDLE = {}

# RabbitMQ Consumers
from consumers.traffic_consumer import start_traffic_consumer
from consumers.environment_consumer import start_environment_consumer
from consumers.incident_consumer import start_incident_consumer
from consumers.recommendation_publisher import RecommendationPublisher

rec_publisher = RecommendationPublisher()

def _start_consumers():
    import time
    time.sleep(5)
    threads = [
        threading.Thread(
            target=start_traffic_consumer,
            args=(BUNDLE, rec_publisher),
            daemon=True, 
            name="traffic-consumer"
        ),
        threading.Thread(
            target=start_environment_consumer,
            args=(BUNDLE, rec_publisher),
            daemon=True, 
            name="environment-consumer"
        ),
        threading.Thread(
            target=start_incident_consumer,
            args=(BUNDLE, rec_publisher),
            daemon=True, 
            name="incident-consumer"
        ),
    ]
    for t in threads:
        t.start()
        logger.info(f"✓ Started: {t.name}")

threading.Thread(
    target=_start_consumers, 
    daemon=True, 
    name="consumer-starter"
).start()

# DB
def get_db():
    try:
        return mysql.connector.connect(
            host=os.getenv("DB_HOST", "mysql"),
            port=int(os.getenv("DB_PORT", 3306)),
            database=os.getenv("DB_NAME", "smartcity"),
            user=os.getenv("DB_USER", "root"),
            password=os.getenv("DB_PASS", ""),
        )
    except MySQLError:
        return None

def save_analysis(input_data: dict, result: dict):
    import json
    conn = get_db()
    if not conn:
        return
    try:
        cur = conn.cursor()
        cur.execute(
            """INSERT INTO ml_predictions
            (model_type, zone, input_data, result, confidence_score)
            VALUES (%s, %s, %s, %s, %s)""",
            ("congestion", "MT_Haryono",
             json.dumps(input_data), json.dumps(result),
             result.get("confidence"))
        )
        conn.commit()
    except MySQLError:
        pass
    finally:
        conn.close()

# JWT
JWT_SECRET = os.getenv("JWT_SECRET", "accessrahasia")
JWT_ALGORITHM = "HS256"

def verify_jwt(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict:
    try:
        return pyjwt.decode(
            credentials.credentials, JWT_SECRET, algorithms=[JWT_ALGORITHM]
        )
    except pyjwt.ExpiredSignatureError:
        raise HTTPException(401, detail=resp("error", 401, None, "Token expired"))
    except pyjwt.InvalidTokenError:
        raise HTTPException(401, detail=resp("error", 401, None, "Token invalid"))

def require_operator(payload: dict = Depends(verify_jwt)) -> dict:
    if payload.get("role") not in ("operator", "admin"):
        raise HTTPException(403, detail=resp("error", 403, None, "Operator only"))
    return payload

# Response Helper
def resp(status, code, data, message):
    return {
        "status": status,
        "code": code,
        "data": data,
        "message": message,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "service": "python-ml",
    }

def ok(data, msg="OK", code=200):
    return JSONResponse(status_code=code, content=resp("success", code, data, msg))

def err(code, msg):
    return JSONResponse(status_code=code, content=resp("error", code, None, msg))

# Schemas
class TrafficAnalysisIn(BaseModel):
    vehicle_count: int = Field(..., ge=0)
    average_speed: float = Field(..., ge=0)
    rainfall: float = Field(0.0, ge=0)
    water_level: float = Field(200.0, ge=0)
    incident_count: int = Field(0, ge=0)
    hour: Optional[int] = None  # auto-detect kalau None
    day_of_week: Optional[int] = None  # auto-detect kalau None

class BatchAnalysisItem(BaseModel):
    vehicle_count: int
    average_speed: float
    rainfall: float = 0.0
    water_level: float = 200.0
    incident_count: int = 0

class BatchIn(BaseModel):
    items: List[BatchAnalysisItem]

# Core Inference
def run_inference(
    vehicle_count: int,
    average_speed: float,
    rainfall: float,
    water_level: float,
    incident_count: int,
    hour: int,
    day_of_week: int,
) -> dict:
    if "congestion" not in BUNDLE:
        raise ValueError("Model belum tersedia")
    
    b = BUNDLE["congestion"]
    X = b["scaler"].transform([[
        vehicle_count, average_speed, rainfall,
        water_level, incident_count, hour, day_of_week
    ]])
    
    pred_enc = b["model"].predict(X)[0]
    proba = b["model"].predict_proba(X)[0]
    label = b["le"].inverse_transform([pred_enc])[0]
    conf = float(proba.max())
    
    # Generate rekomendasi
    result = generate_recommendations(
        congestion_level=label,
        vehicle_count=vehicle_count,
        average_speed=average_speed,
        rainfall=rainfall,
        water_level=water_level,
        incident_count=incident_count,
        hour=hour,
        confidence=conf,
    )
    
    result["congestion_probabilities"] = dict(zip(
        b["classes"],
        [round(float(p), 4) for p in proba]
    ))
    
    return result

# ENDPOINTS

@app.get("/health")
def health():
    db_ok = get_db() is not None
    return ok({
        "service": "python-ml",
        "version": "2.0.0",
        "models_loaded": list(BUNDLE.keys()),
        "db_connected": db_ok,
    }, "Service healthy")

@app.post("/api/ml/analyze")
def analyze_traffic(body: TrafficAnalysisIn, _=Depends(verify_jwt)):
    if "congestion" not in BUNDLE:
        return err(503, "Model belum tersedia — jalankan train_models.py")
    
    now = datetime.now()
    hour = body.hour if body.hour is not None else now.hour
    day_of_week = body.day_of_week if body.day_of_week is not None else now.weekday()
    
    try:
        result = run_inference(
            body.vehicle_count, body.average_speed,
            body.rainfall, body.water_level,
            body.incident_count, hour, day_of_week,
        )
    except Exception as e:
        return err(500, f"Analisis gagal: {str(e)}")
    
    result["input"] = {
        "vehicle_count": body.vehicle_count,
        "average_speed": body.average_speed,
        "rainfall": body.rainfall,
        "water_level": body.water_level,
        "incident_count": body.incident_count,
        "hour": hour,
        "day_of_week": day_of_week,
    }
    
    save_analysis(body.model_dump(), result)
    return ok(result, "Analisis berhasil")

@app.get("/api/ml/status")
def get_current_status(_=Depends(verify_jwt)):
    import json
    conn = get_db()
    if not conn:
        return err(503, "Database tidak tersedia")
    
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(
            "SELECT * FROM ml_predictions WHERE model_type='congestion' "
            "ORDER BY created_at DESC LIMIT 1"
        )
        row = cur.fetchone()
        if not row:
            return ok({"message": "Belum ada data analisis"}, "No data yet")
        
        if isinstance(row.get("result"), str):
            row["result"] = json.loads(row["result"])
        row["created_at"] = str(row.get("created_at", ""))
        return ok(row["result"], "Status terkini")
    except MySQLError as e:
        return err(500, f"DB error: {str(e)}")
    finally:
        conn.close()

@app.get("/api/ml/recommendations/latest")
def get_latest_recommendations(
    limit: int = 10,
    _=Depends(require_operator)
):
    import json
    conn = get_db()
    if not conn:
        return err(503, "Database tidak tersedia")
    
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(
            "SELECT * FROM ml_predictions WHERE model_type='congestion' "
            "ORDER BY created_at DESC LIMIT %s",
            (limit,)
        )
        rows = cur.fetchall()
        for row in rows:
            if isinstance(row.get("result"), str):
                row["result"] = json.loads(row["result"])
            if isinstance(row.get("input_data"), str):
                row["input_data"] = json.loads(row["input_data"])
            row["created_at"] = str(row.get("created_at", ""))
        
        return ok({"recommendations": rows, "count": len(rows)},
                  f"{len(rows)} rekomendasi terakhir")
    except MySQLError as e:
        return err(500, f"DB error: {str(e)}")
    finally:
        conn.close()

@app.get("/api/ml/model/feature-importance")
def feature_importance(_=Depends(verify_jwt)):
    if "congestion" not in BUNDLE:
        return err(503, "Model belum tersedia")
    
    return ok({
        "model": "Gradient Boosting Classifier",
        "features": BUNDLE["congestion"].get("feature_importance", {}),
        "classes": BUNDLE["congestion"].get("classes", []),
        "metrics": BUNDLE["congestion"].get("metrics", {}),
    }, "Feature importance berhasil diambil")

@app.post("/api/ml/analyze/batch")
def analyze_batch(body: BatchIn, _=Depends(verify_jwt)):
    if "congestion" not in BUNDLE:
        return err(503, "Model belum tersedia")
    
    now = datetime.now()
    results = []
    for i, item in enumerate(body.items):
        try:
            result = run_inference(
                item.vehicle_count, item.average_speed,
                item.rainfall, item.water_level,
                item.incident_count,
                now.hour, now.weekday(),
            )
            results.append({"index": i, "status": "success", "data": result})
        except Exception as e:
            results.append({"index": i, "status": "error", "message": str(e)})
    
    return ok({"results": results, "count": len(results)},
              f"Batch analysis selesai: {len(results)} item")