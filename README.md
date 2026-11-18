# 🌾 Digital Fertilizer Management System (DFMS)

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.1-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-2E7D32?style=for-the-badge)](LICENSE)

A comprehensive, role-based web application engineered to digitize, manage, and audit agricultural fertilizer registration, farmer verification, inventory stock, and regional distribution. Built with native **PHP**, **MySQL**, and **Bootstrap 5**, the system bridges administrative oversight with field-level officer operations while providing multi-format reporting and bilingual interface support (English & Amharic).

---

## 📋 Table of Contents

1. [Overview & Problem Statement](#-overview--problem-statement)
2. [Key Features](#-key-features)
3. [System Architecture & Directory Structure](#-system-architecture--directory-structure)
4. [Technology Stack](#-technology-stack)
5. [Database Schema Overview](#-database-schema-overview)
6. [Installation & Local Setup](#-installation--local-setup)
7. [Default Access Credentials](#-default-access-credentials)
8. [Reporting & Data Export Capabilities](#-reporting--data-export-capabilities)
9. [Security Implementation](#-security-implementation)
10. [How to Add & Push This README to GitHub](#-how-to-add--push-this-readme-to-github)
11. [License](#-license)

---

## 🎯 Overview & Problem Statement

Manual paper-based fertilizer allocation often leads to duplicate registrations, inaccurate regional demand forecasting, delayed verification, and opaque distribution records. The **Digital Fertilizer Management System (DFMS)** centralizes farmer onboarding and stock tracking into a unified digital registry, enabling agricultural administrators and field officers to verify land holdings, monitor distribution quotas, and generate auditable reports in real time.

---

## ✨ Key Features

### 🔐 Role-Based Access Control (RBAC)
* **Administrator Portal (`admin/`):** Full system oversight including dashboard analytics, farmer record editing, stock inventory management, fertilizer distribution tracking, system audit logs, and automated database backups.
* **Agricultural Officer Portal (`officer/`):** Dedicated operational workspace for registering farmers, viewing verification statuses, managing officer profiles, and generating regional field reports.
* **Session-Protected Routing:** Strict server-side session validation (`$_SESSION['user_id']`) across all internal views and export endpoints prevents unauthorized direct URL access[cite: 1, 2, 3, 5].

### 👨‍🌾 Farmer Registry & Verification Workflow
* Comprehensive farmer profiling capturing **Farmer ID**, **Full Name**, **Region**, **District**, **Kebele**, **Phone Number**, **Farm Size (hectares)**, **Crop Type**, and **Harvest Season**.
* Document upload management for farmer identification cards and farm verification photos (`assets/uploads/`).
* Three-tier verification lifecycle: **Verified**, **Pending**, and **Rejected**.

### 📦 Inventory & Distribution Management
* Real-time tracking of fertilizer stock levels (`admin/stock.php`) and allocation records (`admin/distribution.php`).
* Activity logging (`admin/logs.php`) and SQL database backup utilities (`admin/backup.php`) for administrative accountability.

### 📊 Multi-Format Reporting & Analytics
* Dynamic filtering by **Region**, **Verification Status**, and **Registration Date Range (`date_from` / `date_to`)**[cite: 2, 3].
* Instant data export into **CSV** (with UTF-8 BOM for proper Amharic/Unicode rendering in Excel), styled **Excel (`.xls`)** spreadsheets, and **Print-Ready PDF** summary layouts featuring real-time statistical counters[cite: 3].

### 🌐 Bilingual Interface Support
* Built-in language switching support (`🇬🇧 EN` / `🇪🇹 አማ`) tailored for Ethiopian agricultural administration workflows.

---

## 📁 System Architecture & Directory Structure

```text
digital-fertilizer-management-system/
├── admin/                        # Administrator control panel & management modules
│   ├── backup.php                # Database backup generation utility
│   ├── dashboard.php             # Admin analytics & overview dashboard
│   ├── distribution.php          # Fertilizer allocation & distribution tracking
│   ├── edit_farmer.php           # Farmer record modification & status updates
│   ├── export_excel.php          # Admin-level Excel export handler
│   ├── farmers.php               # Master farmer registry management
│   ├── index.php                 # Admin directory entry redirect
│   ├── login.php                 # Admin authentication route
│   ├── logs.php                  # System activity & audit trail logs
│   ├── profile.php               # Administrator profile settings
│   ├── reports.php               # Comprehensive administrative reporting
│   ├── stock.php                 # Fertilizer inventory & stock control
│   └── view_farmer.php           # Detailed farmer profile & document viewer
├── officer/                      # Field Officer operational portal
│   ├── dashboard.php             # Officer summary & task dashboard
│   ├── farmers.php               # Field farmer registration & listing
│   ├── profile.php               # Officer profile & avatar management
│   ├── reports.php               # Regional officer reporting tools
│   └── view_farmer.php           # Officer farmer inspection view
├── config/                       # Core system configuration & shared helpers
│   ├── database.php              # MySQLi connection & prepared statement helpers
│   ├── fertilizer_system.sql     # Complete database schema & seed dump
│   ├── language.php              # English / Amharic localization dictionary
│   └── session.php               # Session validation & access control middleware
├── assets/                       # Static assets & user uploads
│   ├── profile_images/           # Staff profile avatars
│   └── uploads/                  # Uploaded farmer IDs and farm verification photos
├── backups/                      # Generated SQL backup snapshots
├── export_csv.php                # Global UTF-8 CSV report generator[cite: 1]
├── export_excel.php              # Global formatted XLS report generator[cite: 2]
├── export_pdf.php                # Print-ready HTML/PDF statistical report[cite: 3]
├── index.php                     # Root entry point (redirects to login.php)[cite: 4]
├── login.php                     # Primary glassmorphism authentication portal
├── logout.php                    # Secure session & cookie termination script[cite: 6]
└── README.md                     # Project documentation
