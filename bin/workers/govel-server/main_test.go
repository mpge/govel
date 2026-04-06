package main

import (
	"strings"
	"testing"
)

func TestIsValidTaskName(t *testing.T) {
	t.Run("valid names", func(t *testing.T) {
		valid := []string{
			"process-image",
			"my_task",
			"abc123",
			"A",
			"z",
			"TaskName",
			"a-b_c-d",
			strings.Repeat("a", 128),
		}
		for _, name := range valid {
			if !isValidTaskName(name) {
				t.Errorf("expected %q to be valid", name)
			}
		}
	})

	t.Run("invalid names", func(t *testing.T) {
		invalid := []string{
			"../etc/passwd",
			"foo/bar",
			"bad.name",
			"has.dot",
			"",
			strings.Repeat("a", 129),
			"has space",
			"special!char",
			"semi;colon",
			"back\\slash",
			"at@sign",
			"hash#tag",
		}
		for _, name := range invalid {
			if isValidTaskName(name) {
				t.Errorf("expected %q to be invalid", name)
			}
		}
	})
}

func TestIsPathWithin(t *testing.T) {
	t.Run("path inside base", func(t *testing.T) {
		cases := []struct {
			path, base string
		}{
			{"/opt/bin/task", "/opt/bin"},
			{"/opt/bin/sub/task", "/opt/bin"},
			{"/opt/bin", "/opt/bin"},
		}
		for _, tc := range cases {
			if !isPathWithin(tc.path, tc.base) {
				t.Errorf("expected isPathWithin(%q, %q) = true", tc.path, tc.base)
			}
		}
	})

	t.Run("path outside base", func(t *testing.T) {
		cases := []struct {
			path, base string
		}{
			{"/opt/bin/../etc/passwd", "/opt/bin"},
			{"/opt/other/task", "/opt/bin"},
			{"/opt/bi", "/opt/bin"},
		}
		for _, tc := range cases {
			if isPathWithin(tc.path, tc.base) {
				t.Errorf("expected isPathWithin(%q, %q) = false", tc.path, tc.base)
			}
		}
	})
}

func TestFilterEnv(t *testing.T) {
	t.Run("allows permitted vars", func(t *testing.T) {
		input := []string{
			"PATH=/usr/bin",
			"HOME=/home/user",
			"LANG=en_US.UTF-8",
			"USER=testuser",
			"GOPATH=/go",
			"TMPDIR=/tmp",
			"TEMP=/tmp",
			"TMP=/tmp",
			"LC_ALL=C",
		}
		result := filterEnv(input)
		if len(result) != len(input) {
			t.Errorf("expected %d vars, got %d", len(input), len(result))
		}
		for i, v := range result {
			if v != input[i] {
				t.Errorf("expected %q, got %q", input[i], v)
			}
		}
	})

	t.Run("filters sensitive vars", func(t *testing.T) {
		input := []string{
			"PATH=/usr/bin",
			"AWS_SECRET_ACCESS_KEY=supersecret",
			"DATABASE_URL=postgres://...",
			"GOVEL_AUTH_TOKEN=mytoken",
			"API_KEY=abc123",
			"HOME=/home/user",
		}
		result := filterEnv(input)
		if len(result) != 2 {
			t.Errorf("expected 2 vars, got %d: %v", len(result), result)
		}
		expected := map[string]bool{
			"PATH=/usr/bin":    true,
			"HOME=/home/user":  true,
		}
		for _, v := range result {
			if !expected[v] {
				t.Errorf("unexpected env var in output: %q", v)
			}
		}
	})

	t.Run("empty input", func(t *testing.T) {
		result := filterEnv([]string{})
		if len(result) != 0 {
			t.Errorf("expected empty result, got %v", result)
		}
	})

	t.Run("malformed entries ignored", func(t *testing.T) {
		input := []string{
			"NOEQUALS",
			"PATH=/usr/bin",
		}
		result := filterEnv(input)
		if len(result) != 1 {
			t.Errorf("expected 1 var, got %d: %v", len(result), result)
		}
	})
}
