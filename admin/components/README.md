# 📦 Reusable Admin Components

This directory contains reusable components for the admin panel to avoid code duplication and make maintenance easier.

## 🎯 Components Available

### 1. **Sidebar Navigation** (`sidebar.php` / `sidebar.js`)
The main admin navigation sidebar used across all admin pages.

### 2. **Mobile Menu** (`mobile-menu.js`)
JavaScript for the mobile hamburger menu functionality.

### 3. **Student Header** (`../components/student-header.php`)
Header navigation for student pages.

---

## 🚀 Usage

### **Option A: PHP Includes** (Recommended)

#### Step 1: Rename page from `.html` to `.php`
```bash
mv admin/dashboard.html admin/dashboard.php
```

#### Step 2: Use PHP include for sidebar

**At the top of your admin page:**
```php
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'dashboard'; // or 'students', 'tasks', 'forms', etc.
        $basePath = './';          // adjust based on folder depth
        include __DIR__ . '/components/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="admin-main">
        <h1>Your content here</h1>
    </main>

    <script src="admin.js"></script>
    <script src="components/mobile-menu.js"></script>
</body>
</html>
```

#### Step 3: Update links
Update all internal links to point to `.php` instead of `.html`

---

### **Option B: JavaScript (No file renaming needed)**

**In your HTML page:**
```html
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body" data-active-page="dashboard">
    <!-- Sidebar will be injected here automatically -->

    <!-- Main Content -->
    <main class="admin-main">
        <h1>Your content here</h1>
    </main>

    <script src="admin.js"></script>
    <script src="components/sidebar.js"></script>
    <script src="components/mobile-menu.js"></script>
</body>
</html>
```

**Active Page Values:**
- `dashboard` - Dashboard page
- `students` - Student management
- `tasks` - Task library
- `assign` - Bulk assignment
- `materials` - Course materials
- `reports` - Reports & analytics
- `forms` - Form builder
- `questions` - Question library
- `responses` - View responses

---

## 🎨 Changing the Navigation

### **If using PHP includes** (Option A):
✅ Edit **ONE file**: `admin/components/sidebar.php`
✅ Changes appear on **ALL pages** automatically!

### **If using current HTML** (No components):
❌ Need to edit **14+ files** individually
❌ Easy to miss files and create inconsistencies

---

## 📝 Example: Adding a New Menu Item

### Edit `sidebar.php` or `sidebar.js`:

```html
<!-- Add this in the "ניהול קורס" section -->
<a href="${basePath}course/certificates.html" class="nav-item ${activePage === 'certificates' ? 'active' : ''}">
    <span class="nav-icon">🏆</span>
    <span>תעודות</span>
</a>
```

**That's it!** The new menu item appears on all admin pages.

---

## 🔄 Migration Path

### Current State
- ❌ Navigation duplicated in ~14 files
- ❌ Changes require editing multiple files
- ✅ Works without PHP

### Recommended: Migrate to PHP Components

1. **Rename one page** to `.php` (e.g., `dashboard.html` → `dashboard.php`)
2. **Replace navigation** with PHP include
3. **Test** the page works
4. **Repeat** for all other admin pages
5. **Update** all internal links from `.html` → `.php`

### Benefits After Migration
- ✅ Edit menu in ONE place
- ✅ Guaranteed consistency
- ✅ Easier maintenance
- ✅ Can add dynamic features (user permissions, etc.)

---

## 🛠️ Maintenance

### Adding a Menu Item
1. Edit `components/sidebar.php`
2. Add new `<a>` tag with proper icon and link
3. Save - done! ✅

### Changing Menu Order
1. Edit `components/sidebar.php`
2. Reorder the `<a>` tags
3. Save - done! ✅

### Updating Icons or Labels
1. Edit `components/sidebar.php`
2. Change the icon emoji or text
3. Save - done! ✅

---

## 📚 File Structure

```
admin/
├── components/
│   ├── README.md           ← You are here
│   ├── sidebar.php         ← PHP sidebar component (recommended)
│   ├── sidebar.js          ← JavaScript sidebar (alternative)
│   └── mobile-menu.js      ← Mobile menu toggle
├── dashboard.html          ← Admin pages (current)
├── course/
│   ├── index.html
│   ├── tasks.html
│   └── ...
├── forms/
└── ...
```

---

## 💡 Pro Tips

1. **Use PHP includes** - Most maintainable solution
2. **Keep components simple** - Easy to understand and modify
3. **Document active page values** - Clear naming convention
4. **Test after changes** - Check navigation works on all pages

---

## 🆘 Support

Need help with component integration? Check:
1. This README
2. Example in `sidebar.php` comments
3. Test with one page first before migrating all
