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

### 3. Reset Task
- Allows instructors to reset a task to its initial state
- Clears all progress, submissions, grades, and feedback
- Sets the task status back to "pending"
- Student receives a notification about the reset
- Useful when a student needs to redo a task from scratch

### 4. Remove Task
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

## API Endpoints

### Review Task with Score and Feedback
**Endpoint:** `POST /admin/api.php`
**Action:** `review_task`

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

### Example 1: Grade a Task with Feedback
1. Navigate to a student's detail page
2. Find a task that needs review
3. Click "✓ אישור" or "✗ דחייה" button
4. Enter grade (e.g., 85)
5. Enter feedback (e.g., "Good work, but needs improvement in section 2")
6. Click "שלח אישור" or "שלח דחייה"

### Example 2: Reset a Task
1. Navigate to a student's detail page
2. Find the task you want to reset
3. Click "🔄 איפוס" button
4. Confirm the action
5. Task is reset to "pending" status with all data cleared

### Example 3: Remove a Task Assignment
1. Navigate to a student's detail page
2. Find the task you want to remove
3. Click "🗑️ הסרה" button
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
