# Student History & Class Promotion System

## Overview

This system automatically tracks student class progression throughout their academic journey, preserving complete history of:

-   Which classes they attended
-   When they were promoted
-   Their roll numbers at each stage
-   Their academic results for each class/year
-   Academic year associations

## Database Structure

### Tables

#### `academic_years`

Manages academic sessions (e.g., "2024-2025")

-   `id` - Primary key
-   `name` - Academic year name (e.g., "2024-2025")
-   `start_date` - Session start date
-   `end_date` - Session end date
-   `is_current` - Boolean flag (only one can be current)
-   `description` - Optional notes
-   `created_at`, `updated_at`

#### `student_class_histories` (Enhanced)

Tracks complete student class progression

-   `id` - Primary key
-   `student_id` - Foreign key to students
-   `class_id` - Foreign key to classes
-   `year` - Calendar year
-   `academic_year_id` - Foreign key to academic_years
-   `roll_number` - Student's roll number at that time
-   `promoted_date` - When they were promoted/moved
-   `status` - Enum: 'active', 'promoted', 'transferred', 'graduated'
-   `remarks` - Additional notes
-   `created_at`, `updated_at`

## Features

### 1. **Automatic History Tracking**

#### When Creating a Student

-   Automatically creates initial history record
-   Marks as 'active' status
-   Records current academic year
-   Stores initial roll number

#### When Updating Student Class

-   Marks old history as 'promoted'
-   Records promotion date
-   Creates new 'active' history record
-   Updates roll numbers in both classes

### 2. **Academic Year Management**

**Create Academic Year:**

```http
POST /api/academic-years
Content-Type: application/json

{
  "name": "2024-2025",
  "start_date": "2024-04-01",
  "end_date": "2025-03-31",
  "is_current": true,
  "description": "Academic session 2024-2025"
}
```

**Set Current Academic Year:**

```http
POST /api/academic-years/{id}/set-current
```

**Get Current Academic Year:**

```http
GET /api/academic-years/current
```

### 3. **Bulk Class Promotion**

Promote entire class or selected students to next grade:

```http
POST /api/students/promote
Content-Type: application/json

{
  "from_class_id": 5,
  "to_class_id": 6,
  "student_ids": [1, 2, 3], // Optional - if empty, promotes all
  "academic_year_id": 1,    // Optional - uses current if not provided
  "remarks": "Promoted to Grade 6 for academic year 2025-2026"
}
```

**What happens:**

1. Marks old class history as 'promoted'
2. Updates student's current class
3. Reassigns roll numbers in both classes (alphabetically)
4. Creates new 'active' history records
5. Preserves all previous results

### 4. **View Student History**

Get complete academic journey of a student:

```http
GET /api/students/{id}/history
```

**Response:**

```json
{
    "status": true,
    "message": "Student history fetched successfully",
    "data": {
        "student": {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "current_class": "Grade 5"
        },
        "history": [
            {
                "id": 3,
                "class": {
                    "id": 5,
                    "name": "Grade 5"
                },
                "academic_year": {
                    "id": 2,
                    "name": "2024-2025"
                },
                "roll_number": 12,
                "status": "active",
                "year": 2024,
                "created_at": "2024-04-01"
            },
            {
                "id": 2,
                "class": {
                    "id": 4,
                    "name": "Grade 4"
                },
                "academic_year": {
                    "id": 1,
                    "name": "2023-2024"
                },
                "roll_number": 15,
                "status": "promoted",
                "promoted_date": "2024-03-31",
                "year": 2023,
                "created_at": "2023-04-01"
            }
        ]
    }
}
```

### 5. **View Class History**

See all students who attended a class during a specific academic year:

```http
GET /api/classes/{id}/history?academic_year_id=1&status=promoted
```

**Query Parameters:**

-   `academic_year_id` (optional) - Filter by academic year
-   `status` (optional) - Filter by status (active, promoted, transferred, graduated)

### 6. **Mark Students as Graduated**

For final year students:

```http
POST /api/students/graduate
Content-Type: application/json

{
  "student_ids": [45, 46, 47],
  "remarks": "Graduated - Class of 2025"
}
```

## API Endpoints

### Academic Years

