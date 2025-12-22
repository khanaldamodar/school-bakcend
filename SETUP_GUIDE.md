# Quick Setup Guide - Student History System

## Step 1: Run Migration

```bash
php artisan migrate
```

This creates:

-   `academic_years` table
-   Enhanced `student_class_histories` table with new columns

## Step 2: Create Initial Academic Year

You have two options:

### Option A: Via Command (Interactive)

```bash
php artisan students:backfill-history
```

This will prompt you to create an academic year if none exists.

### Option B: Via API

```http
POST /api/academic-years
Content-Type: application/json

{
  "name": "2024-2025",
  "start_date": "2024-04-01",
  "end_date": "2025-03-31",
  "is_current": true,
  "description": "Current academic session"
}
```

## Step 3: Backfill History for Existing Students

```bash
php artisan students:backfill-history --force
```

This creates initial history records for all existing students.

## Step 4: Add API Routes

Add these routes to your `routes/tenant.php` or appropriate route file:

```php
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\StudentPromotionController;

// Academic Years
Route::prefix('academic-years')->group(function () {
    Route::get('/', [AcademicYearController::class, 'index']);
    Route::post('/', [AcademicYearController::class, 'store']);
    Route::get('/current', [AcademicYearController::class, 'current']);
    Route::get('/{id}', [AcademicYearController::class, 'show']);
    Route::put('/{id}', [AcademicYearController::class, 'update']);
    Route::delete('/{id}', [AcademicYearController::class, 'destroy']);
    Route::post('/{id}/set-current', [AcademicYearController::class, 'setCurrent']);
});

// Student Promotion & History
Route::prefix('students')->group(function () {
    Route::post('/promote', [StudentPromotionController::class, 'promoteClass']);
    Route::post('/graduate', [StudentPromotionController::class, 'markGraduated']);
    Route::get('/{id}/history', [StudentPromotionController::class, 'getStudentHistory']);
});

Route::get('/classes/{id}/history', [StudentPromotionController::class, 'getClassHistory']);
```

## Step 5: Test the System

### Test 1: Create a New Student

```http
POST /api/students
```

✓ Should automatically create history record

### Test 2: Move Student to Different Class

```http
PUT /api/students/{id}
{
  "class_id": 5  // Different from current
}
```

✓ Should mark old history as 'promoted'  
✓ Should create new 'active' history

### Test 3: View Student History

```http
GET /api/students/{id}/history
```

✓ Should show all class progressions

### Test 4: Bulk Promote Class

```http
POST /api/students/promote
{
  "from_class_id": 1,
  "to_class_id": 2
}
```

✓ Should promote all students  
✓ Should update histories

## Verification Checklist

-   [ ] Migration ran successfully
-   [ ] Academic year created and set as current
-   [ ] Existing students have history records
-   [ ] New student creation creates history
-   [ ] Class changes update history correctly
-   [ ] Bulk promotion works
-   [ ] Student history API returns correct data
-   [ ] Roll numbers are preserved in history

## Common Commands

```bash
# View all academic years
GET /api/academic-years

# Get current academic year
GET /api/academic-years/current

# View student's complete history
GET /api/students/{id}/history

# View class history for specific year
GET /api/classes/{id}/history?academic_year_id=1

# Promote entire class
POST /api/students/promote

# Reassign all roll numbers (if needed)
php artisan students:reassign-roll-numbers

# Backfill history for new students
php artisan students:backfill-history
```

## Troubleshooting

### Issue: "No current academic year set"

**Solution:** Create and set an academic year as current:

```http
POST /api/academic-years
{
  "name": "2024-2025",
  "start_date": "2024-04-01",
  "end_date": "2025-03-31",
  "is_current": true
}
```

### Issue: Students don't have history records

**Solution:** Run backfill command:

```bash
php artisan students:backfill-history --force
```

### Issue: History not created when creating new student

**Solution:** Check that:

1. Academic year exists and is set as current
2. StudentController imports are correct
3. `createClassHistory` method is being called

## Next Steps

1. **Update Frontend** - Add UI for:

    - Academic year management
    - Bulk student promotion
    - Viewing student history
    - Viewing class history

2. **Add Permissions** - Restrict promotion features to admin/principal roles

3. **Add Notifications** - Notify parents when student is promoted

4. **Generate Reports** - Create promotion reports, graduation certificates, etc.

## Support

For detailed documentation, see `STUDENT_HISTORY_SYSTEM.md`
