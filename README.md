# Chat API

A RESTful chat application backend built with PHP and the Slim framework.

## Requirements

- PHP 8.1+
- Composer

## Installation

```bash
composer install
```

## Running the Server

```bash
php -S localhost:8080 -t public
```

## Running Tests

```bash
./vendor/bin/phpunit
```

## Architecture

```
src/
├── Controllers/     # HTTP request handlers
├── Services/        # Business logic layer
├── Models/          # Data access layer (repositories)
├── Middleware/      # Auth and error handling
├── Exceptions/      # Custom HTTP exceptions
└── Database/        # SQLite connection and schema
```

## API Endpoints

### Users

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/users` | No | Create user (returns token) |
| GET | `/users/me` | Yes | Get current user |

### Groups

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/groups` | Yes | List all groups |
| POST | `/groups` | Yes | Create a group |
| GET | `/groups/{id}` | Yes | Get group details |
| POST | `/groups/{id}/join` | Yes | Join a group |
| GET | `/groups/{id}/members` | Yes | List group members |

### Messages

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/groups/{id}/messages` | Yes | List messages |
| POST | `/groups/{id}/messages` | Yes | Send a message |

## Authentication

Include the token in the `X-User-Token` header:

```
X-User-Token: <your-token>
```

## Example Usage

```bash
# Create a user
curl -X POST http://localhost:8080/users \
  -H "Content-Type: application/json" \
  -d '{"username": "alice"}'

# Create a group
curl -X POST http://localhost:8080/groups \
  -H "Content-Type: application/json" \
  -H "X-User-Token: <token>" \
  -d '{"name": "General"}'

# Send a message
curl -X POST http://localhost:8080/groups/1/messages \
  -H "Content-Type: application/json" \
  -H "X-User-Token: <token>" \
  -d '{"content": "Hello!"}'

# Get messages (with polling support)
curl http://localhost:8080/groups/1/messages?after_id=0 \
  -H "X-User-Token: <token>"
```

## Query Parameters for Messages

- `limit` - Max messages to return (max: 100)
- `offset` - Skip N messages
- `after_id` - Get messages after this ID (for polling)
