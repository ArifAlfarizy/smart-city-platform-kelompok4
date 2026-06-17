#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include <IRremote.hpp>

const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

const char* MQTT_BROKER   = "103.147.92.134";
const int   MQTT_PORT     = 1883;
const char* MQTT_USER     = "iot_device";
const char* MQTT_PASS     = "iot_secret";
const char* MQTT_CLIENT   = "ESP32-ENV-ZONE-A";

const char* ZONE          = "A";
const char* SENSOR_ID     = "ESP32-A-01";

// ── Pin Definitions ──────────────────────────────────────────
#define DHT_PIN           15
#define DHT_TYPE          DHT22
#define AQI_PIN           34
#define FLOOD_PIN         35
#define RAIN_PIN          32
#define RAIN_INTENSITY_PIN 33
#define IR_RECV_PIN       19

const unsigned long PUBLISH_INTERVAL_MS = 30000;
const unsigned long VEHICLE_PUBLISH_INTERVAL_MS = 60000;

// ── Globals ──────────────────────────────────────────────────
DHT          dht(DHT_PIN, DHT_TYPE);
WiFiClient   wifiClient;
PubSubClient mqtt(wifiClient);

char         topicBuf[64];
unsigned long lastPublish   = 0;
unsigned long lastVehiclePublish = 0;
volatile int  vehicleCount  = 0;   // counter kendaraan lewat
unsigned long lastIRTime    = 0;   // debounce IR

