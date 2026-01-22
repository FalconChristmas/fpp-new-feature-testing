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

#include "fpp-pch.h"

#include <errno.h>
#include <fcntl.h>
#include <string.h>
#include <sys/time.h>
#include <sys/stat.h>
#include <termios.h>
#include <unistd.h>

#include "../log.h"
#include "RP2354BOutput.h"

// CRC32 lookup table for efficient calculation
static const uint32_t crc32_table[256] = {
    0x00000000, 0x77073096, 0xee0e612c, 0x990951ba, 0x076dc419, 0x706af48f,
    0xe963a535, 0x9e6495a3, 0x0edb8832, 0x79dcb8a4, 0xe0d5e91e, 0x97d2d988,
    0x09b64c2b, 0x7eb17cbd, 0xe7b82d07, 0x90bf1d91, 0x1db71064, 0x6ab020f2,
    0xf3b97148, 0x84be41de, 0x1adad47d, 0x6ddde4eb, 0xf4d4b551, 0x83d385c7,
    0x136c9856, 0x646ba8c0, 0xfd62f97a, 0x8a65c9ec, 0x14015c4f, 0x63066cd9,
    0xfa0f3d63, 0x8d080df5, 0x3b6e20c8, 0x4c69105e, 0xd56041e4, 0xa2677172,
    0x3c03e4d1, 0x4b04d447, 0xd20d85fd, 0xa50ab56b, 0x35b5a8fa, 0x42b2986c,
    0xdbbbc9d6, 0xacbcf940, 0x32d86ce3, 0x45df5c75, 0xdcd60dcf, 0xabd13d59,
    0x26d930ac, 0x51de003a, 0xc8d75180, 0xbfd06116, 0x21b4f4b5, 0x56b3c423,
    0xcfba9599, 0xb8bda50f, 0x2802b89e, 0x5f058808, 0xc60cd9b2, 0xb10be924,
    0x2f6f7c87, 0x58684c11, 0xc1611dab, 0xb6662d3d, 0x76dc4190, 0x01db7106,
    0x98d220bc, 0xefd5102a, 0x71b18589, 0x06b6b51f, 0x9fbfe4a5, 0xe8b8d433,
    0x7807c9a2, 0x0f00f934, 0x9609a88e, 0xe10e9818, 0x7f6a0dbb, 0x086d3d2d,
    0x91646c97, 0xe6635c01, 0x6b6b51f4, 0x1c6c6162, 0x856530d8, 0xf262004e,
    0x6c0695ed, 0x1b01a57b, 0x8208f4c1, 0xf50fc457, 0x65b0d9c6, 0x12b7e950,
    0x8bbeb8ea, 0xfcb9887c, 0x62dd1ddf, 0x15da2d49, 0x8cd37cf3, 0xfbd44c65,
    0x4db26158, 0x3ab551ce, 0xa3bc0074, 0xd4bb30e2, 0x4adfa541, 0x3dd895d7,
    0xa4d1c46d, 0xd3d6f4fb, 0x4369e96a, 0x346ed9fc, 0xad678846, 0xda60b8d0,
    0x44042d73, 0x33031de5, 0xaa0a4c5f, 0xdd0d7cc9, 0x5005713c, 0x270241aa,
    0xbe0b1010, 0xc90c2086, 0x5768b525, 0x206f85b3, 0xb966d409, 0xce61e49f,
    0x5edef90e, 0x29d9c998, 0xb0d09822, 0xc7d7a8b4, 0x59b33d17, 0x2eb40d81,
    0xb7bd5c3b, 0xc0ba6cad, 0xedb88320, 0x9abfb3b6, 0x03b6e20c, 0x74b1d29a,
    0xead54739, 0x9dd277af, 0x04db2615, 0x73dc1683, 0xe3630b12, 0x94643b84,
    0x0d6d6a3e, 0x7a6a5aa8, 0xe40ecf0b, 0x9309ff9d, 0x0a00ae27, 0x7d079eb1,
    0xf00f9344, 0x8708a3d2, 0x1e01f268, 0x6906c2fe, 0xf762575d, 0x806567cb,
    0x196c3671, 0x6e6b06e7, 0xfed41b76, 0x89d32be0, 0x10da7a5a, 0x67dd4acc,
    0xf9b9df6f, 0x8ebeeff9, 0x17b7be43, 0x60b08ed5, 0xd6d6a3e8, 0xa1d1937e,
    0x38d8c2c4, 0x4fdff252, 0xd1bb67f1, 0xa6bc5767, 0x3fb506dd, 0x48b2364b,
    0xd80d2bda, 0xaf0a1b4c, 0x36034af6, 0x41047a60, 0xdf60efc3, 0xa867df55,
    0x316e8eef, 0x4669be79, 0xcb61b38c, 0xbc66831a, 0x256fd2a0, 0x5268e236,
    0xcc0c7795, 0xbb0b4703, 0x220216b9, 0x5505262f, 0xc5ba3bbe, 0xb2bd0b28,
    0x2bb45a92, 0x5cb36a04, 0xc2d7ffa7, 0xb5d0cf31, 0x2cd99e8b, 0x5bdeae1d,
    0x9b64c2b0, 0xec63f226, 0x756aa39c, 0x026d930a, 0x9c0906a9, 0xeb0e363f,
    0x72076785, 0x05005713, 0x95bf4a82, 0xe2b87a14, 0x7bb12bae, 0x0cb61b38,
    0x92d28e9b, 0xe5d5be0d, 0x7cdcefb7, 0x0bdbdf21, 0x86d3d2d4, 0xf1d4e242,
    0x68ddb3f8, 0x1fda836e, 0x81be16cd, 0xf6b9265b, 0x6fb077e1, 0x18b74777,
    0x88085ae6, 0xff0f6a70, 0x66063bca, 0x11010b5c, 0x8f659eff, 0xf862ae69,
    0x616bffd3, 0x166ccf45, 0xa00ae278, 0xd70dd2ee, 0x4e048354, 0x3903b3c2,
    0xa7672661, 0xd06016f7, 0x4969474d, 0x3e6e77db, 0xaed16a4a, 0xd9d65adc,
    0x40df0b66, 0x37d83bf0, 0xa9bcae53, 0xdebb9ec5, 0x47b2cf7f, 0x30b5ffe9,
    0xbdbdf21c, 0xcabac28a, 0x53b39330, 0x24b4a3a6, 0xbad03605, 0xcdd70693,
    0x54de5729, 0x23d967bf, 0xb3667a2e, 0xc4614ab8, 0x5d681b02, 0x2a6f2b94,
    0xb40bbe37, 0xc30c8ea1, 0x5a05df1b, 0x2d02ef8d
};

