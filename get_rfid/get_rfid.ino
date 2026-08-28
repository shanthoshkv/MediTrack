// ============================================================
//  RFID UID READER — RC522 + ESP32
//  Prints UID to Serial Monitor when tag is scanned
//  SS_PIN  = GPIO 5
//  RST_PIN = GPIO 4
//  Open Serial Monitor at 115200 baud
// ============================================================

#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN  5    // SDA pin of RC522 → GPIO 5
#define RST_PIN 27    // RST pin of RC522 → GPIO 4

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  SPI.begin();             // Start SPI bus
  mfrc522.PCD_Init();      // Start RC522

  Serial.println("=================================");
  Serial.println("  RFID UID READER — Ready!");
  Serial.println("  Scan your tag now...");
  Serial.println("=================================\n");
}

void loop() {
  // Wait for a new card
  if (!mfrc522.PICC_IsNewCardPresent()) return;
  if (!mfrc522.PICC_ReadCardSerial())   return;

  // Build UID string
  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0"; // pad single digits
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  // Print results
  Serial.println("─────────────────────────────────");
  Serial.println("  TAG DETECTED!");
  Serial.print  ("  UID (HEX): ");
  Serial.println(uid);
  Serial.print  ("  Tag size : ");
  Serial.print  (mfrc522.uid.size);
  Serial.println(" bytes");
  Serial.println("─────────────────────────────────\n");

  // Stop reading so next scan is fresh
  mfrc522.PICC_HaltA();
  delay(1000);  // 1 second cooldown between scans
}