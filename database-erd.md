# Database ERD - form_data.db

## Overview
This database supports a comprehensive learning management and form system with user management, dynamic forms/questions, task assignments, progress tracking, file submissions, and activity logging.

## Core Entities & Relationships

### User Management

#### users (Main Users)
```
users
├── id (PK)
├── tz, tz_hash
├── full_name, email, phone, username
├── role, id_type
├── is_active, is_blocked, is_archived
├── password_hash, profile_photo
└── timestamps (created_at, updated_at, last_login)
    │
    ├─── (1:N) → user_forms
    ├─── (1:N) → form_responses
    ├─── (1:N) → user_tasks
    ├─── (1:N) → notifications
    └─── (1:N) → user_activity_log
```

#### admin_users (Admin Users)
```
admin_users
├── id (PK)
├── username, password_hash, email
├── full_name, role
├── is_active
└── timestamps (created_at, updated_at, last_login)
    │
    ├─── (1:N) → forms (created_by)
    ├─── (1:N) → user_forms (assigned_by)
    ├─── (1:N) → activity_log
    ├─── (1:N) → course_tasks (created_by)
    ├─── (1:N) → user_tasks (assigned_by, reviewed_by)
    ├─── (1:N) → course_materials (created_by)
    └─── (1:N) → registration_tokens
```

---

### Forms & Questions System

#### forms
```
forms
├── id (PK)
├── title, description
├── structure_json
├── is_active, allow_multiple_submissions, show_progress
├── created_by (FK → admin_users)
└── timestamps
    │
    ├─── (1:N) → form_questions
    ├─── (1:N) → user_forms
    ├─── (1:N) → form_responses
    └─── (1:N) → course_tasks
```

#### question_types
```
question_types
├── id (PK)
├── type_code, type_name
├── html_input_type, validation_rules
└── description
    │
    └─── (1:N) → questions
```

#### questions
```
questions
├── id (PK)
├── question_text, question_type_id (FK)
├── placeholder, help_text
├── is_required, validation_rules
├── options, default_value
└── timestamps
    │
    └─── (1:N) → form_questions
```

#### form_questions (Junction Table)
```
form_questions
├── id (PK)
├── form_id (FK → forms) CASCADE DELETE
├── question_id (FK → questions) CASCADE DELETE
├── category_id (FK → categories)
├── sequence_order, section_sequence
├── section_title, conditional_logic
├── is_active
└── created_at
    │
    └─── UNIQUE(form_id, question_id)
```

#### categories
```
categories
├── id (PK)
├── name, description, color
├── sequence_order, is_active
└── timestamps
    │
    └─── (1:N) → form_questions
```

---

### User Interactions

#### user_forms (Form Assignments)
```
user_forms
├── id (PK)
├── user_id (FK → users) CASCADE DELETE
├── form_id (FK → forms) CASCADE DELETE
├── assigned_by (FK → admin_users)
├── status, due_date
└── timestamps (assigned_at, started_at, completed_at)
    │
    └─── UNIQUE(user_id, form_id)
```

#### form_responses (User Answers)
```
form_responses
├── id (PK)
├── user_id (FK → users)
├── form_id, question_id
├── answer_value, answer_json
└── timestamps (submitted_at, updated_at)
    │
    └─── UNIQUE(user_id, question_id, form_id)
```

---

### Course & Tasks System

#### course_tasks
```
course_tasks
├── id (PK)
├── title, description, instructions
├── task_type, form_id (FK → forms)
├── estimated_duration, points
├── sequence_order, is_active
├── created_by (FK → admin_users)
└── timestamps
    │
    ├─── (1:N) → user_tasks
    ├─── (1:N) → course_materials
    └─── (1:N) → notifications
```

#### user_tasks (Task Assignments)
```
user_tasks
├── id (PK)
├── user_id (FK → users) CASCADE DELETE
├── task_id (FK → course_tasks) CASCADE DELETE
├── assigned_by (FK → admin_users)
├── reviewed_by (FK → admin_users)
├── status, priority, progress_percentage
├── due_date
├── submission_text, submission_file_path
├── grade, feedback
├── admin_notes, student_notes
└── timestamps (assigned_at, started_at, completed_at, submitted_at, reviewed_at)
    │
    ├─── UNIQUE(user_id, task_id)
    ├─── (1:1) → task_progress
    ├─── (1:N) → task_submissions
    ├─── (1:N) → notifications
    └─── (1:N) → task_comments
```