#include "Plugin.h"
class RP2354BPlugin : public FPPPlugins::Plugin, public FPPPlugins::ChannelOutputPlugin {
public:
    RP2354BPlugin() : FPPPlugins::Plugin("RP2354B") {}
    
    virtual ChannelOutput* createChannelOutput(unsigned int startChannel, 
                                               unsigned int channelCount) override {
        return new RP2354BOutput(startChannel, channelCount);
    }
};

extern "C" {
    FPPPlugins::Plugin* createPlugin() {
        return new RP2354BPlugin();
    }
}

/////////////////////////////////////////////////////////////////////////////

RP2354BOutput::RP2354BOutput(unsigned int startChannel, unsigned int channelCount)
    : ThreadedChannelOutput(startChannel, channelCount),
      m_spi(nullptr),
      m_spiPort(0),
      m_spiSpeed(40000000),  // Default 40 MHz (was 20 MHz)
      m_chipCount(1),
      m_gpioFd(-1),
      m_activePortCount(0),
      m_usbDevice(""),
      m_autoUpdateFirmware(false),
      m_usbFd(-1),
      m_frameBuffer(nullptr),
      m_frameBufferSize(0),
      m_outputBuffer(nullptr),
      m_framesSent(0),
      m_bytesSent(0),
      m_lastFrameTime(0),
      m_sequenceNumber(0),
      m_testCycle(-1),
      m_testType(0),
      m_testPercent(0.0f),
      m_configSent(false),
      m_compressionEnabled(false) {
    
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::RP2354BOutput(%u, %u)\n",
             startChannel, channelCount);
    
    // Initialize port mask and chip select pins
    memset(m_activePortMask, 0, sizeof(m_activePortMask));
    memset(m_chipSelectPins, -1, sizeof(m_chipSelectPins));
}

RP2354BOutput::~RP2354BOutput() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::~RP2354BOutput()\n");
    
    CloseGPIO();
    
    if (m_usbFd >= 0) {
        close(m_usbFd);
        m_usbFd = -1;
    }
    
    if (m_frameBuffer) {
        free(m_frameBuffer);
        m_frameBuffer = nullptr;
    }
    
    if (m_outputBuffer) {
        free(m_outputBuffer);
        m_outputBuffer = nullptr;
    }
    
    if (m_spi) {
        delete m_spi;
        m_spi = nullptr;
    }
    
    for (auto ps : m_pixelStrings) {
        delete ps;
    }
    m_pixelStrings.clear();
}

