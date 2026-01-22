#pragma once
/*
 * This file is part of the Falcon Player (FPP) and is Copyright (C)
 * 2013-2025 by the Falcon Player Developers.
 *
 * The Falcon Player (FPP) is free software, and is covered under
 * multiple Open Source licenses.  Please see the included 'LICENSES'
 * file for descriptions of what files are covered by each license.
 *
 * This source file is covered under the GPL v2 as described in the
 * included LICENSE.GPL file.
 */

#include "ThreadedChannelOutput.h"
#include "PixelString.h"
#include "util/SPIUtils.h"
#include <vector>

// Protocol constants
#define RP2354B_MAGIC_0 0x5A
#define RP2354B_MAGIC_1 0xA5

// Commands
#define RP2354B_CMD_CONFIG 0x01
#define RP2354B_CMD_FRAME  0x02
#define RP2354B_CMD_TEST   0x03
#define RP2354B_CMD_RESET  0x04

// Flags
#define RP2354B_FLAG_COMPRESSED   0x01
#define RP2354B_FLAG_DOUBLE_BUF   0x02
#define RP2354B_FLAG_ADC_ENABLE   0x08  // Enable ADC monitoring (eFuse protection)

// Pixel types
#define RP2354B_TYPE_WS2811  0x00
#define RP2354B_TYPE_WS2812  0x01
#define RP2354B_TYPE_WS2813  0x02
#define RP2354B_TYPE_WS2815  0x03
#define RP2354B_TYPE_APA102  0x10
#define RP2354B_TYPE_SK6812  0x20

// Maximum ports supported by RP2354B
#define RP2354B_MAX_PORTS 24

// Multi-chip support
#define RP2354B_MAX_CHIPS_PER_BUS 4  // Recommended: 2, Maximum: 4
#define RP2354B_MAX_TOTAL_PORTS (RP2354B_MAX_PORTS * RP2354B_MAX_CHIPS_PER_BUS)

// Port status flags (from ADC monitoring)
#define RP2354B_PORT_STATUS_OK              0x00
#define RP2354B_PORT_STATUS_OVERCURRENT     0x01
#define RP2354B_PORT_STATUS_OVERVOLTAGE     0x02
#define RP2354B_PORT_STATUS_UNDERVOLTAGE    0x04
#define RP2354B_PORT_STATUS_SHORT           0x08
#define RP2354B_PORT_STATUS_OPEN            0x10
#define RP2354B_PORT_STATUS_DISABLED        0x80

/**
 * RP2354B Output - Drives pixels via RP2354B microcontroller over SPI
 * 
 * This output type allows FPP to drive WS281x and similar pixels through
 * an RP2354B microcontroller connected via SPI. The RP2354B handles the
 * timing-critical pixel protocol using PIO, allowing for higher pixel
 * counts and better performance than direct Pi GPIO driving.
 */
class RP2354BOutput : public ThreadedChannelOutput {
public:
    RP2354BOutput(unsigned int startChannel, unsigned int channelCount);
    virtual ~RP2354BOutput();

    virtual std::string GetOutputType() const override {
        return "RP2354B Pixel Driver";
    }

    virtual int Init(Json::Value config) override;
    virtual int Close(void) override;

    virtual void PrepData(unsigned char* channelData) override;
    virtual int RawSendData(unsigned char* channelData) override;

    virtual void DumpConfig(void) override;

    virtual void GetRequiredChannelRanges(const std::function<void(int, int)>& addRange) override;

    virtual void OverlayTestData(unsigned char* channelData, int cycleNum, float percentOfCycle, 
                                 int testType, const Json::Value& config) override;
    virtual bool SupportsTesting() const override { return true; }

private:
    // Configuration
    struct PortConfig {
        uint16_t pixelCount;
        uint8_t  pixelType;
        uint8_t  colorOrder;
        uint8_t  brightness;
        uint8_t  gpioPin;
        bool     enabled;
        
        PortConfig() : pixelCount(0), pixelType(RP2354B_TYPE_WS2812), 
                      colorOrder(0), brightness(255), gpioPin(0), enabled(false) {}
    };

