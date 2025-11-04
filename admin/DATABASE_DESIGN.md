# Database Design for Admin Management System

## 📊 Complete Database Schema

### Current Problems:
- ❌ Questions hardcoded in JavaScript
- ❌ Only one form possible
- ❌ No way to manage question types
- ❌ No sequence control
- ❌ No form assignment to users

### New Solution:
✅ Dynamic forms
✅ Flexible question types
✅ Multiple forms support
✅ User-form assignments
✅ Full admin control

---

## 🗄️ Database Tables

### 1. **users** (Enhanced)
Stores user information and authentication

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tz TEXT UNIQUE NOT NULL,                    -- ID card number (9 digits)
    full_name TEXT,                             -- User's full name
    email TEXT,                                 -- Email address
    phone TEXT,                                 -- Phone number
    role TEXT DEFAULT 'user',                   -- 'user' | 'admin' | 'manager'
    is_blocked INTEGER DEFAULT 0,               -- 1 = blocked, 0 = active
    failed_attempts INTEGER DEFAULT 0,          -- Login attempt counter
    ip_address TEXT,                            -- Last login IP
    last_login TEXT,                            -- Last login timestamp
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

**Indexes:**
```sql
CREATE INDEX idx_users_tz ON users(tz);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_is_blocked ON users(is_blocked);
```

---

### 2. **forms** (New)
Stores form definitions

```sql
CREATE TABLE forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,                        -- Form title (e.g., "טופס גיוס")
    description TEXT,                           -- Form description
    is_active INTEGER DEFAULT 1,                -- 1 = active, 0 = inactive
    allow_multiple_submissions INTEGER DEFAULT 0, -- Allow resubmit
    show_progress INTEGER DEFAULT 1,            -- Show progress bar
    created_by INTEGER,                         -- Admin user ID
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

**Indexes:**
```sql
CREATE INDEX idx_forms_is_active ON forms(is_active);
```

---

### 3. **question_types** (New)
Defines available question types

```sql
CREATE TABLE question_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_code TEXT UNIQUE NOT NULL,             -- 'text', 'number', 'email', etc.
    type_name TEXT NOT NULL,                    -- Display name
    validation_rules TEXT,                      -- JSON validation rules
    html_input_type TEXT,                       -- 'text', 'email', 'tel', 'number'
    description TEXT
);
```

**Pre-populated Types:**
```sql
INSERT INTO question_types (type_code, type_name, html_input_type, description) VALUES
('text', 'טקסט חופשי', 'text', 'שדה טקסט פתוח'),
('text_short', 'טקסט קצר', 'text', 'טקסט מוגבל ל-100 תווים'),
('textarea', 'טקסט ארוך', 'textarea', 'שדה טקסט רב שורות'),
('number', 'מספר', 'number', 'שדה מספרי'),
('number_range', 'מספר בטווח', 'number', 'מספר עם min/max'),
('email', 'דוא"ל', 'email', 'כתובת אימייל'),
('phone', 'טלפון', 'tel', 'מספר טלפון'),
('tz', 'תעודת זהות', 'tel', 'מספר ת.ז 9 ספרות'),
('date', 'תאריך', 'date', 'בחירת תאריך'),
('select', 'בחירה מרשימה', 'select', 'תפריט נפתח'),
('radio', 'בחירה יחידה', 'radio', 'רדיו כפתורים'),
('checkbox', 'בחירה מרובה', 'checkbox', 'תיבות סימון'),
('rating', 'דירוג', 'number', 'דירוג 1-5'),
('yes_no', 'כן/לא', 'radio', 'שאלת כן/לא');
```

---

### 4. **questions** (New)
Stores all questions

```sql
CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_text TEXT NOT NULL,                -- Question content
    question_type_id INTEGER NOT NULL,          -- FK to question_types
    placeholder TEXT,                           -- Input placeholder
    help_text TEXT,                             -- Help/hint text
    is_required INTEGER DEFAULT 1,              -- 1 = required, 0 = optional
    validation_rules TEXT,                      -- JSON validation (min, max, regex, etc.)
    options TEXT,                               -- JSON array for select/radio/checkbox
    default_value TEXT,                         -- Default answer
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_type_id) REFERENCES question_types(id)
);
```

**Example JSON structures:**

**validation_rules:**
```json
{
  "min_length": 2,
  "max_length": 100,
  "pattern": "^[a-zA-Z\\s]+$",
  "min_value": 1,
  "max_value": 10
}
```

**options (for select/radio/checkbox):**
```json
["אופציה 1", "אופציה 2", "אופציה 3"]
```

**Indexes:**
```sql
CREATE INDEX idx_questions_type ON questions(question_type_id);
```

---

### 5. **form_questions** (New)
Maps questions to forms with sequence

```sql
CREATE TABLE form_questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    sequence_order INTEGER NOT NULL,            -- Display order (1, 2, 3...)
    is_active INTEGER DEFAULT 1,                -- Can disable without deleting
    section_title TEXT,                         -- Optional section grouping
    conditional_logic TEXT,                     -- JSON: show if another answer matches
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE(form_id, question_id)
);
```

**conditional_logic example:**
```json
{
  "show_if": {
    "question_id": 5,
    "operator": "equals",
    "value": "כן"
  }
}
```

**Indexes:**
```sql
CREATE INDEX idx_form_questions_form ON form_questions(form_id);
CREATE INDEX idx_form_questions_sequence ON form_questions(form_id, sequence_order);
```

---

### 6. **user_forms** (New)
Assigns forms to users

```sql
CREATE TABLE user_forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    form_id INTEGER NOT NULL,
    assigned_by INTEGER,                        -- Admin who assigned
    assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
    due_date TEXT,                              -- Optional deadline
    status TEXT DEFAULT 'pending',              -- 'pending' | 'in_progress' | 'completed'
    started_at TEXT,                            -- When user started
    completed_at TEXT,                          -- When user finished
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE(user_id, form_id)
);
```

**Indexes:**
```sql
CREATE INDEX idx_user_forms_user ON user_forms(user_id);
CREATE INDEX idx_user_forms_status ON user_forms(status);
```

---

### 7. **form_responses** (Enhanced)
Stores user answers

```sql
CREATE TABLE form_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    form_id INTEGER NOT NULL,                   -- NEW: Which form
    question_id INTEGER NOT NULL,               -- FK to questions table
    answer_value TEXT,                          -- The answer
    answer_json TEXT,                           -- For complex answers (checkbox arrays)
    submitted_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id),
    UNIQUE(user_id, form_id, question_id)
);
```

**Indexes:**
```sql
CREATE INDEX idx_responses_user ON form_responses(user_id);
CREATE INDEX idx_responses_form ON form_responses(form_id);
CREATE INDEX idx_responses_user_form ON form_responses(user_id, form_id);
```

---

### 8. **admin_users** (New)
Admin authentication (separate from regular users)

```sql
CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,                -- bcrypt/argon2 hash
    email TEXT UNIQUE NOT NULL,
    full_name TEXT,
    role TEXT DEFAULT 'admin',                  -- 'super_admin' | 'admin' | 'viewer'
    is_active INTEGER DEFAULT 1,
    last_login TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