int RP2354BOutput::Init(Json::Value config) {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::Init()\n");

    // Parse SPI device configuration
    if (config.isMember("device")) {
        std::string device = config["device"].asString();
        if (device == "spidev0.0") {
            m_spiPort = 0;
        } else if (device == "spidev0.1") {
            m_spiPort = 1;
        } else {
            LogErr(VB_CHANNELOUT, "Invalid SPI device: %s\n", device.c_str());
            return 0;
        }
    }

    // Parse SPI speed (in kHz from UI, convert to Hz)
    if (config.isMember("speed")) {
        m_spiSpeed = config["speed"].asInt() * 1000;
        if (m_spiSpeed < 1000000) m_spiSpeed = 1000000;       // Min 1 MHz
        if (m_spiSpeed > 62500000) m_spiSpeed = 62500000;     // Max 62.5 MHz (Pi Zero 2 W limit)
    }

    // Parse USB device for firmware management
    if (config.isMember("usbDevice")) {
        m_usbDevice = config["usbDevice"].asString();
    }
    
    // Parse auto-update firmware setting
    if (config.isMember("autoUpdateFirmware")) {
        m_autoUpdateFirmware = config["autoUpdateFirmware"].asBool();
    }

    // Parse compression setting
    if (config.isMember("compression")) {
        m_compressionEnabled = config["compression"].asBool();
    }

    // Parse multi-chip configuration
    if (config.isMember("chipCount")) {
        m_chipCount = config["chipCount"].asInt();
        if (m_chipCount < 1) m_chipCount = 1;
        if (m_chipCount > RP2354B_MAX_CHIPS_PER_BUS) {
            LogWarn(VB_CHANNELOUT, "Chip count %d exceeds maximum %d, clamping\n",
                    m_chipCount, RP2354B_MAX_CHIPS_PER_BUS);
            m_chipCount = RP2354B_MAX_CHIPS_PER_BUS;
        }
    }
    
    // Parse chip select GPIO pins
    if (config.isMember("chipSelects") && config["chipSelects"].isArray()) {
        for (int i = 0; i < config["chipSelects"].size() && i < RP2354B_MAX_CHIPS_PER_BUS; i++) {
            m_chipSelectPins[i] = config["chipSelects"][i].asInt();
            LogDebug(VB_CHANNELOUT, "Chip %d CS GPIO: %d\n", i, m_chipSelectPins[i]);
        }
    } else if (m_chipCount > 1) {
        LogWarn(VB_CHANNELOUT, "Multi-chip mode requires chipSelects configuration\n");
        m_chipCount = 1;  // Fall back to single chip
    }

    // Parse pixel string outputs
    memset(m_activePortMask, 0, sizeof(m_activePortMask));
    m_activePortCount = 0;
    
    if (config.isMember("outputs") && config["outputs"].isArray()) {
        int maxPorts = m_chipCount * RP2354B_MAX_PORTS;
        for (int i = 0; i < config["outputs"].size() && i < maxPorts; i++) {
            Json::Value portConfig = config["outputs"][i];
            
            PixelString* ps = new PixelString(true);  // Support smart receivers
            if (ps->Init(portConfig)) {
                m_pixelStrings.push_back(ps);
                
                // Configure port
                m_portConfigs[i].enabled = true;
                m_portConfigs[i].pixelCount = ps->m_outputChannels / 3;  // Assuming RGB
                m_portConfigs[i].brightness = 255;
                
                // Parse pixel type
                std::string type = portConfig.isMember("type") ? 
                                  portConfig["type"].asString() : "WS2812";
                if (type == "WS2811") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_WS2811;
                } else if (type == "WS2812") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_WS2812;
                } else if (type == "WS2813") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_WS2813;
                } else if (type == "WS2815") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_WS2815;
                } else if (type == "APA102") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_APA102;
                } else if (type == "SK6812") {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_SK6812;
                } else {
                    m_portConfigs[i].pixelType = RP2354B_TYPE_WS2812;  // Default
                }
                
                // Parse color order (0=RGB, 1=GRB, 2=BRG, etc.)
                m_portConfigs[i].colorOrder = 1;  // Default GRB for WS2812
                
                // Parse GPIO pin
                m_portConfigs[i].gpioPin = i;  // Default to port number
                if (portConfig.isMember("gpio")) {
                    m_portConfigs[i].gpioPin = portConfig["gpio"].asInt();
                }
                
                // Parse brightness
                if (portConfig.isMember("brightness")) {
                    m_portConfigs[i].brightness = portConfig["brightness"].asInt();
                }
                
                // Set bit in 24-bit port mask
                int byte_idx = i / 8;
                int bit_idx = i % 8;
                m_activePortMask[byte_idx] |= (1 << bit_idx);
                m_activePortCount++;
                
                LogDebug(VB_CHANNELOUT, 
                         "Port %d: %d pixels, type %d, GPIO %d, brightness %d\n",
                         i, m_portConfigs[i].pixelCount, m_portConfigs[i].pixelType,
                         m_portConfigs[i].gpioPin, m_portConfigs[i].brightness);
            } else {
                delete ps;
                LogErr(VB_CHANNELOUT, "Failed to initialize pixel string %d\n", i);
            }
        }
    }

    if (m_activePortCount == 0) {
        LogErr(VB_CHANNELOUT, "No valid pixel outputs configured\n");
        return 0;
    }

    // Calculate maximum frame buffer size
    size_t maxDataSize = 0;
    for (int i = 0; i < RP2354B_MAX_PORTS; i++) {
        if (m_portConfigs[i].enabled) {
            maxDataSize += m_portConfigs[i].pixelCount * 3;  // RGB
        }
    }
    
    m_frameBufferSize = sizeof(PacketHeader) + maxDataSize + sizeof(uint32_t);  // Header + data + CRC
    m_frameBuffer = (uint8_t*)malloc(m_frameBufferSize);
    m_outputBuffer = (uint8_t*)malloc(maxDataSize);
    
    if (!m_frameBuffer || !m_outputBuffer) {
        LogErr(VB_CHANNELOUT, "Failed to allocate frame buffers\n");
        return 0;
    }

    // Initialize SPI
    LogDebug(VB_CHANNELOUT, "Opening SPI port %d at %d Hz\n", m_spiPort, m_spiSpeed);
    m_spi = new SPIUtils(m_spiPort, m_spiSpeed);
    
    if (!m_spi->isOk()) {
        LogErr(VB_CHANNELOUT, "Failed to open SPI device\n");
        delete m_spi;
        m_spi = nullptr;
        return 0;
    }
    
    // Initialize GPIO for chip selects if multi-chip mode
    if (m_chipCount > 1) {
        if (!InitGPIO()) {
            LogErr(VB_CHANNELOUT, "Failed to initialize GPIO for chip selects\n");
            return 0;
        }
    }

    // Initialize USB connection if configured
    if (!m_usbDevice.empty()) {
        if (!InitUSB()) {
            LogWarn(VB_CHANNELOUT, "Failed to initialize USB connection, firmware updates disabled\n");
        } else if (m_autoUpdateFirmware) {
            CheckAndUpdateFirmware();
        }
    }

    // Send configuration to RP2354B
    if (!SendConfiguration()) {
        LogErr(VB_CHANNELOUT, "Failed to send configuration to RP2354B\n");
        WarningHolder::AddWarning("RP2354B: Failed to send initial configuration");
    } else {
        m_configSent = true;
        LogInfo(VB_CHANNELOUT, "RP2354B configured: %d ports, %zu max bytes/frame\n",
                m_activePortCount, maxDataSize);
    }

    // Auto-create overlay models for testing/visualization
    PixelString::AutoCreateOverlayModels(m_pixelStrings, m_autoCreatedModelNames);

    return ThreadedChannelOutput::Init(config);
}

