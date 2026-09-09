# Jocrams ↔ PAEC Parity Implementation Plan

Execute steps **in order**. Do not skip ahead unless a step is explicitly marked optional.
Each step lists: goal, backend work, frontend work, tests, and done criteria.

---

## Phase A — Journal (PAEC core differentiator)

### Step 1 — Journal database & permissions ✅ DONE
**Goal:** Schema and RBAC foundation matching PAEC Journal module.

- [x] Migrations: `journal_submissions`, assignments, timelines, comments, revisions
- [x] Enums: `JournalSubmissionStatus`, `JournalVisibility`
- [x] Permissions: `journal.review`, `journal.assign`
- [x] Seeder: `reviewer` role

**Done when:** Migrations run; permissions seeded.

---

### Step 2 — Journal backend API ✅ DONE
**Goal:** Full editorial workflow API ported from `paec_api/Modules/Journal/`.

- [x] Submit, list, browse, download (access-controlled)
- [x] Review, assign, revision, resubmit, comments, timeline
- [x] Reviewer management, visibility control
- [x] Email notifications (8 lifecycle events)
- [x] Feature tests: `JournalDocumentAccessTest`, `JournalSubmissionNotificationTest`

**Done when:** All journal API routes respond like PAEC; tests pass.

---

### Step 3 — Journal frontend ✅ DONE
**Goal:** Usable UI for authors, reviewers, admins, and public readers.

- [x] Public: `/journal`, `/journal/:slugOrId`
- [x] Member: submit, my submissions, detail, resubmit, reviewer queue
- [x] Admin: submissions list, detail, assign, review, reviewers
- [x] Navigation wired in public, member, admin layouts

**Done when:** End-to-end submit → review → approve → public browse works in browser.

---

## Phase B — Auth & identity (PAEC IdentityAccess parity)

### Step 4 — Password reset & change password ✅ DONE
**Goal:** Members and admins can recover and change passwords like PAEC.

**Backend**
- [x] Migration: `has_changed_password` on `users`
- [x] `ResetPasswordNotification` with frontend URLs in config
- [x] `POST /api/v1/auth/password/forgot`
- [x] `POST /api/v1/auth/password/reset`
- [x] `POST /api/v1/member/auth/password/change` (authenticated)
- [x] `POST /api/v1/admin/auth/password/change` (authenticated)
- [x] Feature test: `PasswordResetTest` (ported from PAEC)

**Frontend**
- [x] Member: forgot password + reset password pages
- [x] Admin: forgot password + reset password pages
- [x] Links from login pages; change password on profile

**Done when:** User receives reset email, sets new password, can log in; tests pass.

---

### Step 5 — Email verification ✅ DONE
**Goal:** Verified email before sensitive actions (optional gate on submit).

**Backend**
- [x] `User` implements `MustVerifyEmail`
- [x] `POST /api/v1/member/auth/email/verification-notification`
- [x] `GET /api/v1/member/auth/email/verify/{id}/{hash}` (signed)
- [x] Same for admin
- [x] Verification email sent on member registration
- [x] Feature test: `EmailVerificationTest`

**Frontend**
- [x] Verification banner on member dashboard
- [x] Verify redirect landing page (member + admin)

**Done when:** Verification email sends; link marks user verified.

---

### Step 6 — Profile update & avatar ✅ DONE
**Goal:** PAEC-style profile fields (bio, address, social links, profile image).

**Backend**
- [x] Migration: `user_profiles` table (port PAEC schema)
- [x] `PUT /api/v1/member/auth/profile`
- [x] `POST /api/v1/member/auth/profile/image`
- [x] `GET /api/v1/public/users/{uuid}/profile-image`
- [x] Extend `GET /auth/me` to include profile
- [x] Journal author cards include `author_profile_image`
- [x] Feature test: `ProfileTest`

**Frontend**
- [x] Complete `member/Profile.vue` with edit form + image upload

**Done when:** Member can update profile and upload avatar; shown on journal author cards.

---

## Phase C — Events (PAEC Events module parity)

### Step 7 — Event admin CRUD ✅ DONE
**Goal:** Admins can create, update, delete events (currently read-only).

**Backend**
- [x] Admin CRUD controllers: create, update, delete, banner upload
- [x] Sessions + pricing tiers migrations
- [x] Slug auto-generation on create/update
- [x] `GET /api/v1/public/events/{uuid}/banner`
- [x] Feature test: `EventCrudTest`

**Frontend**
- [x] Admin Events page: create/edit form
- [x] Banner upload, sessions & pricing tiers editors

**Done when:** Admin can publish an event; it appears on public `/events`.

---

### Step 8 — Event registration & payments ✅ DONE
**Goal:** Members and guests can register; Paystack checkout for paid events.

**Backend**
- [x] Port PAEC: member register, guest register, Paystack initialize/verify
- [x] Registration export CSV
- [x] `EventRegistration` model wired to routes
- [x] Feature test: `EventRegistrationTest`

**Frontend**
- [x] Public event detail with register button
- [x] Member/guest registration flow + payment
- [x] Admin registrations list with export

**Done when:** Paid event registration completes via Paystack; admin sees registrants.

---

## Phase D — Library & content

