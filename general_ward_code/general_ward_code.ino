#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN   5
#define RST_PIN  27
#define SCK_PIN  18
#define MISO_PIN 19
#define MOSI_PIN 23

const char* WIFI_SSID = "sandy";
const char* WIFI_PASS = "123456789";

// CHANGE ONLY THESE 4 LINES
const char* SERVER_IP = "10.138.135.38";
const char* PROJECT_DIR = "meditrack_compact";   // change if folder name is different
const char* READER_ID = "ESP32ANITA";
const char* API_KEY   = "KEYANITA";

MFRC522 rfid(SS_PIN, RST_PIN);

unsigned long lastHeartbeat = 0;
const unsigned long HEARTBEAT_INTERVAL = 10000;
const unsigned long DEBOUNCE_MS = 2000;
String lastUid = "";
unsigned long lastScanTime = 0;

String baseUrl() {
  String b = "http://";
  b += SERVER_IP;
  if (String(PROJECT_DIR).length() > 0) {
    b += "/";
    b += PROJECT_DIR;
  }
  return b;
}

String uidToString(MFRC522::Uid* uid) {
  String s = "";
  for (byte i = 0; i < uid->size; i++) {
    if (uid->uidByte[i] < 0x10) s += "0";
    s += String(uid->uidByte[i], HEX);
  }
  s.toUpperCase();
  return s;
}

String urlEncode(String str) {
  String encoded = "";
  char c;
  char code0;
  char code1;
  for (int i = 0; i < str.length(); i++) {
    c = str.charAt(i);
    if (isalnum(c) || c == '-' || c == '_' || c == '.' || c == '~') {
      encoded += c;
    } else {
      code1 = (c & 0xF) + '0';
      if ((c & 0xF) > 9) code1 = (c & 0xF) - 10 + 'A';
      c = (c >> 4) & 0xF;
      code0 = c + '0';
      if (c > 9) code0 = c - 10 + 'A';
      encoded += '%';
      encoded += code0;
      encoded += code1;
    }
  }
  return encoded;
}

String httpGet(String url) {
  HTTPClient http;
  Serial.println("[HTTP] GET " + url);

  http.begin(url);
  http.setTimeout(5000);
  int code = http.GET();

  String body;
  if (code > 0) {
    body = http.getString();
    Serial.println("[HTTP] Code: " + String(code));
  } else {
    body = "HTTP_ERR:" + String(code);
    Serial.println("[HTTP] Failed: " + String(code));
  }

  http.end();
  return body;
}

void sendHeartbeat() {
  String url = baseUrl()
             + "/heartbeat.php?readerid=" + urlEncode(READER_ID)
             + "&apikey=" + urlEncode(API_KEY);

  String resp = httpGet(url);
  Serial.println("[HB] " + resp);
}

void scanSingleTag(String uid) {
  String url = baseUrl()
             + "/scan.php?mode=single"
             + "&readerid=" + urlEncode(READER_ID)
             + "&apikey=" + urlEncode(API_KEY)
             + "&uid=" + urlEncode(uid);

  String resp = httpGet(url);
  Serial.println("[SCAN] " + uid + " -> " + resp);
}

void reconnectWifi() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Reconnecting...");
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    unsigned long t = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - t < 8000) {
      delay(500);
      Serial.print(".");
    }
    Serial.println(WiFi.status() == WL_CONNECTED ? "\n[WiFi] Connected" : "\n[WiFi] Failed");
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n=== MediTrack ESP32 #1 - Ward Reader ===");
  Serial.println("[CFG] Base URL: " + baseUrl());
  Serial.println("[CFG] Reader ID: " + String(READER_ID));

  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("[WiFi] Connecting");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n[WiFi] Connected - IP: " + WiFi.localIP().toString());

  SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
  rfid.PCD_Init();
  delay(50);
  rfid.PCD_DumpVersionToSerial();
  Serial.println("[RFID] Reader ready");

  sendHeartbeat();
  lastHeartbeat = millis();
}

void loop() {
  reconnectWifi();

  if (millis() - lastHeartbeat >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }

  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    delay(80);
    return;
  }

  String uid = uidToString(&rfid.uid);
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  if (uid == lastUid && (millis() - lastScanTime) < DEBOUNCE_MS) {
    delay(80);
    return;
  }

  lastUid = uid;
  lastScanTime = millis();

  scanSingleTag(uid);
  delay(80);
}