int RP2354BOutput::Close(void) {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::Close()\n");
    
    // Send reset command to RP2354B
    if (m_spi && m_spi->isOk()) {
        PacketHeader header;
        BuildPacketHeader(header, RP2354B_CMD_RESET, 0, 0);
        m_spi->xfer((uint8_t*)&header, nullptr, sizeof(PacketHeader));
    }
    
    return ThreadedChannelOutput::Close();
}

void RP2354BOutput::GetRequiredChannelRanges(const std::function<void(int, int)>& addRange) {
    // Add ranges for all pixel strings
    for (auto ps : m_pixelStrings) {
        for (const auto& vs : ps->m_virtualStrings) {
            int start = vs.startChannel;
            int end = start + (vs.pixelCount * vs.channelsPerNode()) - 1;
            addRange(start, end);
        }
    }
}

void RP2354BOutput::PrepData(unsigned char* channelData) {
    LogExcess(VB_CHANNELOUT, "RP2354BOutput::PrepData(%p)\n", channelData);
    
    // Prepare pixel data from channel data
    for (auto ps : m_pixelStrings) {
        if (ps->m_outputBuffer && ps->m_outputChannels > 0) {
            // Copy and remap channel data to output buffer
            for (const auto& vs : ps->m_virtualStrings) {
                int startChan = vs.startChannel - 1;  // Channel numbers are 1-based
                int channels = vs.pixelCount * vs.channelsPerNode();
                
                if (startChan + channels <= m_channelCount) {
                    int destOffset = ps->m_channelOffset;
                    
                    // Apply brightness, gamma, color order, etc.
                    for (int p = 0; p < vs.pixelCount; p++) {
                        int srcIdx = startChan + (p * vs.channelsPerNode());
                        int destIdx = destOffset + (p * 3);
                        
                        if (destIdx + 2 < ps->m_outputChannels) {
                            // Apply color order remapping (simplified - actual implementation
                            // should use vs.colorOrder)
                            ps->m_outputBuffer[destIdx + 0] = channelData[srcIdx + 1];  // G
                            ps->m_outputBuffer[destIdx + 1] = channelData[srcIdx + 0];  // R
                            ps->m_outputBuffer[destIdx + 2] = channelData[srcIdx + 2];  // B
                        }
                    }
                }
            }
        }
    }
}

