import json
import logging
import os
import joblib
import numpy as np
import pika
from datetime import datetime

logger = logging.getLogger(__name__)

EXCHANGE     = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE        = "traffic.sensor.received"
ROUTING_KEY  = "traffic.sensor.received"
MODEL_PATH   = os.getenv("MODEL_PATH", "models/smartcity_models.pkl")

# Zone mapping
ZONE_MAP = {"A": 0, "B": 1, "C": 2, "D": 3,
            "zone1": 0, "zone2": 1, "zone3": 2, "zone4": 3}


def get_connection():
    credentials = pika.PlainCredentials(
        os.getenv("RABBITMQ_USER", "guest"),
        os.getenv("RABBITMQ_PASS", "guest"),
    )
    params = pika.ConnectionParameters(
        host=os.getenv("RABBITMQ_HOST", "rabbitmq"),
        port=int(os.getenv("RABBITMQ_PORT", 5672)),
        credentials=credentials,
        heartbeat=600,
        blocked_connection_timeout=300,
    )
    return pika.BlockingConnection(params)


def process_traffic_event(event: dict, bundle: dict) -> dict:
    b    = bundle["traffic"]
    zone = event.get("zone", "A")
    loc  = ZONE_MAP.get(str(zone).upper(), ZONE_MAP.get(zone, 0))

    hour        = event.get("hour", datetime.now().hour)
    day_of_week = event.get("day_of_week", datetime.now().weekday())
    density     = float(event.get("vehicle_count", event.get("density", 30)))

    X = b["scaler"].transform([[hour, day_of_week, 0, density, loc]])

    predicted = float(b["model"].predict(X)[0])
    level     = "Padat" if predicted > 80 else "Sedang" if predicted > 40 else "Lancar"

    tree_preds = np.array([t.predict(X)[0] for t in b["model"].estimators_])
    confidence = float(1 - (tree_preds.std() / (tree_preds.mean() + 1e-6)))
    confidence = max(0.0, min(1.0, confidence))

    return {
        "zone":              zone,
        "predicted_density": round(predicted, 1),
        "congestion_level":  level,
        "confidence":        round(confidence, 4),
        "source_event":      "traffic.sensor.received",
    }


def start_traffic_consumer(bundle: dict, anomaly_publisher=None):
    try:
        conn    = get_connection()
        channel = conn.channel()

        channel.exchange_declare(
            exchange=EXCHANGE, exchange_type="topic", durable=True
        )
        channel.queue_declare(queue=QUEUE, durable=True)
        channel.queue_bind(
            queue=QUEUE, exchange=EXCHANGE, routing_key=ROUTING_KEY
        )
        channel.basic_qos(prefetch_count=1)

        def callback(ch, method, properties, body):
            try:
                event  = json.loads(body)
                logger.info(f"[traffic_consumer] Received: {event}")

                result = process_traffic_event(event, bundle)
                logger.info(f"[traffic_consumer] Prediction: {result}")

                # Kalau macet padat, publish anomali
                if result["congestion_level"] == "Padat" and anomaly_publisher:
                    anomaly_publisher.publish_anomaly({
                        "zone":         result["zone"],
                        "anomaly_type": "traffic_congestion",
                        "message":      f"Kemacetan padat terdeteksi di zona {result['zone']}",
                        "severity":     "Peringatan",
                        "data":         result,
                    })

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[traffic_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        channel.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[traffic_consumer] Listening on {QUEUE}...")
        channel.start_consuming()

    except Exception as e:
        logger.error(f"[traffic_consumer] Connection failed: {e}")