# API Architecture Documentation

## Overview

The API has been refactored from a monolithic structure to a component-based architecture with an orchestrator pattern. This improves maintainability, testability, and makes it easier to add new features.

## Architecture

### Components

```
/api
├── BaseComponent.php          # Base class for all components
├── Orchestrator.php           # Routes requests to components
└── components/
    ├── AuthComponent.php      # Authentication operations
    ├── FormComponent.php      # Form operations
    ├── TaskComponent.php      # Task operations
    └── UserComponent.php      # User profile operations
```

### How It Works

1. **api.php** - Main entry point
   - Initializes session and database
   - Creates Orchestrator instance
   - Routes requests based on action parameter

2. **Orchestrator** - Request router
   - Maps actions to appropriate components
   - Instantiates components with shared dependencies
   - Handles unknown actions gracefully

3. **BaseComponent** - Abstract base class
   - Provides common functionality:
     - Authentication checks (`requireAuth()`)
     - Session timeout management
     - Response helpers (`sendSuccess()`, `sendError()`)
     - Parameter validation
     - Database initialization
   - Each component extends this class

4. **Component Classes** - Handle specific domains
   - Implement `handleAction($action)` method
   - Use inherited helper methods
   - Keep logic focused on single responsibility

## Component Responsibilities

### AuthComponent
- `login` - User authentication (username/password or ID)
- `check_session` - Session validation
- `logout` - Session destruction

### FormComponent
- `get_questions` - Fetch form questions
- `submit` - Submit complete form
- `auto_save` - Auto-save individual answers

### TaskComponent
- `get_dashboard` - Get user tasks dashboard
- `get_task_detail` - Get specific task details
- `update_task_status` - Update task status

### UserComponent
- `get_user_info` - Get user profile
- `update_username` - Update username
- `update_password` - Update password
- `setup_credentials` - Initial credential setup
- `check_needs_credential_setup` - Check if setup needed

## Bug Fixes

### Task Progress Table
The original code referenced a `task_progress` table that wasn't being created during initialization. This caused 500 errors when loading task details.

**Fix**: Added `task_progress` table creation in `BaseComponent::initializeCourseTables()`:

```sql
CREATE TABLE task_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_task_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    progress_percentage INTEGER DEFAULT 0,
    started_at DATETIME,
    completed_at DATETIME,
    reviewed_at DATETIME,
    reviewed_by INTEGER,
    review_notes TEXT,
    submission_data TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE,
    UNIQUE(user_task_id)
)
```

## Adding New Actions

To add a new action:

1. **Choose the right component** (or create a new one)
2. **Add action to Orchestrator** mapping:
   ```php
   'new_action' => 'ComponentName',
   ```
3. **Implement handler** in component:
   ```php
   case 'new_action':
       return $this->handleNewAction();
   ```
4. **Add method** to component class:
   ```php
   private function handleNewAction() {
       // Implementation
   }
   ```

## Creating New Components

1. Create file in `/api/components/`
2. Extend `BaseComponent`:
   ```php
   class NewComponent extends BaseComponent {
       public function handleAction($action) {
           switch ($action) {
               case 'action_name':
                   return $this->handleAction();
           }
       }
   }
   ```
3. Add to `Orchestrator.php` requires
4. Map actions in Orchestrator constructor

## Benefits

- **Separation of Concerns**: Each component handles one domain
- **Reusability**: Common functionality in BaseComponent
- **Testability**: Components can be tested independently
- **Maintainability**: Easier to locate and fix bugs
- **Scalability**: Easy to add new features
- **Readability**: Smaller, focused files vs 1400-line monolith

## Migration Notes

- Original `api.php` backed up as `api.php.backup`
- All existing actions preserved
- Backward compatible - no frontend changes needed
- Session handling unchanged
- Security headers maintained

## Performance

- No performance impact
- Same number of database queries
- Slight overhead from class instantiation (negligible)
- Better for long-term maintenance

## Testing

Test each endpoint:

```bash
# Login
curl -X POST http://localhost/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","tz":"123456789"}'

# Get tasks
curl -X POST http://localhost/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_dashboard"}' \
  -b cookies.txt

# Get task detail
curl -X POST http://localhost/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_task_detail","user_task_id":"1"}' \
  -b cookies.txt
```

## Future Improvements

- Add middleware layer for logging, rate limiting
- Extract database operations to repositories
- Add validation layer
- Implement caching for frequently accessed data
- Add automated tests for each component