| Method | Endpoint                               | Description                |
| ------ | -------------------------------------- | -------------------------- |
| GET    | `/api/academic-years`                  | List all academic years    |
| POST   | `/api/academic-years`                  | Create new academic year   |
| GET    | `/api/academic-years/{id}`             | Get specific academic year |
| PUT    | `/api/academic-years/{id}`             | Update academic year       |
| DELETE | `/api/academic-years/{id}`             | Delete academic year       |
| GET    | `/api/academic-years/current`          | Get current academic year  |
| POST   | `/api/academic-years/{id}/set-current` | Set as current year        |

### Student Promotion & History

| Method | Endpoint                     | Description                    |
| ------ | ---------------------------- | ------------------------------ |
| POST   | `/api/students/promote`      | Bulk promote students          |
| GET    | `/api/students/{id}/history` | Get student's complete history |
| GET    | `/api/classes/{id}/history`  | Get class history              |
| POST   | `/api/students/graduate`     | Mark students as graduated     |

## Status Types

| Status        | Description                      |
| ------------- | -------------------------------- |
| `active`      | Currently enrolled in this class |
| `promoted`    | Moved to next class              |
| `transferred` | Transferred to another school    |
| `graduated`   | Completed final year             |

## Results Preservation

All student results are automatically preserved with their class history:

-   Results remain linked to `student_id` and `class_id`
-   Historical results can be retrieved via the history record
-   Results are filtered by academic year date ranges when available

## Workflow Example

### End of Academic Year Process

1. **Create New Academic Year**

    ```http
    POST /api/academic-years
    {
      "name": "2025-2026",
      "start_date": "2025-04-01",
      "end_date": "2026-03-31",
      "is_current": false
    }
    ```

2. **Promote Students Class by Class**

    ```http
    POST /api/students/promote
    {
      "from_class_id": 1,  // Grade 1
      "to_class_id": 2,    // Grade 2
      "academic_year_id": 2
    }
    ```

    Repeat for each class (Grade 1→2, Grade 2→3, etc.)

3. **Mark Final Year as Graduated**

    ```http
    POST /api/students/graduate
    {
      "student_ids": [/* all grade 10 students */],
      "remarks": "Graduated - Class of 2025"
    }
    ```

4. **Set New Academic Year as Current**
    ```http
    POST /api/academic-years/2/set-current
    ```

## Migration Instructions

### 1. Run the Migration

```bash
php artisan migrate
```

This will:

-   Create `academic_years` table
-   Add new columns to `student_class_histories`

### 2. Create Initial Academic Year

```bash
# Via API or directly in database
INSERT INTO academic_years (name, start_date, end_date, is_current, created_at, updated_at)
VALUES ('2024-2025', '2024-04-01', '2025-03-31', 1, NOW(), NOW());
```

### 3. Backfill History (Optional)

If you have existing students and want to create their initial history:

```php
use App\Models\Admin\Student;
use App\Models\Admin\StudentClassHistory;
use App\Models\Admin\AcademicYear;

$currentYear = AcademicYear::current();

Student::all()->each(function ($student) use ($currentYear) {
    StudentClassHistory::create([
        'student_id' => $student->id,
        'class_id' => $student->class_id,
        'year' => now()->year,
        'academic_year_id' => $currentYear?->id,
        'roll_number' => $student->roll_number,
        'status' => 'active',
        'remarks' => 'Initial history record',
    ]);
});
```

## Benefits

✅ **Complete Academic History** - Track every class a student attended  
✅ **Results Preservation** - All results remain accessible with historical context  
✅ **Automatic Tracking** - No manual intervention needed  
✅ **Bulk Operations** - Promote entire classes at once  
✅ **Academic Year Management** - Organize by school sessions  
✅ **Roll Number History** - Know what roll number they had in each class  
✅ **Flexible Queries** - Filter by year, class, status, etc.  
✅ **Graduation Tracking** - Mark and track graduated students

## Notes

-   Only one academic year can be marked as `is_current` at a time
-   History records are never deleted, only status is updated
-   Results are preserved even after promotion
-   Roll numbers are automatically reassigned after promotions
-   All operations are wrapped in database transactions for data integrity
