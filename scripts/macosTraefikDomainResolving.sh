#!/bin/sh
set -eu

# Usage: ./macosTraefikDomainResolving.sh [domain1 domain2 ...]
# Default domain used if none given.
if [ "$#" -gt 0 ]; then
  DOMAINS="$*"
else
  DOMAINS="emoti test"
fi

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

echo "➡️ Using brew prefix: ${BREW_PREFIX}"
echo "➡️ Setting wildcard DNS for domains: ${DOMAINS} -> ${IP}"

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

# Build dnsmasq address lines: one per domain
ADDRESS_LINES=""
for DOMAIN in ${DOMAINS}; do
  ADDRESS_LINES="${ADDRESS_LINES}address=/.${DOMAIN}/${IP}
"
done

# Write dnsmasq config:
# - map *.<domain> to 127.0.0.1 for each domain
# - listen only on localhost
# - use port 53
echo "➡️ Writing ${DNSMASQ_CONF_FILE}"
sudo tee "${DNSMASQ_CONF_FILE}" >/dev/null <<EOF
# Managed by setup-wildcard-dns.sh
${ADDRESS_LINES}listen-address=${IP}
port=53
EOF

# Create resolver file per domain so macOS sends *.<domain> queries to 127.0.0.1
sudo mkdir -p "${RESOLVER_DIR}"
for DOMAIN in ${DOMAINS}; do
  RESOLVER_FILE="${RESOLVER_DIR}/${DOMAIN}"
  echo "➡️ Writing ${RESOLVER_FILE}"
  sudo tee "${RESOLVER_FILE}" >/dev/null <<EOF
# Managed by setup-wildcard-dns.sh
nameserver ${IP}
EOF
done

# Start dnsmasq as a service
echo "➡️ Starting dnsmasq..."
# Needs sudo because it binds to port 53
sudo brew services restart dnsmasq

# Flush DNS cache
echo "➡️ Flushing macOS DNS cache..."
sudo dscacheutil -flushcache || true
sudo killall -HUP mDNSResponder || true

for DOMAIN in ${DOMAINS}; do
  dig "test.${DOMAIN}" @${IP}
  ping -c 1 "test.${DOMAIN}"
done

echo "✅ Done."
echo ""
echo "🔎 Quick tests:"
for DOMAIN in ${DOMAINS}; do
  echo "  dig test.${DOMAIN} @${IP}"
  echo "  ping -c 1 test.${DOMAIN}"
done
echo ""
echo "If something fails, check who is listening on port 53:"
echo "  sudo lsof -i :53"
