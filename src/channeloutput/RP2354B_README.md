# RP2354B Output Driver for FPP

This directory contains the FPP driver for the RP2354B pixel controller.

## Firmware Repository

The RP2354B firmware is maintained in a separate repository:

**Private Repository:** https://github.com/OnlineDynamic/rp2354b-pixel-driver

### Getting the Firmware

If you have access to the private repository:

```bash
cd /opt/fpp/external
git clone https://github.com/OnlineDynamic/rp2354b-pixel-driver.git
```

Or if using SSH:

```bash
cd /opt/fpp/external
git clone git@github.com:OnlineDynamic/rp2354b-pixel-driver.git
```

### Building the Firmware

See the firmware repository for build instructions:
- [BUILD.md](https://github.com/OnlineDynamic/rp2354b-pixel-driver/blob/main/BUILD.md)
- [FLASHING.md](https://github.com/OnlineDynamic/rp2354b-pixel-driver/blob/main/FLASHING.md)

## FPP Driver Components

The FPP-side driver includes:

- **Driver:** `src/channeloutput/RP2354BOutput.cpp/h`
- **Makefile:** `src/makefiles/libfpp-co-RP2354B.mk`
- **Web UI:** `www/co-other-modules.php` (RP2354BDevice class)

## Building FPP Driver

The driver compiles automatically with FPP:

```bash
cd /opt/fpp/src
make libfpp-co-RP2354B.so
```

Or build all of FPP:

```bash
cd /opt/fpp/src
make
```

## Configuration

Configure via FPP web interface:
1. Go to **Input/Output Setup → Channel Outputs**
2. Add **RP2354B Pixel Driver**
3. Set SPI device, speed, chip count, and CS GPIOs
4. Configure pixel strings in **Input/Output Setup → Pixel Strings**

## Architecture

```
FPP (Raspberry Pi)
  ├── libfpp-co-RP2354B.so ────┐
  │   └── RP2354BOutput        │
  │                            │ SPI (40-62.5 MHz)
  └── Web UI Config            │
                               ▼
                    RP2354B Firmware ──────> LED Strings
                    (separate repo)         (24 ports)
```

## License

This FPP driver follows FPP licensing (GPL v2).
The firmware repository has its own licensing.

## Support

- **FPP Issues:** https://github.com/FalconChristmas/fpp/issues
- **Firmware Issues:** Contact repository owner
- **Forum:** https://falconchristmas.com/forum/