**Indexes:**
```sql
CREATE INDEX idx_admin_username ON admin_users(username);
CREATE INDEX idx_admin_email ON admin_users(email);
```

---

### 9. **activity_log** (New)
Audit trail for admin actions

```sql
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER,
    action TEXT NOT NULL,                       -- 'create', 'update', 'delete'
    entity_type TEXT NOT NULL,                  -- 'user', 'form', 'question'
    entity_id INTEGER,
    old_value TEXT,                             -- JSON of old data
    new_value TEXT,                             -- JSON of new data
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
);
```

**Indexes:**
```sql
CREATE INDEX idx_activity_admin ON activity_log(admin_user_id);
CREATE INDEX idx_activity_date ON activity_log(created_at);
CREATE INDEX idx_activity_entity ON activity_log(entity_type, entity_id);
```

---

## 🔄 Relationships

```
users 1─────────────────────* user_forms
                                   │
forms 1─────────────────────* user_forms
  │
  └──1───────────────────────* form_questions
                                   │
questions 1─────────────────────* form_questions
  │
  └──* form_responses

question_types 1────────────* questions

admin_users 1───────────────* activity_log
```

---

## 📝 Migration Plan

### Phase 1: Create New Tables
1. Create all new tables
2. Populate question_types with defaults
3. Create admin_users table

### Phase 2: Migrate Existing Data
1. Keep existing users table data
2. Create default form from current questions.js
3. Link existing form_responses to new structure

### Phase 3: Admin Panel
1. Build admin login
2. Build user management
3. Build form builder
4. Build question builder

---

## 🎯 Key Features Enabled

### User Management
- ✅ Add/edit/delete users
- ✅ Block/unblock users
- ✅ View user submissions
- ✅ Assign forms to users

### Form Management
- ✅ Create multiple forms
- ✅ Activate/deactivate forms
- ✅ Duplicate forms
- ✅ View form statistics

### Question Management
- ✅ Create reusable questions
- ✅ 14+ question types
- ✅ Set validation rules
- ✅ Add/remove options dynamically

### Form Builder
- ✅ Drag-and-drop question ordering
- ✅ Add questions to forms
- ✅ Set required/optional
- ✅ Preview forms
- ✅ Conditional logic

### Reporting
- ✅ View all submissions
- ✅ Export to CSV/Excel
- ✅ Filter by form, user, date
- ✅ Analytics dashboard

---

## 🔐 Security Considerations

1. **Admin Authentication**
   - Separate from user auth
   - Strong password hashing
   - Session timeout
   - IP logging

2. **Data Validation**
   - Input sanitization
   - SQL injection prevention (prepared statements)
   - XSS prevention
   - CSRF tokens

3. **Access Control**
   - Role-based permissions
   - Audit logging
   - API key for admin panel

4. **Data Privacy**
   - Encrypted sensitive fields
   - GDPR compliance ready
   - Data retention policies

---

## 📊 Example Data Flow

### Creating a Form:
1. Admin creates form: "טופס גיוס 2025"
2. Admin adds questions from library
3. Admin sets sequence order
4. Admin assigns to users
5. Users login and see their assigned forms
6. Users fill and submit
7. Admin views responses

---

**Next Steps:**
1. Create migration script
2. Build admin panel API
3. Create admin UI
4. Migrate existing data
5. Test complete system

This structure is **scalable, flexible, and production-ready**! 🚀
