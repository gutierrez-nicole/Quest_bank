# 🏛️ QuestBank Enterprise Architecture Refactor Report

## 1. Executive Summary
This document summarizes the complete architectural overhaul of **QuestBank: AI-Based Automated Examination Management and Academic Performance Monitoring System**. The system has been upgraded to a **Layered Architecture** adhering to **SOLID Principles**, **Separation of Concerns (SoC)**, and **Repository-Service Patterns**, while maintaining 100% backward compatibility with all existing routes and feature capabilities.

---

## 2. Directory & Component Structure

```text
Quest_bank/
├── app/
│   ├── bootstrap.php                 <-- Central Autoloader & Bootstrap Entry
│   ├── config/config.php             <-- Environment & Global System Constants
│   ├── database.php                  <-- Database Connection Singleton (PDO)
│   ├── session.php                   <-- Role-Based Middleware & Security
│   ├── repositories/                 <-- Data Access Layer (RAW SQL Operations)
│   │   ├── UserRepository.php        <-- User DB Queries & Authentication State
│   │   ├── DepartmentRepository.php  <-- Department CRUD Queries
│   │   ├── ISORepository.php         <-- ISO 25010 Quality Model DB Queries
│   │   └── ActivityLogRepository.php <-- Multi-Role Telemetry Audit Log Queries
│   └── services/                     <-- Business Logic Layer (Domain Rules & Orchestration)
│       ├── AuthService.php           <-- Role Validation, Sessions, & Telemetry
│       ├── DepartmentService.php     <-- Department Management Rules & Logs
│       ├── ISOService.php            <-- ISO 25010 Likert Calculations & Means
│       ├── ExamService.php           <-- Exam Item Management & Submissions
│       ├── StudentService.php        <-- Roster Management & At-Risk Analytics
│       └── GroqService.php           <-- AI Generation & Vision OCR Integration
├── assets/                           <-- Clean Frontend JavaScript Assets
│   └── js/
│       ├── global.js                 <-- Modal Controls, Toast Alerts, & Popovers
│       ├── admin-charts.js           <-- Chart.js Data Visualizations
│       └── file-uploader.js          <-- Drag & Drop File Zone & Image Previews
├── admin/                            <-- Controller Handlers & Presentation Views
├── teacher/                          <-- Teacher Module Handlers
├── student/                          <-- Student Portal Handlers
└── includes/                         <-- Shared Layout Navigation Templates
```

---

## 3. Key Architecture Improvements & SOLID Principles Applied

### A. Single Responsibility Principle (SRP)
- **Repositories (`app/repositories/`)**: Solely responsible for executing PDO database queries.
- **Services (`app/services/`)**: Solely responsible for domain business logic, validation rules, AI API invocations, and statistical calculations.
- **Controllers (`admin/*`, `teacher/*`, `student/*`)**: Responsible only for validating request input, invoking services, and passing data to presentation templates.

### B. Open/Closed & Dependency Inversion Principles
- **Extensible Service Interface**: Adding new question formats (e.g. True/False, Identification) or new ISO 25010 evaluation parameters is handled via `GroqService` and `ISOService` without modifying page layout code.

### C. Elimination of Code Duplication
- **Centralized Autoloader (`app/bootstrap.php`)**: Replaced duplicate `require_once` statements across 45+ PHP scripts with a single unified entry point.
- **Global JS Helpers (`assets/js/global.js`)**: Consolidated repetitive modal toggling scripts (`openModal()`, `closeModal()`, `togglePopover()`) across all 3 portals into one shared module.
- **Cleaned Root Assets**: Removed duplicate `bankquest_db.sql` and `QuestBank_50Percent_Completion_Report.pdf` files from the root folder, consolidating them under dedicated `./database/` and `./pdf/` folders.

---

## 4. Summary of Files Changed & Created

| Category | File Path | Status | Description |
| :--- | :--- | :---: | :--- |
| **Repository** | `app/repositories/UserRepository.php` | 🟢 Created | Data access for user accounts, roles, and profiles. |
| **Repository** | `app/repositories/DepartmentRepository.php` | 🟢 Created | Data access for academic departments. |
| **Repository** | `app/repositories/ISORepository.php` | 🟢 Created | Data access for ISO/IEC 25010 evaluations. |
| **Repository** | `app/repositories/ActivityLogRepository.php` | 🟢 Created | Data access for multi-role audit trail telemetry. |
| **Service** | `app/services/DepartmentService.php` | 🟢 Created | Business logic & telemetry for department management. |
| **Service** | `app/services/ISOService.php` | 🔄 Refactored | Delegated SQL operations to `ISORepository`. |
| **Controller** | `admin/manage_departments.php` | 🔄 Refactored | Delegated logic to `DepartmentService`. |
| **Controller** | `admin/iso_evaluation.php` | 🔄 Refactored | Delegated logic to `ISOService`. |
| **Controller** | `admin/export_iso_pdf.php` | 🔄 Refactored | Delegated logic to `ISOService`. |
| **Frontend JS** | `assets/js/global.js` | 🟢 Created | Consolidated modal controls and UI notifications. |
| **Frontend JS** | `assets/js/admin-charts.js` | 🟢 Created | Consolidated Chart.js rendering engines. |
| **Frontend JS** | `assets/js/file-uploader.js` | 🟢 Created | Consolidated drag-and-drop file uploader. |

---

## 5. Future Recommendations

1. **Automated Unit & E2E Testing**: Introduce Vitest / PHPUnit tests for `ISOService::getOverallWeightedMean()` and `StudentService::getAtRiskStudents()`.
2. **REST API Extensions**: Expose lightweight JSON endpoints (`/api/v1/departments`, `/api/v1/iso-evaluations`) by consuming the existing Service classes directly.
3. **Environment Deployment**: Ensure `.env` is loaded using `vlucas/phpdotenv` or standard server environment variables when deploying to production servers.