void setup() {
    Serial.begin(115200);
    dht.begin();

    // IR Receiver init
    IrReceiver.begin(IR_RECV_PIN, DISABLE_LED_FEEDBACK);
    Serial.println("[IR] Receiver aktif di pin " + String(IR_RECV_PIN));

    Serial.printf("[WiFi] Connecting to %s...\n", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.printf("\n[WiFi] Connected. IP: %s\n", WiFi.localIP().toString().c_str());

    mqtt.setServer(MQTT_BROKER, MQTT_PORT);
    mqtt.setCallback(onMqttMessage);
    mqtt.setBufferSize(512);

    snprintf(topicBuf, sizeof(topicBuf), "city/%s/air", ZONE);
}

void loop() {
    if (!mqtt.connected()) {
        reconnectMQTT();
    }
    mqtt.loop();

    // ── Cek IR Sensor kendaraan ──────────────────────────────
    if (IrReceiver.decode()) {
        unsigned long now = millis();
        if (now - lastIRTime > 500) {
            vehicleCount++;
            lastIRTime = now;
            Serial.printf("[IR] Kendaraan terdeteksi! Total: %d | Code: 0x%lX\n",
                          vehicleCount,
                          IrReceiver.decodedIRData.decodedRawData);
        }
        IrReceiver.resume();
    }

    unsigned long now = millis();

    // ── Publish sensor lingkungan tiap 30 detik ──────────────
    if (now - lastPublish >= PUBLISH_INTERVAL_MS) {
        lastPublish = now;
        publishSensorData();
    }

    // ── Publish kendaraan tiap 1 menit ───────────────────────
    if (now - lastVehiclePublish >= VEHICLE_PUBLISH_INTERVAL_MS) {
        lastVehiclePublish = now;
        publishVehicleData();
    }
}

void publishVehicleData() {
    StaticJsonDocument<128> doc;
    doc["sensor_id"]      = SENSOR_ID;
    doc["zone"]           = ZONE;
    doc["timestamp"]      = millis();
    doc["vehicle_count"]  = vehicleCount;
    doc["interval_sec"]   = VEHICLE_PUBLISH_INTERVAL_MS / 1000;  // 60

    // Estimasi kepadatan lalu lintas
    const char* trafficStatus;
    if      (vehicleCount < 5)   trafficStatus = "Lancar";
    else if (vehicleCount < 15)  trafficStatus = "Sedang";
    else if (vehicleCount < 30)  trafficStatus = "Padat";
    else                         trafficStatus = "Macet";

    doc["traffic_status"] = trafficStatus;

    vehicleCount = 0;  // reset counter setelah publish

    char payload[128];
    serializeJson(doc, payload);

    char vehicleTopic[64];
    snprintf(vehicleTopic, sizeof(vehicleTopic), "city/%s/vehicle", ZONE);

    bool ok = mqtt.publish(vehicleTopic, payload, true);
    Serial.printf("[MQTT] %s → %s\n%s\n",
                  ok ? "OK" : "FAIL",
                  vehicleTopic,
                  payload);
}

void publishSensorData() {
    // ── DHT22 ────────────────────────────────────────────────
    float temperature = dht.readTemperature();
    float humidity    = dht.readHumidity();

    if (isnan(temperature) || isnan(humidity)) {
        Serial.println("[DHT22] Pembacaan gagal, coba lagi...");
        return;
    }

    // ── Analog Sensors ───────────────────────────────────────
    int rawAqi           = analogRead(AQI_PIN);
    int rawFlood         = analogRead(FLOOD_PIN);
    int rawRain          = analogRead(RAIN_PIN);
    int rawRainIntensity = analogRead(RAIN_INTENSITY_PIN);

    float aqi           = mapFloat(rawAqi,           0, 4095, 0.0,   300.0);
    float floodLevel    = mapFloat(rawFlood,          0, 4095, 0.0,   100.0);
    float rainLevel     = mapFloat(rawRain,           0, 4095, 0.0,   100.0);  // 0-100%
    float rainIntensity = mapFloat(rawRainIntensity,  0, 4095, 0.0,   100.0);  // 0-100 mm/h

    float pm25 = aqi * 0.25f;
    float pm10 = aqi * 0.40f;

    // ── Klasifikasi hujan ────────────────────────────────────
    const char* rainStatus;
    if      (rainIntensity < 5.0)   rainStatus = "Tidak Hujan";
    else if (rainIntensity < 20.0)  rainStatus = "Hujan Ringan";
    else if (rainIntensity < 50.0)  rainStatus = "Hujan Sedang";
    else                            rainStatus = "Hujan Lebat";

    // ── Klasifikasi AQI ──────────────────────────────────────
    const char* aqiStatus;
    if      (aqi < 50)   aqiStatus = "Baik";
    else if (aqi < 100)  aqiStatus = "Sedang";
    else if (aqi < 150)  aqiStatus = "Tidak Sehat (Sensitif)";
    else if (aqi < 200)  aqiStatus = "Tidak Sehat";
    else if (aqi < 300)  aqiStatus = "Sangat Tidak Sehat";
    else                 aqiStatus = "Berbahaya";

    // ── Klasifikasi Banjir ───────────────────────────────────
    const char* floodStatus;
    if      (floodLevel < 20)  floodStatus = "Aman";
    else if (floodLevel < 50)  floodStatus = "Waspada";
    else if (floodLevel < 80)  floodStatus = "Siaga";
    else                       floodStatus = "Bahaya";

    // ── Build JSON ───────────────────────────────────────────
    StaticJsonDocument<512> doc;
    doc["sensor_id"]       = SENSOR_ID;
    doc["zone"]            = ZONE;
    doc["timestamp"]       = millis();

    // Udara
    doc["aqi"]             = roundf(aqi * 10) / 10.0f;
    doc["aqi_status"]      = aqiStatus;
    doc["pm25"]            = roundf(pm25 * 10) / 10.0f;
    doc["pm10"]            = roundf(pm10 * 10) / 10.0f;

    // Cuaca
    doc["temperature"]     = roundf(temperature * 10) / 10.0f;
    doc["humidity"]        = roundf(humidity * 10) / 10.0f;

    // Hujan
    doc["rain_level"]      = roundf(rainLevel * 10) / 10.0f;
    doc["rain_intensity"]  = roundf(rainIntensity * 10) / 10.0f;
    doc["rain_status"]     = rainStatus;

    // Banjir
    doc["flood_level"]     = roundf(floodLevel * 10) / 10.0f;
    doc["flood_status"]    = floodStatus;

    // Kendaraan — reset counter setelah publish
    doc["vehicle_count"]   = vehicleCount;
    vehicleCount = 0;

    char payload[512];
    serializeJson(doc, payload);

    bool ok = mqtt.publish(topicBuf, payload, true);
    Serial.printf("[MQTT] %s → %s\n%s\n",
                  ok ? "OK" : "FAIL",
                  topicBuf,
                  payload);
}

// ── Helper ───────────────────────────────────────────────────
float mapFloat(int x, int inMin, int inMax, float outMin, float outMax) {
    return (float)(x - inMin) * (outMax - outMin) / (float)(inMax - inMin) + outMin;
}

void reconnectMQTT() {
    while (!mqtt.connected()) {
        Serial.print("[MQTT] Connecting...");
        if (mqtt.connect(MQTT_CLIENT, MQTT_USER, MQTT_PASS)) {
            Serial.println(" connected.");
            char cmdTopic[64];
            snprintf(cmdTopic, sizeof(cmdTopic), "city/%s/cmd", ZONE);
            mqtt.subscribe(cmdTopic);
        } else {
            Serial.printf(" failed (rc=%d). Retry in 5s\n", mqtt.state());
            delay(5000);
        }
    }
}

void onMqttMessage(char* topic, byte* message, unsigned int length) {
    Serial.printf("[MQTT CMD] Topic: %s | Message: ", topic);
    for (unsigned int i = 0; i < length; i++) Serial.print((char)message[i]);
    Serial.println();
}