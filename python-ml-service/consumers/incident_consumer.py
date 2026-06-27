import json
import logging
import os
import pika
from datetime import datetime
from recommendation_engine import generate_recommendations

logger = logging.getLogger(__name__)

EXCHANGE = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE = "incident.created"
ROUTING_KEY = "incident.created"

from consumers.traffic_consumer import _last_env, _last_incident

# Kategori insiden yang pengaruhnya tinggi terhadap lalu lintas
HIGH_IMPACT_CATEGORIES = {"accident", "flood", "fallen_tree"}


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


def start_incident_consumer(bundle: dict, rec_publisher=None):
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
                category = event.get("category", "")
                logger.info(f"[incident_consumer] Incident received: {category}")

                # Update incident count di state cache
                _last_incident["incident_count"] = min(
                    _last_incident["incident_count"] + 1, 10
                )

                # Kalau insiden berdampak tinggi, langsung generate rekomendasi
                if category in HIGH_IMPACT_CATEGORIES and rec_publisher:
                    now = datetime.now()
                    
                    result = generate_recommendations(
                        congestion_level="Macet",
                        vehicle_count=700,
                        average_speed=15,
                        rainfall=_last_env["rainfall"],
                        water_level=_last_env["water_level"],
                        incident_count=_last_incident["incident_count"],
                        hour=now.hour,
                        confidence=0.80,
                    )
                    
                    result["triggered_by_incident"] = category
                    result["incident_detail"] = event.get("description", "")

                    rec_publisher.publish({
                        "trigger": "incident.created",
                        "road_name": event.get("road_name", "MT Haryono"),
                        **result,
                    })

                    logger.info(
                        f"[incident_consumer] Recommendation published for: {category}"
                    )

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[incident_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        ch.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[incident_consumer] Listening on {QUEUE}...")
        ch.start_consuming()

    except Exception as e:
        logger.error(f"[incident_consumer] Connection failed: {e}")