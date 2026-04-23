# Job Doc Collector – Claude Code Guide

## Project Overview
A web app for hiring managers to collect job application documents from candidates via magic links.
Candidates upload Resume, Aadhaar Card, Cover Letter, and ID Proof via a token-based link (no account needed).
The app auto-processes documents: blur detection, OCR extraction, AI parsing, and PDF report generation.

## Stack
- **Frontend:** HTML, CSS (Inter font + Font Awesome 6), JavaScript (vanilla)
- **Backend:** PHP (no framework)
- **Database:** PostgreSQL
- **Server:** XAMPP (local dev)
- **PDF Generation:** FPDF (via Composer — `vendor/`)
- **PDF Text Extraction:** smalot/pdfparser (via Composer — `vendor/`)

## APIs in Use

### Mistral AI — `config/functions.php`
- **Key location:** `config/env.php` → `define('MISTRAL_API_KEY', '...')`
- **Aadhaar OCR** → model `pixtral-12b-2409` (vision model, handles images + PDFs)
  - Extracts: `aadhaar_number`, `name`, `dob`
  - Images use `image_url` content type; PDFs use `document_url` content type
- **Resume Parsing** → model `mistral-small-latest` (text model)
  - Extracts: `name`, `email`, `phone`, `skills`, `education`, `latest_company`, `latest_role`, `latest_start_date`, `latest_end_date`, `address`, `linkedin`, `github`
  - Response is JSON; strip markdown code fences before `json_decode()`
- **Endpoint:** `https://api.mistral.ai/v1/chat/completions`
- **Auth:** Bearer token in `Authorization` header

### GitHub MCP — `.mcp.json`
- Used for creating issues, branches, pull requests, and merging
- Token stored in `.mcp.json` → `env.GITHUB_TOKEN`
- Repo: `nikhildhawan3093/job-doc-collector`

## Directory Structure
```
job-doc-collector/
├── config/
│   ├── db.php          # PostgreSQL connection
│   ├── env.php         # API keys (gitignored)
│   ├── functions.php   # All shared logic (blur, OCR, parsing, validation)
│   └── schema.sql      # Full DB schema
├── pages/              # All PHP pages
├── assets/
│   └── css/app.css     # Shared design system (CSS variables, components)
├── uploads/            # Uploaded candidate files
│   └── reports/        # Generated PDF reports
└── vendor/             # Composer dependencies
```

## Key Pages
| File | Purpose |
|------|---------|
| pages/login.php | Hiring manager login |
| pages/dashboard.php | All applications — stats, search, inline data |
| pages/create_application.php | New application form |
| pages/save_application.php | Saves application + generates magic token |
| pages/application_link.php | Shows magic upload link after creation |
| pages/application_detail.php | Full candidate detail — docs, extracted data, edit, PDF |
| pages/upload.php | Candidate-facing upload page (token-based, no login) |
| pages/save_document.php | File upload handler — validation, blur, OCR, parsing |
| pages/generate_report.php | Generates PDF report using FPDF, saves to uploads/reports/ |
| pages/update_aadhaar_data.php | Manual correction endpoint for Aadhaar extracted data |
| pages/update_resume_data.php | Manual correction endpoint for resume extracted data |
| pages/logout.php | Destroys session |

## Database Tables
| Table | Purpose |
|-------|---------|
| `users` | Hiring managers (id, email, password) |
| `applications` | Job applications (id, candidate_name, candidate_email, role, token, created_by, created_at) |
| `documents` | Uploaded files (id, application_id, document_type, file_url, blur_status, processed_status, uploaded_at) |
| `aadhaar_data` | OCR-extracted Aadhaar fields (document_id, aadhaar_number, name, dob, extracted_at) |
| `resume_data` | AI-parsed resume fields (document_id, name, email, phone, skills, education, latest_company, latest_role, latest_start_date, latest_end_date, address, linkedin, github, extracted_at) |
| `pdf_reports` | Generated report paths (document_id, pdf_path, generated_at) |

## Key Functions — `config/functions.php`
| Function | What it does |
|----------|-------------|
| `detect_blur($file_path)` | GD Laplacian variance — returns `clear` / `blurry` / `skipped` (PDFs) |
| `extract_aadhaar_data($file_path)` | Mistral pixtral vision OCR on image or PDF |
| `validate_aadhaar_data($data)` | 12-digit number, non-empty name, valid DOB formats |
| `extract_resume_text($file_path)` | smalot/pdfparser — returns raw text from PDF |
| `parse_resume_data($text)` | Mistral text model — returns structured fields array |
| `validate_resume_data($data)` | Name, email format, phone digits, experience exists |

## Document Processing Flow
1. Candidate uploads file → `save_document.php`
2. **Aadhaar:** blur detection → if clear/skipped → Mistral OCR → validate → store in `aadhaar_data`
3. **Resume:** PDF text extraction → Mistral parse → validate → store in `resume_data`
4. On failure: `processed_status = 'failed'`, return `validation_failed:message` to client
5. On blur: return `blurry` to client → candidate re-uploads
6. Hiring manager can manually correct extracted data via detail page

## Code Standards
- Modular architecture — all shared logic in `config/functions.php`
- No large unstructured code blocks
- Plain passwords (no hashing) for now
- Strip markdown code fences from Mistral responses before `json_decode()`
- Always check `($blur_status === 'clear' || $blur_status === 'skipped')` before OCR — PDFs are always skipped for blur

## Git Workflow
- One task → One GitHub issue → One branch
- Branch naming: `feature/issue-{number}-short-description`
- Commit and push to GitHub, close issue after merge
