# CENIM Conference Management System

Web application for the **International Conference on Computer Engineering,
Network and Intelligent Multimedia (CENIM)**, organised by the Department of
Computer Engineering and the Department of Biomedical Engineering, Institut
Teknologi Sepuluh Nopember (ITS), Surabaya, Indonesia.

Live site: <https://elib.its.ac.id/conf/cenim/>

## What it does

- **Public website** — landing page, registration, submission and workshop
  pages, all editable from the admin panel (WYSIWYG editor), with timeline,
  keynote speakers, committee, sponsors, gallery and previous editions.
- **Author & committee portal** — sign in with a 6-digit code sent by email.
  Authors of an accepted paper fill in the registration form (presenter,
  category, additional paper, non-presenter attendees), receive an invoice,
  submit the proof of payment, get the receipt, LoA and certificates, and
  send the presentation video for online sessions.
- **Admin panel** — import papers and authors from an EDAS export, review
  registrations and payments, issue invoices, receipts, LoAs and certificates,
  manage forms, fees, letterhead and content, and browse past editions.
- **Multi-edition** — each year is an edition; settings can be copied to the
  next one and past editions stay available as an archive.
- **Certificate verification** — every certificate has a code and QR code that
  can be checked on the public verification page.

## Tech stack

- PHP 8.3, no framework, no build step
- SQLite (PDO, WAL mode)
- S3-compatible object storage for uploads and theme assets (local fallback)
- tFPDF for invoices, receipts and LoAs; certificates rendered in the browser
- Bootstrap 5, Font Awesome, DataTables, SweetAlert2 from CDN

## Getting started

```bash
cp .env.example .env          # fill in the values (never commit .env)
php db/migrate.php            # create / update the database
php db/seed.php cenim2026     # first edition and default data
php bin/preflight.php         # configuration check
```

Serve the folder with any PHP 8.3 web server (Apache with `.htaccess`, or
php-fpm behind nginx). See `.env.example` for every configuration option.

## Security

No credentials, participant data or databases are stored in this repository.
Secrets live only in `.env` on the server; uploads, logs and backups live in a
storage folder outside the web root. Please report security issues privately
to the conference secretariat at info.cenim@its.ac.id.

## License

Copyright © CENIM, Institut Teknologi Sepuluh Nopember. All rights reserved.
