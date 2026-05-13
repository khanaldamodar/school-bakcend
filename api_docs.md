# School Management System - API Documentation

> Base URL: `http://your-domain.com/api`

## Authentication

All protected routes require a **Bearer Token** sent via the `Authorization` header:
```
Authorization: Bearer <your-token-here>
```

## Response Format

All APIs return JSON with this general structure:
```json
{
  "status": true|false,
  "message": "Description of what happened",
  "data": { ... }
}
```

---

# 1. Super Admin APIs

These endpoints are for the **Super Admin** (central level). They manage the entire system.

## 1.1 Super Admin Auth

### Register Super Admin
```
POST /api/superadmin/register
```
**Body:**
```json
{
  "name": "Admin Name",
  "email": "admin@school.com",
  "role": "superadmin",
  "district": "Kathmandu",
  "local_bodies": "Kathmandu Metropolitan",
  "password": "password123"
}
```

### Login Super Admin
```
POST /api/superadmin/login
```
**Body:**
```json
{
  "email": "admin@school.com",
  "password": "password123"
}
```
**Response:**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "1|abc123..."
  }
}
```

### Logout Super Admin
```
POST /api/superadmin/logout
```
*Requires auth:sanctum*

### View All Super Admin Users
```
GET /api/superadmin/users
```
*Requires auth:sanctum*

### Get Super Admin Stats
```
GET /api/superadmin/stats
```
*Requires auth:sanctum*

---

## 1.2 School (Tenant) Management

All routes under `api/superadmin/school` require `auth:sanctum`.

### List All Schools
```
GET /api/superadmin/school
```

### Register a New School
```
POST /api/superadmin/school
```
**Body:**
```json
{
  "name": "Sunrise English School",
  "email": "school@email.com",
  "district": "Kathmandu",
  "local_unit": "Kathmandu Metropolitan",
  "ward": 5,
  "domain": "sunrise.school.com",
  "password": "schooladmin123",
  "phone": "9812345678"
}
```
This creates the school, its database, runs migrations, and sets up the admin user.

### Get Single School Details
```
GET /api/superadmin/school/{tenant_id}
```

### Update School
```
PUT /api/superadmin/school/{tenant_id}
PATCH /api/superadmin/school/{tenant_id}
```
**Body:**
```json
{
  "name": "Updated School Name",
  "email": "newemail@school.com",
  "password": "newpassword",
  "latitude": 27.7172,
  "longitude": 85.3240
}
```

### Delete School
```
DELETE /api/superadmin/school/{tenant_id}
```

### Get School SMS Balance
```
GET /api/superadmin/school/{tenant_id}/sms-balance
```

### Add SMS Balance to School
```
POST /api/superadmin/school/{tenant_id}/add-sms
```

### Get Deleted Students of a School
```
GET /api/superadmin/school/{tenant_id}/deleted-students
```

### Get Deleted Teachers of a School
```
GET /api/superadmin/school/{tenant_id}/deleted-teachers
```

---

## 1.3 System Logs

```
GET /api/superadmin/logs
GET /api/superadmin/logs/{id}
```

## 1.4 Local Bodies

```
GET /api/superadmin/local-bodies
```

---

## 1.5 Public - Local Bodies

```
GET /api/local-bodies/districts
GET /api/local-bodies/{district}
```

---

# 2. Tenant (School) APIs

All school-specific APIs follow this pattern:
```
/api/tenants/{domain}/...
```
Where `{domain}` is the school's domain name (e.g., `sunrise.school.com`).

---

## 2.1 School User Authentication

### Register (Anyone can register)
```
POST /api/tenants/{domain}/register
```
**Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "9812345678",
  "role": "teacher|student|parent|admin",
  "password": "password123"
}
```

### Login
```
POST /api/tenants/{domain}/login
```
*Rate-limited via `RateLimitLogin` middleware*
**Body (email or student_id):**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```
OR for students:
```json
{
  "student_id": "STU001",
  "password": "STU001"
}
```
(Student passwords are their student_id by default)

**Login Response:**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "user": { "id": 1, "name": "John", "email": "...", "role": "admin" },
    "token": "2|abc123..."
  }
}
```

### Logout
```
POST /api/tenants/{domain}/logout
```

### Get Current User (Authenticated)
```
GET /api/tenants/{domain}/user
```
*Requires auth:sanctum*

Returns the currently authenticated user's details.

---

# 3. Admin APIs (Require `role:admin`)

All endpoints below require `auth:sanctum` + `role:admin`.

---

