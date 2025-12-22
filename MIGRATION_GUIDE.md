# Roll Number Migration Guide

## Quick Start

After deploying the automatic roll number assignment changes, follow these steps:

### 1. Run the Migration Command

To reassign roll numbers for **all existing students** in **all classes**:

```bash
php artisan students:reassign-roll-numbers
```

To reassign roll numbers for a **specific class only**:

```bash
php artisan students:reassign-roll-numbers --class_id=5
```

### 2. Update Frontend

Remove or disable the `roll_number` input field from:

-   Student creation form
-   Student edit form
-   Bulk upload Excel/CSV template

### 3. Test the System

1. Create a new student → Verify roll number is assigned
2. Edit a student's name → Verify roll numbers are updated
3. Move a student to another class → Verify both classes are updated
4. Delete a student → Verify remaining students are renumbered

## Command Options

| Command                                                   | Description                          |
| --------------------------------------------------------- | ------------------------------------ |
| `php artisan students:reassign-roll-numbers`              | Reassign all students in all classes |
| `php artisan students:reassign-roll-numbers --class_id=X` | Reassign students in specific class  |

## What the Command Does

1. Fetches all students in the target class(es)
2. Sorts them alphabetically by full name (first + middle + last)
3. Assigns roll numbers sequentially (1, 2, 3, ...)
4. Updates the database

## Example Output

```
Reassigning roll numbers for all classes...
 10/10 [============================] 100%
✓ Completed for all classes!
```

## Rollback (If Needed)

If you need to rollback to manual roll number entry:

1. Restore the original `StudentController.php` from version control
2. Re-enable the `roll_number` field in your frontend forms

## Support

If you encounter any issues, check:

-   Database connection
-   Student records have valid `class_id`
-   All students have at least a `first_name`