#### course_materials
```
course_materials
├── id (PK)
├── task_id (FK → course_tasks) CASCADE DELETE
├── title, description
├── material_type (document/video/etc)
├── file_path, external_url, content_text
├── display_order, is_required
├── created_by (FK → admin_users)
└── timestamps
```

#### task_progress
```
task_progress
├── id (PK)
├── user_task_id (FK → user_tasks) CASCADE DELETE
├── status, progress_percentage
├── reviewed_by (FK → admin_users)
├── review_notes, submission_data
└── timestamps (started_at, completed_at, reviewed_at, updated_at)
    │
    └─── UNIQUE(user_task_id)
```

#### task_submissions (File Uploads)
```
task_submissions
├── id (PK)
├── user_task_id (FK → user_tasks) CASCADE DELETE
├── filename, original_filename, filepath
├── filesize, mime_type
├── description
└── uploaded_at
```

#### task_comments
```
task_comments
├── id (PK)
├── user_task_id (FK → user_tasks) CASCADE DELETE
├── author_id, author_type
├── comment_text, is_internal
└── created_at
```

---

### Notifications & Logging

#### notifications
```
notifications
├── id (PK)
├── user_id (FK → users) CASCADE DELETE
├── title, message, notification_type
├── related_task_id (FK → course_tasks)
├── related_user_task_id (FK → user_tasks) CASCADE DELETE
├── is_read
└── timestamps (created_at, read_at)
```

#### activity_log (Admin Activity)
```
activity_log
├── id (PK)
├── admin_user_id (FK → admin_users)
├── action, entity_type, entity_id
├── old_value, new_value
├── ip_address, user_agent
└── created_at
```

#### user_activity_log (User Activity)
```
user_activity_log
├── id (PK)
├── user_id (FK → users) CASCADE DELETE
├── action, entity_type, entity_id
├── details
├── ip_address, user_agent, session_id
└── created_at
```

#### registration_tokens
```
registration_tokens
├── id (PK)
├── token, role
├── preset_full_name
├── created_by_admin_id (FK → admin_users)
├── used_by_admin_id (FK → admin_users)
├── expires_at, used_at, is_active
└── created_at
```

---

## Key Relationships Summary

### One-to-Many Relationships
- `admin_users` → `forms`, `course_tasks`, `course_materials`
- `users` → `user_forms`, `form_responses`, `user_tasks`, `notifications`
- `forms` → `form_questions`, `user_forms`
- `questions` → `form_questions`
- `course_tasks` → `user_tasks`, `course_materials`
- `user_tasks` → `task_submissions`, `task_comments`, `notifications`

### One-to-One Relationships
- `user_tasks` ↔ `task_progress`

### Many-to-Many Relationships (via junction tables)
- `forms` ↔ `questions` (via `form_questions`)
- `users` ↔ `forms` (via `user_forms`)
- `users` ↔ `course_tasks` (via `user_tasks`)

---

## Cascade Delete Policies

### CASCADE DELETE
- Deleting a `user` cascades to: `user_forms`, `form_responses`, `user_tasks`, `notifications`, `user_activity_log`
- Deleting a `form` cascades to: `form_questions`, `user_forms`
- Deleting a `question` cascades to: `form_questions`
- Deleting a `course_task` cascades to: `user_tasks`, `course_materials`
- Deleting a `user_task` cascades to: `task_progress`, `task_submissions`, `task_comments`, `notifications`

### SET NULL
- Deleting an `admin_user` sets `created_by`, `assigned_by`, `reviewed_by` to NULL in related tables
- Deleting a `course_task` sets `form_id` to NULL
- Deleting a `course_task` sets `related_task_id` to NULL in notifications

---

## Database File Location
- Path: `/www/wwwroot/qr.bot4wa.com/kodkod/form_data.db`
- Type: SQLite
- Foreign Keys: Enabled (`PRAGMA foreign_keys = ON`)