## 3.1 Dashboard Stats
```
GET /api/tenants/{domain}/admin/dashboard/stats
```
*No auth required (public)*
**Response:**
```json
{
  "status": true,
  "data": {
    "total_teachers": 25,
    "total_students": 500,
    "total_classes": 12,
    "total_parents": 400,
    "passed_students_count": 380,
    "pass_percentage": 76.0
  }
}
```

---

## 3.2 School Settings

### Get Settings (Public)
```
GET /api/tenants/{domain}/settings
```

### Update Settings
```
PUT /api/tenants/{domain}/settings
```
**Body (multipart/form-data):**
- `name` (string)
- `about` (string)
- `logo` (file, image)
- `favicon` (file, image)
- `address` (string)
- `phone` (string)
- `email` (email)
- `facebook` (url)
- `twitter` (url)
- `start_time` (H:i:s)
- `end_time` (H:i:s)
- `district` (string)
- `local_body` (string)
- `ward` (integer)
- `school_type` (string)
- `established_date` (date)
- `principle` (string)
- `signature` (file, image)

---

## 3.3 Subjects

### List All Subjects
```
GET /api/tenants/{domain}/subjects
```
*Requires admin role*

### Create Subject
```
POST /api/tenants/{domain}/subjects
```
**Body:**
```json
{
  "name": "Mathematics",
  "subject_code": "MTH101",
  "theory_marks": 75,
  "theory_pass_marks": 24,
  "practical_marks": 25,
  "teacher_id": 1,
  "activities": [
    { "activity_name": "Project Work", "full_marks": 10, "pass_marks": 4 }
  ]
}
```

### Show Subject
```
GET /api/tenants/{domain}/subjects/{id}
```

### Update Subject
```
PUT /api/tenants/{domain}/subjects/{id}
```
**Body:**
```json
{
  "name": "Advanced Mathematics",
  "theory_marks": 80,
  "practical_marks": 20,
  "activities": [
    { "id": 1, "activity_name": "Lab Work", "full_marks": 10, "pass_marks": 4 },
    { "activity_name": "New Activity", "full_marks": 5, "pass_marks": 2 }
  ]
}
```

### Delete Subject
```
DELETE /api/tenants/{domain}/subjects/{id}
```

---

## 3.4 Classes

### List All Classes (with subjects, teachers, activities)
```
GET /api/tenants/{domain}/classes
```
*Also accessible by admin & teacher roles*

Query params:
- `slim=true` — Returns only `id`, `name`, `section`

### Show Class Details
```
GET /api/tenants/{domain}/classes/{id}
```
*Accessible by admin & teacher*

### Create Class
```
POST /api/tenants/{domain}/classes
```
**Body:**
```json
{
  "name": "Class 10",
  "section": "A",
  "subject_ids": [1, 2, 3],
  "class_teacher_id": 5
}
```

### Update Class
```
PUT /api/tenants/{domain}/classes/{id}
```

### Delete Class
```
DELETE /api/tenants/{domain}/classes/{id}
```

---

## 3.5 Teachers

### List All Teachers (Public)
```
GET /api/tenants/{domain}/teachers
```

### Show Teacher (Public)
```
GET /api/tenants/{domain}/teachers/{id}
```

### Create Teacher
```
POST /api/tenants/{domain}/teachers
```
**Body (multipart/form-data):**
```json
{
  "name": "Ram Sharma",
  "email": "ram@school.com",
  "phone": "9812345678",
  "qualification": "M.Ed.",
  "address": "Kathmandu",
  "blood_group": "A+",
  "is_disabled": false,
  "disability_options": "none",
  "is_tribe": false,
  "gender": "male",
  "dob": "1990-01-15",
  "nationality": "Nepali",
  "grade": "Senior",
  "ethnicity": "Brahmin",
  "post": "Mathematics Teacher",
  "dob_bs": "2046-10-02",
  "joining_date": "2023-01-01",
  "joining_data_bs": "2079-09-17",
  "subject_ids": [1, 2],
  "class_teacher_of": 1,
  "role_ids": [1, 2],
  "image": (file)
}
```

### Update Teacher
```
PUT /api/tenants/{domain}/teachers/{id}
```

### Delete Teacher (soft delete)
```
DELETE /api/tenants/{domain}/teachers/{id}
```

---

## 3.6 Teacher Roles

### List All Roles (Public)
```
GET /api/tenants/{domain}/teacher-roles
```

### Create Role
```
POST /api/tenants/{domain}/teacher-roles
```

