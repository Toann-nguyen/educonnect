package grpcserver

import (
	"testing"

	"educonnect/finance/internal/model"
	"educonnect/finance/internal/pkg/proto/common"
	"educonnect/finance/internal/pkg/proto/finance"
)

func TestParseID(t *testing.T) {
	id, err := parseID("42", "invoice ID")
	if err != nil || id != 42 {
		t.Fatalf("expected 42, got %d, %v", id, err)
	}
	for _, value := range []string{"", "0", "-1", "abc"} {
		if _, err := parseID(value, "invoice ID"); err == nil {
			t.Fatalf("expected error for %q", value)
		}
	}
}

func TestMoneyRoundTrip(t *testing.T) {
	money := moneyFromFloat(1000000.4, "")
	if money.Amount != 1000000 || money.Currency != defaultCurrency {
		t.Fatalf("unexpected money: %+v", money)
	}
	amount, currency, err := moneyToFloat(&common.Money{Amount: 1500, Currency: "VND"})
	if err != nil || amount != 1500 || currency != "VND" {
		t.Fatalf("unexpected conversion: %v %v %v", amount, currency, err)
	}
	if _, _, err := moneyToFloat(&common.Money{Amount: -1}); err == nil {
		t.Fatal("expected negative amount to fail")
	}
}

func TestInvoiceStatusMapping(t *testing.T) {
	if got := invoiceStatusToProto("paid"); got != finance.InvoiceStatus_INVOICE_STATUS_PAID {
		t.Fatalf("unexpected proto status: %v", got)
	}
	if got, err := invoiceStatusToModel(finance.InvoiceStatus_INVOICE_STATUS_OVERDUE); err != nil || got != "overdue" {
		t.Fatalf("unexpected model status: %v %v", got, err)
	}
	if _, err := invoiceStatusToModel(finance.InvoiceStatus_INVOICE_STATUS_UNSPECIFIED); err == nil {
		t.Fatal("expected unspecified status to fail")
	}
}

func TestProtoInvoiceDerivedAmounts(t *testing.T) {
	invoice := model.Invoice{ID: 7, StudentID: 10, Amount: 100, Currency: "VND", Status: "pending"}
	protoInvoice := protoInvoice(invoice, nil, 40)
	if protoInvoice.GetTotalAmount().GetAmount() != 100 {
		t.Fatalf("unexpected total: %+v", protoInvoice.GetTotalAmount())
	}
	if protoInvoice.GetPaidAmount().GetAmount() != 40 {
		t.Fatalf("unexpected paid: %+v", protoInvoice.GetPaidAmount())
	}
	if protoInvoice.GetRemainingAmount().GetAmount() != 60 {
		t.Fatalf("unexpected remaining: %+v", protoInvoice.GetRemainingAmount())
	}
	if len(protoInvoice.GetItems()) != 1 {
		t.Fatalf("expected compatibility item, got %d", len(protoInvoice.GetItems()))
	}
}

func TestPaginationBounds(t *testing.T) {
	response := paginate(45, &common.PaginationRequest{Page: 2, PageSize: 20})
	if response.GetTotalPages() != 3 || response.GetCurrentPage() != 2 || !response.GetHasNext() || !response.GetHasPrev() {
		t.Fatalf("unexpected pagination: %+v", response)
	}
	capped := paginate(1, &common.PaginationRequest{PageSize: 1000})
	if capped.GetPageSize() != 100 {
		t.Fatalf("expected capped page size, got %+v", capped)
	}
}
