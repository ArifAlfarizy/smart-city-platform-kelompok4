import pika, json, time

conn = pika.BlockingConnection(
    pika.ConnectionParameters('localhost')
)
ch = conn.channel()
ch.exchange_declare(exchange='city.events', exchange_type='topic', durable=True)
ch.queue_declare(queue='traffic.sensor.received', durable=True)
ch.queue_bind(
    queue='traffic.sensor.received',
    exchange='city.events',
    routing_key='traffic.sensor.received'
)

# Simulasikan 3 event sensor dari zona berbeda
events = [
    {"zone": "A", "vehicle_count": 95, "hour": 8,  "day_of_week": 1},
    {"zone": "B", "vehicle_count": 30, "hour": 14, "day_of_week": 3},
    {"zone": "C", "vehicle_count": 65, "hour": 17, "day_of_week": 5},
]

for event in events:
    ch.basic_publish(
        exchange='city.events',
        routing_key='traffic.sensor.received',
        body=json.dumps(event),
        properties=pika.BasicProperties(delivery_mode=2)
    )
    print(f"Published traffic event: {event}")
    time.sleep(1)

conn.close()
print("\nS1 test selesai — cek log FastAPI untuk hasil prediksi")