#!/bin/sh
set -eu

DOMAIN="emoti"
IP="127.0.0.1"

# Detect Homebrew prefix (Intel vs Apple Silicon)
if command -v brew >/dev/null 2>&1; then
  BREW_PREFIX="$(brew --prefix)"
else
  echo "❌ Homebrew (brew) not found. Install it first: https://brew.sh"
  exit 1
fi

DNSMASQ_CONF_DIR="${BREW_PREFIX}/etc"
DNSMASQ_CONF_FILE="${DNSMASQ_CONF_DIR}/dnsmasq.conf"
RESOLVER_DIR="/etc/resolver"
RESOLVER_FILE="${RESOLVER_DIR}/${DOMAIN}"

echo "➡️ Using brew prefix: ${BREW_PREFIX}"
echo "➡️ Setting wildcard DNS: *.${DOMAIN} -> ${IP}"

# Install dnsmasq if missing
if ! brew list dnsmasq >/dev/null 2>&1; then
  echo "➡️ Installing dnsmasq..."
  brew install dnsmasq
else
  echo "✅ dnsmasq already installed"
fi

# Ensure dnsmasq config directory exists
sudo mkdir -p "${DNSMASQ_CONF_DIR}"

# Backup existing config if present
if [ -f "${DNSMASQ_CONF_FILE}" ]; then
  TS="$(date +%Y%m%d%H%M%S)"
  echo "➡️ Backing up existing dnsmasq.conf -> dnsmasq.conf.bak.${TS}"
  sudo cp "${DNSMASQ_CONF_FILE}" "${DNSMASQ_CONF_FILE}.bak.${TS}"
fi

# Write a minimal dnsmasq config:
# - map *.emoti to 127.0.0.1
# - listen only on localhost
# - use port 53
echo "➡️ Writing ${DNSMASQ_CONF_FILE}"
sudo tee "${DNSMASQ_CONF_FILE}" >/dev/null <<EOF
# Managed by setup-wildcard-dns.sh
address=/.${DOMAIN}/${IP}
listen-address=${IP}
port=53
EOF

# Create resolver file so macOS sends *.emoti queries to 127.0.0.1
echo "➡️ Writing ${RESOLVER_FILE}"
sudo mkdir -p "${RESOLVER_DIR}"
sudo tee "${RESOLVER_FILE}" >/dev/null <<EOF
# Managed by setup-wildcard-dns.sh
nameserver ${IP}
EOF

# Start dnsmasq as a service
echo "➡️ Starting dnsmasq..."
# Needs sudo because it binds to port 53
sudo brew services restart dnsmasq

# Flush DNS cache
echo "➡️ Flushing macOS DNS cache..."
sudo dscacheutil -flushcache || true
sudo killall -HUP mDNSResponder || true

dig test.${DOMAIN} @${IP}

ping -c 1 test.${DOMAIN}

echo "✅ Done."
echo ""
echo "🔎 Quick tests:"
echo "  dig test.${DOMAIN} @${IP}"
echo "  ping -c 1 test.${DOMAIN}"
echo ""
echo "If something fails, check who is listening on port 53:"
echo "  sudo lsof -i :53"
