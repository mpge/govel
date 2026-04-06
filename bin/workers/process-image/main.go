package main

import (
	"encoding/json"
	"time"

	govel "github.com/mpge/govel/sdk"
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
	govel.Run(func(payload json.RawMessage) (interface{}, error) {
		start := time.Now()

		req := govel.MustParse[Request](payload)

		govel.Require(req.Path != "", "path is required")

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

		return Response{
			Status:    "processed",
			Path:      req.Path,
			Width:     width,
			Height:    height,
			Format:    "webp",
			SizeBytes: 245760,
			Duration:  time.Since(start).String(),
		}, nil
	})
}