### Step 9 — Library/publications admin CRUD ✅
**Goal:** Admins can upload and manage downloadable publications (not just read).

**Backend**
- [x] Admin CRUD for `Download` model + file upload via `MediaUploadService`
- [x] Visibility + tier gating on create/update

**Frontend**
- [x] Admin publications page: upload, edit, deactivate
- [x] Distinguish journal articles vs library files in UI copy

**Done when:** Admin uploads PDF; appears on public/member downloads.

---

### Step 10 — Content CMS & media library ✅
**Goal:** PAEC Content module — CMS items + media catalog.

**Backend**
- [x] Extend existing models: `Page` CRUD + public pages API
- [x] Media library upload/list/delete on `MediaFile` (`media-catalog` collection)
- [x] Public media catalog endpoints (list, view, download)
- [x] Admin content overview dashboard API

**Frontend**
- [x] Admin content dashboard (`/admin/content`)
- [x] Media library browser (`/admin/assets`)
- [x] Static pages CMS (`/admin/pages`)

**Done when:** Admin manages CMS pages/media; public catalog endpoint works.

---

## Phase E — Admin operations

### Step 11 — Support & contact inbox ✅
**Goal:** Admin can view and respond to contact form submissions.

**Backend**
- [x] Support inbox on `contact_inquiries` with respond/resolve
- [x] Member support ticket creation + my tickets API
- [x] `support.manage` permission + contact rate limit

**Frontend**
- [x] Admin support inbox page (`/admin/help`)
- [x] Member support page (`/member/support`)

**Done when:** Contact submissions visible; admin can respond.

---

### Step 12 — Settings module ✅
**Goal:** Grouped system settings like PAEC (`general`, `notifications`, `membership`, `payment`, `security`, `signatory`).

**Backend**
- [x] Migration: `system_settings` table
- [x] GET/POST `/api/v1/admin/settings/{group}`
- [x] Role assignment endpoint

**Frontend**
- [x] Replace stub `Settings.vue` with real forms

**Done when:** Admin can save settings groups; values persist.

---

### Step 13 — Reports & dashboards ✅
**Goal:** Aggregate reporting + member dashboard snapshot.

**Backend**
- [x] `GET /api/v1/admin/reports/overview`
- [x] `GET /api/v1/admin/reports/recent-activities`
- [x] `GET /api/v1/member/dashboard` snapshot (membership, events, journal)

**Frontend**
- [x] Wire Reports page to live data
- [x] Enhance member dashboard with PAEC-style snapshot

**Done when:** Admin reports show live counts; member dashboard shows upcoming events.

---

### Step 14 — In-app notifications inbox ✅
**Goal:** `GET /notifications/my` with bell UI.

**Backend**
- [x] Ensure `notification_logs` supports in-app channel
- [x] `GET /api/v1/member/notifications/my`
- [x] `GET /api/v1/admin/notifications/my`

**Frontend**
- [x] Notification bell with unread count in layouts

**Done when:** Journal events appear in in-app inbox.

---

## Phase F — Membership extras

### Step 15 — Public OTP onboarding (PAEC-style) ✅
**Goal:** Optional public onboarding flow before member account.

**Backend**
- [x] Port: `/onboarding/start`, verify-email OTP, professional details, submit
- [x] Keep existing member register as alternative

**Frontend**
- [x] Public onboarding wizard

**Done when:** New user completes OTP onboarding → membership application.

---

### Step 16 — Membership admin extras ✅
**Goal:** Manual member management + public directory.

**Backend**
- [x] Add/deactivate/reactivate member endpoints
- [x] Public member directory `GET /members`
- [x] Digital membership card API (leverage existing credentials)

**Done when:** Admin can manually add member; public directory lists members.

---

## Phase G — Ops & polish

### Step 17 — Payment admin parity ✅
**Goal:** Manual payment record, stats, CSV export (PAEC Payments admin).

**Backend**
- [x] Record payment, admin list/filter, stats, export
- [x] Keep Jocrams Flutterwave advantage

**Done when:** Admin payment ledger matches PAEC capabilities.

---

### Step 18 — API docs, rate limits, monitoring ✅
**Goal:** Production ops parity.

- [x] OpenAPI/Swagger at `/docs`
- [x] Rate limiting aligned with PAEC
- [x] Security headers middleware
- [x] Health monitor artisan command (optional)

**Done when:** `/docs` serves API spec; auth routes rate-limited.

---

## Phase H — Preserve Jocrams advantages (ongoing)

Do **not** remove during parity work:
- Flutterwave payments alongside Paystack
- Bulk email campaigns
- Digital credentials + QR verification
- Batch approve/reject
- Excel exports
- Docker Compose deployment

---

## Execution log

| Step | Status | Date |
|------|--------|------|
| 1 | ✅ Complete | 2026-09-01 |
| 2 | ✅ Complete | 2026-09-01 |
| 3 | ✅ Complete | 2026-09-01 |
| 4 | ✅ Complete | 2026-09-01 |
| 5 | 🔄 Next | — |
| 6–18 | ⏳ Pending | — |

---

## How to use this document

1. Open the step marked **CURRENT**.
2. Complete all backend → frontend → tests for that step.
3. Mark step done in Execution log.
4. Move to next step only when **Done when** criteria are met.
