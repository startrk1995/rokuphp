#!/bin/bash

clear

RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

if (( $EUID != 0 )); then
    echo -e "${RED}Please run as root: sudo ./install.sh${NC}"
    exit 1
fi

# Detect architecture
ARCH=$(uname -m)
echo -e "${GREEN}Detected architecture: $ARCH${NC}"

# Verify ARM64 / aarch64
if [[ "$ARCH" != "aarch64" && "$ARCH" != "arm64" ]]; then
    echo -e "${YELLOW}Warning: This script is optimised for ARM64 (aarch64).${NC}"
    echo -e "${YELLOW}Detected: $ARCH — continuing anyway.${NC}"
fi

# Detect OS release
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_NAME="$NAME"
    OS_VER="$VERSION_CODENAME"
else
    OS_NAME="Unknown"
    OS_VER="unknown"
fi
echo -e "${GREEN}OS: $OS_NAME ($OS_VER)${NC}"

echo "---------------------"
echo -e "${YELLOW}PLEASE READ CAREFULLY${NC}"
echo "---------------------"
echo "This script will install everything needed to interface with Roku IP Camera Viewer Pro"
echo -e "${YELLOW}Press 1 (${NC}${GREEN}recommended option${NC}${YELLOW}) To clean install all the needed packages."
echo -e "\t This option will remove apache2, php, ffmpeg first and then reinstall them.${NC}\n"
echo -e "${YELLOW}Press 2 to skip the uninstall process (dirty install)${NC}"
echo -e "${YELLOW}Press 0 to exit this script and abandon the installation${NC}"

echo "Enter your option:"
while read line
do
  case $line in
        0)
          echo -e "${RED}Exit selected!${NC}"
          exit 0
        ;;
        1)
          echo -e "${YELLOW}Clean install selected.${NC}"
          break
        ;;
        2)
          echo -e "${YELLOW}Dirty install selected.${NC}"
          break
        ;;
        *)
          echo -e "${RED}Not valid, try again: 0=exit, 1=clean install, 2=dirty install${NC}"
          echo "Enter your option:"
        ;;
  esac
done

echo -e "${GREEN}Stopping apache2${NC}"
systemctl stop apache2 2>/dev/null || true

if [ "$line" == "1" ]; then
    echo -e "${GREEN}Uninstalling existing packages${NC}"
    rm -rf /var/www/html
    apt-get remove --purge ffmpeg -y 2>/dev/null || true
    apt-get remove --purge apache2 apache2-utils apache2-bin -y 2>/dev/null || true
    apt-get purge 'php*' -y 2>/dev/null || true
    apt-get autoremove -y
fi

ROKUPHP="# Added by rokuphp arm64 install"

echo -e "${GREEN}Updating package lists${NC}"
apt-get update -y

echo -e "${GREEN}Installing ffmpeg${NC}"
apt-get install -y ffmpeg

echo -e "${GREEN}Installing Apache2${NC}"
apt-get install -y apache2

# Detect available PHP version (8.x preferred)
echo -e "${GREEN}Detecting PHP version${NC}"
if apt-cache show php8.3 &>/dev/null; then
    PHP_VER="8.3"
elif apt-cache show php8.2 &>/dev/null; then
    PHP_VER="8.2"
elif apt-cache show php8.1 &>/dev/null; then
    PHP_VER="8.1"
elif apt-cache show php8.0 &>/dev/null; then
    PHP_VER="8.0"
else
    PHP_VER=""
fi

if [ -n "$PHP_VER" ]; then
    echo -e "${GREEN}Installing PHP $PHP_VER${NC}"
    apt-get install -y \
        php${PHP_VER} \
        php${PHP_VER}-curl \
        php${PHP_VER}-xml \
        php${PHP_VER}-mbstring \
        libapache2-mod-php${PHP_VER}
else
    echo -e "${GREEN}Installing default PHP${NC}"
    apt-get install -y php php-curl php-xml php-mbstring libapache2-mod-php
fi

# Ensure required Apache modules are enabled
a2enmod php* 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

echo -e "${GREEN}Preparing web directory${NC}"
mkdir -p /var/www/html

# Backup existing index.html if present
if [ -f /var/www/html/index.html ]; then
    mv /var/www/html/index.html /var/www/html/index-old.html
fi

echo -e "${GREEN}Extracting rokuphp web files${NC}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -f "$SCRIPT_DIR/html.tar.gz" ]; then
    tar -xzf "$SCRIPT_DIR/html.tar.gz" --directory /var/www
    echo -e "${GREEN}Extracted from local archive${NC}"
else
    echo -e "${YELLOW}Local html.tar.gz not found. Downloading from GitHub...${NC}"
    apt-get install -y wget 2>/dev/null || true
    wget --no-http-keep-alive -O /tmp/html.tar.gz \
        "https://github.com/e1ioan/rokuphp/raw/master/html/html.tar.gz"
    tar -xzf /tmp/html.tar.gz --directory /var/www
    rm -f /tmp/html.tar.gz
fi

echo -e "${GREEN}Setting directory permissions${NC}"
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R g+rw /var/www/html

# Create writable data directory
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data
chmod -R 775 /var/www/html/data

# HLS output uses tmpfs (/dev/shm) — ensure it is accessible
chmod 1777 /dev/shm 2>/dev/null || true

echo -e "${GREEN}Configuring Apache2${NC}"
APACHECONFIG=/etc/apache2/apache2.conf

if grep -q "$ROKUPHP" "$APACHECONFIG"; then
    echo -e "${GREEN}Apache config already configured${NC}"
else
    echo -e "${GREEN}Adding HLS alias to apache2.conf${NC}"
    cat >> "$APACHECONFIG" << 'EOF'
# Added by rokuphp arm64 install
Alias /hls /dev/shm
<Directory /dev/shm>
        Options Indexes FollowSymLinks
        Require all granted
</Directory>
EOF
fi

# Also configure VirtualHost AllowOverride if needed
SITE_CONF=/etc/apache2/sites-available/000-default.conf
if ! grep -q "AllowOverride All" "$SITE_CONF" 2>/dev/null; then
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html\n\t<Directory /var/www/html>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>|' "$SITE_CONF" 2>/dev/null || true
fi

echo -e "${GREEN}Restarting Apache2${NC}"
systemctl daemon-reload
systemctl enable apache2
systemctl restart apache2

# Get the local IP address (works on all modern Linux distros, no ifconfig needed)
LOCALIP=$(hostname -I | awk '{print $1}')
if [ -z "$LOCALIP" ]; then
    LOCALIP=$(ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
fi

echo ""
echo -e "${YELLOW}---------------------${NC}"
echo -e "${YELLOW}DONE INSTALLING!${NC}"
echo -e "${YELLOW}---------------------${NC}"
echo ""
echo -e "${GREEN}Architecture: $ARCH${NC}"
if [ -n "$PHP_VER" ]; then
    echo -e "${GREEN}PHP version: $PHP_VER${NC}"
fi
echo ""
echo -e "${YELLOW}Now go to 'IP Camera Viewer Pro' on Roku, and in settings,"
echo -e "in the field PiIP enter: ${GREEN}$LOCALIP${YELLOW}${NC}"
echo -e "${YELLOW}To configure your cameras, open in your browser: ${GREEN}http://$LOCALIP${NC}"
echo ""
echo -e "${YELLOW}If you need to restart the web server:${NC}"
echo -e "${GREEN}  sudo systemctl restart apache2${NC}"
