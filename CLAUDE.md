# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

smtp2json is a PHP-based fake SMTP server that receives emails on port 25 and forwards them as JSON to an HTTP endpoint. It does NOT send emails—it only receives and transforms them.

## Architecture

### Runtime Model: inetd + PHP CLI

This application uses a unique architecture:
- **inetd daemon** listens on port 25 (configured in `build/inetd.conf`)
- When a connection arrives, inetd spawns `app.php` as a new process
- Each email reception is a separate process lifecycle
- The process reads from STDIN (client connection) and writes to STDOUT (client responses)

This is NOT a long-running web server. Each SMTP connection is a new PHP process execution.

### Key Components

**app.php** (entry point)
- Loaded by inetd on each port 25 connection
- Instantiates `SMTPServer` and Guzzle HTTP client
- Calls `SMTPServer::receive()` to handle SMTP protocol
- POSTs the resulting JSON to configured endpoint

**src/SMTPServer.php** (SMTP protocol implementation)
- Implements SMTP RFC 2821 protocol commands
- Reads from STDIN, writes to STDOUT (inetd model)
- State machine tracking: hasValidFrom, hasValidTo, receivingData
- Parses email into structured array: sender, recipients, subject, headers, TextBody
- Detects client IP via `stream_socket_get_name(STDIN, true)`

**build/inetd.conf**
```
smtp   stream    tcp    nowait    root   /app/app.php
```
This single line makes inetd spawn app.php for each SMTP connection.

### Environment Configuration

The `.env` file controls behavior:

- `ENV=dev`: Enables logging, proxy routing through Proxyman (port 8888), saves emails/responses to disk
- `ENV=prod`: Minimal logging, direct HTTP posts
- `ENDPOINT`: The HTTP URL that receives the JSON payload

Development mode specifics:
- Logs SMTP transactions to `/app/log/log.txt`
- Appends received emails to `/app/log/emails.txt`
- Appends HTTP responses to `/app/log/res.txt`
- Routes HTTP through `tcp://host.docker.internal:8888` (Proxyman)

## Development Commands

### Docker Workflow

```bash
# Start development environment (includes httpbin test endpoint)
docker-compose up

# Build production image
./build/build.sh
# or manually:
docker build build/ --tag philetaylor/smtp2json:latest --no-cache

# Run production container
./build/run.sh
# or manually:
docker run --restart=always -d -p25:25 -v $(pwd)/.env:/app/.env philetaylor/smtp2json:latest

# View logs (dev mode only)
docker exec -it smtp2json cat /app/log/log.txt
docker exec -it smtp2json cat /app/log/emails.txt
docker exec -it smtp2json cat /app/log/res.txt
```

### PHP Dependencies

```bash
# Install dependencies (Guzzle, SwiftMailer, Symfony Dotenv)
composer install

# Test email sending
php test_sendMailBySMTP.php
```

## Testing

The `test_sendMailBySMTP.php` script sends a test email to `127.0.0.1:25` using SwiftMailer. This verifies:
- SMTP protocol handling
- Email parsing
- JSON output generation
- HTTP POST to endpoint

Run with the server running:
```bash
docker-compose up -d
composer install
php test_sendMailBySMTP.php
```

Then check `docker exec -it smtp2json cat /app/log/emails.txt` to see the JSON output.

## Important Behavioral Notes

### SMTP Protocol State Machine

The `SMTPServer::receive()` method implements a state machine:
1. Client connects → Send "220 SMTPServer ESMTP PHP Mail Server Ready"
2. Wait for HELO/EHLO → Respond with "250 HELO {ip}"
3. Wait for MAIL FROM → Validate, set `$hasValidFrom = true`
4. Wait for RCPT TO → Validate (requires `$hasValidFrom`), set `$hasValidTo = true`
5. Wait for DATA → Requires `$hasValidTo`, enter `$receivingData` mode
6. Collect lines until lone "." → Parse headers/body, generate JSON
7. QUIT → Close connection

Commands like RSET, NOOP, VRFY are supported. Invalid commands return "502 5.5.2 Error: command not recognized".

### Email Parsing Logic

After receiving the complete email (lines 93-111 in SMTPServer.php):
1. Split on first "\n\n" to separate headers from body
2. Normalize multi-line headers (lines continuing with spaces)
3. Parse headers into associative array
4. Extract Subject from headers
5. Store raw email for full context

The `emailHeaders` field is removed before JSON output (line 28) to avoid duplication with the parsed `headers` array.

### IP Detection

Client IP is detected using `stream_socket_get_name(STDIN, true)` which returns format "ip:port". The code splits on ":" and takes the first element (line 139).

## Current Branch Context

You are on branch `symfony-command`. The main branch is `master`. Recent commits show refactoring work and Docker build improvements.
