import json
import logging
import os
import pika

logger = logging.getLogger(__name__)

EXCHANGE    = os.getenv("RABBITMQ_EXCHANGE", "city.events")
QUEUE       = "citizen.report.submitted"
ROUTING_KEY = "citizen.report.submitted"

SENSOR_RELATED = {"polusi", "banjir", "lampu_mati"}


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


def analyze_report(event: dict, bundle: dict) -> dict:
    category = event.get("category", "").lower()
    zone     = event.get("zone", "A")
    result   = {
        "report_id":  event.get("report_id"),
        "citizen_id": event.get("citizen_id"),
        "zone":       zone,
        "category":   category,
        "ml_action":  "none",
    }

    if category == "polusi" and "air" in bundle:
        b    = bundle["air"]
        # Default AQI moderate untuk laporan polusi manual
        aqi_val = 80
        pm25 = aqi_val * 0.8; pm10 = pm25 * 1.5
        no2  = aqi_val * 0.3; co   = aqi_val * 0.02
        o3   = max(0, 80 - aqi_val * 0.2)
        X    = b["scaler"].transform([[pm25, pm10, no2, co, o3, 28, 65]])
        pred = b["model"].predict(X)[0]
        result["ml_action"]   = "aqi_check"
        result["aqi_category"] = b["le_aqi"].inverse_transform([pred])[0]

    elif category == "banjir" and "anomaly" in bundle:
        b     = bundle["anomaly"]
        # Flood sensor value tinggi
        X     = b["scaler"].transform([[95.0, 12, 30.0, 4.5]])
        score = float(b["model"].score_samples(X)[0])
        result["ml_action"]    = "anomaly_check"
        result["is_anomaly"]   = score < -0.1
        result["anomaly_score"] = round(-score, 4)

    logger.info(f"[citizen_consumer] Report analyzed: {result}")
    return result


def start_citizen_consumer(bundle: dict, anomaly_publisher=None):
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
                logger.info(f"[citizen_consumer] Report received: {event}")

                result = analyze_report(event, bundle)

                # Kalau analisis menemukan anomali → publish
                if result.get("is_anomaly") and anomaly_publisher:
                    anomaly_publisher.publish_anomaly({
                        "zone":         result["zone"],
                        "anomaly_type": f"citizen_report_{result['category']}",
                        "message":      f"Laporan warga terkonfirmasi anomali di zona {result['zone']}",
                        "severity":     "Peringatan",
                        "data":         result,
                    })

                ch.basic_ack(delivery_tag=method.delivery_tag)

            except Exception as e:
                logger.error(f"[citizen_consumer] Error: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

        channel.basic_consume(queue=QUEUE, on_message_callback=callback)
        logger.info(f"[citizen_consumer] Listening on {QUEUE}...")
        channel.start_consuming()

    except Exception as e:
        logger.error(f"[citizen_consumer] Connection failed: {e}")