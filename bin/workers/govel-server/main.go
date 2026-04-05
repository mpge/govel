package main

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"runtime"
	"strings"
	"syscall"
	"time"
)

const maxRequestBody = 10 * 1024 * 1024 // 10MB

// Config holds the server configuration.
type Config struct {
	Port    int    `json:"port"`
	BinPath string `json:"bin_path"`
	Timeout int    `json:"timeout"` // seconds, 0 = no timeout
}

// Request represents an incoming task execution request from PHP.
type Request struct {
	Task    string          `json:"task"`
	Payload json.RawMessage `json:"payload"`
	Async   bool            `json:"async"`
}

// Response represents the JSON result sent back to PHP.
type Response struct {
	Status    string          `json:"status"`
	Output    json.RawMessage `json:"output,omitempty"`
	Error     string          `json:"error,omitempty"`
	Duration  string          `json:"duration,omitempty"`
	RequestID string          `json:"request_id,omitempty"`
}

var requestCounter uint64

func main() {
	config := loadConfig()

	mux := http.NewServeMux()
	mux.HandleFunc("/govel/execute", makeExecuteHandler(config))
	mux.HandleFunc("/govel/health", healthHandler)

	addr := fmt.Sprintf(":%d", config.Port)

	server := &http.Server{
		Addr:         addr,
		Handler:      mux,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: time.Duration(config.Timeout+5) * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	// Graceful shutdown
	go func() {
		sigCh := make(chan os.Signal, 1)
		signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
		sig := <-sigCh
		log.Printf("Received %s, shutting down gracefully...", sig)

		ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()

		if err := server.Shutdown(ctx); err != nil {
			log.Printf("Shutdown error: %v", err)
		}
	}()

	log.Printf("Govel server starting on %s (bin_path: %s, timeout: %ds)", addr, config.BinPath, config.Timeout)

	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatalf("Server failed: %v", err)
	}

	log.Println("Server stopped.")
}

func loadConfig() Config {
	config := Config{
		Port:    9800,
		BinPath: "./bin",
		Timeout: 30,
	}

	if port := os.Getenv("GOVEL_PORT"); port != "" {
		fmt.Sscanf(port, "%d", &config.Port)
	}
	if binPath := os.Getenv("GOVEL_BIN_PATH"); binPath != "" {
		config.BinPath = binPath
	}
	if timeout := os.Getenv("GOVEL_TIMEOUT"); timeout != "" {
		fmt.Sscanf(timeout, "%d", &config.Timeout)
	}

	if data, err := os.ReadFile("govel-server.json"); err == nil {
		if err := json.Unmarshal(data, &config); err != nil {
			log.Printf("Warning: failed to parse govel-server.json: %v", err)
		}
	}

	// Resolve bin_path to absolute
	if abs, err := filepath.Abs(config.BinPath); err == nil {
		config.BinPath = abs
	}

	return config
}

func makeExecuteHandler(config Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			writeError(w, "method not allowed", http.StatusMethodNotAllowed)
			return
		}

		requestCounter++
		reqID := fmt.Sprintf("govel-%d-%d", time.Now().UnixMilli(), requestCounter)

		// Limit request body size
		body, err := io.ReadAll(io.LimitReader(r.Body, maxRequestBody))
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

		// Validate task name — no path traversal
		if !isValidTaskName(req.Task) {
			writeError(w, "invalid task name", http.StatusBadRequest)
			return
		}

		// Health check task
		if req.Task == "__health" {
			writeJSON(w, Response{Status: "healthy", RequestID: reqID})
			return
		}

		binaryPath := resolveBinary(config.BinPath, req.Task)

		// Verify binary exists and is within bin_path
		if !isPathWithin(binaryPath, config.BinPath) {
			writeError(w, "invalid binary path", http.StatusBadRequest)
			return
		}

		info, err := os.Stat(binaryPath)
		if os.IsNotExist(err) {
			writeError(w, fmt.Sprintf("binary not found: %s", req.Task), http.StatusNotFound)
			return
		}
		if info.IsDir() {
			writeError(w, fmt.Sprintf("path is a directory, not a binary: %s", req.Task), http.StatusBadRequest)
			return
		}

		log.Printf("[%s] Executing task: %s (async: %v)", reqID, req.Task, req.Async)

		if req.Async {
			go executeBinaryAsync(binaryPath, req.Payload, reqID, config.Timeout)
			writeJSON(w, Response{Status: "dispatched", RequestID: reqID})
			return
		}

		// Synchronous execution with timeout
		timeout := time.Duration(config.Timeout) * time.Second
		if config.Timeout == 0 {
			timeout = 0
		}

		start := time.Now()
		result := executeBinary(binaryPath, req.Payload, timeout)
		duration := time.Since(start)

		result.Duration = duration.String()
		result.RequestID = reqID

		log.Printf("[%s] Completed: status=%s duration=%s", reqID, result.Status, result.Duration)

		writeJSON(w, result)
	}
}

