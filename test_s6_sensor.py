import pika, json, time

conn = pika.BlockingConnection(pika.ConnectionParameters('localhost'))
ch   = conn.channel()
ch.exchange_declare(exchange='city.events', exchange_type='topic', durable=True)
ch.queue_declare(queue='traffic.sensor.received', durable=True)
ch.queue_bind(
    queue='traffic.sensor.received',
    exchange='city.events',
    routing_key='traffic.sensor.received'
)

extreme_event = {
    "zone": "A",
    "vehicle_count": 200,   # jauh di atas normal
    "hour": 8,
    "day_of_week": 1,
}

ch.basic_publish(
    exchange='city.events',
    routing_key='traffic.sensor.received',
    body=json.dumps(extreme_event),
    properties=pika.BasicProperties(delivery_mode=2)
)
print(f"Extreme sensor event published: {extreme_event}")
conn.close()