// ================================================================
// SmartRF Inventory System — RFID Reader Node
// Zone A — Rack A1 (Electronics)
// Reader ID: ESP32_A1
// ================================================================
// HARDWARE WIRING (same pins for ALL readers):
//   MFRC522 VCC  → 3.3V     (NOT 5V)
//   MFRC522 GND  → GND
//   MFRC522 RST  → GPIO 4
//   MFRC522 SDA/SS→ GPIO 5
//   MFRC522 SCK  → GPIO 18   (ESP32 SPI default)
//   MFRC522 MISO → GPIO 19   (ESP32 SPI default)
//   MFRC522 MOSI → GPIO 23   (ESP32 SPI default)
// ================================================================
// HOW TO COPY THIS FOR OTHER ZONES:
//   Only change lines marked with  ← CHANGE THIS
//   Everything else (pins, logic, WiFi) stays identical.
//   Specifically change:
//     READER_ID      — must match locations.reader_id in database
//     ZONE_LABEL     — human-readable label for Serial Monitor
//     HEARTBEAT_UID  — UNIQUE per reader (prevents heartbeats
//                      from one zone being attributed to another)
// ================================================================

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ── PIN CONFIG (DO NOT CHANGE — same for all readers) ──────────
#define SS_PIN   5
#define RST_PIN  4

// ── READER IDENTITY  ← CHANGE THESE FOR EACH ZONE ─────────────
#define READER_ID       "ESP32_A1"              // ← CHANGE THIS
#define ZONE_LABEL      "Zone A — Rack A1 (Electronics)"  // ← CHANGE THIS
// Heartbeat UID MUST be unique per reader so the server knows
// which specific reader sent the heartbeat. Format: HB-<READER_ID>
#define HEARTBEAT_UID   "HB-ESP32A1"            // ← CHANGE THIS  (e.g. HB-ESP32A2 for next reader)

// ── NETWORK CONFIG ─────────────────────────────────────────────
const char* WIFI_SSID      = "ACT-ai_102695965210";
const char* WIFI_PASSWORD  = "70014954";
const char* SERVER_SCAN    = "http://192.168.0.106/rfid_inventory/verify.php";
const char* SERVER_HB      = "http://192.168.0.106/rfid_inventory/heartbeat.php";

// ── TIMING ─────────────────────────────────────────────────────
#define SCAN_COOLDOWN_MS     1500   // Ignore same tag re-read within this window
#define HEARTBEAT_INTERVAL_S   60   // Send heartbeat every N seconds
#define HTTP_TIMEOUT_MS       5000
#define WIFI_MAX_TRIES          30

// ── OBJECTS & STATE ────────────────────────────────────────────
MFRC522 rfid(SS_PIN, RST_PIN);
String        lastUID       = "";
unsigned long lastScanMs    = 0;
unsigned long lastHeartbeatMs = 0;
bool          wifiOk        = false;

// ================================================================
void printBanner() {
  Serial.println();
  Serial.println(F("╔══════════════════════════════════════════════════╗"));
  Serial.println(F("║   SmartRF INVENTORY — RFID READER NODE           ║"));
  Serial.print  (F("║   Reader : "));
  Serial.print  (READER_ID);
  Serial.println(F("                           ║"));
  Serial.print  (F("║   Zone   : "));
  Serial.print  (ZONE_LABEL);
  Serial.println();
  Serial.println(F("╚══════════════════════════════════════════════════╝"));
  Serial.println();
}

// ── WiFi ─────────────────────────────────────────────────────
bool connectWiFi() {
  Serial.print(F("[WiFi] Connecting to "));
  Serial.println(WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < WIFI_MAX_TRIES) {
    delay(500); Serial.print('.'); tries++;
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WiFi] Connected — IP: "));
    Serial.println(WiFi.localIP());
    return true;
  }
  Serial.println(F("[WiFi] FAILED — check SSID/password"));
  return false;
}

bool ensureWiFi() {
  if (WiFi.status() == WL_CONNECTED) return true;
  Serial.println(F("[WiFi] Lost — reconnecting..."));
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  delay(3000);
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WiFi] Reconnected — IP: "));
    Serial.println(WiFi.localIP());
    return true;
  }
  Serial.println(F("[WiFi] Reconnect failed"));
  return false;
}

