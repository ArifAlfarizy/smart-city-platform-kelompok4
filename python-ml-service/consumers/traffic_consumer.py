import json
import logging
import os
import pika
from datetime import datetime
from recommendation_engine import generate_recommendations

logger = logging.getLogger(__name__)

EXCHANGE = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE = "traffic.updated"
ROUTING_KEY = "traffic.updated"

# State cache — simpan data environment terakhir untuk dipakai di analisis traffic
_last_env = {"rainfall": 0.0, "water_level": 200.0}
_last_incident = {"incident_count": 0}


def get_connection():
    creds = pika.PlainCredentials(
        os.getenv("RABBITMQ_USER", "guest"),
        os.getenv("RABBITMQ_PASS", "guest")
    )
    params = pika.ConnectionParameters(
        host=os.getenv("RABBITMQ_HOST", "rabbitmq"),
        port=int(os.getenv("RABBITMQ_PORT", 5672)),
        credentials=creds,
        heartbeat=600,
    )
    return pika.BlockingConnection(params)


def start_traffic_consumer(bundle: dict, rec_publisher=None):
    try:
        conn = get_connection()
        ch = conn.channel()
        ch.exchange_declare(exchange=EXCHANGE, exchange_type="topic", durable=True)
        ch.queue_declare(queue=QUEUE, durable=True)
        ch.queue_bind(queue=QUEUE, exchange=EXCHANGE, routing_key=ROUTING_KEY)
        ch.basic_qos(prefetch_count=1)

        def callback(ch, method, props, body):
            try:
                event = json.loads(body)
                logger.info(f"[traffic_consumer] Received: {event}")

                if "congestion" not in bundle:
                    ch.basic_ack(delivery_tag=method.delivery_tag)
                    return

                b = bundle["congestion"]
                vehicle_count = int(event.get("vehicle_count", 0))
                average_speed = float(event.get("average_speed", 30))
                rainfall = _last_env["rainfall"]
                water_level = _last_env["water_level"]
                incident_count = _last_incident["incident_count"]
                now = datetime.now()

                X = b["scaler"].transform([[
                    vehicle_count, average_speed, rainfall,
                    water_level, incident_count, now.hour, now.weekday()
                ]])

                pred = b["model"].predict(X)[0]
                proba = b["model"].predict_proba(X)[0]
                label = b["le"].inverse_transform([pred])[0]
                conf = float(proba.max())

                result = generate_recommendations(
                    congestion_level=label,
                    vehicle_count=vehicle_count,
                    average_speed=average_speed,
                    rainfall=rainfall,
                    water_level=water_level,
                    incident_count=incident_count,
                    hour=now.hour,
                    confidence=conf,
                )

                logger.info(
                    f"[traffic_consumer] → {label} | {result['priority']} | "
                    f"{len(result['recommendations'])} rekomendasi"
                )

                # Publish recommendation
                if rec_publisher:
                    rec_publisher.publish({
                        "trigger": "traffic.updated",
                        "road_name": event.get("road_name", "MT Haryono"),
                        **result,
                    })

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[traffic_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        ch.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[traffic_consumer] Listening on {QUEUE}...")
        ch.start_consuming()

    except Exception as e:
        logger.error(f"[traffic_consumer] Connection failed: {e}")