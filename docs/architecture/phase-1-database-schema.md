# Phase 1: Database Schema Reference

This document provides a field-level reference for all core database tables. Migrations live in `database/migrations/2026_08_31_*`.

## Status Enums

| Entity | Allowed Values |
|--------|---------------|
| `users.status` | `active`, `suspended`, `pending` |
| `members.status` | `pending`, `active`, `expired`, `suspended`, `rejected` |
| `membership_applications.status` | `draft`, `submitted`, `under_review`, `approved`, `rejected`, `cancelled` |
| `approvals.status` | `pending`, `approved`, `rejected` |
| `payments.status` | `pending`, `processing`, `successful`, `failed`, `refunded`, `cancelled` |
| `subscriptions.status` | `active`, `expired`, `cancelled`, `pending` |
| `events.status` | `draft`, `published`, `cancelled`, `completed` |
| `event_registrations.status` | `pending`, `confirmed`, `cancelled`, `attended` |
| `news_articles.status` | `draft`, `published`, `archived` |
| `bulk_campaigns.status` | `draft`, `scheduled`, `sending`, `completed`, `cancelled` |

## Visibility Enums

| Field | Allowed Values |
|-------|---------------|
| Content visibility | `public`, `members_only` |
| Download visibility | `public`, `members_only`, `tier_specific` |
| Media visibility | `public`, `members_only`, `private` |

## Payment Purpose Values

`dues`, `registration`, `event_fee`, `certification`, `donation`

## Gateway Values

`paystack`, `flutterwave`

---

## Table: users

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE, NOT NULL |
| name | varchar | NOT NULL |
| email | varchar | UNIQUE |
| phone | varchar(20) | NULLABLE, INDEX |
| email_verified_at | timestamp | NULLABLE |
| password | varchar | NOT NULL |
| status | varchar(20) | DEFAULT `active`, INDEX |
| remember_token | varchar | NULLABLE |
| last_login_at | timestamp | NULLABLE |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | SOFT DELETE |

## Table: roles

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | varchar | NOT NULL |
| slug | varchar | UNIQUE |
| guard_name | varchar(50) | DEFAULT `admin`, INDEX |
| description | text | NULLABLE |
| is_system | boolean | DEFAULT false |

## Table: permissions

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | varchar | NOT NULL |
| slug | varchar | UNIQUE |
| guard_name | varchar(50) | DEFAULT `admin` |
| module | varchar(50) | INDEX with guard_name |
| description | text | NULLABLE |

## Table: membership_tiers

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| name / slug | varchar | slug UNIQUE |
| description | text | NULLABLE |
| annual_dues | decimal(12,2) | DEFAULT 0 |
| currency | char(3) | DEFAULT `NGN` |
| benefits | json | NULLABLE |
| sort_order | smallint | DEFAULT 0 |
| is_active | boolean | INDEX |
| min_documents_required | tinyint | DEFAULT 0 |
| requires_approval | boolean | DEFAULT true |

## Table: members

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| user_id | FK → users | CASCADE |
| membership_tier_id | FK → membership_tiers | RESTRICT |
| membership_number | varchar | UNIQUE |
| status | varchar(20) | INDEX |
| joined_at / expires_at | timestamp | NULLABLE |
| approved_at | timestamp | NULLABLE |
| approved_by | FK → users | NULL ON DELETE |
| rejection_reason | text | NULLABLE |
| metadata | json | NULLABLE |

## Table: membership_applications

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| user_id | FK → users | NULL ON DELETE |
| membership_tier_id | FK → membership_tiers | RESTRICT |
| status | varchar(20) | INDEX with created_at |
| form_data | json | NULLABLE |
| applicant_email / name | varchar | INDEX on email |
| applicant_phone | varchar(20) | NULLABLE |
| submitted_at / reviewed_at | timestamp | NULLABLE |
| reviewed_by | FK → users | NULL ON DELETE |
| notes / rejection_reason | text | NULLABLE |

## Table: approvals

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| approvable_type / approvable_id | morphs | INDEX |
| type | varchar(50) | INDEX with status |
| submitted_by / reviewed_by | FK → users | NULL ON DELETE |
| status | varchar(20) | INDEX with created_at |
| notes / rejection_reason | text | NULLABLE |
| reviewed_at | timestamp | NULLABLE |

## Table: payments

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| user_id / member_id | FK | NULL ON DELETE |
| payable_type / payable_id | morphs | NULLABLE |
| gateway | varchar(20) | INDEX with status |
| reference | varchar | UNIQUE |
| idempotency_key | varchar | UNIQUE |
| amount / fee | decimal(12,2) | |
| currency | char(3) | DEFAULT `NGN` |
| status | varchar(20) | INDEX |
| purpose | varchar(30) | INDEX |
| description | varchar | NULLABLE |
| metadata | json | NULLABLE |
| authorization_url | varchar | NULLABLE |
| paid_at / expires_at | timestamp | NULLABLE |

## Table: digital_credentials

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| member_id | FK → members | CASCADE |
| type | varchar(30) | `membership_card`, `certificate` |
| title | varchar | NOT NULL |
| template_key | varchar(50) | NOT NULL |
| verification_token | varchar(64) | UNIQUE |
| file_media_id | FK → media_files | NULL ON DELETE |
| issued_at / expires_at | timestamp | |
| revoked_at | timestamp | NULLABLE |
| revocation_reason | text | NULLABLE |
| metadata | json | NULLABLE |

## Table: downloads

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| media_file_id | FK → media_files | RESTRICT |
| category_id | FK → categories | NULL ON DELETE |
| title / slug | varchar | slug UNIQUE |
| description | text | NULLABLE |
| visibility | varchar(20) | INDEX |
| allowed_tier_ids | json | NULLABLE (tier_specific) |
| download_count | int | DEFAULT 0 |
| is_active | boolean | INDEX |
| published_at | timestamp | NULLABLE |

## Table: events

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| organizer_id | FK → users | RESTRICT |
| category_id | FK → categories | NULL ON DELETE |
| title / slug | varchar | slug UNIQUE |
| description / body | text | NULLABLE |
| location / virtual_url | varchar | NULLABLE |
| starts_at / ends_at | timestamp | INDEX |
| registration_opens_at / closes_at | timestamp | NULLABLE |
| max_attendees | int | NULLABLE |
| fee | decimal(12,2) | DEFAULT 0 |
| currency | char(3) | DEFAULT `NGN` |
| visibility / status | varchar(20) | INDEX |
| featured_image_media_id | FK → media_files | NULL ON DELETE |

## Index Summary

| Table | Index | Purpose |
|-------|-------|---------|
| approvals | (status, created_at) | Approval queue ordering |
| membership_applications | (status, created_at) | Application review queue |
| payments | (status, created_at) | Transaction monitoring |
| members | (status, expires_at) | Renewal reminders |
| subscriptions | (status, ends_at) | Expiry batch jobs |
| news_articles | (status, published_at) | Public news feed |
| events | (status, starts_at) | Upcoming events listing |
| payment_webhook_logs | (gateway, idempotency_key) UNIQUE | Webhook deduplication |
