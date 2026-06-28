import pika, json, time

EXCHANGE = "city.events"

def get_channel():
    conn = pika.BlockingConnection(
        pika.ConnectionParameters(
            host='localhost',
            port=5640,
            credentials=pika.PlainCredentials('guest', 'guest')
        )
    )
    ch = conn.channel()
    ch.exchange_declare(exchange=EXCHANGE, exchange_type="topic", durable=True)
    return conn, ch

def declare_queue(ch, queue, routing_key):
    ch.queue_declare(queue=queue, durable=True)
    ch.queue_bind(queue=queue, exchange=EXCHANGE, routing_key=routing_key)

def test_traffic_event():
    conn, ch = get_channel()
    declare_queue(ch, "traffic.updated", "traffic.updated")
    
    payload = {
        "road_name": "MT Haryono",
        "vehicle_count": 1200,
        "average_speed": 18.5,
        "observation_time": "2026-06-25T08:30:00"
    }
    ch.basic_publish(
        exchange=EXCHANGE,
        routing_key="traffic.updated",
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2)
    )
    print(f"\n[TEST] traffic.updated published:")
    print(f"  vehicle_count: {payload['vehicle_count']}")
    print(f"  average_speed: {payload['average_speed']}")
    conn.close()

def test_environment_event():
    conn, ch = get_channel()
    declare_queue(ch, "environment.updated", "environment.updated")
    
    payload = {
        "rainfall": 35.0,
        "water_level": 420.0,
        "timestamp": "2026-06-25T08:30:00"
    }
    ch.basic_publish(
        exchange=EXCHANGE,
        routing_key="environment.updated",
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2)
    )
    print(f"\n[TEST] environment.updated published:")
    print(f"  rainfall: {payload['rainfall']} mm")
    print(f"  water_level: {payload['water_level']} cm")
    conn.close()

def test_incident_event():
    conn, ch = get_channel()
    declare_queue(ch, "incident.created", "incident.created")
    
    payload = {
        "incident_id": 42,
        "category": "accident",
        "road_name": "MT Haryono",
        "description": "Kecelakaan 2 kendaraan di Simpang Cawang"
    }
    ch.basic_publish(
        exchange=EXCHANGE,
        routing_key="incident.created",
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2)
    )
    print(f"\n[TEST] incident.created published:")
    print(f"  category: {payload['category']}")
    print(f"  description: {payload['description']}")
    conn.close()

def subscribe_recommendations():
    conn, ch = get_channel()
    
    # Fix: pake durable=True
    ch.queue_declare(queue="recommendations.test", durable=True, auto_delete=True)
    ch.queue_bind(
        queue="recommendations.test",
        exchange=EXCHANGE,
        routing_key="recommendation.generated"
    )
    
    def on_message(ch, method, props, body):
        rec = json.loads(body)
        print("\n" + "="*55)
        print("RECOMMENDATION RECEIVED")
        print("="*55)
        print(f" Trigger           : {rec.get('trigger')}")
        print(f" Road Name         : {rec.get('road_name', 'N/A')}")
        print(f" Congestion Level  : {rec.get('congestion_level')}")
        print(f" Environment Status: {rec.get('environment_status')}")
        print(f" Priority          : {rec.get('priority')}")
        print(f" Confidence        : {rec.get('confidence')}")
        print(f"\n Rekomendasi:")
        for i, r in enumerate(rec.get("recommendations", []), 1):
            print(f"   {i}. {r}")
        print("="*55)
        ch.basic_ack(delivery_tag=method.delivery_tag)
    
    ch.basic_consume(queue="recommendations.test", on_message_callback=on_message)
    print("\n[*] Waiting for recommendations... (Press CTRL+C to stop)\n")
    try:
        ch.start_consuming()
    except KeyboardInterrupt:
        print("\n[*] Stopping...")
        conn.close()

def test_all_events():
    print("\n" + "="*55)
    print("RUNNING ALL TESTS")
    print("="*55)
    test_traffic_event()
    time.sleep(1)
    test_environment_event()
    time.sleep(1)
    test_incident_event()
    print("\n" + "="*55)
    print("All events published! Check subscriber terminal.")
    print("="*55)

def test_anomaly_alert():
    conn, ch = get_channel()
    declare_queue(ch, "environment.updated", "environment.updated")
    
    # Payload BANJIR EKSTREM (melewati threshold 500)
    payload = {
        "rainfall": 99.0,
        "water_level": 999.9,
        "timestamp": "2026-06-29T08:00:00"
    }
    ch.basic_publish(
        exchange=EXCHANGE,
        routing_key="environment.updated",
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2)
    )
    print(f"\n[ANOMALI] environment.updated published:")
    print(f"   rainfall: {payload['rainfall']} mm (EKSTREM)")
    print(f"   water_level: {payload['water_level']} cm (BAHAYA)")
    conn.close()


if __name__ == "__main__":
    print("="*55)
    print("Smart Traffic Integration Test")
    print("="*55)
    print("\nPilih test:")
    print(" 1. Publish traffic event (traffic.updated)")
    print(" 2. Publish environment event (environment.updated)")
    print(" 3. Publish incident event (incident.created)")
    print(" 4. Subscribe recommendations (buka di terminal terpisah)")
    print(" 5. Run ALL tests (publish all events at once)")
    print(" 6. Trigger ANOMALY ALERT (S6 - Water Level 999.9 cm)") 
    print(" 0. Exit")
    
    choice = input("\nMasukkan pilihan (0-5): ").strip()
    
    if choice == "1":
        test_traffic_event()
    elif choice == "2":
        test_environment_event()
    elif choice == "3":
        test_incident_event()
    elif choice == "4":
        subscribe_recommendations()
    elif choice == "5":
        test_all_events()
    elif choice == "6":                                
        test_anomaly_alert()
    elif choice == "0":
        print("Exit.")
    else:
        print("Pilihan tidak valid")