# Reddit Scraper - Vibe coded

A simple PHP-based Reddit scraper with a user interface, built using Symfony. It fetches media posts from Reddit, processes them, and stores them in OpenSearch for easy searching and viewing.

**It's mostly vibe coded with Junie by JetBrains.**

## Features

- Scrapes Reddit for MP4 media posts.
- Uses RabbitMQ for asynchronous processing of posts.
- Stores post data in OpenSearch.
- Web UI for browsing and searching scraped posts.
- Built-in resolution picker to fetch the best image/video quality.

## Tech Stack

- **PHP 8.4** (Symfony 8)
- **OpenSearch** (Search Engine)
- **PostgreSQL** (Database)
- **RabbitMQ** (Message Broker)
- **Redis** (Cache)
- **Docker** (Containerization)

## Prerequisites

- Docker and Docker Compose
- Make (optional, but recommended)

## Installation

The easiest way to get started is to use the provided `Makefile`:

```bash
make install
```

This command will:
1. Start the Docker containers.
2. Install PHP and NPM dependencies.
3. Build the frontend assets.
4. Migrate DB
4. Wait for OpenSearch to be ready.
5. Fetch some initial data from Reddit.
6. Start the import process.

After installation, the application will be available at [http://localhost/](http://localhost/).

## Usage

### Manual Commands

You can run various tasks manually using `make` commands:

- `make fetch`: Fetch the latest media posts from Reddit.
- `make import`: Import fetched posts into the system.
- `make reindex`: Re-index data in OpenSearch.
- `make migrate`: Run database migrations.
- `make consume`: Start the RabbitMQ message consumer.
- `make up`: Start the environment.
- `make down`: Stop the environment.

### Accessing Services

- **Web UI**: [http://localhost/](http://localhost/)
- **RabbitMQ Management**: [http://localhost:15672/](http://localhost:15672/) (user: `app`, pass: `app`)
- **OpenSearch Dashboards**: [http://localhost:5601/](http://localhost:5601/)
- **Adminer**: [http://localhost:8080/](http://localhost:8080/) (server: `reddit-postgres`, user: `app`, pass: `app`, db: `app`)

## Testing

The project uses PHPUnit for testing. To run the tests, execute:

```bash
cd app
./vendor/bin/phpunit
```

## License

This project is open-source and available under the MIT License.
