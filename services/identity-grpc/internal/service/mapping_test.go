package service

import (
	"testing"

	"educonnect/internal/pkg/proto/auth"
)

func TestRoleMapping(t *testing.T) {
	if got := roleToProto("teacher"); got != auth.UserRole_USER_ROLE_TEACHER {
		t.Fatalf("unexpected role: %v", got)
	}
	if got := roleToProto("principal"); got != auth.UserRole_USER_ROLE_UNSPECIFIED {
		t.Fatalf("unexpected unmapped role: %v", got)
	}
	if got, err := roleToModel(auth.UserRole_USER_ROLE_PARENT); err != nil || got != "parent" {
		t.Fatalf("unexpected model role: %v %v", got, err)
	}
	if _, err := roleToModel(auth.UserRole_USER_ROLE_UNSPECIFIED); err == nil {
		t.Fatal("expected unspecified role to fail")
	}
}

func TestPasswordPolicy(t *testing.T) {
	if err := passwordPolicy("Password123"); err != nil {
		t.Fatalf("expected valid password: %v", err)
	}
	for _, password := range []string{"short1A", "alllowercase123", "ALLUPPERCASE123", "NoDigitsHere"} {
		if err := passwordPolicy(password); err == nil {
			t.Fatalf("expected %q to fail", password)
		}
	}
}

func TestValidEmail(t *testing.T) {
	if !validEmail("user@example.com") {
		t.Fatal("expected email to be valid")
	}
	for _, email := range []string{"", "not-an-email", "user@", "@example.com"} {
		if validEmail(email) {
			t.Fatalf("expected %q to be invalid", email)
		}
	}
}
