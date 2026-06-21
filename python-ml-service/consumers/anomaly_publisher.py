import json
import logging
import os
from datetime import datetime, timezone

import pika

logger = logging.getLogger(__name__)

EXCHANGE    = os.getenv("RABBITMQ_EXCHANGE", "city.events")
ROUTING_KEY = "ml.anomaly.detected"


class AnomalyPublisher:
    def __init__(self):
        self._connection = None
        self._channel    = None

    def _connect(self):
        credentials = pika.PlainCredentials(
            os.getenv("RABBITMQ_USER", "guest"),
            os.getenv("RABBITMQ_PASS", "guest"),
        )
        params = pika.ConnectionParameters(
            host=os.getenv("RABBITMQ_HOST", "rabbitmq"),
            port=int(os.getenv("RABBITMQ_PORT", 5672)),
            credentials=credentials,
            heartbeat=600,
        )
        self._connection = pika.BlockingConnection(params)
        self._channel    = self._connection.channel()
        self._channel.exchange_declare(
            exchange=EXCHANGE, exchange_type="topic", durable=True
        )
        logger.info("[anomaly_publisher] Connected to RabbitMQ")

    def publish_anomaly(self, payload: dict):
        # Tambahkan timestamp kalau belum ada
        if "timestamp" not in payload:
            payload["timestamp"] = datetime.now(timezone.utc).isoformat()

        # Reconnect kalau perlu
        try:
            if not self._connection or self._connection.is_closed:
                self._connect()
        except Exception:
            self._connect()

        try:
            self._channel.basic_publish(
                exchange=EXCHANGE,
                routing_key=ROUTING_KEY,
                body=json.dumps(payload),
                properties=pika.BasicProperties(
                    delivery_mode=2,  # persistent message
                    content_type="application/json",
                ),
            )
            logger.info(f"[anomaly_publisher] Published: {payload.get('anomaly_type')} "
                        f"at zone {payload.get('zone')} — {payload.get('severity')}")
        except Exception as e:
            logger.error(f"[anomaly_publisher] Publish failed: {e}")
            # Reset koneksi supaya next call reconnect
            self._connection = None
            self._channel    = None

    def close(self):
        if self._connection and not self._connection.is_closed:
            self._connection.close()