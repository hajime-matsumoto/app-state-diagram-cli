#!/bin/bash
set -e

# asd-cli installer
# Usage: curl -fsSL https://raw.githubusercontent.com/hajime-matsumoto/app-state-diagram-cli/main/install.sh | bash

REPO="hajime-matsumoto/app-state-diagram-cli"
BINARY_NAME="asd-cli"
INSTALL_DIR="${INSTALL_DIR:-/usr/local/bin}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Installing asd-cli...${NC}"

# Detect OS
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
case "$OS" in
    linux)  OS="linux" ;;
    darwin) OS="macos" ;;
    *)
        echo -e "${RED}Unsupported OS: $OS${NC}"
        exit 1
        ;;
esac

# Detect architecture
ARCH=$(uname -m)
case "$ARCH" in
    x86_64|amd64)   ARCH="x86_64" ;;
    aarch64|arm64)  ARCH="aarch64" ;;
    *)
        echo -e "${RED}Unsupported architecture: $ARCH${NC}"
        exit 1
        ;;
esac

BINARY="${BINARY_NAME}-${OS}-${ARCH}"
echo -e "${YELLOW}Platform: ${OS}-${ARCH}${NC}"

# Get latest release URL
RELEASE_URL="https://github.com/${REPO}/releases/latest/download/${BINARY}"

# Create temp directory
TMP_DIR=$(mktemp -d)
trap "rm -rf $TMP_DIR" EXIT

# Download
echo "Downloading from: ${RELEASE_URL}"
if command -v curl &> /dev/null; then
    curl -fsSL -o "${TMP_DIR}/${BINARY_NAME}" "${RELEASE_URL}"
elif command -v wget &> /dev/null; then
    wget -q -O "${TMP_DIR}/${BINARY_NAME}" "${RELEASE_URL}"
else
    echo -e "${RED}Error: curl or wget required${NC}"
    exit 1
fi

chmod +x "${TMP_DIR}/${BINARY_NAME}"

# Install
if [ -w "$INSTALL_DIR" ]; then
    mv "${TMP_DIR}/${BINARY_NAME}" "${INSTALL_DIR}/${BINARY_NAME}"
else
    echo -e "${YELLOW}Need sudo to install to ${INSTALL_DIR}${NC}"
    sudo mv "${TMP_DIR}/${BINARY_NAME}" "${INSTALL_DIR}/${BINARY_NAME}"
fi

echo -e "${GREEN}Installed: ${INSTALL_DIR}/${BINARY_NAME}${NC}"
echo ""
"${INSTALL_DIR}/${BINARY_NAME}" --version
