# smtp2json

A PHP-based fake SMTP server that receives emails via port 25 and forwards them as JSON to an HTTP endpoint. Instead of delivering emails, it converts them to JSON format and POSTs them to a configurable URL.

## Overview

smtp2json runs as an inetd service inside a Docker container. When an email client connects to port 25, the inetd daemon spawns `app.php` which:
1. Implements the SMTP protocol (via `SMTPServer` class)
2. Receives the complete email (headers, body, recipients)
3. Parses and structures the email data
4. POSTs it as JSON to the configured endpoint

**Important:** This server does not send emails. It only receives and forwards them as JSON.

## Quick Start

### Using Docker Compose

```bash
docker-compose up
```

This starts two services:
- `smtp2json`: The SMTP server on port 25
- `httpbin`: A test HTTP endpoint on port 80

### Using Docker Build & Run

```bash
# Build the image
./build/build.sh

# Run the container
./build/run.sh
```

## Configuration

Edit `.env` to configure the behavior:

```bash
ENV=dev                              # Set to 'dev' for debug logging, 'prod' for production
ENDPOINT=https://httpbin.org/post   # HTTP endpoint to receive the JSON payload
```

### Development Mode (`ENV=dev`)

When `ENV=dev`, the application:
- Logs SMTP transactions to `/app/log/log.txt`
- Saves received emails to `/app/log/emails.txt`
- Saves HTTP responses to `/app/log/res.txt`
- Routes requests through Proxyman (port 8888) for debugging

### Production Mode (`ENV=prod`)

Production mode disables logging and proxy routing for performance.

## Architecture

### Components

- **app.php**: Entry point that initializes the SMTP server and HTTP client
- **src/SMTPServer.php**: SMTP protocol implementation that:
  - Handles SMTP commands (HELO, MAIL FROM, RCPT TO, DATA, etc.)
  - Validates email addresses
  - Parses email headers and body
  - Generates JSON output
- **build/inetd.conf**: Configures inetd to spawn app.php on port 25 connections
- **build/Dockerfile**: Alpine-based image with PHP and required extensions

### Data Flow

```
Email Client → Port 25 → inetd → app.php → SMTPServer::receive() → JSON → HTTP POST → ENDPOINT
```

### JSON Output Format

The server extracts and sends the following JSON structure:

```json
{
  "ipaddress": "192.168.1.100",
  "sender": "from@example.com",
  "recipients": ["to@example.com"],
  "subject": "Email Subject",
  "rawEmail": "Full email content...",
  "headers": {
    "From": "from@example.com",
    "To": "to@example.com",
    "Subject": "Email Subject"
  },
  "TextBody": "Email body content..."
}
```

## Testing

Use the included test script to send a test email:

```bash
composer install
php test_sendMailBySMTP.php
```

This uses SwiftMailer to send a test email to `127.0.0.1:25`.

## Production Deployment on Ubuntu

### Installing on Ubuntu Server (without Docker)

1. **Install required packages:**

```bash
sudo apt-get update
sudo apt-get install -y php-cli php-json php-mbstring php-curl php-xml composer openbsd-inetd
```

2. **Clone and install the application:**

```bash
cd /opt
sudo git clone https://github.com/PhilETaylor/smtp2json.git
cd smtp2json
sudo composer install --no-dev --optimize-autoloader
```

3. **Configure environment:**

```bash
sudo nano /opt/smtp2json/.env
```

Set production values:
```bash
ENV=prod
ENDPOINT=https://your-endpoint.com/api/emails
```

4. **Make app.php executable:**

```bash
sudo chmod +x /opt/smtp2json/app.php
```

5. **Configure inetd to handle port 25:**

Add this line to `/etc/inetd.conf`:
```bash
smtp   stream    tcp    nowait    root   /opt/smtp2json/app.php
```

Or use this command:
```bash
echo "smtp   stream    tcp    nowait    root   /opt/smtp2json/app.php" | sudo tee -a /etc/inetd.conf
```

6. **Ensure no other service is using port 25:**

```bash
# Stop and disable postfix if installed
sudo systemctl stop postfix
sudo systemctl disable postfix

# Or stop exim4 if installed
sudo systemctl stop exim4
sudo systemctl disable exim4
```

7. **Restart inetd to apply changes:**

```bash
sudo systemctl restart openbsd-inetd
# or on some systems:
sudo systemctl restart inetd
```

8. **Verify inetd is listening on port 25:**

```bash
sudo netstat -tlnp | grep :25
# Should show: tcp  0  0.0.0.0:25  0.0.0.0:*  LISTEN  [pid]/inetd
```

9. **Test the setup:**

```bash
telnet localhost 25
# You should see: 220 SMTPServer ESMTP PHP Mail Server Ready
# Type: QUIT
```

### Troubleshooting Production Setup

**inetd not starting:**
```bash
# Check inetd status
sudo systemctl status openbsd-inetd

# View inetd logs
sudo journalctl -u openbsd-inetd -f
```

**Port 25 already in use:**
```bash
# Find what's using port 25
sudo lsof -i :25

# Common culprits and how to stop them
sudo systemctl stop postfix sendmail exim4
```

**PHP errors:**
```bash
# Test app.php directly
echo "QUIT" | /opt/smtp2json/app.php

# Check PHP error log
sudo tail -f /var/log/syslog | grep php
```

**Permissions issues:**
```bash
# Ensure app.php is executable
ls -la /opt/smtp2json/app.php

# Ensure log directory exists and is writable (if using ENV=dev)
sudo mkdir -p /opt/smtp2json/log
sudo chmod 777 /opt/smtp2json/log
```

## Development

### Dependencies

Install PHP dependencies:

```bash
composer install
```

### Running Locally

The app requires:
- PHP 7.0+ with json, phar, mbstring, and openssl extensions
- inetd daemon
- Composer dependencies (Guzzle, SwiftMailer, Symfony Dotenv)

### Docker Development

Mount the local directory to see changes live:

```bash
docker run -d -p25:25 -v $(pwd)/.env:/app/.env -v $(pwd):/app philetaylor/smtp2json:latest
```

### Viewing Logs

When running with docker-compose in dev mode:

```bash
docker exec -it smtp2json cat /app/log/log.txt     # SMTP transaction log
docker exec -it smtp2json cat /app/log/emails.txt # Received emails as JSON
docker exec -it smtp2json cat /app/log/res.txt    # HTTP endpoint responses
```

## License

See LICENSE file.
