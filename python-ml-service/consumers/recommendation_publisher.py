import json
import logging
import os
import pika
from datetime import datetime, timezone

logger = logging.getLogger(__name__)

EXCHANGE = os.getenv("RABBITMQ_EXCHANGE", "city.events")
ROUTING_KEY = "recommendation.generated"


class RecommendationPublisher:
    def __init__(self):
        self._connection = None
        self._channel = None
        self._reconnect_attempts = 0
        self._max_reconnect_attempts = 3

    def _connect(self):
        try:
            creds = pika.PlainCredentials(
                os.getenv("RABBITMQ_USER", "guest"),
                os.getenv("RABBITMQ_PASS", "guest")
            )
            params = pika.ConnectionParameters(
                host=os.getenv("RABBITMQ_HOST", "rabbitmq"),
                port=int(os.getenv("RABBITMQ_PORT", 5672)),
                credentials=creds,
                heartbeat=600,
                connection_attempts=3,
                retry_delay=5,
            )
            self._connection = pika.BlockingConnection(params)
            self._channel = self._connection.channel()
            self._channel.exchange_declare(
                exchange=EXCHANGE,
                exchange_type="topic",
                durable=True
            )
            self._reconnect_attempts = 0
            logger.info("[rec_publisher] Connected to RabbitMQ")
            return True
        except Exception as e:
            logger.error(f"[rec_publisher] Connection failed: {e}")
            self._connection = None
            self._channel = None
            return False

    def _ensure_connection(self):
        try:
            if not self._connection or self._connection.is_closed:
                logger.warning("[rec_publisher] Connection lost, reconnecting...")
                if self._reconnect_attempts < self._max_reconnect_attempts:
                    self._reconnect_attempts += 1
                    return self._connect()
                else:
                    logger.error(
                        f"[rec_publisher] Max reconnect attempts ({self._max_reconnect_attempts}) reached"
                    )
                    return False
            return True
        except Exception as e:
            logger.error(f"[rec_publisher] Connection check failed: {e}")
            return False

    def publish(self, payload: dict):
        if "timestamp" not in payload:
            payload["timestamp"] = datetime.now(timezone.utc).isoformat()

        # Ensure connection
        if not self._ensure_connection():
            logger.error("[rec_publisher] Cannot publish: no connection")
            return False

        try:
            self._channel.basic_publish(
                exchange=EXCHANGE,
                routing_key=ROUTING_KEY,
                body=json.dumps(payload),
                properties=pika.BasicProperties(
                    delivery_mode=2,  # Persistent
                    content_type="application/json",
                ),
            )
            logger.info(
                f"[rec_publisher] Published: {payload.get('congestion_level')} | "
                f"{payload.get('priority')} | trigger={payload.get('trigger')}"
            )
            return True
        except (pika.exceptions.AMQPError, pika.exceptions.StreamLostError) as e:
            logger.error(f"[rec_publisher] Publish failed: {e}")
            self._connection = None
            self._channel = None
            # Try once more immediately
            if self._connect():
                try:
                    self._channel.basic_publish(
                        exchange=EXCHANGE,
                        routing_key=ROUTING_KEY,
                        body=json.dumps(payload),
                        properties=pika.BasicProperties(
                            delivery_mode=2,
                            content_type="application/json",
                        ),
                    )
                    logger.info(
                        f"[rec_publisher] Published (retry): {payload.get('congestion_level')}"
                    )
                    return True
                except Exception as retry_error:
                    logger.error(f"[rec_publisher] Retry publish failed: {retry_error}")
                    return False
            return False
        except Exception as e:
            logger.error(f"[rec_publisher] Unexpected error: {e}")
            return False

    def close(self):
        try:
            if self._connection and not self._connection.is_closed:
                self._connection.close()
                logger.info("[rec_publisher] Connection closed")
        except Exception as e:
            logger.error(f"[rec_publisher] Error closing connection: {e}")
        finally:
            self._connection = None
            self._channel = None

    def __enter__(self):
        self._connect()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.close()


# Singleton instance for easy import
_default_publisher = None


def get_publisher() -> RecommendationPublisher:
    global _default_publisher
    if _default_publisher is None:
        _default_publisher = RecommendationPublisher()
        _default_publisher._connect()
    return _default_publisher


def publish_recommendation(payload: dict) -> bool:
    """Convenience function to publish recommendation"""
    publisher = get_publisher()
    return publisher.publish(payload)