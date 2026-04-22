# Job Doc Collector – Claude Code Guide

## Project Overview
A web app for hiring managers to collect job application documents from candidates via magic links.

## Stack
- **Frontend:** HTML, CSS, JavaScript (vanilla)
- **Backend:** PHP (no framework)
- **Database:** PostgreSQL
- **Server:** XAMPP (local dev)

## Directory Structure
```
job-doc-collector/
├── config/          # DB connection (db.php), schema (schema.sql)
├── pages/           # All PHP pages
├── assets/          # CSS, JS, images (future)
└── uploads/         # Uploaded candidate documents
```

## Key Pages
| File | Purpose |
|------|---------|
| pages/login.php | Hiring manager login |
| pages/dashboard.php | View all applications + progress |
| pages/create_application.php | New application form |
| pages/save_application.php | Saves application + generates magic token |
| pages/application_link.php | Shows magic upload link |
| pages/application_detail.php | View candidate docs, download files |
| pages/upload.php | Candidate-facing upload page (token-based) |
| pages/save_document.php | Handles file upload + validation |
| pages/logout.php | Destroys session |

## Database Tables
- `users` — hiring managers (id, email, password)
- `applications` — job applications (id, candidate_name, email, role, token, created_by)
- `documents` — uploaded files (id, application_id, document_type, file_url)

## Code Standards
- Modular architecture — use reusable functions
- Write clean, commented code
- No large unstructured code blocks
- Plain passwords (no hashing) for now

## Git Workflow
- One task → One GitHub issue → One branch
- Branch naming: `feature/issue-{number}-short-description`
- Commit via GitHub MCP, close issue after merge

## Upcoming Features (Phase 2+)
- Aadhaar document upload
- Blur detection (Google Vision API)
- OCR data extraction — Aadhaar Number, Name, DOB
- PDF report generation
- OpenAI for parsing extracted text
