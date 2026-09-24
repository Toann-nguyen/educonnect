module educonnect/identity-grpc

go 1.25.0

require (
	educonnect/internal/pkg/proto v0.0.0-00010101000000-000000000000
	github.com/rabbitmq/amqp091-go v1.10.0
	go.uber.org/zap v1.27.0
	golang.org/x/crypto v0.53.0
	google.golang.org/grpc v1.70.0
	google.golang.org/protobuf v1.36.10
	gorm.io/driver/mysql v1.6.0
	gorm.io/gorm v1.31.2
)

require (
	filippo.io/edwards25519 v1.1.0 // indirect
	github.com/go-sql-driver/mysql v1.8.1 // indirect
	github.com/jinzhu/inflection v1.0.0 // indirect
	github.com/jinzhu/now v1.1.5 // indirect
	github.com/planetscale/vtprotobuf v0.6.1-0.20240319094008-0393e58bdf10 // indirect
	go.uber.org/multierr v1.10.0 // indirect
	golang.org/x/net v0.55.0 // indirect
	golang.org/x/sys v0.46.0 // indirect
	golang.org/x/text v0.38.0 // indirect
	google.golang.org/genproto/googleapis/rpc v0.0.0-20250804133106-a7a43d27e69b // indirect
)

replace (
	educonnect/internal/pkg/proto => ../../internal/pkg/proto
	github.com/educonnect/educonnect/internal/pkg/proto => ../../internal/pkg/proto
)