### Update Role
```
PUT /api/tenants/{domain}/teacher-roles/{id}
```

### Delete Role
```
DELETE /api/tenants/{domain}/teacher-roles/{id}
```

---

## 3.7 Students

### List All Students
```
GET /api/tenants/{domain}/students
```
*Accessible by admin & teacher*

Query params:
- `class={class_id}` — Filter by class
- `search={name}` — Search by name
- `per_page={number}` — Pagination (default: 10)

### Show Student
```
GET /api/tenants/{domain}/students/{id}
```
*Accessible by student, parent, admin, teacher*

### Student Profile (Authenticated)
```
POST /api/tenants/{domain}/students/profile
```
*Accessible by student, parent, admin, teacher*

**Body:**
```json
{
  "student_id": 1
}
```
Returns detailed profile for the specified student (for parents viewing children, teachers viewing students).

### Create Student
```
POST /api/tenants/{domain}/students
```
*Accessible by admin & teacher*

**Body (multipart/form-data):**
```json
{
  "first_name": "Sita",
  "middle_name": "Kumari",
  "last_name": "Sharma",
  "dob": "2010-05-15",
  "gender": "female",
  "email": "sita@example.com",
  "blood_group": "B+",
  "is_disabled": false,
  "disability_options": "none",
  "ethnicity": "Brahmin",
  "is_tribe": false,
  "phone": "9812345670",
  "address": "Kathmandu",
  "class_id": 1,
  "enrollment_year": 2024,
  "is_transferred": false,
  "transferred_to": null,
  "image": (file),
  "parents": [
    {
      "first_name": "Hari",
      "middle_name": "",
      "last_name": "Sharma",
      "email": "hari@example.com",
      "phone": "9812345671",
      "relation": "father"
    }
  ]
}
```
Roll numbers are auto-assigned alphabetically.

### Update Student
```
PUT /api/tenants/{domain}/students/{id}
```
*Accessible by admin only*

### Delete Student (soft delete)
```
DELETE /api/tenants/{domain}/students/{id}
```

### Filter Students by Class
```
GET /api/tenants/{domain}/students/class/{classId}
```
Query params: `?minimal=true` — Returns only id, name, roll_no, image

### Search Students in Class
```
GET /api/tenants/{domain}/students/class/{classId}/search?name={searchText}
```

### Bulk Upload Students
```
POST /api/tenants/{domain}/students/bulk-upload
```
*Accessible by admin & teacher*

**Body:** Upload via Excel/CSV file or JSON array:
```json
// JSON upload:
{
  "students": [
    {
      "first_name": "Sita",
      "last_name": "Sharma",
      "email": "sita@test.com",
      "phone": "9812345670",
      "class_id": 1,
      "address": "Kathmandu",
      "gender": "female",
      "is_disabled": false,
      "disability_options": "none",
      "is_tribe": false,
      "parents": [
        {
          "first_name": "Hari",
          "last_name": "Sharma",
          "email": "hari@test.com",
          "phone": "9812345671",
          "relation": "father"
        }
      ]
    }
  ]
}
```
OR via file upload: `file` (xlsx/csv with matching column headers).

---

## 3.8 Clubs

### List All Clubs (Public)
```
GET /api/tenants/{domain}/clubs
```

### Get All Club Students (Public)
```
GET /api/tenants/{domain}/clubs/all-students
```

### Show Club (Public)
```
GET /api/tenants/{domain}/clubs/{id}
```

### Get Club's Students (Public)
```
GET /api/tenants/{domain}/clubs/{id}/students
```

### Create Club
```
POST /api/tenants/{domain}/clubs
```
**Body (multipart/form-data):**
```json
{
  "name": "Science Club",
  "description": "A club for science enthusiasts",
  "logo": (file)
}
```

### Update Club
```
PUT /api/tenants/{domain}/clubs/{id}
```

### Delete Club
```
DELETE /api/tenants/{domain}/clubs/{id}
```

### Add Students to Club
```
POST /api/tenants/{domain}/clubs/students
```
**Body:**
```json
{
  "club_id": 1,
  "student_ids": [1, 2, 3],
  "position": "member"
}
```

### Update Student Club Membership
```
PUT /api/tenants/{domain}/clubs/{clubStudentId}/students
```

### Remove Student from Club
```
DELETE /api/tenants/{domain}/clubs/{clubStudentId}/students
```

---

## 3.9 Results

### List Results
```
GET /api/tenants/{domain}/students/results
```
*Accessible by admin (all), teacher (their subjects/class), student (own), parent (children)*