int RP2354BOutput::RawSendData(unsigned char* channelData) {
    LogExcess(VB_CHANNELOUT, "RP2354BOutput::RawSendData(%p)\n", channelData);

    if (!m_spi || !m_spi->isOk()) {
        return 0;
    }

    // Send configuration if not already sent
    if (!m_configSent) {
        if (!SendConfiguration()) {
            LogErr(VB_CHANNELOUT, "Failed to send configuration\n");
            return 0;
        }
        m_configSent = true;
    }

    // Send frame data
    if (!SendFrameData(channelData)) {
        LogErr(VB_CHANNELOUT, "Failed to send frame data\n");
        return 0;
    }

    m_framesSent++;
    
    return m_channelCount;
}

void RP2354BOutput::DumpConfig(void) {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::DumpConfig()\n");
    LogDebug(VB_CHANNELOUT, "    SPI Port     : %d\n", m_spiPort);
    LogDebug(VB_CHANNELOUT, "    SPI Speed    : %d Hz\n", m_spiSpeed);
    LogDebug(VB_CHANNELOUT, "    Chip Count   : %d\n", m_chipCount);
    LogDebug(VB_CHANNELOUT, "    Active Ports : %d\n", m_activePortCount);
    LogDebug(VB_CHANNELOUT, "    Port Mask    : 0x%02X\n", m_activePortMask);
    LogDebug(VB_CHANNELOUT, "    Compression  : %s\n", m_compressionEnabled ? "Yes" : "No");
    LogDebug(VB_CHANNELOUT, "    Frames Sent  : %llu\n", (unsigned long long)m_framesSent);
    
    if (m_chipCount > 1) {
        for (int i = 0; i < m_chipCount; i++) {
            LogDebug(VB_CHANNELOUT, "    Chip %d CS    : GPIO %d\n", i, m_chipSelectPins[i]);
        }
    }
    
    for (int i = 0; i < RP2354B_MAX_PORTS; i++) {
        if (m_portConfigs[i].enabled) {
            LogDebug(VB_CHANNELOUT, "    Port %d: %d pixels, GPIO %d\n",
                     i, m_portConfigs[i].pixelCount, m_portConfigs[i].gpioPin);
        }
    }

    ThreadedChannelOutput::DumpConfig();
}

void RP2354BOutput::OverlayTestData(unsigned char* channelData, int cycleNum, 
                                     float percentOfCycle, int testType, 
                                     const Json::Value& config) {
    m_testCycle = cycleNum;
    m_testPercent = percentOfCycle;
    m_testType = testType;
    
    // Generate test patterns (can be customized)
    // For now, just pass through to pixel strings
}

/////////////////////////////////////////////////////////////////////////////
// Private Methods
/////////////////////////////////////////////////////////////////////////////

bool RP2354BOutput::SendConfiguration() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::SendConfiguration()\n");
    
    // Send configuration to each chip
    for (int chip = 0; chip < m_chipCount; chip++) {
        if (!SendConfigurationToChip(chip)) {
            LogErr(VB_CHANNELOUT, "Failed to send configuration to chip %d\n", chip);
            return false;
        }
    }
    
    return true;
}

