package dto

import "testing"

func TestNormalizeCurrency(t *testing.T) {
	if got := NormalizeCurrency(""); got != "VND" {
		t.Fatalf("expected VND default, got %q", got)
	}
	if got := NormalizeCurrency("usd"); got != "USD" {
		t.Fatalf("expected USD, got %q", got)
	}
	if got := NormalizeCurrency(" VND "); got != "VND" {
		t.Fatalf("expected trimmed VND, got %q", got)
	}
}
