# ============================================================
# finance-service — Go (Gin + GORM + MySQL)
# Multi-stage tối ưu: builder golang → runtime alpine (non-root)
# ============================================================

# ── Stage 1: Builder ─────────────────────────────────────────
FROM golang:1.26-alpine AS builder

WORKDIR /src

# Cache layer dependencies trước (chỉ copy go.mod/go.sum)
COPY go.mod go.sum ./
RUN go mod download

COPY . .

# Static binary: CGO off, trimpath, strip symbols
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -trimpath -ldflags="-s -w" -o /out/main .

# ── Stage 2: Runtime ─────────────────────────────────────────
FROM alpine:3.21

# ca-certificates: HTTPS ra ngoài (nếu có gọi API); tzdata: đúng timezone log
RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S app \
    && adduser -S app -G app -u 10001

WORKDIR /app
COPY --from=builder /out/main .

# Non-root — security best practice
USER app

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/health >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/app/main"]
