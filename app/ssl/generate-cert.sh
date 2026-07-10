#!/bin/bash

# Generate SSL certificate for chinhanh.local and subdomains

SSL_DIR="."
CERT_NAME="chinhanh.local"

# ─── DOMAIN LIST ─────────────────────────────────────────────────────────────
# Chỉ cần thêm tên domain gốc (không có *.local prefix).
# Script sẽ tự generate: domain.local + *.domain.local cho mỗi entry.
DOMAINS=(
    chinhanh
)
# ─────────────────────────────────────────────────────────────────────────────

SAN_LIST=()
for domain in "${DOMAINS[@]}"; do
    SAN_LIST+=("${domain}.local" "*.${domain}.local")
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " GenAnh Local SSL Certificate Generator"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Domains to cover (${#SAN_LIST[@]} SANs):"
for entry in "${SAN_LIST[@]}"; do
    echo "  + $entry"
done
echo ""

mkdir -p "$SSL_DIR"

if command -v mkcert &> /dev/null; then
    echo "Tool     : mkcert $(mkcert --version 2>/dev/null || echo '')"
    echo "Output   : $SSL_DIR/$CERT_NAME.pem"
    echo "          $SSL_DIR/$CERT_NAME-key.pem"
    echo ""

    cd "$SSL_DIR"

    mkcert \
        -cert-file "$CERT_NAME.pem" \
        -key-file "$CERT_NAME-key.pem" \
        "${SAN_LIST[@]}"

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " SUCCESS"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " Certificate : $SSL_DIR/$CERT_NAME.pem"
    echo " Private Key : $SSL_DIR/$CERT_NAME-key.pem"
else
    echo "Tool     : openssl (mkcert not found)"
    echo "Output   : $SSL_DIR/$CERT_NAME.pem"
    echo "          $SSL_DIR/$CERT_NAME-key.pem"
    echo ""

    openssl genrsa -out "$SSL_DIR/$CERT_NAME-key.pem" 2048

    ALT_NAMES=""
    idx=1
    for entry in "${SAN_LIST[@]}"; do
        ALT_NAMES+="DNS.${idx} = ${entry}"$'\n'
        (( idx++ ))
    done

    cat > "$SSL_DIR/openssl.cnf" <<EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = v3_req

[dn]
C = VN
ST = Hanoi
L = Hanoi
O = GenAnh Development
OU = Development
CN = chinhanh.local

[v3_req]
subjectAltName = @alt_names

[alt_names]
${ALT_NAMES}
EOF

    openssl req -new -key "$SSL_DIR/$CERT_NAME-key.pem" \
        -out "$SSL_DIR/$CERT_NAME.csr" \
        -config "$SSL_DIR/openssl.cnf"

    openssl x509 -req -days 825 \
        -in "$SSL_DIR/$CERT_NAME.csr" \
        -signkey "$SSL_DIR/$CERT_NAME-key.pem" \
        -out "$SSL_DIR/$CERT_NAME.pem" \
        -extensions v3_req \
        -extfile "$SSL_DIR/openssl.cnf"

    rm -f "$SSL_DIR/$CERT_NAME.csr" "$SSL_DIR/openssl.cnf"

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " SUCCESS (self-signed — browser will show warnings)"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " Certificate : $SSL_DIR/$CERT_NAME.pem"
    echo " Private Key : $SSL_DIR/$CERT_NAME-key.pem"
    echo ""
    echo " To avoid browser warnings, install mkcert and re-run:"
    echo "   apt install mkcert   (Ubuntu/Debian)"
    echo "   brew install mkcert  (macOS)"
    echo "   choco install mkcert (Windows)"
fi

chmod 644 "$SSL_DIR/$CERT_NAME.pem" 2>/dev/null
chmod 600 "$SSL_DIR/$CERT_NAME-key.pem" 2>/dev/null

echo ""
echo " To trust certificate: mkcert -install"
echo " Restart nginx after replacing certs."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
