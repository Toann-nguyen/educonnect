.PHONY: generate clean

PROTO_DIR := .
GO_OUT := .

# Tìm tất cả file .proto
PROTOS := $(shell find $(PROTO_DIR) -name "*.proto")

generate: $(PROTOS)
	@echo "Generating Go code from Protobuf..."
	protoc --go_out=$(GO_OUT) --go_opt=paths=source_relative \
		--go-grpc_out=$(GO_OUT) --go-grpc_opt=paths=source_relative \
		--go-vtproto_out=$(GO_OUT) --go-vtproto_opt=paths=source_relative \
		-I$(PROTO_DIR) $(PROTOS)
	@echo "Generation complete!"

clean:
	@echo "Cleaning generated files..."
	find $(GO_OUT) -name "*.pb.go" -delete
	find $(GO_OUT) -name "*.vt.pb.go" -delete
	@echo "Clean complete!"
