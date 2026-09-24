FROM golang:1.26-alpine AS builder

WORKDIR /src
COPY internal/pkg/proto /src/internal/pkg/proto
COPY services/identity-grpc/go.mod services/identity-grpc/go.sum /src/services/identity-grpc/

WORKDIR /src/services/identity-grpc
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/main .

FROM alpine:3.21
RUN apk add --no-cache ca-certificates \
    && addgroup -S app \
    && adduser -S app -G app -u 10001
WORKDIR /app
COPY --from=builder /out/main .
USER app
EXPOSE 50051 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD wget -qO- http://127.0.0.1:8080/health >/dev/null 2>&1 || exit 1
ENTRYPOINT ["/app/main"]
