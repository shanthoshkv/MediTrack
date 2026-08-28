#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>

// RFID Pins
#define SS_PIN 5
#define RST_PIN 4

// RFID Instance
MFRC522 mfrc522(SS_PIN, RST_PIN);

// WiFi Credentials - UPDATE THESE
const char* ssid = "sandy";
const char* password = "123456789";

// Server URL - UPDATE WITH YOUR PC IP
const char* serverName = "http://192.168.0.102/rfid_system/verify.php";

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n\n");
  Serial.println("╔════════════════════════════════════╗");
  Serial.println("║   RFID ENTRY/EXIT TRACKING SYSTEM  ║");
  Serial.println("╚════════════════════════════════════╝");
  Serial.println();
  
  // Initialize RFID
  Serial.println("[1/2] Initializing RFID Reader...");
  SPI.begin();
  mfrc522.PCD_Init();
  Serial.println("      ✓ RFID Ready");
  Serial.println();
  
  // Connect to WiFi
  Serial.println("[2/2] Connecting to WiFi...");
  Serial.print("      SSID: ");
  Serial.println(ssid);
  
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("      ✓ WiFi Connected!");
    Serial.print("      ESP32 IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("      Server: ");
    Serial.println(serverName);
  } else {
    Serial.println("      ✗ WiFi Connection Failed!");
    Serial.println("      System will not work properly!");
  }
  
  Serial.println();
  Serial.println("[2/2] System Initialization Complete!");
  Serial.println();
  Serial.println("════════════════════════════════════");
  Serial.println("         SYSTEM READY!              ");
  Serial.println("════════════════════════════════════");
  Serial.println();
  Serial.println("Waiting for RFID card scan...");
  Serial.println();
}

void loop() {
  // Look for RFID card
  if (!mfrc522.PICC_IsNewCardPresent()) {
    return;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    return;
  }
  
  // Read UID
  String uidString = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) {
      uidString += "0";
    }
    uidString += String(mfrc522.uid.uidByte[i], HEX);
  }
  uidString.toUpperCase();
  
  // Display scan information
  Serial.println("╔════════════════════════════════════╗");
  Serial.println("║         CARD DETECTED!             ║");
  Serial.println("╚════════════════════════════════════╝");
  Serial.print("  🆔 UID: ");
  Serial.println(uidString);
  Serial.println("  ⏳ Verifying with server...");
  Serial.println();
  
  // Send to server
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverName);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    String httpRequestData = "uid=" + uidString;
    int httpResponseCode = http.POST(httpRequestData);
    
    Serial.print("  📡 Server Response Code: ");
    Serial.println(httpResponseCode);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.print("  📦 Response Data: ");
      Serial.println(response);
      Serial.println();
      
      // Parse response: GRANTED|Name|Type|VehicleType|VehicleNumber
      int sep1 = response.indexOf('|');
      
      if (sep1 > 0) {
        String status = response.substring(0, sep1);
        
        if (status == "GRANTED") {
          // Parse full response
          int sep2 = response.indexOf('|', sep1 + 1);
          int sep3 = response.indexOf('|', sep2 + 1);
          int sep4 = response.indexOf('|', sep3 + 1);
          
          String name = response.substring(sep1 + 1, sep2);
          String accessType = response.substring(sep2 + 1, sep3);
          String vehicleType = response.substring(sep3 + 1, sep4);
          String vehicleNumber = response.substring(sep4 + 1);
          
          name.trim();
          accessType.trim();
          vehicleType.trim();
          vehicleNumber.trim();
          
          // Display success
          Serial.println("╔════════════════════════════════════╗");
          if (accessType == "entry") {
            Serial.println("║        ✅ ENTRY ALLOWED!           ║");
          } else {
            Serial.println("║        ✅ EXIT RECORDED!           ║");
          }
          Serial.println("╚════════════════════════════════════╝");
          Serial.print("  👤 Name: ");
          Serial.println(name);
          Serial.print("  🚦 Access Type: ");
          accessType.toUpperCase();
          Serial.println(accessType);
          Serial.print("  🚗 Vehicle: ");
          vehicleType.toUpperCase();
          Serial.print(vehicleType);
          Serial.print(" - ");
          Serial.println(vehicleNumber);
          Serial.println();
          
          delay(3000);
          
        } else {
          // ACCESS DENIED
          Serial.println("╔════════════════════════════════════╗");
          Serial.println("║        ❌ ACCESS DENIED!           ║");
          Serial.println("╚════════════════════════════════════╝");
          Serial.println("  ⚠️  Unknown Card - Not Registered");
          Serial.println();
          
          delay(3000);
        }
      }
    } else {
      // Server error
      Serial.println("╔════════════════════════════════════╗");
      Serial.println("║        ⚠️  SERVER ERROR!           ║");
      Serial.println("╚════════════════════════════════════╝");
      Serial.print("  Error Code: ");
      Serial.println(httpResponseCode);
      Serial.println("  Check if XAMPP is running!");
      Serial.println();
    }
    
    http.end();
    
  } else {
    // WiFi disconnected
    Serial.println("╔════════════════════════════════════╗");
    Serial.println("║        ⚠️  WiFi ERROR!             ║");
    Serial.println("╚════════════════════════════════════╝");
    Serial.println("  WiFi connection lost!");
    Serial.println("  Attempting to reconnect...");
    Serial.println();
    
    // Try to reconnect
    WiFi.begin(ssid, password);
    delay(3000);
  }
  
  Serial.println("────────────────────────────────────");
  Serial.println("Waiting for next scan...");
  Serial.println();
  
  // Halt RFID
  mfrc522.PICC_HaltA();
  delay(1000);
}
