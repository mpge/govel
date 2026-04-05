// Package govel provides helpers for building Govel Go workers.
//
// Usage:
//
//	func main() {
//	    govel.Run(func(payload json.RawMessage) (interface{}, error) {
//	        var req MyRequest
//	        if err := json.Unmarshal(payload, &req); err != nil {
//	            return nil, err
//	        }
//	        // do work...
//	        return MyResponse{Status: "done"}, nil
//	    })
//	}
package govel

import (
	"encoding/json"
	"fmt"
	"io"
	"os"
	"time"
)

// Handler processes a JSON payload and returns a result or error.
type Handler func(payload json.RawMessage) (interface{}, error)

// Run reads JSON from stdin, passes it to the handler, and writes
// the JSON result to stdout. On error, it writes to stderr and exits 1.
//
// This is the main entry point for Govel Go workers.
func Run(handler Handler) {
	start := time.Now()

	input, err := io.ReadAll(os.Stdin)
	if err != nil {
		Fatal("failed to read stdin: " + err.Error())
	}

	// Validate input is valid JSON
	if !json.Valid(input) {
		Fatal("invalid JSON input")
	}

	result, err := handler(json.RawMessage(input))
	if err != nil {
		Fatal(err.Error())
	}

	output, err := json.Marshal(result)
	if err != nil {
		Fatal("failed to marshal response: " + err.Error())
	}

	// Write response
	fmt.Print(string(output))

	// Log duration to stderr (won't interfere with stdout protocol)
	duration := time.Since(start)
	if os.Getenv("GOVEL_DEBUG") == "1" {
		fmt.Fprintf(os.Stderr, "[govel] completed in %s\n", duration)
	}
}

// Fatal writes an error message to stderr and exits with code 1.
func Fatal(msg string) {
	fmt.Fprint(os.Stderr, msg)
	os.Exit(1)
}

// Parse unmarshals a JSON payload into the target struct.
// Returns a clear error if parsing fails.
func Parse[T any](payload json.RawMessage) (T, error) {
	var result T
	if err := json.Unmarshal(payload, &result); err != nil {
		return result, fmt.Errorf("failed to parse payload: %w", err)
	}
	return result, nil
}

// MustParse unmarshals a JSON payload into the target struct.
// Calls Fatal if parsing fails.
func MustParse[T any](payload json.RawMessage) T {
	result, err := Parse[T](payload)
	if err != nil {
		Fatal(err.Error())
	}
	return result
}

// Require checks that a condition is true, and calls Fatal with the
// given message if it is not. Useful for input validation.
func Require(condition bool, msg string) {
	if !condition {
		Fatal(msg)
	}
}
