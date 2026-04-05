package main

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

// Config holds the server configuration.
type Config struct {
	Port    int    `json:"port"`
	BinPath string `json:"bin_path"`
}

// Request represents an incoming task execution request from PHP.
type Request struct {
	Task    string          `json:"task"`
	Payload json.RawMessage `json:"payload"`
	Async   bool            `json:"async"`
}

// Response represents the result sent back to PHP.
type Response struct {
	Status   string          `json:"status"`
	Output   json.RawMessage `json:"output,omitempty"`
	Error    string          `json:"error,omitempty"`
	Duration string          `json:"duration,omitempty"`
}

func main() {
	config := loadConfig()

	mux := http.NewServeMux()
	mux.HandleFunc("/govel/execute", makeExecuteHandler(config))
	mux.HandleFunc("/govel/health", healthHandler)

	addr := fmt.Sprintf(":%d", config.Port)
	log.Printf("Govel server starting on %s (bin_path: %s)", addr, config.BinPath)

	if err := http.ListenAndServe(addr, mux); err != nil {
		log.Fatalf("Server failed: %v", err)
	}
}

func loadConfig() Config {
	config := Config{
		Port:    9800,
		BinPath: "./bin",
	}

	if port := os.Getenv("GOVEL_PORT"); port != "" {
		fmt.Sscanf(port, "%d", &config.Port)
	}
	if binPath := os.Getenv("GOVEL_BIN_PATH"); binPath != "" {
		config.BinPath = binPath
	}

	// Also accept a config file
	if data, err := os.ReadFile("govel-server.json"); err == nil {
		json.Unmarshal(data, &config)
	}

	return config
}

func makeExecuteHandler(config Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			http.Error(w, `{"error":"method not allowed"}`, http.StatusMethodNotAllowed)
			return
		}

		body, err := io.ReadAll(r.Body)
		if err != nil {
			writeError(w, "failed to read request body", http.StatusBadRequest)
			return
		}
		defer r.Body.Close()

		var req Request
		if err := json.Unmarshal(body, &req); err != nil {
			writeError(w, "invalid JSON: "+err.Error(), http.StatusBadRequest)
			return
		}

		if req.Task == "" {
			writeError(w, "task name is required", http.StatusBadRequest)
			return
		}

		// Health check task
		if req.Task == "__health" {
			writeJSON(w, Response{Status: "healthy"})
			return
		}

		binaryPath := resolveBinary(config.BinPath, req.Task)
		if _, err := os.Stat(binaryPath); os.IsNotExist(err) {
			writeError(w, fmt.Sprintf("binary not found: %s", req.Task), http.StatusNotFound)
			return
		}

		if req.Async {
			go executeBinary(binaryPath, req.Payload)
			writeJSON(w, Response{Status: "dispatched"})
			return
		}

		start := time.Now()
		cmd := exec.Command(binaryPath)
		cmd.Stdin = strings.NewReader(string(req.Payload))

		output, err := cmd.Output()
		duration := time.Since(start)

		if err != nil {
			stderr := ""
			if exitErr, ok := err.(*exec.ExitError); ok {
				stderr = string(exitErr.Stderr)
			}
			writeJSON(w, Response{
				Status:   "error",
				Error:    stderr,
				Duration: duration.String(),
			})
			return
		}

		writeJSON(w, Response{
			Status:   "success",
			Output:   json.RawMessage(output),
			Duration: duration.String(),
		})
	}
}

func healthHandler(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, Response{Status: "healthy"})
}

func executeBinary(binaryPath string, payload json.RawMessage) {
	cmd := exec.Command(binaryPath)
	cmd.Stdin = strings.NewReader(string(payload))

	output, err := cmd.CombinedOutput()
	if err != nil {
		log.Printf("Async task failed (%s): %v — %s", binaryPath, err, string(output))
		return
	}

	log.Printf("Async task completed (%s): %s", binaryPath, string(output))
}

func resolveBinary(binPath, taskName string) string {
	binary := taskName
	if runtime.GOOS == "windows" && !strings.HasSuffix(binary, ".exe") {
		binary += ".exe"
	}
	return filepath.Join(binPath, binary)
}

func writeJSON(w http.ResponseWriter, v interface{}) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(v)
}

func writeError(w http.ResponseWriter, msg string, status int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(Response{Status: "error", Error: msg})
}
