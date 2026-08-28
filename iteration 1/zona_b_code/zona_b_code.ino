#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>

#define SS_PIN  5
#define RST_PIN 4
#define READER_ID  "ESP32_ZONE_B"
#define ZONE_LABEL "Zone B - Storage Room"

const char* ssid       = "ACT-ai_102695965210";
const char* password   = "70014954";
const char* serverName = "http://192.168.0.106/rfid_inventory/verify.php";

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n╔══════════════════════════════════════╗");
  Serial.println("║  RFID SMART INVENTORY — ZONE B       ║");
  Serial.println("╚══════════════════════════════════════╝\n");
  SPI.begin();
  mfrc522.PCD_Init();
  Serial.println("[1/2] RFID Ready (SS=GPIO5, RST=GPIO4)");
  Serial.print("[2/2] Connecting to WiFi: "); Serial.println(ssid);
  WiFi.begin(ssid, password);
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 30) { delay(500); Serial.print("."); tries++; }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi Connected! IP: " + WiFi.localIP().toString());
    Serial.println("Server: " + String(serverName));
  } else { Serial.println("WiFi FAILED — check SSID/password"); }
  Serial.println("\nREADY — Waiting for scan...\n");
}

void loop() {
  if (!mfrc522.PICC_IsNewCardPresent()) return;
  if (!mfrc522.PICC_ReadCardSerial())   return;
  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  Serial.println("TAG: " + uid + "  Reader: " + READER_ID);
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverName);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int code = http.POST("uid=" + uid + "&reader_id=" + String(READER_ID));
    if (code > 0) {
      String resp = http.getString();
      Serial.println("Server: " + resp);
      int s1 = resp.indexOf('|');
      String status = resp.substring(0, s1);
      if (status == "FOUND") {
        int s2=resp.indexOf('|',s1+1), s3=resp.indexOf('|',s2+1);
        Serial.println("ITEM: " + resp.substring(s1+1,s2) + " | " + resp.substring(s2+1,s3) + " @ " + resp.substring(s3+1));
      } else { Serial.println("UNKNOWN TAG — register in admin panel"); }
    } else { Serial.println("Server error: " + String(code)); }
    http.end();
  } else { Serial.println("WiFi lost, reconnecting..."); WiFi.begin(ssid, password); delay(3000); }
  mfrc522.PICC_HaltA();
  delay(1500);
}