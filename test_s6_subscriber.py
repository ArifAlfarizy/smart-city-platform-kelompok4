import pika, json

def on_message(ch, method, properties, body):
    event = json.loads(body)
    print(f"\n ANOMALY ALERT RECEIVED!")
    print(f"   Zone        : {event.get('zone')}")
    print(f"   Type        : {event.get('anomaly_type')}")
    print(f"   Message     : {event.get('message')}")
    print(f"   Severity    : {event.get('severity')}")
    print(f"   Timestamp   : {event.get('timestamp')}")
    ch.basic_ack(delivery_tag=method.delivery_tag)

conn = pika.BlockingConnection(pika.ConnectionParameters('localhost'))
ch   = conn.channel()
ch.exchange_declare(exchange='city.events', exchange_type='topic', durable=True)
ch.queue_declare(queue='ml.anomaly.alerts', durable=True)
ch.queue_bind(
    queue='ml.anomaly.alerts',
    exchange='city.events',
    routing_key='ml.anomaly.detected'
)
ch.basic_consume(queue='ml.anomaly.alerts', on_message_callback=on_message)
print("Waiting for anomaly alerts... (Ctrl+C to stop)")
ch.start_consuming()