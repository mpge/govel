package main

import (
	"encoding/json"
	"fmt"
	"io"
	"os"
	"time"
)

// Request represents the incoming JSON payload from PHP.
type Request struct {
	Path   string `json:"path"`
	Width  int    `json:"width,omitempty"`
	Height int    `json:"height,omitempty"`
}

// Response represents the JSON result sent back to PHP.
type Response struct {
	Status    string `json:"status"`
	Path      string `json:"path"`
	Width     int    `json:"width"`
	Height    int    `json:"height"`
	Format    string `json:"format"`
	SizeBytes int    `json:"size_bytes"`
	Duration  string `json:"duration"`
}

func main() {
	start := time.Now()

	input, err := io.ReadAll(os.Stdin)
	if err != nil {
		fatal("failed to read stdin: " + err.Error())
	}

	var req Request
	if err := json.Unmarshal(input, &req); err != nil {
		fatal("invalid JSON input: " + err.Error())
	}

	if req.Path == "" {
		fatal("path is required")
	}

	// Simulate image processing work
	time.Sleep(50 * time.Millisecond)

	width := req.Width
	if width == 0 {
		width = 1920
	}
	height := req.Height
	if height == 0 {
		height = 1080
	}

	resp := Response{
		Status:    "processed",
		Path:      req.Path,
		Width:     width,
		Height:    height,
		Format:    "webp",
		SizeBytes: 245760,
		Duration:  time.Since(start).String(),
	}

	out, _ := json.Marshal(resp)
	fmt.Println(string(out))
}

func fatal(msg string) {
	fmt.Fprintf(os.Stderr, `{"error":"%s"}`, msg)
	os.Exit(1)
}
