# Task Management Features

This document describes the new task management features added to the course management system.

## Features Overview

### 1. Task Scoring (0-100%)
- Each task can now be graded with a score from 0-100%
- The score is **separate** from the task's base points value
- The score represents the percentage of points earned
- Example: A task worth 20 points with a 90% score = 18 points earned

### 2. Task Review/Feedback
- Instructors can provide detailed feedback/explanations for each task
- The feedback is visible to students
- Useful for explaining why a task was approved/rejected
- Helps students understand how to improve

### 3. Task Preview Modal
- **NEW**: Click "👁️ Preview Full Task" to see complete task details before review
- Shows all task information, submissions, files, and previous feedback
- View uploaded files with download links
- See file metadata (size, upload date, description)
- Review task description and requirements
- Make informed decisions before approving/rejecting

### 4. File Upload Support
- **NEW**: Students can upload files for task submissions
- Instructors see file count in task cards (📎 2 files)
- Download links available directly in task cards
- Full file details in preview modal
- Supports multiple file types (PDF, Word, Excel, images, ZIP)
- Maximum file size: 10MB per file

### 5. Enhanced Review Workflow
- **NEW**: Four review options instead of just approve/reject:
  1. **✓ Approve** - Task completed successfully (with optional score/feedback)
  2. **↩️ Return to Student** - Send back for corrections (with feedback)
  3. **✗ Reject** - Task rejected (with feedback required)
  4. **🔍 Mark as Checking** - Indicate review in progress
- All actions send automatic notifications to students
- Flexible grading: score and feedback are optional

### 6. Reset Task
- Allows instructors to reset a task to its initial state
- Clears all progress, submissions, grades, and feedback
- Sets the task status back to "pending"
- Student receives a notification about the reset
- Useful when a student needs to redo a task from scratch

### 7. Remove Task
- Allows instructors to completely remove a task assignment from a specific user
- Deletes all related data (progress, submissions, comments)
- Student receives a notification about the removal
- Use with caution - this action cannot be undone!

## Database Schema Changes

The following fields were added to the `user_tasks` table:

| Field | Type | Description |
|-------|------|-------------|
| `grade` | REAL | Score from 0-100% (NULL if not graded) |
| `feedback` | TEXT | Instructor's review text/explanation |
| `submitted_at` | DATETIME | When student submitted the task |
| `reviewed_at` | DATETIME | When instructor reviewed the task |
| `submission_text` | TEXT | Student's text submission |
| `submission_file_path` | TEXT | Path to student's file submission |

## Task Statuses

The system now supports the following task statuses:

| Status | Hebrew | Description |
|--------|--------|-------------|
| `pending` | ממתינה | Task assigned but not started |
| `in_progress` | בתהליך | Student is working on the task |
| `completed` | הושלמה | Student marked as complete |
| `needs_review` | לבדיקה | Submitted and waiting for instructor review |
| `checking` | **NEW** בבדיקה | Instructor is currently reviewing |
| `approved` | אושרה | Task approved by instructor |
| `rejected` | נדחתה | Task rejected by instructor |
| `returned` | **NEW** הוחזרה לתלמיד | Returned to student for corrections |

## API Endpoints

### Review Task with Score and Feedback
**Endpoint:** `POST /admin/api.php`
**Action:** `review_task`

**Supported Statuses:** `approved`, `rejected`, `returned`, `checking`

**Parameters:**
```json
{
  "action": "review_task",
  "user_task_id": 123,
  "status": "approved",
  "grade": 85.5,
  "feedback": "Great work! Consider improving the structure.",
  "review_notes": "Internal notes (not visible to student)"
}
```

**Example - Return to Student:**
```json
{
  "action": "review_task",
  "user_task_id": 123,
  "status": "returned",
  "feedback": "Please fix the formatting in section 2 and resubmit."
}
```

**Example - Mark as Checking:**
```json
{
  "action": "review_task",
  "user_task_id": 123,
  "status": "checking"
}
```

**Response:**
```json
{
  "success": true,
  "message": "הסטטוס עודכן בהצלחה"
}
```

### Reset Task
**Endpoint:** `POST /admin/api.php`
**Action:** `reset_user_task`

