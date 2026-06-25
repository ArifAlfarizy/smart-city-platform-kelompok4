#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// ── WiFi ───────────────────────────────────────────────────────────
const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

// ── MQTT ───────────────────────────────────────────────────────────
const char* MQTT_BROKER   = "mosquitto";   // Docker: nama service
const int   MQTT_PORT     = 1883;
const char* MQTT_USER     = "iot_device";
const char* MQTT_PASSWORD = "iot_secret";
const char* SENSOR_ID     = "ESP32-ENV-01";

// ── Pin ────────────────────────────────────────────────────────────
// Ultrasonic HC-SR04
const int PIN_TRIG = 5;
const int PIN_ECHO = 18;

// Rain Sensor (analog via potentiometer)
const int PIN_RAIN = 34;

// ── Konstanta ──────────────────────────────────────────────────────
const float MAX_RAINFALL_MM   = 100.0f; 
const float SENSOR_HEIGHT_CM  = 200.0f; 
const unsigned long INTERVAL  = 5000;   

// ── Globals ────────────────────────────────────────────────────────
WiFiClient   wifiClient;
PubSubClient mqtt(wifiClient);
unsigned long lastPublish = 0;

// ── Fungsi Sensor ──────────────────────────────────────────────────

float readWaterLevel() {
  // Kirim pulse TRIG
  digitalWrite(PIN_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(PIN_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_TRIG, LOW);

  // Baca durasi ECHO
  long duration = pulseIn(PIN_ECHO, HIGH, 30000); // timeout 30ms
  if (duration == 0) return 0.0f;

  // Hitung jarak (cm): duration * 0.034 / 2
  float distance_cm = (duration * 0.034f) / 2.0f;

  // Water level = tinggi sensor - jarak ke permukaan air
  float water_level = SENSOR_HEIGHT_CM - distance_cm;
  if (water_level < 0.0f) water_level = 0.0f;

  return water_level;
}

float readRainfall() {
  // Baca ADC (0–4095) → konversi ke mm/h (0–100)
  int raw = analogRead(PIN_RAIN);
  float rainfall = (raw / 4095.0f) * MAX_RAINFALL_MM;
  return rainfall;
}

// ── Setup WiFi ─────────────────────────────────────────────────────

void connectWiFi() {
  Serial.print("[WiFi] Connecting to ");
  Serial.println(WIFI_SSID);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int attempt = 0;
  while (WiFi.status() != WL_CONNECTED && attempt < 20) {
    delay(500);
    Serial.print(".");
    attempt++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n[WiFi] Failed to connect.");
  }
}

// ── Setup MQTT ─────────────────────────────────────────────────────

void connectMQTT() {
  mqtt.setServer(MQTT_BROKER, MQTT_PORT);

  while (!mqtt.connected()) {
    Serial.print("[MQTT] Connecting...");
    String clientId = String("esp32-env-") + String(SENSOR_ID);

    if (mqtt.connect(clientId.c_str(), MQTT_USER, MQTT_PASSWORD)) {
      Serial.println(" Connected!");
    } else {
      Serial.print(" Failed (rc=");
      Serial.print(mqtt.state());
      Serial.println("). Retry in 3s...");
      delay(3000);
    }
  }
}

// ── Publish Data ───────────────────────────────────────────────────

void publishData(float rainfall, float water_level) {
  // Build JSON payload
  StaticJsonDocument<200> doc;
  doc["sensor_id"]   = SENSOR_ID;
  doc["rainfall"]    = roundf(rainfall * 100) / 100.0f;   // 2 desimal
  doc["water_level"] = roundf(water_level * 100) / 100.0f;

  char payload[200];
  serializeJson(doc, payload);

  // Build MQTT topic: city/<SENSOR_ID>/environment
  String topic = String("city/") + String(SENSOR_ID) + "/environment";

  bool ok = mqtt.publish(topic.c_str(), payload, true); // retained

  Serial.print("[MQTT] ");
  Serial.print(ok ? "OK" : "FAIL");
  Serial.print(" → ");
  Serial.print(topic);
  Serial.print(" | ");
  Serial.println(payload);
}

// ── Setup & Loop ───────────────────────────────────────────────────

void setup() {
  Serial.begin(115200);
  delay(100);

  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);

  Serial.println("=== Smart City Environment Sensor ===");
  Serial.println("Sensors: HC-SR04 (water level) + Rain Sensor");

  connectWiFi();
}

void loop() {
  // Pastikan WiFi tetap terhubung
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  // Pastikan MQTT tetap terhubung
  if (!mqtt.connected()) {
    connectMQTT();
  }
  mqtt.loop();

  // Publish setiap INTERVAL ms
  unsigned long now = millis();
  if (now - lastPublish >= INTERVAL) {
    lastPublish = now;

    float water_level = readWaterLevel();
    float rainfall    = readRainfall();

    Serial.printf("[SENSOR] water_level=%.2f cm | rainfall=%.2f mm/h\n",
                  water_level, rainfall);

    publishData(rainfall, water_level);
  }
}