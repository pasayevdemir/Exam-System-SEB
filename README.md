# SITC Exam Management System

**Author: Damir Pashayev** &lt;pashayevdamir@gmail.com&gt; · [github.com/pasayevdemir](https://github.com/pasayevdemir)
Copyright © 2026 Damir Pashayev. All rights reserved. See [LICENSE](LICENSE).

A comprehensive Laravel-based online examination platform designed for educational institutions. Create versatile exams, manage multiple question types including file uploads, and provide secure assessment environment with automatic and manual scoring capabilities.

## 🌟 Key Features

### For Administrators

-   **Complete Exam Management**: Create, edit, and manage exams with unique IDs and detailed descriptions
-   **Multiple Question Types**:
    -   Single choice MCQ questions
    -   Multiple choice MCQ questions
    -   File upload questions with customizable settings
-   **Advanced Question Management**: Add, edit, delete questions with real-time validation
-   **File Upload Configuration**: Set allowed file types, maximum file sizes (1-100MB)
-   **Exam Control**: Activate/deactivate exams to control student access
-   **Comprehensive Results Dashboard**: View detailed student submissions, scores, and file submissions
-   **Manual Grading System**: Grade file uploads with feedback and custom scoring
-   **Export Capabilities**: Download exam results in Excel format with detailed analytics
-   **Admin Authentication**: Secure admin panel with session-based authentication

### For Students

-   **Simple Access**: Login using Exam ID, Student ID, and Index Number
-   **Mixed Question Types**: Answer MCQ questions and submit files in the same exam
-   **File Upload Support**: Submit documents, images, and other files per question requirements
-   **Secure Sessions**: One attempt per exam with concurrent login prevention
-   **Results Checking**: View detailed results including MCQ scores and file submission status
-   **Auto-Scoring**: Immediate scoring for MCQ questions
-   **User-Friendly Interface**: Clean, responsive design optimized for various devices

### Security & Technical Features

-   **Single Attempt Enforcement**: Prevents multiple exam submissions per student
-   **Concurrent Login Prevention**: Atomic cache locks prevent simultaneous logins
-   **Session-Based Security**: Secure session management with automatic cleanup
-   **File Upload Security**: Validation of file types, sizes, and storage
-   **CSRF Protection**: Complete protection across all forms and routes
-   **Input Validation**: Comprehensive server-side validation
-   **Admin Route Protection**: Middleware-based access control
-   **Modern CSS Architecture**: Optimized Bootstrap + Tailwind CSS integration

## 🚀 Quick Setup

### Prerequisites

-   PHP 8.2 or higher
-   Composer
-   Node.js & NPM
-   Database (MySQL, PostgreSQL, or SQLite)

### Installation Steps

1. **Clone & Install Dependencies:**

    ```bash
    git clone <repository-url>
    cd crazy-project
    composer install
    npm install
    ```

2. **Environment Configuration:**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. **Database Setup:**

    Edit `.env` with your database settings:

    **For SQLite (Recommended for Development):**

    ```env
    DB_CONNECTION=sqlite
    DB_DATABASE=/absolute/path/to/database/database.sqlite
    ```

    **For MySQL:**

    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=exam_system
    DB_USERNAME=your_username
    DB_PASSWORD=your_password
    ```

    Create SQLite database file:

    ```bash
    touch database/database.sqlite
    ```

4. **File Storage Setup:**

    ```bash
    php artisan storage:link
    chmod -R 775 storage/
    chmod -R 775 public/storage/
    ```

5. **Database Migration:**

    ```bash
    php artisan migrate
    ```

6. **Asset Compilation:**

    ```bash
    npm run build
    # or for development with hot reload: npm run dev
    ```

7. **Start Development Server:**

    ```bash
    php artisan serve
    ```

8. **Access the Application:**
    - **Home/Student Portal**: http://127.0.0.1:8000
    - **Admin Login**: http://127.0.0.1:8000/admin/login
    - **Check Results**: http://127.0.0.1:8000/check-results

9. **Opt into the blame ignore list** (once per clone):

    ```bash
    git config blame.ignoreRevsFile .git-blame-ignore-revs
    ```

    Formatting-only commits are listed there, so `git blame` reports whoever
    last changed a line's meaning rather than whoever last reformatted it.
    GitHub's blame view applies the file on its own.

### Checks

CI runs these on every push and pull request; run them locally before pushing:

```bash
./vendor/bin/pint --test                    # formatting (use --dirty to fix only your changes)
./vendor/bin/phpstan analyse                # static analysis, level 5
php artisan test                            # feature suite
npm run test:js                             # exam page client logic (Vitest)
npm run build                               # asset build
```

## 🔑 Default Admin Credentials

-   **Username**: `sitcexm-admin`
-   **Password**: `SITC@saudiarabia`

## 📖 Complete User Guide

### Admin Workflow

1. **Login to Admin Panel**:

    - Navigate to `/admin/login`
    - Use the default credentials above

2. **Create an Exam**:

    - Click "Create New Exam"
    - Enter unique **Exam ID** (e.g., "MATH101", "ENG202")
    - Enter **Exam Name** (e.g., "Mathematics Final Exam")
    - Add optional **Description** for detailed exam information
    - Save to proceed to question management

3. **Add Questions**:

    **MCQ Questions (Single/Multiple Choice):**

    - Select question type: "Single Choice" or "Multiple Choice"
    - Enter question text
    - Add 2-8 answer options
    - Select correct answer(s) by checking boxes

    **File Upload Questions:**

    - Select "File Upload" question type
    - Configure allowed file types (PDF, Word, Excel, Images, etc.)
    - Set maximum file size (1-100 MB)
    - Add question instructions

4. **Manage Exams**:

    - **Edit Questions**: Modify existing questions and answers
    - **Delete Questions**: Remove unwanted questions with confirmation
    - **Activate/Deactivate**: Control exam availability to students
    - **View Results**: Access comprehensive analytics and submissions

5. **Grade File Submissions**:

    - Access "Grade Submissions" for each exam
    - Download and review student file uploads
    - Assign manual scores (0-100)
    - Provide detailed feedback
    - Mark submissions as graded

6. **Export Results**:
    - Download detailed Excel reports
    - View student-wise performance analytics
    - Access submission timestamps and file information

### Student Workflow

1. **Access Exam**:

    - Visit home page at `/` (redirects to `/start-exam`)
    - Enter **Exam ID** (provided by instructor)
    - Enter **Student ID** (your unique identifier)
    - Enter **Index Number** (registration/roll number)

2. **Take Exam**:

    **MCQ Questions:**

    - Select appropriate answer(s) based on question type
    - Single choice: One answer per question
    - Multiple choice: Multiple answers allowed

    **File Upload Questions:**

    - Click "Choose File" button
    - Select file according to specified requirements
    - Verify file meets size and type restrictions
    - Upload progress indicator shows completion

3. **Submit Exam**:

    - Answer ALL questions (submission requires complete responses)
    - Review answers before final submission
    - Click "Submit Exam" for immediate MCQ scoring
    - File uploads await manual grading

4. **Check Results**:
    - Use "Check Results" link on home page
    - Enter same Student ID and Index Number used for exam
    - View detailed breakdown:
        - MCQ scores and correct answers
        - File submission status
        - Manual grades and feedback
        - Overall exam performance

### One-Time Policy

-   Each student can attempt each exam only once
-   Concurrent logins prevented through security locks
-   Session management ensures data integrity

## 🏗️ Database Architecture

### Core Tables

| Table               | Purpose                  | Key Fields                                                                      |
| ------------------- | ------------------------ | ------------------------------------------------------------------------------- |
| **exams**           | Store exam definitions   | `id`, `exam_id` (unique), `exam_name`, `description`, `is_active`               |
| **questions**       | Store exam questions     | `id`, `exam_id`, `question_text`, `question_type`, `file_upload_settings`       |
| **answers**         | Store MCQ answer options | `id`, `question_id`, `answer_text`, `is_correct`                                |
| **exam_results**    | Store student results    | `id`, `exam_id`, `student_id`, `index_no`, `score`, `submitted_at`              |
| **student_answers** | Track student responses  | `id`, `exam_result_id`, `question_id`, `answer_id`, `file_path`, `manual_score` |

### Enhanced Features Schema

| Field                  | Type                                      | Purpose                                |
| ---------------------- | ----------------------------------------- | -------------------------------------- |
| `question_type`        | ENUM('single', 'multiple', 'file_upload') | Question type classification           |
| `file_upload_settings` | JSON                                      | File restrictions (types, size limits) |
| `file_path`            | VARCHAR                                   | Storage path for uploaded files        |
| `original_filename`    | VARCHAR                                   | Original file name preservation        |
| `file_size`            | INTEGER                                   | File size in bytes                     |
| `file_mime_type`       | VARCHAR                                   | MIME type validation                   |
| `manual_score`         | DECIMAL(5,2)                              | Admin-assigned scores                  |
| `admin_feedback`       | TEXT                                      | Detailed grading feedback              |
| `is_graded`            | BOOLEAN                                   | Manual grading completion status       |

## 🔐 Security Implementation

### Multi-Layer Security

| Security Feature                | Implementation                        | Protection Against   |
| ------------------------------- | ------------------------------------- | -------------------- |
| **Single Attempt Enforcement**  | Database constraint checks            | Multiple submissions |
| **Concurrent Login Prevention** | Atomic cache locks (`Cache::add()`)   | Simultaneous access  |
| **Session Management**          | Laravel sessions with exam scope      | Unauthorized access  |
| **Admin Authentication**        | Session-based with middleware         | Admin impersonation  |
| **File Upload Security**        | Type, size, and content validation    | Malicious uploads    |
| **CSRF Protection**             | Laravel @csrf tokens                  | Cross-site attacks   |
| **Input Validation**            | Server-side form requests             | Data injection       |
| **SQL Injection Prevention**    | Eloquent ORM with prepared statements | Database attacks     |
| **Cache-based Locking**         | Atomic operations for concurrency     | Race conditions      |

### File Upload Security

-   **Type Validation**: Configurable allowed extensions
-   **Size Limits**: 1-100 MB range with validation
-   **Storage Security**: Secure file storage outside web root
-   **MIME Type Checking**: Content-based validation
-   **Filename Sanitization**: Prevention of path traversal

## 🛠️ Technology Stack

### Backend

-   **Framework**: Laravel 12.x (PHP 8.2+)
-   **Database**: MySQL/PostgreSQL/SQLite compatible
-   **File Storage**: Laravel Storage with configurable drivers
-   **Caching**: Laravel Cache (File/Redis/Memcached)
-   **Excel Export**: Maatwebsite/Laravel-Excel

### Frontend & Assets

-   **Templating**: Blade engine with component architecture
-   **CSS Framework**: Bootstrap 5.1.3 + Tailwind CSS 4.0
-   **Icons**: Font Awesome 6.0
-   **Build Tool**: Vite 7.0 with Laravel integration
-   **Responsive Design**: Mobile-first approach

### Development & Testing

-   **Testing**: Pest PHP testing framework
-   **Code Quality**: Laravel Pint for code formatting
-   **Development**: Laravel Sail for containerization
-   **Debugging**: Laravel Tinker for interactive testing

## 📋 API Routes & Endpoints

### Admin Routes (Protected by AdminMiddleware)

#### Authentication

```
GET  /admin/login                     → Admin login form
POST /admin/authenticate              → Process admin login
POST /admin/logout                    → Admin logout
```

#### Exam Management

```
GET  /examadmin/                      → Dashboard with exam list
GET  /examadmin/create-exam           → Create new exam form
POST /examadmin/store-exam            → Save new exam
GET  /examadmin/exam/{exam}/edit      → Edit exam details
PUT  /examadmin/exam/{exam}           → Update exam information
POST /examadmin/exam/{exam}/toggle-status → Activate/deactivate exam
```

#### Question Management

```
GET  /examadmin/exam/{exam}/questions → Question management interface
POST /examadmin/exam/{exam}/questions → Add new question
GET  /examadmin/question/{question}/edit → Edit question form
PUT  /examadmin/question/{question}   → Update question
DELETE /examadmin/question/{question} → Delete question
```

#### Results & Grading

```
GET  /examadmin/exam/{exam}/results   → View exam results
GET  /examadmin/exam/{exam}/results/download → Export results to Excel
GET  /examadmin/exam/{exam}/grade-submissions → Manual grading interface
PUT  /examadmin/student-answer/{studentAnswer}/grade → Grade file submission
```

### Student Routes

#### Access & Authentication

```
GET  /start-exam                      → Student login form (redirected from /)
POST /student/authenticate            → Process student login
POST /student/logout                  → Student logout with cache cleanup
```

#### Exam Interface

```
GET  /student/exam/{exam}             → Take exam interface (session required)
POST /student/exam/{exam}/submit      → Submit exam answers
```

#### Results

```
GET  /check-results                   → Results check form
POST /check-results                   → Display student results
```

## 🧪 Testing & Development

### Running Tests

```bash
php artisan test
# or
./vendor/bin/pest
```

### Database Management

```bash
# Fresh migration with sample data
php artisan migrate:fresh

# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Clear student session locks
php artisan cache:flush
```

### Development Tools

```bash
# Interactive debugging
php artisan tinker

# Code formatting
./vendor/bin/pint

# Asset compilation with hot reload
npm run dev
```

### File Storage Testing

```bash
# Create symbolic link for public storage
php artisan storage:link

# Check storage permissions
ls -la storage/
ls -la public/storage/
```

## 🚢 Production Deployment

Pushing to `main` deploys to production automatically. There is nothing to run by hand.

```
push to main  →  .github/workflows/deploy.yml  →  ssh  →  /usr/local/sbin/exam-deploy
```

The workflow carries **no deploy logic**. It opens a single SSH connection using a key that the
server pins to a forced command, so whatever the client sends is ignored and the server always runs
`/usr/local/sbin/exam-deploy`. Deploy behaviour is changed by editing that script on the server —
which also means a broken deploy can be fixed without first landing a deploy.

### What the server does on each run

1. Takes a non-blocking lock, so two pushes can never deploy at once
2. Re-asserts that `/var/www/exam` is owned by `exam` (a stray root-owned file breaks `git pull`)
3. **Refuses to deploy while an exam attempt is in flight** — recreating the container would drop a
   student's session mid-exam
4. Dumps the database and `.env` to `/home/exam/backups/<timestamp>/`, keeping the last 10
5. Pulls `--ff-only`, rebuilds the image, restarts, runs `migrate --force`
6. Re-creates the `storage` symlink and rebuilds the config/route/view caches as `www-data`
7. Health-checks the site, and **rolls back to the previous commit and image if anything fails**

### Deploy outcomes in GitHub Actions

| Result | Meaning |
| --- | --- |
| ✅ green | Deployed, health check passed |
| ⚠️ warning | **Deferred on purpose** — an exam was in progress or a deploy was already running. Nothing is broken; re-run the workflow when the exam ends |
| ❌ red | Deploy failed and the server rolled back. Read the job summary for the server-side log |

To deploy without pushing (for example after a deferral), use **Actions → Deploy to production →
Run workflow**.

### Required repository secrets

| Secret | Value |
| --- | --- |
| `DEPLOY_SSH_KEY` | Private half of the `exam-deploy-ci` ed25519 key |
| `DEPLOY_HOST` | Production server address |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan` output for that host, so the runner pins the host key |

> This repository is public. Never add a `pull_request` trigger to the deploy workflow and never
> move the job to a self-hosted runner — either would let a fork's code reach the deploy path.

### Logs and backups on the server

```bash
tail -50 /home/exam/backups/deploy.log   # what every deploy did
ls -1 /home/exam/backups/                # timestamped DB + .env snapshots
```

## ⚠️ Current Limitations & Considerations

### Known Limitations

-   **Admin Authentication**: Hardcoded credentials (suitable for single-admin setup)
-   **Time Limits**: No exam duration restrictions implemented
-   **Question Randomization**: Questions appear in creation order
-   **File Preview**: Limited file preview capabilities in admin interface
-   **Backup System**: No automated backup system for uploaded files

### Configuration Notes

-   **File Upload Limits**: Ensure PHP `upload_max_filesize` and `post_max_size` accommodate file size limits
-   **Storage Space**: Monitor storage usage for file uploads
-   **Cache Configuration**: Redis recommended for production concurrent user handling
-   **Database Performance**: Consider indexing for large-scale deployments

## 🔮 Future Enhancement Roadmap

### Planned Features

-   **Enhanced Authentication**: Multi-admin support with role-based permissions
-   **Exam Timers**: Configurable time limits with countdown displays
-   **Question Banks**: Reusable question libraries across exams
-   **Advanced Analytics**: Detailed performance metrics and reporting
-   **Notification System**: Email alerts for exam completion and grading
-   **Mobile App**: Dedicated mobile application for exam taking
-   **Plagiarism Detection**: Basic file similarity checking
-   **Bulk Operations**: Mass import/export of questions and results

### Technical Improvements

-   **API Development**: RESTful API for third-party integrations
-   **Real-time Features**: WebSocket-based live exam monitoring
-   **Advanced Security**: Two-factor authentication and audit logs
-   **Performance Optimization**: Database query optimization and caching strategies
-   **Internationalization**: Multi-language support
-   **Cloud Integration**: Support for cloud storage providers

## 🐛 Troubleshooting Guide

### Common Issues

**File Upload Failures:**

-   Check PHP configuration: `upload_max_filesize`, `post_max_size`, `max_execution_time`
-   Verify storage permissions: `chmod -R 775 storage/`
-   Ensure storage link exists: `php artisan storage:link`

**Cache/Session Issues:**

-   Clear all caches: `php artisan cache:clear`
-   Clear sessions: `php artisan session:flush`
-   Check cache driver configuration in `.env`

**Database Connection Errors:**

-   Verify database credentials in `.env`
-   Ensure database exists and is accessible
-   Check migration status: `php artisan migrate:status`

**Permission Errors:**

-   Set proper directory permissions:
    ```bash
    chmod -R 775 storage/
    chmod -R 775 bootstrap/cache/
    ```

**Excel Export Issues:**

-   Ensure Maatwebsite/Excel is properly installed: `composer require maatwebsite/excel`
-   Check temporary directory permissions

### Performance Optimization

**For High Concurrent Users:**

-   Use Redis for cache and sessions
-   Configure database connection pooling
-   Implement file upload queuing for large files
-   Consider CDN for static assets

## 📄 License

MIT License - Free for educational and commercial use.

## 🙏 Acknowledgments

Built with the Laravel framework and modern web technologies.

### Core Dependencies

-   **Laravel Framework**: https://laravel.com/docs
-   **Laravel Excel**: https://laravel-excel.com/
-   **Bootstrap**: https://getbootstrap.com/
-   **Tailwind CSS**: https://tailwindcss.com/
-   **Font Awesome**: https://fontawesome.com/
-   **Pest Testing**: https://pestphp.com/

---

**For support and contributions, please refer to the project repository and documentation.**