bool RP2354BOutput::SendConfigurationToChip(int chipIndex) {
    LogDebug(VB_CHANNELOUT, "Sending configuration to chip %d\n", chipIndex);
    
    // Calculate port range for this chip (24 ports per chip)
    int startPort = chipIndex * RP2354B_MAX_PORTS;
    int endPort = startPort + RP2354B_MAX_PORTS;
    
    // Build port mask for this chip
    uint8_t chipPortMask[3] = {0, 0, 0};
    int portCount = 0;
    
    for (int i = startPort; i < endPort; i++) {
        if (i >= (int)(m_pixelStrings.size()) || !m_portConfigs[i].enabled) {
            continue;
        }
        int localPort = i - startPort;
        int byte_idx = localPort / 8;
        int bit_idx = localPort % 8;
        chipPortMask[byte_idx] |= (1 << bit_idx);
        portCount++;
    }
    
    if (portCount == 0) {
        LogDebug(VB_CHANNELOUT, "No ports enabled for chip %d, skipping\n", chipIndex);
        return true;  // Not an error
    }
    
    // Build configuration packet
    size_t configPayloadSize = portCount * sizeof(ConfigPacket);
    size_t packetSize = sizeof(PacketHeader) + configPayloadSize + sizeof(uint32_t);
    
    uint8_t* packet = (uint8_t*)malloc(packetSize);
    if (!packet) {
        LogErr(VB_CHANNELOUT, "Failed to allocate config packet\n");
        return false;
    }

    // Build header
    PacketHeader* header = (PacketHeader*)packet;
    BuildPacketHeader(*header, RP2354B_CMD_CONFIG, configPayloadSize, chipPortMask);

    // Build payload
    ConfigPacket* configs = (ConfigPacket*)(packet + sizeof(PacketHeader));
    int configIdx = 0;
    
    for (int i = startPort; i < endPort; i++) {
        if (i >= (int)(m_pixelStrings.size()) || !m_portConfigs[i].enabled) {
            continue;
        }
        configs[configIdx].pixelCount = m_portConfigs[i].pixelCount;
        configs[configIdx].pixelType = m_portConfigs[i].pixelType;
        configs[configIdx].colorOrder = m_portConfigs[i].colorOrder;
        configs[configIdx].brightness = m_portConfigs[i].brightness;
        configs[configIdx].gpioPin = m_portConfigs[i].gpioPin;
        configs[configIdx].reserved = 0;
        configIdx++;
    }

    // Calculate and append CRC32
    uint32_t crc = CalculateCRC32(packet, sizeof(PacketHeader) + configPayloadSize);
    memcpy(packet + sizeof(PacketHeader) + configPayloadSize, &crc, sizeof(uint32_t));

    // Select chip and send packet
    if (m_chipCount > 1) {
        SetChipSelect(chipIndex, true);
    }
    
    int result = m_spi->xfer(packet, nullptr, packetSize);
    
    if (m_chipCount > 1) {
        SetChipSelect(chipIndex, false);
    }
    free(packet);

    if (result < 0) {
        LogErr(VB_CHANNELOUT, "SPI transfer failed: %d\n", result);
        return false;
    }

    m_bytesSent += packetSize;
    LogDebug(VB_CHANNELOUT, "Sent %d byte configuration packet\n", (int)packetSize);
    
    // Small delay to let RP2354B process config
    usleep(10000);  // 10ms

    return true;
}

bool RP2354BOutput::SendFrameData(unsigned char* channelData) {
    // Send frame data to each chip
    for (int chip = 0; chip < m_chipCount; chip++) {
        if (!SendFrameDataToChip(chip, channelData)) {
            LogErr(VB_CHANNELOUT, "Failed to send frame data to chip %d\n", chip);
            return false;
        }
    }
    
    return true;
}

bool RP2354BOutput::SendFrameDataToChip(int chipIndex, unsigned char* channelData) {
    // Calculate port range for this chip
    int startPort = chipIndex * RP2354B_MAX_PORTS;
    int endPort = startPort + RP2354B_MAX_PORTS;
    
    // Build port mask for this chip
    uint8_t chipPortMask[3] = {0, 0, 0};
    size_t dataSize = 0;
    
    for (int i = startPort; i < endPort; i++) {
        if (i >= (int)(m_pixelStrings.size()) || !m_portConfigs[i].enabled) {
            continue;
        }
        int localPort = i - startPort;
        int byte_idx = localPort / 8;
        int bit_idx = localPort % 8;
        chipPortMask[byte_idx] |= (1 << bit_idx);
        dataSize += m_portConfigs[i].pixelCount * 3;  // RGB
    }
    
    if (dataSize == 0) {
        return true;  // No data for this chip, not an error
    }

    // Build packet in frame buffer
    PacketHeader* header = (PacketHeader*)m_frameBuffer;
    uint8_t flags = 0;
    if (m_compressionEnabled) {
        flags |= RP2354B_FLAG_COMPRESSED;
    }
    
    BuildPacketHeader(*header, RP2354B_CMD_FRAME, dataSize, chipPortMask);
    header->flags = flags;
    header->headerCRC = CalculateHeaderCRC(*header);

    // Copy pixel data for this chip's ports for this chip's ports
    uint8_t* dataPtr = m_frameBuffer + sizeof(PacketHeader);
    
    for (int i = startPort; i < endPort && i < (int)(m_pixelStrings.size()); i++) {
        if (!m_portConfigs[i].enabled || !m_pixelStrings[i]->m_outputBuffer) {
            continue;
        }
        int bytes = m_portConfigs[i].pixelCount * 3;
        memcpy(dataPtr, m_pixelStrings[i]->m_outputBuffer, bytes);
        dataPtr += bytes;
    }

    // Calculate and append CRC32
    uint32_t crc = CalculateCRC32(m_frameBuffer, sizeof(PacketHeader) + dataSize);
    memcpy(dataPtr, &crc, sizeof(uint32_t));

    // Select chip and send packet
    if (m_chipCount > 1) {
        SetChipSelect(chipIndex, true);
    }
    
    size_t packetSize = sizeof(PacketHeader) + dataSize + sizeof(uint32_t);
    int result = m_spi->xfer(m_frameBuffer, nullptr, packetSize);
    
    if (m_chipCount > 1) {
        SetChipSelect(chipIndex, false);
    }

    if (result < 0) {
        LogErr(VB_CHANNELOUT, "SPI transfer failed: %d\n", result);
        return false;
    }

    m_bytesSent += packetSize;
    LogExcess(VB_CHANNELOUT, "Sent %d byte frame packet\n", (int)packetSize);

    return true;
}

