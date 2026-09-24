module educonnect/identity-grpc

go 1.23.0

require (
	educonnect/internal/pkg/proto v0.0.0-00010101000000-000000000000
	go.uber.org/zap v1.27.0
	google.golang.org/grpc v1.70.0
)

require (
	github.com/planetscale/vtprotobuf v0.6.1-0.20240319094008-0393e58bdf10 // indirect
	go.uber.org/multierr v1.10.0 // indirect
	golang.org/x/net v0.43.0 // indirect
	golang.org/x/sys v0.35.0 // indirect
	golang.org/x/text v0.28.0 // indirect
	google.golang.org/genproto/googleapis/rpc v0.0.0-20250804133106-a7a43d27e69b // indirect
	google.golang.org/protobuf v1.36.10 // indirect
)

replace (
	educonnect/internal/pkg/proto => ../../internal/pkg/proto
	github.com/educonnect/educonnect/internal/pkg/proto => ../../internal/pkg/proto
)
