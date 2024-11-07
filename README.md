
# Symfony Currency Rate Checker

This Symfony console command fetches currency exchange rates from PrivatBank and Monobank, checks for significant changes based on a set threshold, and sends notifications when changes exceed the threshold.

## Requirements

- PHP 8.1 or higher
- Composer
- Symfony CLI (optional but recommended)
- Docker (optional, for containerized setup)

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url> currency-rate-checker
   cd currency-rate-checker
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

## Configuration

1. Set up your environment variables by creating a `.env.local` file and adding your SMTP configuration for email notifications:
   ```env
   MAILER_DSN=smtp://user:pass@smtp.example.com:port
   ```

2. Adjust the threshold if needed in `config/services.yaml`:
   ```yaml
   services:
       App\Command\CurrencyRateCheckCommand:
           arguments:
               $threshold: '1.5'  # Adjust threshold here
           tags: ['console.command']
   ```

## Usage

To run the command manually, use:
```bash
php bin/console app:currency-rate-check
```

This command fetches the currency rates from both banks, checks if the rate difference exceeds the specified threshold, and sends an email notification if it does.

## Docker Setup (Optional)

If you prefer to run this project in Docker, follow these steps:

1. Build and start the Docker container:
   ```bash
   docker-compose up -d --build
   ```

2. Access the container and run the command:
   ```bash
   docker-compose exec app php bin/console app:currency-rate-check
   ```

## Docker Configuration

In the `docker-compose.yml` file, only essential services are included:

- `php`: the PHP runtime environment for running Symfony commands.

## Sample `docker-compose.yml`

```yaml
version: '3.8'

services:
  app:
    image: php:8.1-cli
    volumes:
      - .:/app
    working_dir: /app
    command: ["php", "bin/console", "app:currency-rate-check"]
    depends_on:
      - mailhog

  mailhog:
    image: mailhog/mailhog
    ports:
      - "8025:8025"
```

After running `docker-compose up`, Mailhog will be available at `http://localhost:8025` to intercept emails sent by the command.

## Testing the Command

To test, execute the console command in either local or Docker setup. It will notify via email if the currency rates change beyond the specified threshold.