### Create Result
```
POST /api/tenants/{domain}/students/results
```
*Accessible by admin & teacher*

**Body:**
```json
{
  "student_id": 1,
  "class_id": 1,
  "subject_id": 1,
  "term_id": 1,
  "academic_year_id": 1,
  "marks_theory": 68,
  "marks_practical": 20,
  "exam_type": "First Term",
  "exam_date": "2024-06-15",
  "remarks": "Good performance"
}
```
GPA & percentage are auto-calculated based on Result Settings.

### Create Result by Teacher (Alternate)
```
POST /api/tenants/{domain}/students/results/create
```
*Accessible by admin & teacher*

Creates result records with teacher-specific logic. Same body structure as Create Result above.

### Show Result
```
GET /api/tenants/{domain}/students/results/{id}
```

### Update Result
```
PUT /api/tenants/{domain}/students/results/{id}
```

### Delete Result
```
DELETE /api/tenants/{domain}/students/results/{id}
```
*Accessible by admin & teacher*

### Get Student's Own Result
```
GET /api/tenants/{domain}/students/result
```
*Accessible by student, parent, admin, teacher*

Query params:
- `?student_id={id}` — For admin/teacher to view specific student
- `?academic_year_id={id}` — Filter by academic year

### Get Student Result By ID
```
GET /api/tenants/{domain}/students/{studentId}/results
```
*Accessible by student, parent, admin, teacher*

Query params: `?academic_year_id={id}`

### Get Results by Class
```
GET /api/tenants/{domain}/students/results/class/{classId}
```

### Get Class Results (Teacher/Admin)
```
GET /api/tenants/{domain}/classes/{classId}/results
```
Query params:
- `?exam_type=First Term`
- `?term_id=1`
- `?academic_year_id=1`
- `?only_complete=true` (only students with all subjects)

### Get Results Ledger by Class
```
GET /api/tenants/{domain}/results/{classId}
```
Returns ranked list of all students in class.

### Create Class Result by Teacher
```
POST /api/tenants/{domain}/results/class
```
*Accessible by admin & teacher*

**Body:**
```json
{
  "class_id": 1,
  "term_id": 1,
  "exam_type": "First Term",
  "exam_date": "2024-06-15",
  "academic_year_id": 1,
  "students": [
    {
      "student_id": 1,
      "results": [
        {
          "subject_id": 1,
          "marks_theory": 68,
          "marks_practical": 20,
          "activities": [
            { "activity_id": 1, "marks": 8 }
          ]
        }
      ]
    }
  ]
}
```

### Edit Class Result by Teacher
```
PUT /api/tenants/{domain}/results/class/edit
```
*Same structure as create but updates existing records*

### Bulk Store Results
```
POST /api/tenants/{domain}/students/results/bulk-upload
```
*Accessible by admin & teacher*

**Body:**
```json
{
  "class_id": 1,
  "exam_type": "First Term",
  "exam_date": "2024-06-15",
  "academic_year_id": 1,
  "results": [
    {
      "student_id": 1,
      "subject_id": 1,
      "marks_theory": 68,
      "marks_practical": 20
    }
  ]
}
```

### Generate Final Result (Weighted)
```
POST /api/tenants/{domain}/students/results/generate
```
Query params: `?academic_year_id=1`
**Body:**
```json
{
  "student_id": 1,
  "class_id": 1
}
```

### Generate Class Final Result
```
POST /api/tenants/{domain}/classes/{classId}/generate-final
```

### Get Class Final Results
```
GET /api/tenants/{domain}/classes/{classId}/final-results
```

### Get Student Final Result
```
GET /api/tenants/{domain}/students/{studentId}/final-result
```

---

## 3.10 Final Results (Admin)

### Generate Final Results for Class
```
POST /api/tenants/{domain}/final-results/generate
```
**Body:**
```json
{
  "class_id": 1,
  "academic_year_id": 1,
  "min_marks": 60,
  "max_marks": 100,
  "student_ids": [1, 2, 3]
}
```

### View Final Results
```
GET /api/tenants/{domain}/final-results/class/{classId}
```
*Accessible by student, parent, admin, teacher*

Query params: `?academic_year_id=1`

---

## 3.11 Event Types & Events

### List Event Types (Public)
```
GET /api/tenants/{domain}/events-type
```

### Show Event Type (Public)
```
GET /api/tenants/{domain}/events-type/{id}
```

### Create Event Type
```
POST /api/tenants/{domain}/events-type
```