void RP2354BOutput::BuildPacketHeader(PacketHeader& header, uint8_t command,
                                       uint16_t payloadLen, const uint8_t portMask[3]) {
    header.magic[0] = RP2354B_MAGIC_0;
    header.magic[1] = RP2354B_MAGIC_1;
    header.command = command;
    header.flags = 0;
    header.payloadLen = payloadLen;
    memcpy(header.portMask, portMask, 3);
    header.reserved = 0;
    header.sequence = m_sequenceNumber++;
    header.headerCRC = CalculateHeaderCRC(header);
}

uint8_t RP2354BOutput::CalculateHeaderCRC(const PacketHeader& header) {
    uint8_t crc = 0;
    const uint8_t* data = (const uint8_t*)&header;
    
    // XOR all bytes except the CRC byte itself (last byte of 12-byte header)
    for (size_t i = 0; i < sizeof(PacketHeader) - 1; i++) {
        crc ^= data[i];
    }
    
    return crc;
}

uint32_t RP2354BOutput::CalculateCRC32(const uint8_t* data, size_t length) {
    uint32_t crc = 0xFFFFFFFF;
    
    for (size_t i = 0; i < length; i++) {
        uint8_t index = (crc ^ data[i]) & 0xFF;
        crc = (crc >> 8) ^ crc32_table[index];
    }
    
    return crc ^ 0xFFFFFFFF;
}

/////////////////////////////////////////////////////////////////////////////
// USB Firmware Management Methods
/////////////////////////////////////////////////////////////////////////////

bool RP2354BOutput::InitUSB() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::InitUSB() - device: %s\n", m_usbDevice.c_str());
    
    // Open USB serial device
    m_usbFd = open(m_usbDevice.c_str(), O_RDWR | O_NOCTTY | O_NONBLOCK);
    if (m_usbFd < 0) {
        LogErr(VB_CHANNELOUT, "Failed to open USB device %s: %s\n", 
               m_usbDevice.c_str(), strerror(errno));
        return false;
    }
    
    // Configure serial port (115200 baud, 8N1)
    struct termios tty;
    if (tcgetattr(m_usbFd, &tty) != 0) {
        LogErr(VB_CHANNELOUT, "Error from tcgetattr: %s\n", strerror(errno));
        close(m_usbFd);
        m_usbFd = -1;
        return false;
    }
    
    cfsetospeed(&tty, B115200);
    cfsetispeed(&tty, B115200);
    
    tty.c_cflag = (tty.c_cflag & ~CSIZE) | CS8;     // 8-bit chars
    tty.c_iflag &= ~IGNBRK;                          // disable break processing
    tty.c_lflag = 0;                                 // no signaling chars, no echo,
    tty.c_oflag = 0;                                 // no remapping, no delays
    tty.c_cc[VMIN]  = 0;                             // read doesn't block
    tty.c_cc[VTIME] = 5;                             // 0.5 seconds read timeout
    
    tty.c_iflag &= ~(IXON | IXOFF | IXANY);          // shut off xon/xoff ctrl
    tty.c_cflag |= (CLOCAL | CREAD);                 // ignore modem controls, enable reading
    tty.c_cflag &= ~(PARENB | PARODD);               // shut off parity
    tty.c_cflag &= ~CSTOPB;
    tty.c_cflag &= ~CRTSCTS;
    
    if (tcsetattr(m_usbFd, TCSANOW, &tty) != 0) {
        LogErr(VB_CHANNELOUT, "Error from tcsetattr: %s\n", strerror(errno));
        close(m_usbFd);
        m_usbFd = -1;
        return false;
    }
    
    LogDebug(VB_CHANNELOUT, "USB connection established\n");
    return true;
}

bool RP2354BOutput::CheckAndUpdateFirmware() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::CheckAndUpdateFirmware()\n");
    
    std::string firmwarePath = "/opt/fpp/external/rp2354b-pixel-driver/build/rp2354b_pixel_driver.uf2";
    
    // Check if firmware file exists
    struct stat st;
    if (stat(firmwarePath.c_str(), &st) != 0) {
        LogDebug(VB_CHANNELOUT, "Firmware file not found: %s\n", firmwarePath.c_str());
        return false;
    }
    
    // TODO: Check version via USB serial query to RP2354B
    // For now, we skip auto-update and let user manually trigger it
    
    LogDebug(VB_CHANNELOUT, "Firmware check complete (auto-update not yet implemented)\n");
    return true;
}

