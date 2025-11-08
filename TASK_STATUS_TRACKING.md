# Task Status Tracking System

## Overview
A comprehensive task status tracking and analytics system that provides three different views for monitoring task assignments, completion status, and student progress.

## Features

### 📋 View 1: Single Task Selector (Detailed View)
**Purpose:** Deep dive into a specific task to see all student assignments

**Features:**
- Dropdown selector to choose any task
- Real-time statistics dashboard:
  - Total assigned
  - Total completed
  - Total pending
  - Total overdue
  - Average grade
- Detailed assignment grid showing:
  - Student name and email
  - Current status (with color coding)
  - Priority level
  - Due date
  - Submission date
  - Grade
  - Quick action buttons
- Filters:
  - Search by student name
  - Filter by status
  - Filter by priority
- Export to CSV
- Print functionality

**Best for:** Grading sessions, checking specific task completion, following up on overdue assignments

### 📊 View 2: List View with Expandable Rows
**Purpose:** Quick overview of all tasks with expandable details

**Features:**
- Shows all tasks in a collapsible list
- Each task row displays:
  - Task name, type, and points
  - Number of students assigned
  - Completion progress bar
  - Completion percentage
- Click to expand and see:
  - Full assignment table for that task
  - All students assigned
  - Status, priority, grades
- Filters:
  - Search by task name
  - Filter by task type (assignment, reading, form, quiz, video)
- Export all data to CSV

**Best for:** General monitoring, quick status checks, comparing tasks side-by-side

### 📈 View 3: Analytics Dashboard
**Purpose:** High-level analytics and insights across all tasks

**Features:**
- Summary cards with key metrics:
  - Total assignments
  - Completed successfully
  - Pending
  - Overdue
- Charts and visualizations:
  - Status distribution across all tasks
  - Top 10 task performance (by completion rate)
  - Grade distribution histogram
- Export full analytics report
- Refresh data button

**Best for:** Weekly reviews, course planning, identifying problem areas, reporting

## Status Indicators

### Status Badges (Color Coded)
- 🟡 **Pending** - Not yet started (yellow)
- 🔵 **In Progress** - Student is working on it (blue)
- 🟢 **Completed** - Finished by student (green)
- 🟠 **Needs Review** - Waiting for admin grading (orange)
- ✅ **Approved** - Graded and approved (dark green)
- 🔴 **Rejected** - Rejected by admin (red)
- ⚠️ **Overdue** - Past due date and not completed (red)

### Priority Badges
- 🔴 **High** - Urgent (red)
- 🟡 **Medium** - Normal priority (yellow)
- ⚪ **Low** - Can wait (gray)

## API Endpoints

### `get_task_assignments`
Get all assignments for a specific task.

**Request:**
```json
{
  "action": "get_task_assignments",
  "task_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "assignments": [
    {
      "id": 1,
      "user_id": 45,
      "task_id": 123,
      "status": "completed",
      "grade": 85,
      "due_date": "2025-11-15",
      "submitted_at": "2025-11-14",
      "priority": "medium",
      "full_name": "John Doe",
      "email": "john@example.com",
      ...
    }
  ]
}
```

### `get_all_task_assignments`
Get all assignments for all tasks (for analytics).

**Request:**
```json
{
  "action": "get_all_task_assignments"
}
```

**Response:**
```json
{
  "success": true,
  "assignments": [
    {
      "id": 1,
      "user_id": 45,
      "task_id": 123,
      "task_title": "Complete reading assignment",
      "task_type": "reading",
      "status": "completed",
      ...
    }
  ]
}
```

## Usage Guide

### Accessing the Page
1. Login to admin panel
2. Navigate to **מעקב סטטוס משימות** (Task Status Tracking) in the sidebar
3. Choose your preferred view using the tabs at the top

### Common Workflows

#### Workflow 1: Check Who Hasn't Submitted a Task
1. Go to **View 1: Single Task Selector**
2. Select the task from dropdown
3. Filter by status = "Pending" or "In Progress"
4. See list of students who haven't completed
5. Click "View" to see student detail page

#### Workflow 2: Grade Multiple Submissions
1. Go to **View 1: Single Task Selector**
2. Select the task
3. Filter by status = "Needs Review"
4. Click "View" for each student to grade
5. Export to CSV for record keeping

#### Workflow 3: Weekly Progress Review
1. Go to **View 3: Analytics Dashboard**
2. Review summary cards
3. Check task performance chart
4. Identify tasks with low completion rates
5. Export full report

#### Workflow 4: Follow Up on Overdue Tasks
1. Go to **View 2: List View**
2. Expand each task
3. Look for red "Overdue" badges
4. Contact students or send reminders

### Export Features

**CSV Export Options:**
- **View 1:** Export current task assignments
- **View 2:** Export all tasks and assignments
- **View 3:** Export analytics summary

**CSV Format:**
All exports include UTF-8 BOM for proper Hebrew character display in Excel.

## Technical Details

### Files Created/Modified
- **Created:** `/admin/course/task-status.php` - Main page
- **Modified:** `/admin/api.php` - Added new API endpoints
- **Modified:** `/admin/components/sidebar.php` - Added navigation link

### Database Queries
The system queries the following tables:
- `course_tasks` - Task templates
- `user_tasks` - Task assignments
- `users` - Student information

### Performance Notes
- All data is loaded once on page load
- Client-side filtering for fast response
- Minimal database queries
- Caches data in JavaScript for view switching

## Browser Compatibility
- ✅ Chrome/Edge (Recommended)
- ✅ Firefox
- ✅ Safari
- ✅ Mobile browsers (responsive design)

## Future Enhancements (Possible)
- Real-time updates with WebSockets
- Advanced charts with Chart.js or similar
- Email reminders for overdue tasks
- Bulk status updates
- Custom date range filtering
- Student activity timeline

---

**Created:** 2025-11-08
**Version:** 1.0
**Location:** `/admin/course/task-status.php`