### Update Event Type
```
PUT /api/tenants/{domain}/events-type/{id}
```

### Delete Event Type
```
DELETE /api/tenants/{domain}/events-type/{id}
```

### List Events (Public)
```
GET /api/tenants/{domain}/events
```

### Show Event (Public)
```
GET /api/tenants/{domain}/events/{id}
```

### Show Event (Admin/Authenticated)
```
GET /api/tenants/{domain}/admin/events/{id}
```
*Accessible by student, parent, admin, teacher*

### Create Event
```
POST /api/tenants/{domain}/events
```
**Body:**
```json
{
  "title": "Sports Day",
  "date": "2024-12-15",
  "time": "10:00",
  "event_type_id": 1,
  "type": "annual",
  "description": "Annual sports competition",
  "location": "School Ground"
}
```

### Update Event
```
PUT /api/tenants/{domain}/events/{id}
```

### Delete Event
```
DELETE /api/tenants/{domain}/events/{id}
```

---

## 3.12 Notices

### List Notices (Public)
```
GET /api/tenants/{domain}/notices
```

### Show Notice (Public)
```
GET /api/tenants/{domain}/notices/{id}
```

### Create Notice
```
POST /api/tenants/{domain}/notices
```
**Body (multipart/form-data):**
```json
{
  "title": "Exam Schedule",
  "description": "Final exams start from...",
  "notice_date": "2024-12-01",
  "image": (file)
}
```

### Update Notice
```
PUT /api/tenants/{domain}/notices/{id}
```

### Delete Notice
```
DELETE /api/tenants/{domain}/notices/{id}
```

---

## 3.13 Gallery

### List Gallery (Public)
```
GET /api/tenants/{domain}/gallery
```

### Show Gallery Item (Public)
```
GET /api/tenants/{domain}/gallery/{id}
```

### Create Gallery Item
```
POST /api/tenants/{domain}/gallery
```
**Body (multipart/form-data):**
```json
{
  "title": "School Trip 2024",
  "description": "Photos from the school trip",
  "content_type": "image",
  "for": "annual",
  "media": [(files)]
}
```
`content_type`: `image`, `video`, or `mixed`

### Update Gallery Item
```
POST /api/tenants/{domain}/gallery/{galleryId}
```
Additional fields:
- `remove_media` — Array of public_ids to delete from cloudinary

### Delete Gallery Item
```
DELETE /api/tenants/{domain}/gallery/{galleryId}
```

---

## 3.14 Extra-Curricular Activities

### Create Activity
```
POST /api/tenants/{domain}/extra-curricular
```
**Body:**
```json
{
  "subject_id": 1,
  "class_id": 1,
  "activity_name": "Project Work",
  "full_marks": 10,
  "pass_marks": 4
}
```

### Bulk Create Activities
```
POST /api/tenants/{domain}/extra-curricular/bulk
```

### Update Activity
```
PUT /api/tenants/{domain}/extra-curricular/{curricularId}
```

### Delete Activity
```
DELETE /api/tenants/{domain}/extra-curricular/{curricularId}
```

---

## 3.15 Contacts

### List Contacts
```
GET /api/tenants/{domain}/contacts
```
*Requires admin*

### Show Contact
```
GET /api/tenants/{domain}/contacts/{id}
```

### Create Contact (Public - no auth)
```
POST /api/tenants/{domain}/contacts
```
**Body:**
```json
{
  "name": "Visitor Name",
  "email": "visitor@example.com",
  "phone": "9812345678",
  "message": "I want to know about admission"
}
```

### Update Contact
```
PUT /api/tenants/{domain}/contacts/{id}
```

### Delete Contact
```
DELETE /api/tenants/{domain}/contacts/{id}
```

---

## 3.16 Quick Links

### List Quick Links (Public)
```
GET /api/tenants/{domain}/quick-links
```

### Show Quick Link (Public)
```
GET /api/tenants/{domain}/quick-links/{id}
```

### Create/Update/Delete Quick Link
```
POST   /api/tenants/{domain}/quick-links
PUT    /api/tenants/{domain}/quick-links/{id}
DELETE /api/tenants/{domain}/quick-links/{id}
```

---

## 3.17 Voices

### List Voices (Public)
```
GET /api/tenants/{domain}/voices
```

### Show Voice (Public)
```
GET /api/tenants/{domain}/voices/{id}
```

### Create/Update/Delete Voice
```
POST   /api/tenants/{domain}/voices
PUT    /api/tenants/{domain}/voices/{id}
DELETE /api/tenants/{domain}/voices/{id}
```

