import json
import logging
import os
import pika
from datetime import datetime

logger = logging.getLogger(__name__)

EXCHANGE    = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE       = "environment.sensor.received"
ROUTING_KEY = "environment.sensor.received"


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


def process_air_event(event: dict, bundle: dict) -> dict:
    b       = bundle["air"]
    zone    = event.get("zone", "A")
    aqi_val = float(event.get("aqi", event.get("aqi_value", 50)))

    # Derive fitur dari AQI value
    pm25 = aqi_val * 0.8
    pm10 = pm25 * 1.5
    no2  = aqi_val * 0.3
    co   = aqi_val * 0.02
    o3   = max(0, 80 - aqi_val * 0.2)
    temp = float(event.get("temperature", 28))
    hum  = float(event.get("humidity", 65))

    X     = b["scaler"].transform([[pm25, pm10, no2, co, o3, temp, hum]])
    pred  = b["model"].predict(X)[0]
    proba = b["model"].predict_proba(X)[0]
    label = b["le_aqi"].inverse_transform([pred])[0]

    return {
        "zone":          zone,
        "aqi_category":  label,
        "confidence":    round(float(proba.max()), 4),
        "source_event":  "environment.sensor.received",
    }


def start_air_consumer(bundle: dict, anomaly_publisher=None):
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
                logger.info(f"[air_consumer] Received: {event}")

                result = process_air_event(event, bundle)
                logger.info(f"[air_consumer] Prediction: {result}")

                # Kalau AQI buruk, publish anomali
                danger_categories = ["Unhealthy", "Very Unhealthy",
                                     "Hazardous", "Unhealthy for Sensitive Groups"]
                if result["aqi_category"] in danger_categories and anomaly_publisher:
                    anomaly_publisher.publish_anomaly({
                        "zone":         result["zone"],
                        "anomaly_type": "air_quality_degraded",
                        "message":      f"Kualitas udara {result['aqi_category']} di zona {result['zone']}",
                        "severity":     "Kritis" if result["aqi_category"] == "Hazardous"
                                        else "Peringatan",
                        "data":         result,
                    })

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[air_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        channel.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[air_consumer] Listening on {QUEUE}...")
        channel.start_consuming()

    except Exception as e:
        logger.error(f"[air_consumer] Connection failed: {e}")