**Parameters:**
```json
{
  "action": "reset_user_task",
  "user_task_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "message": "המשימה אופסה בהצלחה"
}
```

### Remove Task
**Endpoint:** `POST /admin/api.php`
**Action:** `remove_user_task`

**Parameters:**
```json
{
  "action": "remove_user_task",
  "user_task_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "message": "המשימה הוסרה בהצלחה"
}
```

## UI Changes

### User Detail Page (`admin/course/user-detail.php`)

**New Elements:**

1. **Score Display:** Shows the task score (0-100%) in the task metadata
   - Displayed as: 📊 ציון: 85%
   - Only shown if a grade has been assigned

2. **Feedback Display:** Shows instructor's feedback in a highlighted box
   - Displayed below task description
   - Only shown if feedback has been provided

3. **Review Form:** Enhanced with three sections:
   - **Grade Input:** Number input (0-100) for task score
   - **Feedback Text:** Textarea for instructor's review/explanation (visible to student)
   - **Internal Notes:** Textarea for private instructor notes (not visible to student)

4. **Action Buttons:**
   - **🔄 Reset:** Resets the task to initial state
   - **🗑️ Remove:** Completely removes the task assignment

## Migration Instructions

### Option 1: PHP Migration Script (Recommended)
```bash
php admin/migrate_add_task_review_fields.php
```

### Option 2: Manual SQL Execution
```bash
sqlite3 course_management.db < admin/migration_task_review_fields.sql
```

## Usage Examples

### Example 1: Review a Task with File Submission
1. Navigate to a student's detail page
2. See tasks with file submissions (📎 2 files indicator)
3. Click **"👁️ Preview Full Task"** to view:
   - Complete task description
   - All uploaded files with download links
   - Previous feedback (if any)
   - Current grade and status
4. Download and review submitted files
5. Click **"🔍 Mark as Checking"** to indicate you're reviewing
6. After review, click one of:
   - **"✓ Approve"** - Enter grade and optional feedback
   - **"↩️ Return"** - Provide feedback for corrections
   - **"✗ Reject"** - Provide feedback explaining why
7. Student receives automatic notification

### Example 2: Return Task to Student for Corrections
1. Open student's detail page
2. Click "👁️ Preview Full Task" on a submitted task
3. Review the submission and identify issues
4. Click "↩️ Return" button
5. Enter feedback: "Please fix the following: 1) Add references, 2) Fix formatting"
6. Click "שלח החזרה"
7. Task status changes to "returned"
8. Student gets notification with feedback

### Example 3: Grade a Task with Feedback
1. Navigate to a student's detail page
2. Find a task that needs review (status: "needs_review" or "checking")
3. Click "👁️ Preview Full Task" to review everything first
4. Click "✓ Approve" button in task card
5. Enter grade (e.g., 85)
6. Enter feedback (e.g., "Good work! The analysis is thorough.")
7. Optional: Add internal notes for your records
8. Click "✓ Send Approval"

### Example 4: Reset a Task
1. Navigate to a student's detail page
2. Find the task you want to reset
3. Click "🔄 Reset" button
4. Confirm the action
5. Task is reset to "pending" status with all data cleared

### Example 5: Remove a Task Assignment
1. Navigate to a student's detail page
2. Find the task you want to remove
3. Click "🗑️ Remove" button
4. Confirm the action (warning: cannot be undone!)
5. Task assignment is completely removed

## Notes

- The **score (grade)** is optional - you can approve/reject tasks without providing a score
- The **feedback** is highly recommended when rejecting tasks to help students improve
- **Internal notes** are only visible to instructors and can be used for record-keeping
- **Reset** preserves the task template, only clearing the student's progress
- **Remove** deletes the entire assignment - use carefully!

## Relationship Between Points and Score

The system now has two separate concepts:

1. **Task Points** (`course_tasks.points`): The maximum points a task is worth
2. **Task Score** (`user_tasks.grade`): The percentage (0-100%) of those points earned

**Example:**
- Task: "Complete Quiz 1"
- Points: 20
- Student Score: 85%
- Points Earned: 20 × 0.85 = 17 points

This allows for:
- Different tasks to have different point values (weight)
- Students to earn partial credit on tasks
- More granular grading and assessment