---

## 3.18 School Members

### List School Members (Public)
```
GET /api/tenants/{domain}/school-members
```

### Show Member (Public)
```
GET /api/tenants/{domain}/school-members/{id}
```

### Create/Update/Delete
```
POST   /api/tenants/{domain}/school-members
PUT    /api/tenants/{domain}/school-members/{id}
DELETE /api/tenants/{domain}/school-members/{id}
```

---

## 3.19 Website Settings

### Get Website Settings (Public)
```
GET /api/tenants/{domain}/website-settings
```

### Create/Update
```
POST /api/tenants/{domain}/website-settings
PUT  /api/tenants/{domain}/website-settings/{id}
```

---

## 3.20 Analytical Reports (Public)
```
GET /api/tenants/{domain}/analytical-report
```

## 3.21 Reports (Public)
```
GET /api/tenants/{domain}/reports
```

---

## 3.22 Result Settings

### Get Result Settings
```
GET /api/tenants/{domain}/result-settings
```
*Accessible by admin & teacher*

Query param: `?academic_year_id=1`

### Create Result Setting
```
POST /api/tenants/{domain}/result-settings
```
**Body:**
```json
{
  "setting_id": 1,
  "academic_year_id": 1,
  "total_terms": 3,
  "calculation_method": "simple|weighted",
  "result_type": "gpa|percentage",
  "evaluation_per_term": true,
  "term_weights": [30, 30, 40],
  "terms": [
    { "name": "First Term", "weight": 30, "exam_date": "2024-06-15", "start_date": "2024-04-01", "end_date": "2024-06-15", "publish_date": "2024-07-01" },
    { "name": "Second Term", "weight": 30, "exam_date": "2024-09-15" },
    { "name": "Final Term", "weight": 40, "exam_date": "2024-12-15" }
  ]
}
```

### Update Result Setting
```
PUT /api/tenants/{domain}/result-settings/{id}
```

### Delete Result Setting
```
DELETE /api/tenants/{domain}/result-settings/{id}
```

---

## 3.23 Academic Years

### List Academic Years
```
GET /api/tenants/{domain}/academic-years
```
*Accessible by admin & teacher*

### Show Academic Year
```
GET /api/tenants/{domain}/academic-years/{id}
```

### Get Current Academic Year
```
GET /api/tenants/{domain}/academic-years/current
```

### Create Academic Year
```
POST /api/tenants/{domain}/academic-years
```
**Body:**
```json
{
  "name": "2024/2025",
  "start_date": "2024-04-01",
  "end_date": "2025-03-31",
  "is_current": true,
  "description": "Academic Year 2024/2025"
}
```

### Update Academic Year
```
PUT /api/tenants/{domain}/academic-years/{id}
```

### Delete Academic Year
```
DELETE /api/tenants/{domain}/academic-years/{id}
```

### Set Current Academic Year
```
POST /api/tenants/{domain}/academic-years/{id}/set-current
```

---

## 3.24 Student Promotion & Graduation

### Promote Students to Next Class
```
POST /api/tenants/{domain}/students/promote
```
**Body:**
```json
{
  "student_ids": [1, 2, 3],
  "target_class_id": 2,
  "target_academic_year_id": 2,
  "remarks": "Promoted to next grade"
}
```

### Graduate Students
```
POST /api/tenants/{domain}/students/graduate
```
**Body:**
```json
{
  "student_ids": [1, 2, 3],
  "remarks": "Graduated successfully"
}
```

### Get Student Class History
```
GET /api/tenants/{domain}/students/{id}/history
```
*Accessible by student, parent, admin, teacher*

### Get Class History
```
GET /api/tenants/{domain}/classes/{classId}/history
```
*Accessible by admin & teacher*

---

## 3.25 Posts

### List Approved Posts (Public)
```
GET /api/tenants/{domain}/posts
```

### Show Post (Public)
```
GET /api/tenants/{domain}/posts/{id}
```

### Admin List All Posts (including pending)
```
GET /api/tenants/{domain}/admin/posts
```

### Create Post
```
POST /api/tenants/{domain}/posts
```
*Accessible by student, parent, admin, teacher*

**Body (multipart/form-data):**
```json
{
  "title": "School Notice",
  "content": "This is the content...",
  "image": (file)
}
```
Posts by admins are auto-approved. Others require admin approval.

### Update Post
```
PUT /api/tenants/{domain}/posts/{id}
```
*Only author or admin can update*

