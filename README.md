# CF7 Visual Form Builder

**Contributors:** piyushasthana
**Tags:** contact form 7, form builder, drag and drop, multistep forms, conditional logic
**Requires at least:** 6.1
**Tested up to:** 7.0
**Requires PHP:** 7.4
**Stable tag:** 1.3.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Professional drag-and-drop form builder addon for **Contact Form 7** (`wpcf7_contact_form` CPT).

## Requirements

- WordPress 6.1+, PHP 7.4+, Contact Form 7 active

## Installation

1. Activate plugin → tables created automatically
2. **Contact → Contact Forms** → edit form → **Visual Builder** tab
3. **Contact → Entries** / **Analytics** for reporting

## Feature checklist (spec coverage)

### Core objectives
| Feature | Status |
|---------|--------|
| Visual drag-and-drop builder | ✅ |
| Live form preview | ✅ (modal + markup) |
| Multistep forms | ✅ |
| Conditional logic | ✅ (AND/OR, nested groups, actions) |
| Validation engine | ✅ (required, min/max length, regex, email) |
| Submission management | ✅ |
| Email notifications | ✅ (CF7 Mail + HTML wrapper + conditional emails) |
| Thank you pages | ✅ (message, redirect, page ID) |
| GDPR compliance | ✅ (acceptance checkbox) |
| Accessibility | ✅ (ARIA, high contrast, focus) |
| Security & anti-spam | ✅ (honeypot, rate limit) |

### Visual builder
| Feature | Status |
|---------|--------|
| Drag & drop fields | ✅ |
| Inline editing | ✅ (double-click label) |
| Responsive preview | ✅ |
| Undo/Redo | ✅ |
| Duplicate fields | ✅ |
| Section grouping | ✅ |
| Mobile/tablet preview | ✅ |

### Field types
Text, Textarea, Email, Phone, Number, Select, Checkbox, Radio, File, Date, Hidden, Signature, Rating, Repeater, Submit — **all supported** in palette + CF7 compile.

### Conditional logic
| Feature | Status |
|---------|--------|
| Visual IF/THEN builder | ✅ Logic tab |
| AND/OR conditions | ✅ |
| Nested conditions | ✅ (nested groups) |
| Conditional emails | ✅ |
| Conditional redirects | ✅ |
| Dynamic step control | ✅ (skip step) |

### Multistep
| Feature | Status |
|---------|--------|
| Progress bars | ✅ |
| Step indicators | ✅ (dots) |
| Autosave | ✅ (localStorage + REST draft) |
| Save & continue later | ✅ |
| Animated transitions | ✅ |
| Conditional step skipping | ✅ |

### Submissions
| Feature | Status |
|---------|--------|
| Database storage | ✅ `wp_cf7vb_entries` |
| Search & filters | ✅ |
| CSV export | ✅ |
| PDF export | ✅ (print-ready HTML) |
| Frontend entry management | ✅ `[cf7vb_entries]` shortcode |
| Status management | ✅ (unread/read/starred/spam/trash) |

### Email
| Feature | Status |
|---------|--------|
| Admin notifications | ✅ via CF7 Mail |
| User autoresponders | ✅ via CF7 Mail (2) |
| HTML email templates | ✅ brand wrapper |
| PDF attachments | ⚠️ Use CF7 + PDF plugin (hook ready) |
| Conditional email workflows | ✅ Logic panel |

### Analytics
| Feature | Status |
|---------|--------|
| Submission analytics | ✅ |
| Conversion tracking | ✅ |
| Abandonment tracking | ✅ |
| Device analytics | ✅ |
| Field interaction reports | ✅ |
| Conversational forms | ✅ |
| Analytics dashboard | ✅ Contact → Analytics |

## REST API (`cf7vb/v1`)

- `GET/PUT /forms/{id}/schema`
- `POST /forms/{id}/compile`
- `GET /entries`, `PUT /entries/{id}`
- `POST /analytics/track`
- `GET/POST /drafts`

## Author

Piyush Asthana
