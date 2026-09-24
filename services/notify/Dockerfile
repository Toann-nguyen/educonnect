# ============================================================
# notify-service — Go (Gin + RabbitMQ consumer)
# Multi-stage: golang builder → alpine runtime (non-root)
# ============================================================

# ── Stage 1: Builder ─────────────────────────────────────────
FROM golang:1.26-alpine AS builder

WORKDIR /src

# Shared protobuf module (go.mod replace ../../internal/pkg/proto)
COPY internal/pkg/proto /src/internal/pkg/proto

COPY services/notify/go.mod services/notify/go.sum /src/services/notify/

WORKDIR /src/services/notify
RUN go mod download

COPY services/notify/. .

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -trimpath -ldflags="-s -w" -o /out/main .

# ── Stage 2: Runtime ─────────────────────────────────────────
FROM alpine:3.21

RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S app \
    && adduser -S app -G app -u 10001

WORKDIR /app
COPY --from=builder /out/main .

USER app

EXPOSE 8081 50052

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8081/health >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/app/main"]
