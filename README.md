# MHWUN OOUTH Portal

This is the web portal for MHWUN (Medical and Health Workers Union of Nigeria) OOUTH Chapter.

## Features

- Member Management
- Loan Processing
- Bulk SMS Notifications
- Transaction History & Reporting

## Setup

1. Clone the repository.
2. Copy `.env.example` to `.env` (Create one if missing) and configure your database credentials.
3. Ensure the database is imported.
4. Run on a PHP server (Apache/Nginx).

## SMS Configuration

The portal uses Termii for SMS Notifications. Configure the following keys in your `.env`:

- `TERMII_API_KEY`
- `TERMII_SENDER`