bool RP2354BOutput::ResetRP2354BToBootloader() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::ResetRP2354BToBootloader()\n");
    
    // This requires GPIO control pins from Pi to RP2354B
    // GPIO 22 -> RP2354B RUN (reset)
    // GPIO 23 -> RP2354B BOOTSEL
    
    // TODO: Implement GPIO control to reset RP2354B into bootloader mode
    // 1. Set BOOTSEL high
    // 2. Pulse RUN low
    // 3. RP2354B boots into USB bootloader mode
    // 4. Pi can then mount as mass storage and copy .uf2 file
    
    LogWarn(VB_CHANNELOUT, "ResetRP2354BToBootloader not yet implemented\n");
    return false;
}

bool RP2354BOutput::UploadFirmware(const std::string& firmwarePath) {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::UploadFirmware(%s)\n", firmwarePath.c_str());
    
    // Reset RP2354B to bootloader
    if (!ResetRP2354BToBootloader()) {
        return false;
    }
    
    // Wait for USB mass storage device to appear
    usleep(2000000);  // 2 seconds in microseconds
    
    // TODO: Mount RPI-RP2 drive and copy firmware
    // This is platform-specific and requires root or proper udev rules
    
    LogWarn(VB_CHANNELOUT, "UploadFirmware not yet implemented\n");
    return false;
}

/////////////////////////////////////////////////////////////////////////////
// GPIO Control for Chip Selects
/////////////////////////////////////////////////////////////////////////////

bool RP2354BOutput::InitGPIO() {
    LogDebug(VB_CHANNELOUT, "RP2354BOutput::InitGPIO()\\n");
    
    // Export and configure GPIO pins for chip selects
    for (int i = 0; i < m_chipCount; i++) {
        if (m_chipSelectPins[i] < 0) {
            LogErr(VB_CHANNELOUT, "Invalid chip select pin for chip %d\\n", i);
            return false;
        }
        
        // Export GPIO
        char path[256];
        snprintf(path, sizeof(path), "/sys/class/gpio/export");
        int fd = open(path, O_WRONLY);
        if (fd >= 0) {
            char buf[16];
            snprintf(buf, sizeof(buf), "%d", m_chipSelectPins[i]);
            write(fd, buf, strlen(buf));
            close(fd);
        }
        
        // Small delay for GPIO to be ready
        usleep(100000);  // 100ms
        
        // Set direction to output
        snprintf(path, sizeof(path), "/sys/class/gpio/gpio%d/direction", m_chipSelectPins[i]);
        fd = open(path, O_WRONLY);
        if (fd < 0) {
            LogErr(VB_CHANNELOUT, "Failed to open GPIO %d direction: %s\\n", 
                   m_chipSelectPins[i], strerror(errno));
            return false;
        }
        write(fd, "out", 3);
        close(fd);
        
        // Set initial value to high (deselected - active low)
        SetChipSelect(i, false);
        
        LogDebug(VB_CHANNELOUT, "Initialized GPIO %d for chip %d select\\n", 
                 m_chipSelectPins[i], i);
    }
    
    return true;
}

void RP2354BOutput::SetChipSelect(int chipIndex, bool active) {
    if (chipIndex < 0 || chipIndex >= m_chipCount || m_chipSelectPins[chipIndex] < 0) {
        return;
    }
    
    char path[256];
    snprintf(path, sizeof(path), "/sys/class/gpio/gpio%d/value", m_chipSelectPins[chipIndex]);
    
    int fd = open(path, O_WRONLY);
    if (fd < 0) {
        LogErr(VB_CHANNELOUT, "Failed to open GPIO %d value: %s\\n", 
               m_chipSelectPins[chipIndex], strerror(errno));
        return;
    }
    
    // Active low - write '0' to select, '1' to deselect
    const char* value = active ? "0" : "1";
    write(fd, value, 1);
    close(fd);
    
    // Small delay for CS to stabilize
    if (active) {
        usleep(10);  // 10 microseconds
    }
}

void RP2354BOutput::CloseGPIO() {
    // Unexport GPIO pins
    for (int i = 0; i < m_chipCount; i++) {
        if (m_chipSelectPins[i] < 0) {
            continue;
        }
        
        // Ensure CS is deselected
        SetChipSelect(i, false);
        
        // Unexport GPIO
        char path[256];
        snprintf(path, sizeof(path), "/sys/class/gpio/unexport");
        int fd = open(path, O_WRONLY);
        if (fd >= 0) {
            char buf[16];
            snprintf(buf, sizeof(buf), "%d", m_chipSelectPins[i]);
            write(fd, buf, strlen(buf));
            close(fd);
        }
    }
    
    if (m_gpioFd >= 0) {
        close(m_gpioFd);
        m_gpioFd = -1;
    }
}
