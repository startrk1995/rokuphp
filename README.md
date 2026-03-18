# RokuPHP — ARM64 / PHP 8 Upgrade

Enable H.264/RTSP to HLS streaming for **IP Camera Viewer Pro** on Roku.
This is a modernised fork of [e1ioan/rokuphp](https://github.com/e1ioan/rokuphp)
upgraded to run on **64-bit Raspberry Pi OS** (aarch64) with **PHP 8.x**.

---

## Supported Platforms

| Hardware | Architecture | Supported |
|----------|-------------|-----------|
| Raspberry Pi 4 | ARM64 / aarch64 | ✅ |
| Raspberry Pi 5 | ARM64 / aarch64 | ✅ |
| Any aarch64 SBC | ARM64 / aarch64 | ✅ |
| Raspberry Pi 3 | ARMv7 (32-bit) | ❌ Use [original](https://github.com/e1Ioan/rokuphp) |
| Orange Pi Zero | ARMv7 (32-bit) | ❌ Use [original](https://github.com/e1Ioan/rokuphp) |
| x86 / x86-64 | amd64 | ❌ Not tested |

| OS | Version | Supported |
|----|---------|-----------|
| Raspberry Pi OS (64-bit) | Bookworm (12) | ✅ |
| Raspberry Pi OS (64-bit) | Bullseye (11) | ✅ |
| Ubuntu for Raspberry Pi | 24.04 LTS | ✅ |
| Ubuntu for Raspberry Pi | 22.04 LTS | ✅ |
| Raspberry Pi OS (32-bit) | Any | ❌ Use [original](https://github.com/e1Ioan/rokuphp) |

| PHP | Supported |
|-----|-----------|
| PHP 8.3 | ✅ (preferred) |
| PHP 8.2 | ✅ |
| PHP 8.1 | ✅ |
| PHP 8.0 | ✅ |
| PHP 7.x | ❌ Use [original](https://github.com/e1Ioan/rokuphp) |
| PHP 5.x | ❌ Use [original](https://github.com/e1Ioan/rokuphp) |

> **Not sure which to use?** Run `uname -m` on your Pi. If it returns `aarch64`, use this fork. If it returns `armv7l`, use the [original](https://github.com/e1Ioan/rokuphp).

---

## What Changed

| Area | Original | Upgraded |
|------|----------|----------|
| Architecture | ARMv7 (32-bit) | **ARM64 / aarch64** |
| PHP | 5.x / 7.x (RPCL framework) | **PHP 8.x** (no RPCL) |
| UI framework | jQuery Mobile via RPCL | **Bootstrap 5** (CDN) |
| Authentication | HTTP Digest (Zend RPCL) | Session-based (same credentials file) |
| IP detection | `ifconfig` (deprecated) | `hostname -I` / `ip addr` |
| PHP deprecated | `each()` removed in PHP 8 | Fixed with `foreach` |
| PHP bug | `$snapshot == ""` (comparison) | Fixed `$snapshot = ""` (assignment) |
| Install script | Single ARM install | **Auto-detects PHP version** (8.0–8.3) |

### What Stays the Same
- **Roku API endpoints** (`getcameras.php`, `getstream.php`) — 100% compatible with IP Camera Viewer Pro
- **credentials file format** (`data/validuser.txt`) — existing credentials work unchanged
- **cameras.xml format** — existing camera configs are preserved
- **streamer.xml** — ffmpeg options unchanged
- **ONVIF library** (`class.ponvif.php`) — copied as-is (already PHP 8 compatible)

---

## Requirements

- Raspberry Pi 4 / 5 (or any aarch64 SBC) running 64-bit OS:
  - Raspberry Pi OS Bookworm / Bullseye (64-bit)
  - Ubuntu 22.04 / 24.04 for Raspberry Pi
- Internet access (for `apt` packages and Bootstrap CDN)
- IP Camera Viewer Pro 2.7+ on Roku

---

## Installation

### Step 1 — Build the archive (on your development machine)

```bash
cd rokuphp-arm64/
./build.sh          # creates html.tar.gz
```

### Step 2 — Copy to the Pi and install

```bash
# Copy files to the Pi (replace 192.168.1.70 with your Pi's IP)
scp install.sh html.tar.gz pi@192.168.1.70:~/

# SSH in and run
ssh pi@192.168.1.70
chmod +x install.sh
sudo ./install.sh
```

Select option **1** (clean install) for a fresh setup, or **2** (dirty install) to keep existing packages.

The installer will:
1. Detect your ARM64 architecture
2. Auto-detect the best PHP 8.x version available
3. Install: `ffmpeg`, `apache2`, `php8.x`, `php8.x-curl`, `php8.x-xml`, `php8.x-mbstring`
4. Deploy the web app to `/var/www/html`
5. Configure Apache to serve HLS segments from `/dev/shm` at `/hls/`
6. Print your Pi's IP address

### Step 3 — Configure Roku

In **IP Camera Viewer Pro** → Settings → **PiIP**, enter the IP address printed at the end of installation (e.g. `192.168.1.70`).

---

## First-Time Setup

Open `http://<pi-ip>` in a browser. Since no user exists yet, you'll be prompted to **create an admin account**. Enter a username and password — this creates `data/validuser.txt`.

Log in and add your cameras:

| Option | Use when |
|--------|----------|
| **Add RTSP Camera Manually** | You know the RTSP URL |
| **Discover ONVIF Cameras** | Camera supports ONVIF (auto-scan) |

---

## Architecture / File Overview

```
rokuphp-arm64/
├── install.sh              ← ARM64-aware installer
├── build.sh                ← Packages html/ → html.tar.gz
├── html/
│   ├── index.php           ← Login / Create user
│   ├── logout.php          ← Session logout
│   ├── menu.php            ← Camera list (main menu)
│   ├── manualm.php         ← Add / edit RTSP camera
│   ├── onvifm.php          ← ONVIF scan & add
│   ├── delete.php          ← Delete camera
│   ├── broadcastm.php      ← Live broadcast (YouTube / Twitch)
│   ├── getcameras.php      ← Roku API: returns camera list
│   ├── getstream.php       ← Roku API: starts HLS, returns .m3u8 URL
│   ├── includes/
│   │   ├── auth.php        ← Session auth helpers
│   │   ├── db.php          ← Camera data & ffmpeg helpers
│   │   └── layout.php      ← Bootstrap 5 HTML helpers
│   ├── lib/
│   │   └── class.ponvif.php ← ONVIF library (unchanged)
│   ├── config/
│   │   └── streamer.xml    ← ffmpeg / HLS / live-stream config
│   └── data/               ← Camera data (auto-created, writable by www-data)
│       └── cameras.xml     ← Camera list (auto-created)
```

### How Streaming Works

```
Roku → GET /getstream.php?cam=FrontDoor
         └→ PHP looks up RTSP URL from cameras.xml
         └→ Kills any stale ffmpeg
         └→ Starts: ffmpeg [options] rtsp://... /dev/shm/RANDOM.m3u8
         └→ Waits up to 20s for .m3u8 to appear
         └→ Returns "/hls/RANDOM.m3u8"
Roku → GET /hls/RANDOM.m3u8    (served by Apache from /dev/shm)
```

HLS segments live in `/dev/shm` (RAM disk) for low-latency, no SD card wear.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| White page / 500 error | `sudo tail -f /var/log/apache2/error.log` |
| `getstream.php` returns `error` | Check ffmpeg can reach the RTSP URL: `ffmpeg -i rtsp://...` |
| ONVIF scan finds no cameras | Ensure Pi and cameras are on same subnet; check firewall |
| Roku can't reach Pi | Confirm Pi IP in Roku settings; check Pi firewall |
| PHP version mismatch | `php -v` — must be 8.x; `sudo apt install php8.2` |

### Restart services

```bash
sudo systemctl restart apache2
```

### Re-run installer without wiping cameras

Use option **2** (dirty install) — it skips removing packages and `/var/www/html`.

---

## Credits

Original project by [e1Ioan](https://github.com/e1ioan/rokuphp).
ARM64 / PHP 8 upgrade — 2026.
