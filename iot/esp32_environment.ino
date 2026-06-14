#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

const char* MQTT_BROKER   = "103.147.92.134";
const int   MQTT_PORT     = 1883;
const char* MQTT_USER     = "iot_device";
const char* MQTT_PASS     = "iot_secret";
const char* MQTT_CLIENT   = "ESP32-ENV-ZONE-A"; 

const char* ZONE          = "A";
const char* SENSOR_ID     = "ESP32-A-01";

#define DHT_PIN  15
#define DHT_TYPE DHT22
#define AQI_PIN  34
#define FLOOD_PIN 35

const unsigned long PUBLISH_INTERVAL_MS = 30000;

DHT       dht(DHT_PIN, DHT_TYPE);
WiFiClient   wifiClient;
PubSubClient mqtt(wifiClient);

char topicBuf[64];
unsigned long lastPublish = 0;

void setup() {
    Serial.begin(115200);
    dht.begin();

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

    unsigned long now = millis();
    if (now - lastPublish >= PUBLISH_INTERVAL_MS) {
        lastPublish = now;
        publishSensorData();
    }
}

void publishSensorData() {
    float temperature = dht.readTemperature();
    float humidity    = dht.readHumidity();

    if (isnan(temperature) || isnan(humidity)) {
        Serial.println("[DHT22] Pembacaan gagal, coba lagi...");
        return;
    }

    int rawAqi   = analogRead(AQI_PIN);
    int rawFlood = analogRead(FLOOD_PIN);

    float aqi        = mapFloat(rawAqi,   0, 4095, 0.0, 300.0);
    float floodLevel = mapFloat(rawFlood, 0, 4095, 0.0, 100.0);

    float pm25 = aqi * 0.25f;
    float pm10 = aqi * 0.40f;

    StaticJsonDocument<256> doc;
    doc["sensor_id"]   = SENSOR_ID;
    doc["zone"]        = ZONE;
    doc["aqi"]         = roundf(aqi * 10) / 10.0f;
    doc["temperature"] = roundf(temperature * 10) / 10.0f;
    doc["humidity"]    = roundf(humidity * 10) / 10.0f;
    doc["flood_level"] = roundf(floodLevel * 10) / 10.0f;
    doc["pm25"]        = roundf(pm25 * 10) / 10.0f;
    doc["pm10"]        = roundf(pm10 * 10) / 10.0f;
    doc["timestamp"]   = millis();

    char payload[256];
    serializeJson(doc, payload);

    bool ok = mqtt.publish(topicBuf, payload, true /* retain */);

    Serial.printf("[MQTT] %s → %s | %s\n",
                  ok ? "OK" : "FAIL",
                  topicBuf,
                  payload);
}

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
    Serial.printf("[MQTT CMD] Topic: %s, Message: ", topic);
    for (unsigned int i = 0; i < length; i++) Serial.print((char)message[i]);
    Serial.println();
}