### Update Post Status (Approve/Reject)
```
PATCH /api/tenants/{domain}/posts/{id}/status
```
**Body:**
```json
{
  "status": "approved|rejected|pending"
}
```

### Delete Post
```
DELETE /api/tenants/{domain}/posts/{id}
```

---

## 3.26 SMS

### Send SMS
```
POST /api/tenants/{domain}/send-sms
```
**Body:**
```json
{
  "target": "parents|students|teachers|all",
  "message": "Your message here (max 160 chars)",
  "class_id": 1,
  "student_ids": [1, 2],
  "teacher_ids": [1, 2]
}
```

### Send SMS to Teachers
```
GET /api/tenants/{domain}/send-sms-teachers?message=Hello
```

### SMS Usage
```
GET /api/tenants/{domain}/sms-usage
```
Query param: `?academic_year_id=1`

### SMS Messages History
```
GET /api/tenants/{domain}/sms-messages
```
Query params: `?academic_year_id=1&target_group=parents&status=sent&per_page=20`

### SMS Settings
```
GET    /api/tenants/{domain}/sms-settings
POST   /api/tenants/{domain}/sms-settings
PUT    /api/tenants/{domain}/sms-settings/{id}
DELETE /api/tenants/{domain}/sms-settings/{id}
```

### Get SMS Classes
```
GET /api/tenants/{domain}/sms-class
```

---

## 3.27 Attendance

### List Attendance
```
GET /api/tenants/{domain}/attendances
```
Query params:
- `?class_id=1`
- `?student_id=1`
- `?teacher_id=1`
- `?date=2024-06-15`
- `?start_date=2024-01-01&end_date=2024-12-31`
- `?type=student|teacher`
- `?per_page=50`

### Mark Attendance
```
POST /api/tenants/{domain}/attendances
```
**Body:**
```json
{
  "student_id": 1,
  "class_id": 1,
  "attendance_date": "2024-06-15",
  "check_in": "09:00:00",
  "check_out": "16:00:00",
  "status": "present|absent|late|half_day|on_leave",
  "source": "manual|device",
  "device_id": "DEV001",
  "device_user_id": "UID001",
  "remarks": "On time"
}
```
Either `student_id` or `teacher_id` is required. Uses `updateOrCreate` (idempotent per date).

### Bulk Attendance
```
POST /api/tenants/{domain}/attendances/bulk
```
**Body:**
```json
{
  "attendances": [
    { "student_id": 1, "attendance_date": "2024-06-15", "status": "present" },
    { "student_id": 2, "attendance_date": "2024-06-15", "status": "absent" }
  ]
}
```

### Class Attendance Report
```
GET /api/tenants/{domain}/attendances/class/{classId}
```
Query param: `?date=2024-06-15`

### My Attendance
```
GET /api/tenants/{domain}/my-attendance
```
*Accessible by student, parent, admin, teacher*

---

## 3.28 Identity Cards

### Get ID Card Settings
```
GET /api/tenants/{domain}/id-card-settings
```

### Bulk Print ID Cards
```
GET /api/tenants/{domain}/identity-cards/bulk?class_id=1
```

### Print Individual ID Card
```
GET /api/tenants/{domain}/identity-cards/{id}
```
Query param: `?class_id=1`

### Get ID Card Settings
```
GET /api/tenants/{domain}/id-card-settings
```
*Accessible by student, parent, admin, teacher*

Returns the school's ID card layout/settings configuration.

---

## 3.29 Subject-Teacher-Class Assignments

### Get Subjects by Class
```
GET /api/tenants/{domain}/classes/{classId}/subjects
```
*Accessible by admin & teacher*

### Assign Subject to Class with Teacher
```
POST /api/tenants/{domain}/class-subjects
```
**Body:**
```json
{
  "subject_id": 1,
  "assignments": [
    { "class_id": 1, "teacher_id": 5 },
    { "class_id": 2, "teacher_id": 5 }
  ]
}
```
*Accessible by admin & teacher*

### Update Teacher Assignment
```
PUT /api/tenants/{domain}/class-subjects/{assignmentId}
```
**Body:**
```json
{ "teacher_id": 6 }
```

### Get All Class-Subject-Teacher Assignments
```
GET /api/tenants/{domain}/class-subjects-teacher
```
Query params: `?subject_id=1&class_id=1&teacher_id=5`

---

## 3.30 Teacher Profile & History

### Get My Teacher Profile
```
POST /api/tenants/{domain}/teachers/me
```
*Accessible by admin & teacher*

