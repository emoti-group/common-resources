#!/usr/bin/env bash
set -euo pipefail

CERT_DIR="./.traefik-devcerts"

CA_KEY="${CERT_DIR}/localCA.key"
CA_CRT="${CERT_DIR}/localCA.crt"
CA_SRL="${CERT_DIR}/localCA.srl"

EXT_FILE="${CERT_DIR}/emoti.ext"
LEAF_KEY="${CERT_DIR}/emoti.key"
LEAF_CSR="${CERT_DIR}/emoti.csr"
LEAF_CRT="${CERT_DIR}/emoti.crt"

mkdir -p "${CERT_DIR}"

echo "==> Using cert dir: ${CERT_DIR}"

# -------------------------
# 1) Root CA (key + cert)
# -------------------------
if [[ ! -f "${CA_KEY}" ]]; then
  echo "==> Generating CA private key: ${CA_KEY}"
  openssl genrsa -out "${CA_KEY}" 4096
else
  echo "==> CA key exists: ${CA_KEY}"
fi

if [[ ! -f "${CA_CRT}" ]]; then
  echo "==> Generating CA certificate: ${CA_CRT}"
  openssl req -x509 -new -nodes -key "${CA_KEY}" -sha256 -days 3650 \
    -out "${CA_CRT}" \
    -subj "/C=DE/O=Local Dev/CN=Local Dev Root CA"
else
  echo "==> CA cert exists: ${CA_CRT}"
fi

# -------------------------
# 2) Trust CA in macOS keychain (only if not already trusted)
# -------------------------
# We identify the cert by SHA-1 fingerprint.
if command -v security >/dev/null 2>&1; then
  echo "==> Checking macOS trust store for CA..."
  CA_SHA1="$(openssl x509 -noout -fingerprint -sha1 -in "${CA_CRT}" | sed 's/^.*=//; s/://g')"

  if security find-certificate -a -Z /Library/Keychains/System.keychain 2>/dev/null | grep -q "${CA_SHA1}"; then
    echo "==> CA is already present in System Keychain (sha1=${CA_SHA1})"
  else
    echo "==> Adding CA to System Keychain (requires sudo)..."
    sudo security add-trusted-cert -d -r trustRoot \
      -k /Library/Keychains/System.keychain \
      "${CA_CRT}"
  fi
else
  echo "==> 'security' tool not found; skipping macOS trust step."
fi

# -------------------------
# 3) .ext file for wildcard SANs
# -------------------------
if [[ ! -f "${EXT_FILE}" ]]; then
  echo "==> Creating ext file: ${EXT_FILE}"
  cat > "${EXT_FILE}" <<'EOF'
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = api.emoti
DNS.2 = *.agcore.emoti
EOF
else
  echo "==> Ext file exists: ${EXT_FILE}"
fi

# -------------------------
# 4) Leaf key + CSR
# -------------------------
if [[ ! -f "${LEAF_KEY}" ]]; then
  echo "==> Generating leaf private key: ${LEAF_KEY}"
  openssl genrsa -out "${LEAF_KEY}" 2048
else
  echo "==> Leaf key exists: ${LEAF_KEY}"
fi

if [[ ! -f "${LEAF_CSR}" ]]; then
  echo "==> Generating CSR: ${LEAF_CSR}"
  # CN doesn't matter much when SANs exist, but keep it sane.
  openssl req -new -key "${LEAF_KEY}" -out "${LEAF_CSR}" \
    -subj "/C=DE/O=Local Dev/CN=*.emoti"
else
  echo "==> CSR exists: ${LEAF_CSR}"
fi

# -------------------------
# 5) Leaf cert signed by CA
# -------------------------
if [[ ! -f "${LEAF_CRT}" ]]; then
  echo "==> Signing certificate: ${LEAF_CRT}"
  openssl x509 -req -in "${LEAF_CSR}" -CA "${CA_CRT}" -CAkey "${CA_KEY}" \
    -CAcreateserial -out "${LEAF_CRT}" -days 825 -sha256 -extfile "${EXT_FILE}"
else
  echo "==> Leaf cert exists: ${LEAF_CRT}"
fi

echo
echo "✅ Done."
echo "CA:   ${CA_CRT}"
echo "CERT: ${LEAF_CRT}"
echo "KEY:  ${LEAF_KEY}"
