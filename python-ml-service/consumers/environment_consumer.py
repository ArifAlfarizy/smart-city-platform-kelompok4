import json
import logging
import os
import pika
from datetime import datetime

logger = logging.getLogger(__name__)

EXCHANGE = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE = "environment.updated"
ROUTING_KEY = "environment.updated"

# Import state cache dari traffic_consumer untuk sinkronisasi data
from consumers.traffic_consumer import _last_env


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


def start_environment_consumer(bundle: dict, rec_publisher=None):
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
                logger.info(f"[env_consumer] Received: {event}")

                # Update state cache — akan dipakai traffic_consumer
                _last_env["rainfall"] = float(event.get("rainfall", 0))
                _last_env["water_level"] = float(event.get("water_level", 200))

                logger.info(
                    f"[env_consumer] State updated: rainfall={_last_env['rainfall']}, "
                    f"water_level={_last_env['water_level']}"
                )

                # Kalau muka air bahaya, langsung publish rekomendasi
                if _last_env["water_level"] > 400 and rec_publisher:
                    from recommendation_engine import generate_recommendations
                    now = datetime.now()
                    
                    result = generate_recommendations(
                        congestion_level="Macet",  # worst case assumption
                        vehicle_count=500,
                        average_speed=20,
                        rainfall=_last_env["rainfall"],
                        water_level=_last_env["water_level"],
                        incident_count=0,
                        hour=now.hour,
                        confidence=0.85,
                    )
                    
                    rec_publisher.publish({
                        "trigger": "environment.updated",
                        "road_name": "MT Haryono",
                        **result,
                    })
                    
                    logger.warning(f"[env_consumer] Water level critical! Alert published.")

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[env_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        ch.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[env_consumer] Listening on {QUEUE}...")
        ch.start_consuming()

    except Exception as e:
        logger.error(f"[env_consumer] Connection failed: {e}")