func executeBinary(binaryPath string, payload json.RawMessage, timeout time.Duration) Response {
	var ctx context.Context
	var cancel context.CancelFunc

	if timeout > 0 {
		ctx, cancel = context.WithTimeout(context.Background(), timeout)
	} else {
		ctx, cancel = context.WithCancel(context.Background())
	}
	defer cancel()

	cmd := exec.CommandContext(ctx, binaryPath)
	cmd.Stdin = strings.NewReader(string(payload))
	cmd.Env = filterEnv(os.Environ())

	var stdout, stderr strings.Builder
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	if ctx.Err() == context.DeadlineExceeded {
		return Response{
			Status: "error",
			Error:  fmt.Sprintf("task timed out after %s", timeout),
		}
	}

	if err != nil {
		errMsg := strings.TrimSpace(stderr.String())
		if errMsg == "" {
			errMsg = err.Error()
		}
		return Response{
			Status: "error",
			Error:  errMsg,
		}
	}

	output := strings.TrimSpace(stdout.String())
	if output == "" {
		return Response{
			Status: "error",
			Error:  "binary produced no output",
		}
	}

	// Validate output is valid JSON
	if !json.Valid([]byte(output)) {
		return Response{
			Status: "error",
			Error:  "binary produced invalid JSON output",
		}
	}

	return Response{
		Status: "success",
		Output: json.RawMessage(output),
	}
}

func executeBinaryAsync(binaryPath string, payload json.RawMessage, reqID string, timeoutSecs int) {
	timeout := time.Duration(timeoutSecs) * time.Second
	if timeoutSecs == 0 {
		timeout = 5 * time.Minute // default async timeout
	}

	result := executeBinary(binaryPath, payload, timeout)

	if result.Status == "error" {
		log.Printf("[%s] Async task failed: %s", reqID, result.Error)
	} else {
		log.Printf("[%s] Async task completed successfully", reqID)
	}
}

func healthHandler(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, Response{Status: "healthy"})
}

// isValidTaskName ensures the task name contains only safe characters.
func isValidTaskName(name string) bool {
	for _, c := range name {
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == '-' || c == '_') {
			return false
		}
	}
	return len(name) > 0 && len(name) <= 128
}

// isPathWithin checks that resolved path stays within the base directory.
func isPathWithin(path, base string) bool {
	absPath, err1 := filepath.Abs(path)
	absBase, err2 := filepath.Abs(base)
	if err1 != nil || err2 != nil {
		return false
	}
	return strings.HasPrefix(absPath, absBase+string(filepath.Separator)) || absPath == absBase
}

// filterEnv returns a safe subset of environment variables for child processes.
func filterEnv(env []string) []string {
	allowed := map[string]bool{
		"PATH": true, "HOME": true, "TMPDIR": true, "TEMP": true, "TMP": true,
		"LANG": true, "LC_ALL": true, "USER": true, "GOPATH": true,
	}

	var filtered []string
	for _, e := range env {
		parts := strings.SplitN(e, "=", 2)
		if len(parts) == 2 && allowed[parts[0]] {
			filtered = append(filtered, e)
		}
	}
	return filtered
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
	data, err := json.Marshal(v)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		w.Write([]byte(`{"status":"error","error":"failed to encode response"}`))
		return
	}
	w.Write(data)
}

func writeError(w http.ResponseWriter, msg string, status int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	data, _ := json.Marshal(Response{Status: "error", Error: msg})
	w.Write(data)
}