### Get Teacher History
```
GET /api/tenants/{domain}/teachers/{id}/history
```

### Get Teacher's Results
```
GET /api/tenants/{domain}/teachers/{teacherId}/results
```
Query param: `?academic_year_id=1`

---

# 4. Government APIs

## 4.1 Government Auth

### Register
```
POST /api/auth/gov
```
**Body:**
```json
{
  "name": "Gov Officer",
  "email": "officer@gov.np",
  "phone": "9812345678",
  "password": "password123",
  "local_body_id": 1
}
```

### Login
```
POST /api/auth/gov/login
```
**Body:**
```json
{
  "email": "officer@gov.np",
  "password": "password123"
}
```

## 4.2 Government Data APIs

All endpoints below require `auth:sanctum` + `role:government`.

### Schools by Local Unit
```
GET /api/schools/by-local-unit/{localUnit}
```

### Schools by Local Unit & Ward
```
GET /api/schools/by-local-unit/{localUnit}/{ward}
```

### Single School Details
```
GET /api/school/details/{schoolId}
```

### Get All Teachers of a School
```
GET /api/school/{schoolId}/teachers
```

### Get Single Teacher Details
```
GET /api/school/{schoolId}/teacher/{teacherId}
```

### Get All Students of a School
```
GET /api/school/{schoolId}/students
```

### Get Student Details
```
GET /api/school/{schoolId}/students/{studentId}
```

### Get Student Result
```
GET /api/school/{schoolId}/students/{studentId}/result
```

### Single School Analytics
```
GET /api/single-school/{schoolId}/{isTribe?}/{isDisable?}/{gender?}
```

### Multi School Comparison
```
GET /api/multiple-school/{school1}/{school2}
```

### Filter Students
```
GET /api/students/filter
```

### Filter Teachers
```
GET /api/teachers/filter
```

### Government Analytics
```
POST /api/gov/analytics
```
**Body:** (filter criteria)

### Single School Student Filter
```
POST /api/gov/analytics/singleschool
```

### Ethnicity Analytics
```
POST /api/gov/analytics/ethnicity
```

### Comprehensive Analytics
```
POST /api/gov/analytics/comprehensive
```

### Class Activity Report
```
POST /api/gov/analytics/class-activity
```

### Teacher Analytics Report
```
POST /api/gov/analytics/teacher-activity
```

### All Teachers in Local Unit
```
GET /api/gov/teachers/{localUnit}
```

### All Data (Teachers & Students) by Local Unit
```
GET /api/gov/all-data/{localUnit}
```

### All Students by Local Unit
```
GET /api/gov/all-students/{localUnit}
```

---

# 5. Role-Based Access Summary

| Role | Can Access |
|------|-----------|
| **superadmin** | Central APIs, school CRUD, system logs |
| **admin** | All school management APIs (students, teachers, classes, results, settings, etc.) |
| **teacher** | View classes, add/edit results for their subjects, view their profile, bulk upload |
| **student** | View own profile, own results, own attendance, create posts |
| **parent** | View children's results, create posts |
| **government** | View school data, analytics across local units |
| **public (no auth)** | View notices, events, gallery, teachers list, quick links, contacts (POST) |

---

# 6. Common Error Responses

```json
// 401 - Unauthenticated
{ "status": false, "message": "Unauthenticated" }

// 403 - Forbidden (wrong role)
{ "status": false, "message": "Unauthorized" }

// 404 - Not Found
{ "status": false, "message": "Resource not found" }

// 422 - Validation Error
{
  "status": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["The field_name field is required."]
  }
}

// 500 - Server Error
{ "status": false, "message": "Something went wrong", "error": "..." }
```

---

# 7. Important Notes

1. **Tenant Context**: All school-specific APIs use the domain in the URL (`tenants/{domain}`). The `tenant` middleware identifies the school.

2. **Pagination**: List endpoints support `?per_page=10` query parameter (default varies by endpoint).

3. **Images**: Image uploads use Cloudinary. Use `multipart/form-data` for endpoints with file uploads.

4. **Soft Delete**: Students and teachers are soft-deleted (`is_deleted = true`). Their email/phone get a `_deleted_timestamp` suffix.

5. **Roll Numbers**: Student roll numbers are auto-generated based on alphabetical order of names within each class.

6. **GPA Calculation**: GPA and percentages are automatically calculated based on Result Settings configuration (`simple` average or `weighted` by terms).

7. **SMS**: SMS sending checks central tenant balance before sending. Messages are stored in `sms_messages` table for history.
