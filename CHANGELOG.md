# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-10-07

First release.

Laravel client for the Google Analytics 4 Data API, authenticated with a service account (JWT signed locally with OpenSSL, cached access tokens).

- `GA4` facade: `aggregate`, `timeseries`, `breakdown`, `pages`, `sources`, `countries`, `browsers`, `devices`, `realtimeVisitors`, `runReport`
- Package metric names (`visitors`, `visits`, `pageviews`, `bounce_rate`, `visit_duration`) mapped to GA4 metrics
- Typed exceptions: `AuthenticationException`, `RateLimitException`, `GA4Exception`
- Settings stored via spatie/laravel-settings (service account key encrypted), seeded from `GA4_PROPERTY_ID` and `GA4_SERVICE_ACCOUNT_JSON`

Requires PHP 8.2+ and Laravel 11, 12 or 13.

## [Unreleased]
