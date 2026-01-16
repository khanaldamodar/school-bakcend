# Class Code Standardization for Multi-School Analytics

## Problem Statement

In a multi-tenant school management system, different schools may:

1. **Use different naming conventions** for the same grade level:

    - School A: "Grade 1", "Grade 2", "Grade 3"
    - School B: "Class 1", "Class 2", "Class 3"
    - School C: "First Standard", "Second Standard"

2. **Have different database IDs** for the same grade level:
    - School A: Grade 1 has `id = 4`
    - School B: Grade 1 has `id = 7`
    - School C: Grade 1 has `id = 12`

This makes it **impossible to filter by grade level** across multiple schools using `class_id`.

## Solution: Standardized `class_code`

We use the `class_code` field in the `classes` table as a **standardized identifier** that represents the same grade level across all schools.

### Database Schema

```sql
CREATE TABLE classes (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,           -- School-specific name (e.g., "Grade 1", "Class 1")
    class_code VARCHAR(255) NULL,         -- Standardized code (e.g., "1", "2", "3")
    section VARCHAR(50) NULL,             -- Section (e.g., "A", "B", "C")
    class_teacher_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Standardization Rules

| Grade Level | `class_code` | School A (`name`) | School B (`name`) | School C (`name`) |
| ----------- | ------------ | ----------------- | ----------------- | ----------------- |
| 1st Grade   | `"1"`        | "Grade 1"         | "Class 1"         | "First Standard"  |
| 2nd Grade   | `"2"`        | "Grade 2"         | "Class 2"         | "Second Standard" |
| 3rd Grade   | `"3"`        | "Grade 3"         | "Class 3"         | "Third Standard"  |
| ...         | ...          | ...               | ...               | ...               |
| 10th Grade  | `"10"`       | "Grade 10"        | "Class 10"        | "Tenth Standard"  |

### Example Data

**School A (tenant: school_a_db)**

```
id | name     | class_code | section
---|----------|------------|--------
4  | Grade 1  | 1          | A
5  | Grade 1  | 1          | B
6  | Grade 2  | 2          | A
```

**School B (tenant: school_b_db)**

```
id | name     | class_code | section
---|----------|------------|--------
7  | Class 1  | 1          | NULL
8  | Class 2  | 2          | NULL
9  | Class 3  | 3          | NULL
```

## API Usage

### Government Analytics Endpoint

**Endpoint:** `POST /api/government/analytics/class-activity-report`

### Filtering Options

#### Option 1: Filter by `class_code` (Recommended for Multi-School)

Use this when you want to filter by **standardized grade level** across multiple schools.

```json
{
    "schools": ["school_a_db", "school_b_db"],
    "class_code": ["1", "2"],
    "academic_year_id": 2,
    "gender": ["male", "female"]
}
```

This will return data for **1st and 2nd grade students** from both schools, regardless of how each school names their classes.

#### Option 2: Filter by `class_id` (Single School)

Use this when you want to filter by **specific class IDs** (backward compatibility).

```json
{
    "schools": ["school_a_db"],
    "class_id": [4, 5],
    "academic_year_id": 2
}
```

This will return data only for classes with `id = 4` and `id = 5` in School A.

### Response Format

```json
{
  "status": true,
  "message": "Class activity report generated successfully",
  "filters_applied": {
    "schools": ["school_a_db", "school_b_db"],
    "class_ids": [],
    "class_codes": ["1", "2"],
    "gender": ["male", "female"],
    "ethnicity": null,
    "age_groups": null,
    "is_disabled": null,
    "academic_year_id": 2
  },
  "data": [
    {
      "school_id": "school_a_db",
      "school_name": "ABC School",
      "academic_year": {
        "id": 2,
        "name": "2024-2025",
        "is_current": true
      },
      "classes": [
        {
          "class_id": 4,
          "class_name": "Grade 1",
          "class_code": "1",
          "section": "A"
        },
        {
          "class_id": 5,
          "class_name": "Grade 1",
          "class_code": "1",
          "section": "B"
        },
        {
          "class_id": 6,
          "class_name": "Grade 2",
          "class_code": "2",
          "section": "A"
        }
      ],
      "age_groups": { ... },
      "ethnicity": [ ... ],
      "academic": { ... },
      "total_students": 150
    },
    {
      "school_id": "school_b_db",
      "school_name": "XYZ School",
      "classes": [
        {
          "class_id": 7,
          "class_name": "Class 1",
          "class_code": "1",
          "section": null
        },
        {
          "class_id": 8,
          "class_name": "Class 2",
          "class_code": "2",
          "section": null
        }
      ],
      ...
    }
  ]
}
```

## Implementation Details

### Controller Logic

The `getClassActivityReport` method in `AnalyticsController` now supports both filtering modes:

1. **Priority: `class_code` > `class_id`**

    - If `class_code` is provided, it takes priority
    - Falls back to `class_id` for backward compatibility

2. **Join Strategy:**
    - When filtering by `class_code`, we join the `classes` table
    - When filtering by `class_id`, we filter directly on `students.class_id`

```php
// Priority: class_code (standardized) > class_id (specific)
if (!empty($requestClassCodes)) {
    // Filter by standardized class_code (works across schools)
    $studentQuery->join('classes', 'students.class_id', '=', 'classes.id')
        ->whereIn('classes.class_code', $requestClassCodes);
} elseif (!empty($requestClassIds)) {
    // Filter by specific class_id (backward compatibility)
    $studentQuery->whereIn('students.class_id', $requestClassIds);
}
```

## Setup Instructions

### For School Admins

When creating or editing a class, ensure you set the `class_code` field:

1. **Grade 1** → Set `class_code = "1"`
2. **Grade 2** → Set `class_code = "2"`
3. **Grade 3** → Set `class_code = "3"`
4. And so on...

### For Existing Schools

If you have existing schools without `class_code` values, run a migration or update script:

```php
// Example: Update existing classes
$classes = SchoolClass::all();
foreach ($classes as $class) {
    // Extract number from class name
    preg_match('/\d+/', $class->name, $matches);
    if (!empty($matches)) {
        $class->class_code = $matches[0];
        $class->save();
    }
}
```

## Benefits

1. ✅ **Cross-School Analytics**: Filter by grade level across multiple schools
2. ✅ **Flexible Naming**: Each school can use their own naming convention
3. ✅ **Backward Compatible**: Existing `class_id` filtering still works
4. ✅ **Scalable**: Easy to add new schools with different naming conventions
5. ✅ **Clear Reporting**: Government can see data by standardized grade levels

## Best Practices

1. **Always set `class_code`** when creating a new class
2. **Use consistent codes** across all schools (e.g., "1", "2", "3", not "01", "02", "03")
3. **Use `class_code` for government analytics**, `class_id` for school-specific operations
4. **Document your standardization** rules for your organization

## Troubleshooting

### Issue: No data returned when filtering by `class_code`

**Solution:** Ensure all schools have `class_code` values set in their `classes` table.

```sql
-- Check for missing class_codes
SELECT * FROM classes WHERE class_code IS NULL;
```

### Issue: Different schools have different `class_code` values for the same grade

**Solution:** Standardize the `class_code` values across all schools. Update them manually or via migration.

```php
// Update School A's "Grade 1" to have class_code = "1"
SchoolClass::where('name', 'Grade 1')->update(['class_code' => '1']);
```