    // Packet header structure (12 bytes for 24-port support)
    struct PacketHeader {
        uint8_t  magic[2];      // Sync bytes
        uint8_t  command;       // Command type
        uint8_t  flags;         // Feature flags
        uint16_t payloadLen;    // Payload length
        uint8_t  portMask[3];   // Active port bitmask (24 bits)
        uint8_t  reserved;      // Reserved
        uint8_t  sequence;      // Sequence number
        uint8_t  headerCRC;     // Header checksum
    } __attribute__((packed));

    // Configuration packet structure
    struct ConfigPacket {
        uint16_t pixelCount;
        uint8_t  pixelType;
        uint8_t  colorOrder;
        uint8_t  brightness;
        uint8_t  gpioPin;
        uint16_t reserved;
    } __attribute__((packed));

    // Frame response structure (28 bytes, received via SPI MISO)
    struct FrameResponse {
        uint16_t adcValues[8];     // ADC readings (0-4095) for 8 ports
        uint8_t  portStatus[8];    // Status flags per port
        uint32_t timestamp;        // Microsecond timestamp from RP2354B
        uint32_t crc32;            // CRC32 checksum
    } __attribute__((packed));

    // Private methods
    bool SendConfiguration();
    bool SendConfigurationToChip(int chipIndex);
    bool SendFrameData(unsigned char* channelData);
    bool SendFrameDataToChip(int chipIndex, unsigned char* channelData);
    uint8_t CalculateHeaderCRC(const PacketHeader& header);
    uint32_t CalculateCRC32(const uint8_t* data, size_t length);
    void BuildPacketHeader(PacketHeader& header, uint8_t command, 
                          uint16_t payloadLen, const uint8_t portMask[3]);
    
    // ADC monitoring and status
    void ProcessFrameResponse(const FrameResponse& response, int chipIndex);
    uint16_t ADCToMillivolts(uint16_t adcValue);
    uint16_t ADCToMilliamps(uint16_t adcValue);
    void CheckPortHealth();
    
    // GPIO control for chip selects
    bool InitGPIO();
    void SetChipSelect(int chipIndex, bool active);
    void CloseGPIO();
    
    // USB firmware management
    bool InitUSB();
    bool CheckAndUpdateFirmware();
    bool ResetRP2354BToBootloader();
    bool UploadFirmware(const std::string& firmwarePath);

    // SPI communication
    SPIUtils* m_spi;
    int m_spiPort;
    int m_spiSpeed;
    
    // Multi-chip support
    int m_chipCount;                          // Number of RP2354B chips on this SPI bus
    int m_chipSelectPins[RP2354B_MAX_CHIPS_PER_BUS];  // GPIO pins for chip select
    int m_gpioFd;                             // GPIO export file descriptor

    // USB firmware management
    std::string m_usbDevice;     // e.g., /dev/ttyACM0
    bool m_autoUpdateFirmware;
    int m_usbFd;                 // USB serial file descriptor

    // Port configuration
    PortConfig m_portConfigs[RP2354B_MAX_PORTS];
    uint8_t m_activePortMask[3];  // 24 bits for 24 ports
    int m_activePortCount;

    // Pixel string management
    std::vector<PixelString*> m_pixelStrings;
    std::list<std::string> m_autoCreatedModelNames;

    // Output buffers
    uint8_t* m_frameBuffer;      // Buffer for complete frame packet
    size_t   m_frameBufferSize;
    uint8_t* m_outputBuffer;     // Temporary output buffer for pixel data

    // Statistics
    uint64_t m_framesSent;
    uint64_t m_bytesSent;
    uint32_t m_lastFrameTime;
    uint8_t  m_sequenceNumber;
    
    // ADC monitoring data (per chip, per port)
    struct PortStatus {
        uint16_t adcValue;       // Last ADC reading
        uint8_t  statusFlags;    // Status flags
        uint32_t timestamp;      // Last update timestamp
        uint16_t millivolts;     // Calculated voltage
        uint16_t milliamps;      // Calculated current
        int      pixelCount;     // Estimated pixel count from current
    };
    PortStatus m_portStatus[RP2354B_MAX_CHIPS_PER_BUS][8];  // 4 chips × 8 ports
    uint32_t m_lastADCWarning[RP2354B_MAX_CHIPS_PER_BUS][8]; // Rate limit warnings
    
    // Testing support
    int m_testCycle;
    int m_testType;
    float m_testPercent;

    // Configuration state
    bool m_configSent;
    bool m_compressionEnabled;
    bool m_adcEnabled;           // Enable ADC monitoring for eFuse protection
};