// ── Read UID from card ────────────────────────────────────────
String readUID() {
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += '0';
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

// ── Parse and print server response ──────────────────────────
void handleResponse(const String& resp, const String& uid) {
  int s1 = resp.indexOf('|');
  if (s1 == -1) { Serial.println("[!] Malformed response: " + resp); return; }
  String status = resp.substring(0, s1);
  String rest   = resp.substring(s1 + 1);

  Serial.println(F("  ┌───────────────────────────────────"));
  if (status == "FOUND") {
    int s2 = rest.indexOf('|');
    Serial.println("  │  ✓  FOUND    : " + rest.substring(0, s2));
    Serial.println("  │     STATUS   : " + rest.substring(s2 + 1));
  } else if (status == "TRANSFER") {
    Serial.println("  │  ↔  TRANSFER : " + rest);
  } else if (status == "COUNTED") {
    Serial.println("  │  #  COUNTED  : " + rest + " (cycle count active)");
  } else if (status == "EXPIRED") {
    Serial.println("  │  ✗  EXPIRED  : " + rest);
    Serial.println("  │     >>>  Notify supervisor immediately  <<<");
  } else if (status == "EXPIRING") {
    Serial.println("  │  !  EXPIRING : " + rest);
  } else if (status == "UNKNOWN") {
    Serial.println("  │  ?  UNKNOWN  : " + uid + " — register in admin panel");
  } else if (status == "IGNORED") {
    Serial.println("  │  ~  SKIPPED  : Conflict suppressed (multi-reader debounce)");
  } else if (status == "ERROR") {
    Serial.println("  │  ✗  ERROR    : " + rest);
  } else {
    Serial.println("  │  ?  RAW      : " + resp);
  }
  Serial.println(F("  └───────────────────────────────────"));
}

// ── POST a scan to the server ─────────────────────────────────
void sendScan(const String& uid) {
  if (!ensureWiFi()) { Serial.println(F("[HTTP] Skipping — no WiFi")); return; }

  HTTPClient http;
  http.begin(SERVER_SCAN);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(HTTP_TIMEOUT_MS);

  String payload = "uid=" + uid + "&reader_id=" + String(READER_ID);
  int code = http.POST(payload);

  if (code > 0) {
    Serial.print(F("[HTTP] "));
    Serial.print(code);
    Serial.print(F(" — "));
    handleResponse(http.getString(), uid);
  } else {
    Serial.print(F("[HTTP] Error: "));
    Serial.println(http.errorToString(code));
  }
  http.end();
}

// ── Send heartbeat  ────────────────────────────────────────────
// The heartbeat is POSTed to heartbeat.php with reader_id.
// HEARTBEAT_UID is only used for Serial logging here; the server
// identifies the reader purely by reader_id.
// Each zone MUST have a unique READER_ID so heartbeats are
// attributed to the correct location in the database.
void sendHeartbeat() {
  if (!ensureWiFi()) return;

  HTTPClient http;
  http.begin(SERVER_HB);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(HTTP_TIMEOUT_MS);

  String payload = "reader_id=" + String(READER_ID);
  int code = http.POST(payload);
  Serial.print(F("[HB]  Heartbeat sent — reader: "));
  Serial.print(READER_ID);
  Serial.print(F("  uid-token: "));
  Serial.print(HEARTBEAT_UID);
  Serial.print(F("  HTTP: "));
  Serial.println(code > 0 ? String(code) + " OK" : "Error " + String(code));
  http.end();
}

// ================================================================
void setup() {
  Serial.begin(115200);
  delay(500);
  printBanner();

  // Init SPI + RFID
  SPI.begin();
  rfid.PCD_Init();
  delay(50);

  // Firmware version check
  byte ver = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  Serial.print(F("[RFID] Firmware: 0x"));
  Serial.println(ver, HEX);
  if (ver == 0x00 || ver == 0xFF) {
    Serial.println(F("[RFID] WARNING: RC522 not detected — check wiring!"));
    Serial.println(F("       SPI pins: SCK=18  MISO=19  MOSI=23  SS=5  RST=4"));
  } else {
    Serial.println(F("[RFID] RC522 ready"));
  }

  // WiFi
  wifiOk = connectWiFi();

  // First heartbeat immediately on boot
  if (wifiOk) sendHeartbeat();
  lastHeartbeatMs = millis();

  Serial.println();
  Serial.println(F("══════════════════════════════════════════════════"));
  Serial.print  (F("[READY]  Reader   : ")); Serial.println(READER_ID);
  Serial.print  (F("[READY]  Zone     : ")); Serial.println(ZONE_LABEL);
  Serial.print  (F("[READY]  HB Token : ")); Serial.println(HEARTBEAT_UID);
  Serial.print  (F("[READY]  Server   : ")); Serial.println(SERVER_SCAN);
  Serial.print  (F("[READY]  HB every : ")); Serial.print(HEARTBEAT_INTERVAL_S); Serial.println("s");
  Serial.println(F("══════════════════════════════════════════════════"));
  Serial.println(F("[READY]  Waiting for RFID tag...\n"));
}

// ================================================================
void loop() {
  unsigned long now = millis();

  // ── Periodic heartbeat ──────────────────────────────────────
  if (now - lastHeartbeatMs >= (unsigned long)(HEARTBEAT_INTERVAL_S * 1000UL)) {
    sendHeartbeat();
    lastHeartbeatMs = now;
  }

  // ── RFID scan ───────────────────────────────────────────────
  if (!rfid.PICC_IsNewCardPresent()) return;
  if (!rfid.PICC_ReadCardSerial())   return;

  String uid = readUID();

  // Debounce — ignore same card within cooldown window
  if (uid == lastUID && (now - lastScanMs) < (unsigned long)SCAN_COOLDOWN_MS) {
    rfid.PICC_HaltA();
    return;
  }
  lastUID   = uid;
  lastScanMs = now;

  Serial.println();
  Serial.print(F("[SCAN] UID: ")); Serial.print(uid);
  Serial.print(F("   Reader: ")); Serial.print(READER_ID);
  Serial.print(F("   Uptime: ")); Serial.print(now / 1000); Serial.println("s");

  sendScan(uid);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  delay(SCAN_COOLDOWN_MS);
}
