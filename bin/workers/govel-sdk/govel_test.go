package govel

import (
	"encoding/json"
	"os"
	"os/exec"
	"testing"
)

func TestParse(t *testing.T) {
	type sample struct {
		Name  string `json:"name"`
		Count int    `json:"count"`
	}

	t.Run("valid JSON", func(t *testing.T) {
		payload := json.RawMessage(`{"name":"alice","count":42}`)
		result, err := Parse[sample](payload)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if result.Name != "alice" {
			t.Errorf("expected name alice, got %q", result.Name)
		}
		if result.Count != 42 {
			t.Errorf("expected count 42, got %d", result.Count)
		}
	})

	t.Run("invalid JSON returns error", func(t *testing.T) {
		payload := json.RawMessage(`{not valid json}`)
		_, err := Parse[sample](payload)
		if err == nil {
			t.Fatal("expected error for invalid JSON, got nil")
		}
	})

	t.Run("empty payload returns error", func(t *testing.T) {
		payload := json.RawMessage(``)
		_, err := Parse[sample](payload)
		if err == nil {
			t.Fatal("expected error for empty payload, got nil")
		}
	})
}

// TestRequire_True verifies that Require with a true condition does not exit.
func TestRequire_True(t *testing.T) {
	// This should simply not panic or exit.
	Require(true, "this should not trigger")
}

// TestRequire_False uses a subprocess test pattern to verify that
// Require(false, ...) calls os.Exit(1).
func TestRequire_False(t *testing.T) {
	if os.Getenv("TEST_REQUIRE_FALSE") == "1" {
		Require(false, "condition failed")
		return
	}

	cmd := exec.Command(os.Args[0], "-test.run=^TestRequire_False$")
	cmd.Env = append(os.Environ(), "TEST_REQUIRE_FALSE=1")
	err := cmd.Run()

	if err == nil {
		t.Fatal("expected process to exit with non-zero status")
	}

	exitErr, ok := err.(*exec.ExitError)
	if !ok {
		t.Fatalf("expected *exec.ExitError, got %T: %v", err, err)
	}
	if exitErr.ExitCode() != 1 {
		t.Errorf("expected exit code 1, got %d", exitErr.ExitCode())
	}
}
